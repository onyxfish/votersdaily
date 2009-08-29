#!/usr/bin/env python

import ConfigParser
import os
import re
import sys

class ScriptValidator(object):
    """
    This class functions like a poor man's cron for all scrapers that exist in
    the directory tree.  It searches the branch directories for all scraper
    scripts and runs at their specified intervals, each in its own process.
    """
        
    def run(self):
        """
        Mine the directory tree for EventScrapers and schedule their first
        instance.
        """
        
        print 'Running validation...'
                
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
                
                # Validate that files exit     
                if not config_file:
                    print 'The %s/%s folder does not contain a config file.' % (branch, folder)
                    continue
                
                if not scraper_file:
                    print 'The %s/%s folder does not contain a scraper script.' % (branch, folder)
                    continue
                
                # Validate that file will be executed correctly
                if not os.access(scraper_file, os.X_OK):
                    print 'The %s/%s scraper script is not executable.' % (branch, folder)
                    
                f = open(scraper_file, 'r')
                first_two = f.read(2)
                
                if first_two != '#!':
                    print 'The %s/%s scraper script does not include a shebang (#!) line.' % (branch, folder)                    
                
                # Read out the configuration information for this scraper
                config_parser = ConfigParser.ConfigParser()
                config_parser.read(config_file)

                # Validate that all config file options exist
                try:
                    name = config_parser.get('Scraper', 'name')
                except ConfigParser.NoOptionError:
                    print 'The config file for %s/%s is missing a value for "name".' % (branch, folder)
                    
                try:
                    language = config_parser.get('Scraper', 'language')
                except ConfigParser.NoOptionError:
                    print 'The config file for %s/%s is missing a value for "language".' % (branch, folder)
                
                try:
                    version = config_parser.get('Scraper', 'version')
                except ConfigParser.NoOptionError:
                    print 'The config file for %s/%s is missing a value for "version".' % (branch, folder)
                
                try:
                    frequency = config_parser.getfloat('Scraper', 'frequency')
                except ConfigParser.NoOptionError:
                    print 'The config file for %s/%s is missing a value for "frequency".' % (branch, folder)
                
                try:
                    enabled = config_parser.getboolean('Scraper', 'enabled')
                except ConfigParser.NoOptionError:
                    print 'The config file for %s/%s is missing a value for "enabled".' % (branch, folder)
                
        print 'Done.'

if __name__ == '__main__':
    ScriptValidator().run()