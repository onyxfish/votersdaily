#!/usr/bin/env python

from datetime import datetime
import os
import re
import sys

from dateutil.parser import parse
import html5lib

sys.path.append(os.path.dirname(os.path.dirname(os.path.dirname(os.path.abspath(__file__)))))
from pyutils.events import *

class HouseScheduleScraper(EventScraper):
    """
    An EventScraper for the House Schedule maintained on www.house.gov.
    """
        
    name = 'House Schedule Scraper'
    version = '1.0'

    parser = html5lib.HTMLParser(
        tree=html5lib.treebuilders.getTreeBuilder('beautifulsoup'))

    def scrape(self):
        """
        Pull down the schedule and parse its event data.
        """
        url =  'http://www.house.gov/house/House_Calendar.shtml'
        soup = self.parser.parse(self.urlopen(url, True))
        
        primary_div = soup.find('div', id='primary')
        
        if str(primary_div).find('111th Congress') > 0:
            year = 2009
        else:
            raise ScrapeError(
                'This script needs to be updated for a new congress.')
            
        events = {}
        
        rows = primary_div.findAll('tr')
        
        # Precompute regular expressions that are going to be used many times
        # See below for usage
        date_format_one = re.compile(
            '^[A-z]+[\s]+[\d]+$')
        date_format_two = re.compile(
            '^([A-z]+)[\s]+([\d]+)[\s]*-[\s]*([\d]+)$')
        date_format_three = re.compile(
            '^([A-z]+[\s]+[\d]+)[\s]*-[\s]*([A-z]+[\s]+[\d]+)$')
        
        for row in rows:
            cells = row.findAll('td')
            
            if len(cells) < 2:
                continue
            
            date_string = cells[0].contents[0]
            name_string = cells[1].contents[0]
            
            # Don't trip over bolded strings
            if hasattr(name_string, 'contents'):
                name_string = name_string.contents[0]
                
            date_string = date_string.strip()
            name_string = name_string.strip()
            
            # Date formats are woefully inconsistent
            # e.g. January 6
            if date_format_one.match(date_string):
                event_datetime = parse(date_string)
                end_datetime = None
            # e.g. January 29-31
            elif date_format_two.match(date_string):
                match = date_format_two.match(date_string)
                event_datetime = parse('%s %s' % (match.group(1), match.group(2)))
                end_datetime = parse('%s %s' % (match.group(1), match.group(3)))
            # e.g. April 6 - April 17
            elif date_format_three.match(date_string):
                match = date_format_three.match(date_string)
                event_datetime = parse(match.group(1))
                end_datetime = parse(match.group(2))
            else:
                raise ScrapeError('Unrecognized date format: %s.' % date_string)
               
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
            
            id = '%s - %s - %s - %s' % (
                self.encode_datetime(new_event['datetime']),
                new_event['branch'],
                new_event['entity'],
                new_event['title'])
            
            events[id] = new_event
            
        return events
                
if __name__ == '__main__':
    HouseScheduleScraper().run()