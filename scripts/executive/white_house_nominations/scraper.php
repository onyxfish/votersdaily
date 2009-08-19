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
        $events['couchdb'] = array();

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
            //if we don't have a date disregard
            if(!empty($_month) && !empty($_day) && !empty($_year)) {

                $_year = (int) $_year;
                $final_date_str = strftime('%Y-%m-%dT%H:%M:%SZ', mktime(0, 0, 0, $_month, $_day, $_year));
                list($e_year, $e_month, $e_day) =  explode('-', date('Y-m-d', (int) $nominations->row[$i]->confirmation_vote));
                
                $end_date_value = strftime('%Y-%m-%dT%H:%M:%SZ', mktime(0,0,0,$e_month, $e_day,$e_year));
                $events[$i]['couchdb_id'] = (string) $final_date_str . ' -  ' .BranchName::$executive.'  - '.EntityName::$whitehouse.' - Nomination of '.$nominations->row[$i]->name.' for ' . trim($nominations->row[$i]->position);
                $events[$i]['datetime'] = (string) $final_date_str;
                $events[$i]['end_datetime'] = (string) $end_date_value;
                $events[$i]['title'] = (string) 'Nomination: ' . trim($nominations->row[$i]->position);
                $events[$i]['description'] = (string) trim($description_str);
                $events[$i]['branch'] = BranchName::$executive;
                $events[$i]['entity'] = EntityName::$whitehouse;
                $events[$i]['nominee'] = (string) $nominations->row[$i]->name;
                $events[$i]['position'] = (string) $nominations->row[$i]->position;
                $events[$i]['is_confirmed'] = (bool) $nominations->row[$i]->confirmed;
                $events[$i]['is_holdover'] = (bool) $nominations->row[$i]->holdover;
                $events[$i]['source_url'] = $this->url;
                $events[$i]['source_text'] = (string) trim($nominations->row[$i]);
                $events[$i]['access_datetime'] = (string) $this->access_time;
                $events[$i]['parser_name'] = (string) $this->parser_name;
                $events[$i]['parser_version'] = $this->parser_version;            
            } //if 
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
