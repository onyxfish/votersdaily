from multiprocessing import Pool, Process
import os
import re
import sys
import threading
import traceback

class ScraperScheduler(object):
    """
    This class functions like a poor man's cron for all scrapers that exist in
    the directory tree.  Using crazy-Python-dynamics its introspects all 
    available modules, imports them, instantiates their EventScraper subclasses 
    and then schedules them to run at their specified intervals.  It also uses
    the multiprocessing module for maximum efficiency.
    """
    
    def run(self):
        """
        Mine the directory tree for EventScrapers and schedule their first
        instance.
        """
        for root, dirs, files in os.walk(os.path.dirname(os.path.abspath(__file__))):
            # Skip utility folders
            if os.path.split(root)[1] in ['example', 'pyutils']:
                continue
            
            # Add new path to sys.path so that modules may be imported
            sys.path.append(root)
            
            for file in files:
                filename, ext = os.path.splitext(file)
                
                if ext != '.py':
                    continue
                
                module = __import__(filename)
                
                for i in dir(module):
                    if re.match('.*Scraper$', i) and i != 'EventScraper':
                        # Instantiate scraper by name
                        scraper = module.__dict__[i]()
                        
                        self.start_scraper(scraper)

    def start_scraper(self, scraper):
        """
        Run the specified scraper and then reschedule it to run at its next 
        interval.  Eat any exceptions so that the scheduler will never crash.
        """
        
        print 'Running %s.' % scraper.name
        
        # Spin off a new process
        p = Process(target=scraper.run)
        p.start()
                        
        print 'Scheduling %s to run again in %i hours.' % (
            scraper.name, scraper.frequency)
        
        # Schedule next run
        t = threading.Timer(
            scraper.frequency * 60.0 * 60.0, self.start_scraper, (scraper))
        t.start()

if __name__ == '__main__':
    ScraperScheduler().run()