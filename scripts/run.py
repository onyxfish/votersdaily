#!/usr/bin/env python

import ConfigParser
import os
import re
import subprocess
import sys
import threading

class ScraperScheduler(object):
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
        self.cli_options = ' '.join(sys.argv[1:])
        
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

    def start_scraper(self, scraper, name, frequency):
        """
        Run the specified scraper and then reschedule it to run at its next 
        interval.
        """
        
        print 'Running %s.' % name
        
        # Spin off a new process (using any passed CLI options)
        scraper_args = [scraper]
        scraper_args.extend(sys.argv[1:])
        subprocess.Popen(scraper_args, shell=False)
                        
        print 'Scheduling %s to run again in %i hours.' % (name, frequency)
        
        # Schedule next run
        t = threading.Timer(
            frequency * 60.0 * 60.0, 
            self.start_scraper, 
            (scraper, name, frequency))
        t.start()

if __name__ == '__main__':
    ScraperScheduler().run()