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


class WhiteHouseNominations extends EventScraper_Abstract
{
    
    protected $url = 'http://www.socrata.com/views/n5m4-mism/rows.xml?accessType=API';
    public $parser_name = 'White House Nominations Scraper';
    public $parser_version = '0.1';
    public $parser_frequency = '6.0';
    protected $csv_filename = 'data/whitehousenominations.csv';
    protected $ical_filename = 'data/whitehousenominations.ics';


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
        
        $nominations = $xml->rows;
        $total_nominations = sizeof($nominations->row);

        for($i=0; $i < $total_nominations; $i++) {
            $description_str = 'Nomination: ' .$nominations->row[$i]->name . ' ' . $nominations->row[$i]->agency->attributes()->description;
            $description_str .= $nominations->row[$i]->position . ' confirmed: (' . $nominations->row[$i]->confirmed . ')';
            $description_str .= ' holdover:  (' . $nominations->row[$i]->holdover.')';
           
            $_date_str = (string) $nominations->row[$i]->formal_nomination_date;
            list($_month,$_day,$_year) = explode('/', $_date_str);
            $events[$i]['datetime'] = (string) $this->_vd_date_format($_year .'-'.$_month.'-'.$_day);
            $events[$i]['end_datetime'] = (string) $this->_vd_date_format($nominations->row[$i]->confirmation_vote);
            $events[$i]['title'] = (string) 'Nomination: ' . trim($nominations->row[$i]->position);
            $events[$i]['description'] = (string) trim($description_str);
            $events[$i]['branch'] = 'Executive';
            $events[$i]['entity'] = 'President WhiteHouse';
            $events[$i]['nominee'] = (string) $nominations->row[$i]->name;
            $events[$i]['is_confirmed'] = (string) $nominations->row[$i]->confirmed;
            $events[$i]['is_holdover'] = (string) $nominations->row[$i]->holdover;
            $events[$i]['source_url'] = $this->url;
            $events[$i]['source_text'] = (string) trim($nominations->row[$i]);
            $events[$i]['access_datetime'] = $this->access_time;
            $events[$i]['parser_name'] = $this->parser_name;
            $events[$i]['parser_version'] = $this->parser_version;            
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


$parser = new WhiteHouseNominations;

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
