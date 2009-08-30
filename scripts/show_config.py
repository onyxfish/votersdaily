#!/usr/bin/env python

import ConfigParser
import os
import re
import sys

class ConfigPrinter(object):
    """
    Dumps configuration information for all scraper scripts.
    """
    
    print_index = 1
    
    def run(self):
        """
        Loop through the available scripts and parse their configuration.
        """
        self.script_configs = {}
        
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
                    
                self.script_configs[name] = {}

                self.script_configs[name]['name'] = name
                self.script_configs[name]['language'] = \
                    config_parser.get('Scraper', 'language')
                self.script_configs[name]['version'] = \
                    config_parser.get('Scraper', 'version')
                self.script_configs[name]['frequency'] = \
                    config_parser.getfloat('Scraper', 'frequency')
                self.script_configs[name]['enabled'] = \
                    config_parser.getboolean('Scraper', 'enabled')
        
        names = self.script_configs.keys()
        names.sort()
        
        for name in names:
            self.print_config(**self.script_configs[name])
                    
    def print_config(self, name, language, version, frequency, enabled):
        """
        Print the configuration for a script.
        """
        print '%i - %s - %s - %s - %s - %s' % (
            self.print_index, name, language, version, frequency, enabled)
        
        self.print_index = self.print_index + 1

if __name__ == '__main__':
    ConfigPrinter().run()