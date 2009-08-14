import datetime
import hashlib
import os
import sys
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
    
    # The user agent used for requests (this will show up in the host's logs):
    user_agent = 'robot: http://wiki.github.com/bouvard/votersdaily'

    def __init__(self):
        """
        Verify that proper attributes have been set in derived classes.
        """
        if not hasattr(self, 'name'):
            raise Exception('EventScrapers must have a state attribute')

        if not hasattr(self, 'version'):
            raise Exception('EventScrapers must have a version attribute')
        
        if not hasattr(self, 'url'):
            raise Exception('EventScrapers must have a url attribute')
        
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

    def urlopen(self, url):
        """
        Retrieve a url, specifying necessary headers.
        """
        request = urllib2.Request(url, headers={'User-Agent': self.user_agent})
        return urllib2.urlopen(request).read()

    def scrape(self):
        """
        This method must be overriden by each derived scraoverridenper.
        """
        pass

    def add_event(self, event):
        """
        Add scraped Event object data to the database.
        
        A CouchDB id is automatically generated for each Event by generating
        a hash from the datetime, title, and entity fields.  This id is also
        used to verify that duplicate Events are not committed to the database.
        """        
        # Append parser properties to event
        event['parser_name'] = self.name
        event['parser_version'] = self.version
        
        # Compute md5 hash/fingerprint
        hash = hashlib.md5()
        hash.update(event['datetime'])
        hash.update(event['title'])
        hash.update(event['entity'])
        
        event.id = hash.hexdigest()
        
        # Skip duplicates
        if event.id in self.event_db:
            return
        
        event.store(self.event_db)
    
    def add_log(self, scrape_log):
        """
        Add a scrape attempt to the database.
        """
        # TODO
        pass
            
    def run(self):
        """
        Run this scraper and log the results.
        """
        self._init_couchdb()
        
        #try:
        self.scrape()
            # TODO - self.add_log(...)
        #except:
            #TODO - self.add_log(...)
            #pass
        
        
class Event(Document):
    """
    A couchdb-python document schema for an event.  Properties that are
    expected to be set by the scraper are defined as fields.  Additional
    properties may be stored by appending them to the event as though it
    were a dictionary.  See EventScraper.add_event for an example of this.
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
    error_text = TextField()