#!/usr/bin/php -q
<?php
$PATH_TO_INCLUDES = dirname(dirname(dirname(dirname(__FILE__))));
require $PATH_TO_INCLUDES.'/phputils/EventScraper.php';
require $PATH_TO_INCLUDES.'/phputils/couchdb.php';
function microtime_float()
{
        list($utime, $time) = explode(" ", microtime());
            return ((float)$utime + (float)$time);
}
 
//$script_start = microtime_float();

ini_set("display_errors", true);
error_reporting(E_ALL & ~E_NOTICE);


class HouseSchedule extends EventScraper_Abstract
{
    protected $url = 'http://www.house.gov/house/House_Calendar.shtml';
    public $parser_name = 'House Schedule Scraper';
    public $parser_version = '0.1';
    public $parser_frequency = '6.0';
    protected $csv_filename = 'data/houseschedule.csv';
    protected $ical_filename = 'data/houseschedule.ics';

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
        
        $response = $this->urlopen($this->url);

        $this->source_url = $this->url;
        $this->access_time = time();

        preg_match('#<table[^>]*>(.+?)<\/table>#is',$response,$matches);
        preg_match_all('#<tr>(.+?)<\/tr>#is',$matches[1],$data);

        $i=0;

        foreach($data[1] as $event) {

            $event = str_replace(array("\r\n",'  :  ',' :  '),':',strip_tags(trim($event)));
            list($date, $text_str) = explode(':', $event); //causing warning
            $date_str = explode('-',trim($date));

            //start getting required data

            if(sizeof($date_str) > 1) {
                $events[$i]['start_date'] = date('Y-m-d', strtotime(trim($date_str[0])));
                $events[$i]['end_date'] = date('Y-m-d', strtotime(trim($date_str[1])));
            }
            else {
                $events[$i]['start_date'] = date('m-d-Y', strtotime(trim($date_str[0])));
                $events[$i]['end_date'] = null;
            }

            $events[$i]['title'] = 'House Schedule';
            $events[$i]['description'] = 'None';
            $events[$i]['branch'] = 'Legislative';
            $events[$i]['entity'] = 'House of Representatives';
            $events[$i]['source_url'] = $this->url;
            $events[$i]['source_text'] = $event;
            $events[$i]['access_datetime'] = $this->access_time;
            $events[$i]['parser_name'] = $this->parser_name;
            $events[$i]['parser_version'] = $this->parser_version;

            $i++;
        }

        return $events;
    }
}

$engine_options = array('couchdb','csv', 'ical');
if(isset($argv[1]) && in_array($argv[1], $engine_options)) {
    $engine= $argv[1];
    echo "Using ".$engine." as Storage Engine...\n\n";
}
else {
    $engine=null;
}


$parser = new HouseSchedule;

echo 'Running Parser: ' . $parser->parser_name . '...'."\n";

//setup loggin array
$scrape_log['parser_name'] = $parser->parser_name;
$scrape_log['parser_version'] = $parser->parser_version;


if($engine) {
    $parser->storageEngine = $engine;
}

$scrape_start = microtime_float();
$parser->run();
$scrape_end = microtime_float();

//value available only after scrape
$scrape_log['url'] = $parser->source_url;
$scrape_log['source_text'] = null;
$scrape_log['access_datetime'] = $parser->access_time;

//deal with logging here

echo "Parse completed in ".bcsub($scrape_end, $scrape_start, 4)." seconds."."\n\n";


?>
