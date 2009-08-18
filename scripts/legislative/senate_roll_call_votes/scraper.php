#!/usr/bin/php -q
<?php
$PATH_TO_INCLUDES = dirname(dirname(dirname(__FILE__)));
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

class SenateRollCallVotes extends EventScraper_Abstract
{
    protected $url = 'http://www.senate.gov/legislative/LIS/roll_call_lists/vote_menu_111_1.xml';
    public $parser_name = 'Senate Roll Call Votes Scraper';
    public $parser_version = '0.1';
    public $parser_frequency = '6.0';
    protected $csv_filename = 'data/senaterollcallvotes.csv';
    protected $ical_filename = 'data/senaterollcallvotes.ics';
    
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
        $xml = $this->urlopen($this->url);
        $this->access_time = time();
        $this->source_text = $xml;

        $response = simplexml_load_string($xml);
        
        $votes = $response->votes->vote;
        $total_events = sizeof($votes);
    
        for($i=0; $i< $total_events; $i++) {
            $description_str = 'Vote Number '.$votes[$i]->vote_number.' Issue ' . $votes[$i]->issue->A;
            $description_str .= ' Answering: ' . $votes[$i]->question . ' Results: ' .$votes[$i]->result;
            $description_str .=  ' Votes - yeas: ' .$votes[$i]->vote_tally->yeas. ' nays: ' .$votes[$i]->vote_tally->nays;

            //deal with date
            $start_date = (string) $votes[$i]->vote_date;
            list($day, $month) = explode('-', $start_date);
            $date_str = $month . ' '. $day.' 2009';
            $events[$i]['start_date'] = date('Y-m-d', strtotime($date_str));
            
            $events[$i]['end_date'] = '';
            $events[$i]['title'] = (string) $votes[$i]->title;
            $events[$i]['description'] = $description_str;
            $events[$i]['branch'] = 'Legislative';
            $events[$i]['entity'] = 'Senate';
            $events[$i]['source_url'] = $this->url;
            $events[$i]['source_text'] = '';
            $events[$i]['access_datetime'] = $this->access_time;
            $events[$i]['parser_name'] = $this->parser_name;
            $events[$i]['parser_version'] = $this->parser_version;            
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


$parser = new SenateRollCallVotes;

echo "\n\n".'Running Parser: ' . $parser->parser_name . '...'."\n";

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
$scrape_log['source_text'] = $parser->source_text;
$scrape_log['access_datetime'] = $parser->access_time;

//deal with logging here

echo "Parse completed in ".bcsub($scrape_end, $scrape_start, 4)." seconds."."\n\n"; 
