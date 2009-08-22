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
    
    
class CouchDBValidator(object):
    """
    TODO
    """
    error_index = 0
        
    def _init_couchdb(self):
        """
        Setup CouchDB.  Encapsulated for clarity.
        """
        self.server_uri = 'http://%s:%s' % (
            self.options.server, self.options.port)
        self.server = couchdb.Server(self.server_uri)
        
        if self.options.eventdb not in self.server:
            self.server.create(self.options.eventdb)
            
        self.event_db = self.server[self.options.eventdb]
        
        if self.options.logdb not in self.server:
            self.server.create(self.options.logdb)
            
        self.log_db = self.server[self.options.logdb]
    
    def parse_cli_options(self):
        """
        Parse any command line options that were passed to the script.
        """       
        parser = optparse.OptionParser()
        
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
        
        (self.options, self.args) = parser.parse_args()
        
    def get_validators(self):
        """
        Get a list of all validators.
        
        A validator method begins with "validate_" and accepts self and event
        as parameters.
        """
        return [getattr(self, f) for f in dir(self) if f.find('validate_') == 0]
        
    def validate_required_fields(self, event):
        """
        Validate that the event includes every required field.
        """
        name = 'validate_required_fields'
        required_fields = ['datetime', 'title', 'description', 'end_datetime',
                           'branch', 'entity', 'source_url', 'source_text',
                           'access_datetime', 'parser_name', 'parser_version']
        
        for field in required_fields:
            if field not in event:
                self.error(name, event, 'missing %s' % field)
                
    def error(self, validator, event, message):
        """
        Write out an error.
        """
        try:
            parser_name = event['parser_name']
        except KeyError:
            parser_name = 'unknown'
            
        print 'Issue %i - %s - %s - \"%s\" is %s' % (
            self.error_index, parser_name, validator, event.id, message)
        
        self.error_index = self.error_index + 1
               
    def run(self):
        """
        Run this scraper and log the results.
        """
        self.parse_cli_options()
        
        self._init_couchdb()
        
        validators = self.get_validators()
        
        print 'Validating %i documents in %s with %i methods...' % (
            len(self.event_db), self.options.eventdb, len(validators))
        
        for id in self.event_db:
            event = self.event_db[id]
            
            for v in validators:
                v(event)
                
        print 'Validation complete.'

if __name__ == '__main__':
    CouchDBValidator().run()