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


class SupremeCountCourtOrders extends EventScraper_Abstract
{
    protected $url = 'http://www.supremecourtus.gov/orders/08ordersofthecourt.html';
    public $parser_name = 'Supreme Court 2008 Court Orders Scraper';
    public $parser_version = '0.1';
    public $parser_frequency = '6.0';
    protected $csv_filename = 'data/supremecourtcourtorders.csv';
    protected $ical_filename = 'data/supremecourtcourtorders.ics';

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

                 preg_match('#<TABLE[^>]*>(.+?)<\/TABLE>#is',$row,$matches);
                 preg_match_all('#<TR[^>]*>(.+?)<\/TR>#is', $matches[1], $trRows);
                 
                 foreach($trRows[1] as $row) {
                    preg_match_all('#<TD[^>]*>(.*?)</TD>#is',$row, $data);
                    $_date_tmp = str_replace('/','-',trim($data[1][0]));
                    list($month,$day,$year) = explode('-',$_date_tmp);

                    $date_str = '20'.$year.'-'.$month.'-'.$day;

                    $title_url = $data[1][1];
                    $title = strip_tags($data[1][1]);
                    
                    $description = strip_tags($matches[1], '<a>');
                    $description = strip_tags($description, '<a>');
                    $description = str_replace(array('<a name='.$calendar_day[1].'></a>','\r'),'',$description);

                    $events[$i]['start_date'] = $date_str;
                    $events[$i]['end_date'] = null;
                    $events[$i]['title'] = $title;
                    $events[$i]['description'] = $title_url;
                    $events[$i]['branch'] = 'Judicial';
                    $events[$i]['entity'] = 'Supreme Court';
                    $events[$i]['source_url'] = $this->url;
                    $events[$i]['source_text'] = $event;
                    $events[$i]['access_datetime'] = $this->access_time;
                    $events[$i]['parser_name'] = $this->parser_name;
                    $events[$i]['parser_version'] = $this->parser_version;
                    $i++;
                 }
            }
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


$parser = new SupremeCountCourtOrders;

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
