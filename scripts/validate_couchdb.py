#!/usr/bin/env python

import datetime
import hashlib
import optparse
import os
import sys
import time
import traceback
import urllib2
import urlparse

import couchdb
from couchdb.schema import *
from dateutil.parser import parse

# Previous versions will yield erroneous 409 responses from CouchDB
if couchdb.__version__ < "0.7":
    print 'couchdb-python version 0.7 or greater is required.'
    sys.exit()
    
    
class CouchDBValidator(object):
    """
    Executes a series of validation methods over the data that exists in the
    CouchDB database.
    """
    
    def __init__(self):
        self.error_index = 0
        
        self.required_fields = {
            'datetime': datetime.datetime, 
            'title': unicode, 
            'description': unicode, 
            'end_datetime': datetime.datetime,
            'branch': unicode, 
            'entity': unicode, 
            'source_url': unicode, 
            'source_text': unicode,
            'access_datetime': datetime.datetime, 
            'parser_name': unicode, 
            'parser_version': unicode }
        
        self.nullable_fields = ['description', 'end_datetime']
        self.empty_allowed_fields = ['description']
        self.url_fields = ['source_url']

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
        
        for field in self.required_fields.keys():
            if field not in event:
                self.error(name, event, 'Missing \"%s\"' % field)
                
    def validate_field_types(self, event):
        """
        Validate that the required fields in the event are of the correct type.
        """
        name = 'validate_field_types'
        
        for field, cls in self.required_fields.items():
            # Handled by validate_required_fields
            if field not in event:
                continue
            
            if cls == datetime.datetime:
                if not isinstance(event[field], unicode):
                    # Ignore fields which are null and may be
                    if field in self.nullable_fields:
                        continue
                        
                    self.error(
                        name, event, 
                        'Field \"%s\" was not properly encoded as unicode' % field)
            else:
                if not isinstance(event[field], cls):
                    # Ignore fields which are null and may be
                    if field in self.nullable_fields:
                        continue
                     
                    self.error(
                        name, event, 
                        'Field \"%s\" is type %s not an instance of %s' % (
                            field, type(event[field]), cls))
                    
    def validate_datetime_format(self, event):
        """
        Validate that all datetime strings are encoded in the proper format
        and are valid for decoding.
        """
        name = 'validate_datetime_format'
        
        for field, cls in self.required_fields.items():
            # Handled by validate_required_fields
            if field not in event:
                continue
            
            if cls == datetime.datetime:
                # Handled by validate_field_types 
                if not isinstance(event[field], unicode):
                    continue
                
                try:
                    value = parse(event[field])
                except ValueError:
                    self.error(
                        name, event, 
                        'Field \"%s\" is not properly encoded as an ISO 8601 date string' % field)
                    continue
                
                if not isinstance(value, datetime.datetime):
                    self.error(
                        name, event, 
                        'Field \"%s\" is not properly encoded as an ISO 8601 date string' % field)
                    
    def validate_empty_strings(self, event):
        """
        Validate that only specifically allowed fields hold empty strings.
        """
        name = 'validate_datetime_format'
        
        for field, cls in self.required_fields.items():
            # Handled by validate_required_fields
            if field not in event:
                continue
            
            if cls == unicode:
                # Handled by validate_field_types 
                if not isinstance(event[field], unicode):
                    continue
                
                if event[field] == u'' and field not in self.empty_allowed_fields:
                    self.error(
                        name, event, 
                        'Field \"%s\" is not allowed to contain an empty string' % field)
                    
    def validate_urls(self, event):
        """
        Validate the fields contain only absolute urls.
        """
        for field, cls in self.required_fields.items():
            # Handled by validate_required_fields
            if field not in event:
                continue
            
            if field in self.url_fields:
                result = urlparse.urlparse(event[field])
                
                if not result.scheme or not result.netloc:
                    self.error(
                        name, event, 
                        'Field \"%s\" is not an absolute URL' % field)
                
    def error(self, validator, event, message):
        """
        Write out an error.
        """
        try:
            parser_name = event['parser_name']
        except KeyError:
            parser_name = 'unknown'
        
        unicode_message = 'Issue %i - %s - %s - \"%s\" - %s' % (
            self.error_index, parser_name, validator, event.id, message)
        
        print unicode_message.encode('utf-8')
        
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
            if id.find('_design') == 0:
                continue
            
            event = self.event_db[id]
            
            for v in validators:
                v(event)
                
        print 'Validation complete.'

if __name__ == '__main__':
    CouchDBValidator().run()