#!/usr/bin/php -q
<?php
$PATH_TO_INCLUDES = dirname(dirname(dirname(__FILE__)));
require $PATH_TO_INCLUDES.'/phputils/EventScraper.php';
require $PATH_TO_INCLUDES.'/phputils/couchdb.php';
 
ini_set("display_errors", true);
error_reporting(E_ALL & ~E_NOTICE);

class HouseCommitteeVeteransAffairs extends EventScraper_Abstract 
{
    
    protected $url = 'http://www3.capwiz.com/c-span/dbq/officials/schedule.dbq?committee=hvete&command=committee_schedules&chambername=House&chamber=H&period=';
    public $parser_name = 'C-SPAN House Veterans Affairs Schedule Scraper';
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
        //print_r($events);
        $this->add_events($events);
    }
    
    protected function scrape()
    {
        $events = array();
        $scrape_start = microtime_float();
        $response = $this->urlopen($this->url);

        $this->access_time = time();

        preg_match_all('#<li>(.+?)<\/li>#is', $response, $li);

        $i=0;
        foreach($li[1] as $li_str) {
            if(!preg_match('/<a href=/is',$li_str)) {
                preg_match_all('#<span[^>]*>(.+?)<\/span>#is', $li_str, $span);
                $_date_tmp = date("m/d/Y", strtotime(strip_tags($span[1][0])));
                list($month, $day, $year) = explode('/',$_date_tmp);
                $_date_str = strftime('%Y-%m-%dT%H:%M:%SZ', mktime(0, 0, 0, $month, $day, $year));

                $events[$i]['couchdb_id'] = (string) $_date_str . ' -  ' .$this->parser_name;        
                $events[$i]['datetime'] = (string) $_date_str;
                $events[$i]['end_datetime'] = null;
                $events[$i]['title'] = (string) 'House Veterans Affairs  Schedule';
                $events[$i]['description'] = (string) strip_tags(trim($span[1][1]));
                $events[$i]['branch'] = BranchName::$legislative;
                $events[$i]['entity'] = EntityName::$house;
                $events[$i]['source_url'] = $this->url;
                $events[$i]['source_text'] = (string) trim($li_str);

                $_access_time = date('D, d M Y H:i:s T', $this->access_time);
                $events[$i]['access_datetime'] = $this->_vd_date_format($_access_time);
                $events[$i]['parser_name'] = (string) $this->parser_name;
                $events[$i]['parser_version'] = $this->parser_version; 

                $i++;
            }
        }

        $scrape_end = microtime_float();
        $this->parser_runtime = round(($scrape_end - $scrape_start), 4);

        return $events;
    }
}
$parser = new HouseCommitteeVeteransAffairs;
$parser->run();
