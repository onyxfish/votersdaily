#!/usr/bin/env python

import datetime
import hashlib
import os
import sys
import time
import traceback
import urllib2

import couchdb
from couchdb.schema import *


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
    
    # CouchDB config
    SERVER_URI = 'http://localhost:5984/'
    EVENT_DB_NAME = 'vd_events'
    LOG_DB_NAME = 'vd_log'
    
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
        
    def _init_couchdb(self):
        """
        Setup CouchDB.  Encapsulated for clarity.
        """
        self.server = couchdb.Server(self.SERVER_URI)
        
        if self.EVENT_DB_NAME not in self.server:
            self.server.create(self.EVENT_DB_NAME)
            
        self.event_db = self.server[self.EVENT_DB_NAME]
        
        if self.LOG_DB_NAME not in self.server:
            self.server.create(self.LOG_DB_NAME)
            
        self.log_db = self.server[self.LOG_DB_NAME]

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
    
    def event_exists(self, event):
        """
        Return True if a specified event already exists in the events database.
        
        Note: helper method for scraper writers.
        """
        if event.exists(self.event_db):
            return True
        
        return False

    def add_event(self, event):
        """
        Add scraped Event object to the database.
        """
        event['parser_name'] = self.name
        event['parser_version'] = self.version
        
        event.store(self.event_db)
    
    def add_log(self, result, traceback=None):
        """
        Add a log entry to the database.
        """
        scrape_log = ScrapeLog(
            self.name,
            self.version,
            self.source_url,
            self.source_text,
            self.access_datetime,
            result)
        
        if traceback:
            scrape_log['traceback'] = traceback
        
        scrape_log.store(self.log_db)

    def scrape(self):
        """
        This method must be overriden by each derived scraper.
        """
        pass
            
    def run(self):
        """
        Run this scraper and log the results.
        """
        self._init_couchdb()
        
        try:
            self.scrape()
            self.add_log(result='success')
        except:
            # Log exception to the database
            cls, exc, trace = sys.exc_info()
            self.add_log(cls.__name__, traceback.format_tb(trace))
            
            # Make exception visible on command line
            raise


class CouchDBDict(dict):
    """
    A subclass of dict that provides utility functions to encode datetime
    objects in such a way that they can safely be used with couchdb-python.
    """
    
    def _encode(self):
        """
        Work around couchdb-python's lack of support for datetime's by pre-
        encoding them to strings.
        """
        copy_self = {}
        
        for k, v in self.items():
            if isinstance(v, datetime.datetime):
                copy_self[k] = self._encode_datetime(v)
            else:
                copy_self[k] = v
        
        return copy_self
    
    def _encode_datetime(self, value):
        return value.replace(microsecond=0).isoformat() + 'Z'

        
class Event(CouchDBDict):
    """
    A representaion of an Event to be stored in the database backend.  Required
    attributes are defined in __init__, but as as subclass of dict additional
    attributes may be added at anytime with event[key] = value syntax.
    """
    
    def __init__(self, datetime, branch, entity, title, **kwargs):
        """
        Setup attribute defaults.
        """
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
        
        self.id = self._id()
        
    def _id(self):
        """
        Return an id for this document which is unique to it by concatenating
        together its significant attributes.
        """
        return '%s - %s - %s - %s' % (
            self._encode_datetime(self['datetime']), 
            self['branch'], 
            self['entity'],
            self['title'])
        
    def exists(self, database):
        """
        Return True if this document already exists in the given database.
        """
        if self.id in database:
            return True
        
        return False
    
    def validate(self):
        """
        Raises an AttributeError exception if all required fields have not been
        populated.
        """
        for attr in ['datetime', 'branch', 'entity', 'title', 'source_url',
                     'source_text', 'access_datetime']:
            if not self[attr]:
                raise AttributeError(
                    '%s is a required attribute for every Event.')
    
    def store(self, database):
        """
        Validate this document and then store it in the database.
        """
        if self.exists(database):
            return
        
        self.validate()
        
        database[self.id] = self._encode()
        
    
class ScrapeLog(CouchDBDict):
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
        
        # Compute a unique id
        self.id = self._id()
        
    def _id(self):
        """
        Return an id for this document which is unique to it by concatenating
        together its significant attributes.
        """        
        return '%s - %s - %s' % (
            self._encode_datetime(self['access_datetime']), 
            self['parser_name'], 
            self['result'])
    
    def store(self, database):
        """
        Store this entry in the database.
        """        
        database[self.id] = self._encode()