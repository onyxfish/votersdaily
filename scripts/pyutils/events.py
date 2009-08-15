import datetime
import hashlib
import os
import sys
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

        if not hasattr(self, 'frequency'):
            raise Exception('EventScrapers must have a frequency attribute')
        
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

    def scrape(self):
        """
        This method must be overriden by each derived scraper.
        """
        pass

    def add_event(self, event):
        """
        Add scraped Event object to the database.
        """        
        # Append parser properties to event
        event.parser_name = self.name
        event.parser_version = self.version
        
        event.store(self.event_db)
    
    def add_log(self, scrape_log):
        """
        Add a ScrapeLog object to the database.
        """        
        scrape_log.parser_name = self.name
        scrape_log.parser_version = self.version
        scrape_log.source_url = self.source_url
        scrape_log.source_text = self.source_text
        scrape_log.access_datetime = self.access_datetime
        
        scrape_log.store(self.log_db)
            
    def run(self):
        """
        Run this scraper and log the results.
        """
        self._init_couchdb()
        
        try:
            self.scrape()
            self.add_log(ScrapeLog(result='success'))
        except:
            # Log exception to the database
            cls, exc, trace = sys.exc_info()
            scrape_log = ScrapeLog(result=cls.__name__)
            scrape_log['traceback'] = traceback.format_tb(trace)
            self.add_log(scrape_log)
            
            # Make exception visible on command line
            raise
        
        
class Event(Document):
    """
    A couchdb-python document schema for an event.  Properties that are
    expected to be set by the scraper are defined as fields.
    """
    
    datetime = DateTimeField()
    title = TextField()
    description = TextField()
    end_datetime = DateTimeField()
    branch = TextField()
    entity = TextField()
    source_url = TextField()
    source_text = TextField()
    access_datetime = DateTimeField()
    
    def store(self, database):
        """
        Generate a unique id for this event, verify that it is not a duplicate,
        and store it in the database.
        """
        self.id = '%s - %s - %s - %s' % (
            self['datetime'], 
            self['branch'], 
            self['entity'],
            self['title'])
        
        if self.id in database:
            return
        
        Document.store(self, database) 
    
class ScrapeLog(Document):
    """
    A couchdb-python document schema for the log of a scraping attempt.
    """
    
    parser_name = TextField()
    parser_version = TextField()
    source_url = TextField()
    source_text = TextField()
    access_datetime = DateTimeField()
    result = TextField()
    
    def store(self, database):
        """
        Generate a unique id for this log entry and store it in the database.
        """
        self.id = '%s - %s - %s' % (
            self['access_datetime'], 
            self['parser_name'], 
            self['result'])
        
        Document.store(self, database)