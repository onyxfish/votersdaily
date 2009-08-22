#!/usr/bin/php -q
<?php
$PATH_TO_INCLUDES = dirname(dirname(dirname(__FILE__)));
require $PATH_TO_INCLUDES.'/phputils/EventScraper.php';
require $PATH_TO_INCLUDES.'/phputils/couchdb.php';

/*
* Voters Daily: PHP - President Remarks Scraper
* http://wiki.github.com/bouvard/votersdaily
*
* @author Chauncey Thorn <chaunceyt@gmail.com>
* Link: http://www.cthorn.com/
*
*/

class PresidentialRemarks extends EventScraper_Abstract
{
    protected $url = 'http://www.whitehouse.gov/briefing_room/Remarks/';
    public $parser_name = 'Presidential Remarks Scraper';
    public $parser_version = '1.0';
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

        preg_match_all('#<div class="timeStamp smaller">(.+?)<\/div>#is',$response,$_timestamps);
        preg_match_all('#<h4 class="modhdgblue">(.+?)<\/h4>#is',$response,$_events);
        $data_arr[] = array('timestamp' => $_timestamps[1], 'description' => $_events[1]);
        
        $total_timestamps = sizeof($data_arr[0]['timestamp']);
        for($i=0; $i < $total_timestamps; $i++) {
            preg_match('#<a[^>]*>(.*?)</a>#is', $data_arr[0]['description'][$i], $title);
            $data_arr[0]['timestamp'][$i];
            list($month, $day, $year) = explode('/',$data_arr[0]['timestamp'][$i]);
            
            $_date_str = strftime('%Y-%m-%dT%H:%M:%SZ', mktime(0, 0, 0, $month, $day, $year));
            $events[$i]['couchdb_id'] = (string) $_date_str . ' - '.BranchName::$executive.' - '.EntityName::$whitehouse.' - '. trim($title[1]);
            $events[$i]['datetime'] = (string) $_date_str; //issue
            $events[$i]['end_datetime'] = null;
            $events[$i]['title'] = (string) trim($title[1]);
            $events[$i]['description'] = (string) trim($data_arr[0]['description'][$i]);
            $events[$i]['branch'] = (string) BranchName::$executive;
            $events[$i]['entity'] = (string) EntityName::$whitehouse;
            $events[$i]['source_url'] = (string) $this->url;
            $events[$i]['source_text'] = (string) trim($title[0]);

            $_access_time = date('D, d M Y H:i:s T', $this->access_time);
            $events[$i]['access_datetime'] = (string) $this->_vd_date_format($_access_time);
            $events[$i]['parser_name'] = (string) $this->parser_name;
            $events[$i]['parser_version'] = (string) $this->parser_version;
        }
        return $events;
    }
}//end of class

$parser = new PresidentialRemarks;
$parser->run();
exit(0);
