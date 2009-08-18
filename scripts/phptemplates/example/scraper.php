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
/* change class name from ScraperName */
class ScraperName extends EventScraper_Abstract 
{
    
    protected $url = 'http://sitename.com/file.ext';
    private $parser_name = 'Scraper Name';
    private $parser_version = '0.1';
    private $parser_frequency = '6.0';
    protected $csv_filename = 'data/mysite.csv';
    protected $ical_filename = 'data/mysite.ics';


    public function __construct()
    {
        parent::__construct();
        $this->year = date("Y");
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

        $this->access_time = time();

        /*
        $events[$i]['start_date'] = null;
        $events[$i]['end_date'] = null;
        $events[$i]['title'] = null;
        $events[$i]['description'] = null;
        $events[$i]['branch'] = null;
        $events[$i]['entity'] = null;
        $events[$i]['source_url'] = $this->url;
        $events[$i]['source_text'] = '';
        $events[$i]['access_datetime'] = $this->access_time;
        $events[$i]['parser_name'] = null;
        $events[$i]['parser_version'] = null; 
        
        return $events;
        */
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


$parser = new ScraperName; //<-- change this to match class name

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
$scrape_log['source_text'] = $parser->source_text;
$scrape_log['access_datetime'] = $parser->access_time;

//deal with logging here

echo "Parse completed in ".bcsub($scrape_end, $scrape_start, 4)." seconds."."\n\n"; 

