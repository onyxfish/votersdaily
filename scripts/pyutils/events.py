#!/usr/bin/env python

import datetime
import hashlib
import optparse
import os
import sys
import time
import traceback
import urllib2

import couchdb
from couchdb.schema import *

# Previous versions will yield erroneous 409 responses from CouchDB
if couchdb.__version__ < "0.7":
    print 'couchdb-python version 0.7 or greater is required.'
    sys.exit()


class ScrapeError(Exception):
    """
    Base class for scrape errors.
    """
    pass
    
    
class EventScraper(object):
    """
    The base class for an scraper, which provides generic url and data
    handling methods.
    
    Subclasses must set name, version, and url attributes before calling
    EventScraper.__init__().
    """
    
    # These values should be set when urlopen() is called,
    # but might not be if the scraper errors out before it completes
    source_url = None
    access_datetime = None
    source_text = None
    
    # The user agent used for requests (this will show up in the host's logs):
    user_agent = 'robot: http://wiki.github.com/bouvard/votersdaily'

    def __init__(self):
        """
        Verify that required attributes have been set in the derived class.
        """
        if not hasattr(self, 'name'):
            raise Exception('EventScrapers must have a state attribute')

        if not hasattr(self, 'version'):
            raise Exception('EventScrapers must have a version attribute')
    
    def _parse_cli_options(self):
        """
        Parse any command line options that were passed to the script.
        """
        parser = optparse.OptionParser()
        
        parser.add_option('--engine', 
                          dest='engine',
                          help='storage engine to scrape data into', 
                          default='couchdb')
        parser.add_option('--server',
                          dest='server',
                          help='ip or hostname of the server where the storage engine resides', 
                          default='localhost')
        parser.add_option('--port',
                          dest='port',
                          help='port on the server where the storage engine connects', 
                          default='5984')
        parser.add_option('--eventdb',
                          dest='eventdb',
                          help='name of the events database on the storage engine', 
                          default='vd_events')
        parser.add_option('--logdb',
                          dest='logdb',
                          help='name of the logs database on the storage engine', 
                          default='vd_logs')
        
        parser.add_option('--debug',
                          dest='debug',
                          action='store_true',
                          help='drop current databases at startup', 
                          default=False)
        
        (self.options, self.args) = parser.parse_args()
        
    def _init_couchdb(self):
        """
        Setup CouchDB.  Encapsulated for clarity.
        """
        self.server_uri = 'http://%s:%s' % (
            self.options.server, self.options.port)
        self.server = couchdb.Server(self.server_uri)
        
        if self.options.eventdb not in self.server:
            self.server.create(self.options.eventdb)
        elif self.options.debug:
            if self.options.eventdb != 'vd_events':
                del self.server[self.options.eventdb]
                self.server.create(self.options.eventdb)
            else:
                print 'Ignoring --debug since the production events database name was specified.'
            
        self.event_db = self.server[self.options.eventdb]
        
        if self.options.logdb not in self.server:
            self.server.create(self.options.logdb)
        elif self.options.debug:
            if self.options.eventdb != 'vd_logs':
                del self.server[self.options.logdb]
                self.server.create(self.options.logdb)
            
        self.log_db = self.server[self.options.logdb]

    def urlopen(self, url, is_root):
        """
        Retrieve a url, specifying necessary headers.  If this is the root,
        or first, page to be scraped then additional properties of the source
        are recorded for logging.
        """
        if is_root:
            self.source_url = url
            self.access_datetime = datetime.datetime.now()
        
        request = urllib2.Request(url, headers={'User-Agent': self.user_agent})
        source_text = urllib2.urlopen(request).read()
        
        if is_root:
            self.source_text = source_text
            
        return source_text
    
    def validate_event(self, event):
        """
        Raises an AttributeError exception if all required fields have not been
        populated.
        """
        for attr in ['datetime', 'branch', 'entity', 'title', 'source_url',
                     'source_text', 'access_datetime']:
            if not event[attr]:
                raise AttributeError(
                    '%s is a required attribute for every Event.')
                
    def event_exists(self, id):
        """
        Return true if an event with this unique already exists.
        """
        if id in self.event_db:
            return True
        
        return False
                
    def encode_dict(self, data):
        """
        Work around couchdb-python's lack of support for datetime's by pre-
        encoding them to strings.
        """
        copy_event = {}
        
        for k, v in data.items():
            if isinstance(v, datetime.datetime):
                copy_event[k] = self.encode_datetime(v)
            else:
                copy_event[k] = v
        
        return copy_event
    
    def encode_datetime(self, value):
        """
        Encode a datetime in ISO 8601 UTC format with second accuracy.
        """
        return value.replace(microsecond=0).isoformat() + 'Z'

    def add_event(self, id, event):
        """
        Add scraped Event object to the database.
        """
        # Skip duplicates
        if self.event_exists(id):
            return
        
        # Append common properties
        event['parser_name'] = self.name
        event['parser_version'] = self.version
        
        # Validate
        self.validate_event(event)
        
        # Store
        self.event_db[id] = self.encode_dict(event)
    
    def add_log(self, result, traceback=None):
        """
        Add a log entry to the database.
        """
        # Create log
        scrape_log = ScrapeLog(
            self.name,
            self.version,
            self.source_url,
            self.source_text,
            self.access_datetime,
            result)
        
        # If appropriate, attach traceback
        if traceback:
            scrape_log['traceback'] = traceback
            
        # Generate a unique id
        id = '%s - %s - %s' % (
            self.encode_datetime(scrape_log['access_datetime']), 
            scrape_log['parser_name'], 
            scrape_log['result'])
        
        # Store
        self.log_db[id] = self.encode_dict(scrape_log)

    def scrape(self):
        """
        This method must be overriden by each derived scraper and must return
        a dictionary with document id's as keys and Event objects as
        corresponding values.
        """
        return {}
            
    def run(self):
        """
        Run this scraper and log the results.
        """
        self._parse_cli_options()
        
        self._init_couchdb()
        
        try:
            events = self.scrape()
            
            for id, event in events.items():
                self.add_event(id, event)
            
            self.add_log(result='success')
        except:
            # Log exception to the database
            cls, exc, trace = sys.exc_info()
            self.add_log(cls.__name__, traceback.format_tb(trace))
            
            # Make exception visible on command line
            raise

        
class Event(dict):
    """
    A representaion of an Event to be stored in the database backend.  Required
    attributes are defined in __init__, but as as subclass of dict additional
    attributes may be added at anytime with event[key] = value syntax.
    """
    
    def __init__(self, datetime, branch, entity, title, **kwargs):
        """
        Setup attribute defaults.
        """
        dict.__init__(self)
        
        self['datetime'] = datetime
        self['branch'] = branch
        self['entity'] = entity
        self['title'] = title
        self['end_datetime'] = None
        self['description'] = None
        self['source_url'] = None
        self['source_text'] = None
        self['access_datetime'] = None
        self.update(kwargs)
        
    
class ScrapeLog(dict):
    """
    A represenation of a scrape attempt log entry for storage in the database.
    """
    
    def __init__(self, parser_name, parser_version, source_url, source_text,
                 access_datetime, result, **kwargs):
        """
        Setup attribute defaults.
        """
        self['parser_name'] = parser_name
        self['parser_version'] = parser_version
        self['source_url'] = source_url
        self['source_text'] = source_text
        self['access_datetime'] = access_datetime
        self['result'] = result
        self.update(kwargs)