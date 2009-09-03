#!/usr/bin/env python

import ConfigParser
import optparse
import os
import re
import subprocess
import sys
import threading

import couchdb
from couchdb.schema import *

# Previous versions will yield erroneous 409 responses from CouchDB
if couchdb.__version__ < "0.7":
    print 'couchdb-python version 0.7 or greater is required.'
    sys.exit()

class ScraperScheduler(object):
    """
    This class functions like a poor man's cron for all scrapers that exist in
    the directory tree.  It searches the branch directories for all scraper
    scripts and runs at their specified intervals, each in its own process.
    """
    
    timers = []
    
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
                          help='ignored', 
                          default=False)
        
        parser.add_option('--nodaemon',
                          dest='nodaemon',
                          action='store_true',
                          help='drop existing databases and recreate, do not schedule future runs', 
                          default=False)
        
        (self.options, self.args) = parser.parse_args()
        
        # Strip --nodaemon from sys.argv so it does not get passed to scrapers
        if '--nodaemon' in sys.argv: sys.argv.remove('--nodaemon')

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
        
    def run(self):
        """
        Mine the directory tree for EventScrapers and schedule their first
        instance.
        """
        self._parse_cli_options()
        
        self._init_couchdb()
                
        # Loop through branch-level folders
        for branch in ['executive', 'judicial', 'legislative', 'other']:
            runner_path = os.path.dirname(os.path.abspath(__file__))
            branch_path = os.path.join(runner_path, branch)
            
            # Loop through scraper-level folders
            for folder in os.listdir(branch_path):
                folder_path = os.path.join(branch_path, folder)
                
                config_file = None
                scraper_file = None
                
                # Get paths to the config and scraper (script) files
                for file in os.listdir(folder_path):
                    if file == 'config':
                        config_file = os.path.join(folder_path, 'config')
                    elif re.match('scraper', file):
                        scraper_file = os.path.join(folder_path, file)
                        
                if not config_file:
                    print 'The %s/%s folder does not contain a config file.' % (branch, folder)
                    continue
                
                if not scraper_file:
                    print 'The %s/%s folder does not contain a scraper script.' % (branch, folder)
                    continue
                
                # Read out the configuration information for this scraper
                config_parser = ConfigParser.ConfigParser()
                config_parser.read(config_file)

                name = config_parser.get('Scraper', 'name')
                frequency = config_parser.getfloat('Scraper', 'frequency')
                enabled = config_parser.getboolean('Scraper', 'enabled')
                
                # Schedule the first run
                if enabled:
                    self.start_scraper(scraper_file, name, frequency)
                else:
                    print '%s is disabled.' % name
        
        # Poll until killed
        if not self.options.nodaemon: 
            while True:
                # Allow user to kill process 
                # (keyboard interrupt won't work due to threads)
                answer = raw_input('Kill all timers?  Type "Q" and then return.')
                
                if answer in [ 'Q', 'q' ]:
                    for t in self.timers:
                        t.cancel()
                    break

    def start_scraper(self, scraper, name, frequency, timer=None):
        """
        Run the specified scraper and then reschedule it to run at its next 
        interval.
        """
        # If run from a timer, remove that timer from the list of active timers
        if timer:
            self.timers.remove(timer)
        
        print 'Running %s.' % name
        
        # Spin off a new process (using any passed CLI options)
        scraper_args = [scraper]
        scraper_args.extend(sys.argv[1:])
        subprocess.Popen(scraper_args, shell=False)
        
        # If in 'nodaemon' mode then skip scheduling
        if self.options.nodaemon:
            return
                        
        print 'Scheduling %s to run again in %i hours.' % (name, frequency)
        
        # Schedule next run
        t = threading.Timer(
            frequency * 60.0 * 60.0, 
            self.start_scraper)
        t.args = (scraper, name, frequency, t)
        t.start()
        
        # Add timer to the list of active timers
        self.timers.append(t)

if __name__ == '__main__':
    ScraperScheduler().run()