"""
This module provides a outline of an EventScraper subclass, along with detailed
notes on how to implement a new scraper.

Please note that this example will NOT run without modification.  For a good
minimally complicated scraper that will run unmodified see 
scripts/legislative/house_schedule/scraper.py
"""

from datetime import datetime
import os
import re
import sys

# For a list of blessed dependencies, see 
# http://wiki.github.com/bouvard/votersdaily/dependencies
from dateutil.parser import parse
import html5lib

# This path hack will allow the scraper to run as a stand-alone script
# It assumes your scraper is located at 
#   scripts/branch_folder/scraper_folder/scraper.py
sys.path.append(os.path.dirname(os.path.dirname(os.path.dirname(os.path.abspath(__file__)))))
from pyutils.events import *

# A scraper must derive from EventScraper
class ExampleScraper(EventScraper):
    """
    Please document any idiosyncracies of the subject matter that is being
    scraped in the scraper's docstrings.
    
    If any exceptions are raised during your scraper, let them percolate up
    so that they can be automagically logged in the database.
    """
    
    # These three properties must be set either here or in __init__ and prior
    # to EventScraper.__init__(self) being called.
    
    # Canonical name for this scraper
    name = 'Example Scraper'
    # Should be incremented each time the format of the scrapped data changes
    version = '0.0.1' 
    # Frequency at which scraping attempts should be made, in hours.
    # (please be kind to government servers and don't get our robot blocked)
    # You may enter a fractional value if there is a good reason to parse
    # this page frequently (such as near real-time data)
    frequency = 6.0

    parser = html5lib.HTMLParser(
        tree=html5lib.treebuilders.getTreeBuilder('beautifulsoup'))

    def scrape(self):
        """
        This method should encapsulate all scraping logic.
        """
        url =  'YOUR GOVERNMENT URL HERE'
        
        # When opening the root, or first, URL that will be scraped, you must
        # pass True as the second parameter of self.urlopen().  This will cause
        # the base class to store several critical pieces of information for
        # logging.  When scraping any sub-pages or sequential pages after the
        # first, you must specify False as the second parameter.
        soup = self.parser.parse(self.urlopen(url, True))
        
        # YOUR SCRAPING LOGIC GOES HERE
        
        for row in rows:
            
            # If a an error is encountered during the scraping process, then...
            # raise ScrapeError('Your error here.')
            
            # Create an event object with the data that has been scraped
            # For details of these parameters, see
            # http://wiki.github.com/bouvard/votersdaily/database-planning
            new_event = Event(
                datetime=event_datetime,
                title=name_string,
                description=None,
                end_datetime=end_datetime,
                branch='Legislative',
                entity='House of Representatives',
                source_url=url,
                source_text=str(row),
                access_datetime=self.access_datetime)
            
            # Store this event in the database
            self.add_event(new_event)

# Scrapers should specify the following main block so that they can be run as 
# stand-alone scripts
if __name__ == '__main__':
    ExampleScraper().run()