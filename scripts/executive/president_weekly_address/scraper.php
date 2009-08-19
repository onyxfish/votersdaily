#!/usr/bin/php -q
<?php
$PATH_TO_INCLUDES = dirname(dirname(dirname(__FILE__)));
echo $PATH_TO_INCLUDES;
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


class PresidentWeeklyAddress extends EventScraper_Abstract
{
    
    protected $url = 'http://www.whitehouse.gov/feed/blog/';
    public $parser_name = 'President Weekly Address Scraper';
    public $parser_version = '0.1';
    public $parser_frequency = '6.0';

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

        $this->source_url = $this->url;
        $response = $this->urlopen($this->url);
        $this->access_time = time();
        $this->source_text = $response;

        $xml = new SimpleXMLElement($response);
        $weeklyaddress = $xml->entry;

        $i=0;
        foreach($xml->entry as $weeklyaddress) {
            $description_str = 'Author: '.$weeklyaddress->author->name.' <a href="'.$weeklyaddress->link->attributes()->href.'">'.$weeklyaddress->title.'</a>';
            $events[$i]['couchdb_id'] = (string) $this->_vd_date_format($weeklyaddress->updated) . ' - '.BranchName::$executive.' - '.EntityName::$whitehouse.' - ' . trim($weeklyaddress->title);
            $events[$i]['datetime'] = (string) $this->_vd_date_format($weeklyaddress->updated);
            $events[$i]['end_datetime'] = null;
            $events[$i]['title'] = (string) trim($weeklyaddress->title);
            $events[$i]['description'] = (string) trim($description_str);
            $events[$i]['branch'] = BranchName::$executive;
            $events[$i]['entity'] = EntityName::$whitehouse;
            $events[$i]['source_url'] = $this->url;
            $events[$i]['source_text'] = (string) trim($weeklyaddress);
            $events[$i]['access_datetime'] = (string) $this->access_time;
            $events[$i]['parser_name'] = (string) $this->parser_name;
            $events[$i]['parser_version'] = (string) $this->parser_version;             
            $i++;
        }

        return $events;
    }
}//end of class

$engine_options = array('couchdb','csv', 'ical');
if(isset($argv[1]) && in_array($argv[1], $engine_options)) {
        $engine= $argv[1];
            echo "Using ".$engine." as Storage Engine...\n\n";
}
else {
        $engine=null;
}


$parser = new PresidentWeeklyAddress;

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

//echo "Parse completed in ".bcsub($scrape_end, $scrape_start, 4)." seconds."."\n\n";
