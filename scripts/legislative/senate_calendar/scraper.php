#!/usr/bin/php -q
<?php
$PATH_TO_INCLUDES = dirname(dirname(dirname(__FILE__)));
require $PATH_TO_INCLUDES.'/phputils/EventScraper.php';
require $PATH_TO_INCLUDES.'/phputils/couchdb.php';

/*
 * Voters Daily: PHP - Senate Calendar Scraper
 * http://wiki.github.com/bouvard/votersdaily
 *
 * @author      Chauncey Thorn <chaunceyt@gmail.com>
 * Link: http://www.cthorn.com/
 *
 */

class SenateCalendar extends EventScraper_Abstract
{
    protected $url = 'http://democrats.senate.gov/calendar/2009-08.html';
    public $parser_name = 'Senate Calendar Scraper';
    public $parser_version = '0.1';
    public $parser_frequency = '6.0';

    public function __construct()
    {
        parent::__construct();
    }

    public function run()
    {
        $events = $this->scrape();
        $this->add_events($events);
    }

    protected function scrape()
    {
        $events = array();

        $this->source_url = $this->url;
        $response = $this->urlopen($this->url);
        $this->access_time = time();
        $this->source_text = $response;

        preg_match_all('#<table[^>]*>(.+?)<\/table>#is',$response,$matches);
       
        foreach($matches as $data) {
            $i=0;
            foreach($data as $row) {
                $row = trim($row);
                //this could cause problems need to monitor
                //hopefully I'll get a bug notice
                if(preg_match('/Convenes: /is',$row)) {

                    preg_match('#<table[^>]*>(.+?)<\/table>#is',$row,$matches);
                    preg_match('#<a name=(.*?)>#is', $matches[1], $calendar_day);

                    $source_text = $matches[0];

                    $_date_str = explode('/',$this->url);
                    list($date_str, $ext) = explode('.', $_date_str[4]);

                    if(!empty($calendar_day[1])) {
                        $description = strip_tags($matches[1], '<a>');
                        $description = strip_tags($description, '<a>');
                        $description = str_replace(array('<a name='.$calendar_day[1].'></a>','\r','\n'),' ',$description);
                        $events[$i]['couchdb_id'] = (string) $this->_vd_date_format($date_str.'-'.$calendar_day[1]) . ' -  '.BranchName::$legislative.' -  - Senate Calendar'; 

                        $events[$i]['datetime'] = $this->_vd_date_format($date_str.'-'.$calendar_day[1]);
                        $events[$i]['end_datetime'] = null;
                        $events[$i]['title'] = 'Senate Calendar';
                        $events[$i]['description'] = trim(str_replace(array("\r\n",':'), ' ', substr($description,1)));
                        $events[$i]['branch'] = BranchName::$legislative;
                        $events[$i]['entity'] = EntityName::$senate;
                        $events[$i]['source_url'] = $this->url;
                        $events[$i]['source_text'] = (string) $source_text;
                        $events[$i]['access_datetime'] = (string) $this->access_time;
                        $events[$i]['parser_name'] = (string) $this->parser_name;
                        $events[$i]['parser_version'] = $this->parser_version;

                    }
                }

            $i++;
            }
        }
        return $events;
    }
}

$parser = new SenateCalendar;

//setup loggin array
$scrape_log['parser_name'] = $parser->parser_name;
$scrape_log['parser_version'] = $parser->parser_version;


$scrape_start = microtime_float();
$parser->run();
$scrape_end = microtime_float();

//value available only after scrape
$scrape_log['url'] = $parser->source_url;
$scrape_log['source_text'] = $parser->source_text;
$scrape_log['access_datetime'] = $parser->access_time;

//deal with logging here

//echo "Parse completed in ".bcsub($scrape_end, $scrape_start, 4)." seconds."."\n\n"; 
