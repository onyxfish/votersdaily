#!/usr/bin/php -q
<?php
$PATH_TO_INCLUDES = dirname(dirname(dirname(__FILE__)));
echo $PATH_TO_INCLUDES;
require $PATH_TO_INCLUDES.'/phputils/EventScraper.php';
require $PATH_TO_INCLUDES.'/phputils/couchdb.php';

/*
 * Voters Daily: PHP - President Weekly Address Scraper
 * http://wiki.github.com/bouvard/votersdaily
 *
 * @author      Chauncey Thorn <chaunceyt@gmail.com>
 * Link: http://www.cthorn.com/
 *
 */


class PresidentWeeklyAddress extends EventScraper_Abstract
{
    
    protected $url = 'http://www.whitehouse.gov/feed/blog/';
    public $parser_name = 'President Weekly Address Scraper';
    public $parser_version = '1.0';
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
            $description_str = 'Author: '.$weeklyaddress->author->name.' <a href="'.$weeklyaddress->link->attributes()->href.'">'.$this->_escape_str(htmlspecialchars($weeklyaddress->title), 'title').'</a>';
            $events[$i]['couchdb_id'] = (string) $this->_vd_date_format($weeklyaddress->updated) . ' - '.BranchName::$executive.' - '.EntityName::$whitehouse.' - ' . $this->_escape_str(str_replace('"', '',$weeklyaddress->title), 'title');
            $events[$i]['datetime'] = (string) $this->_vd_date_format($weeklyaddress->updated);
            $events[$i]['end_datetime'] = null;
            $events[$i]['title'] = (string) $this->_escape_str($weeklyaddress->title);
            $events[$i]['description'] = (string) $this->_escape_str($description_str);
            $events[$i]['branch'] = (string) BranchName::$executive;
            $events[$i]['entity'] = (string) EntityName::$whitehouse;
            $events[$i]['source_url'] = (string) $this->url;

            //deal with validation error
            //have to review why string is empty
            $_source_text_ = trim($weeklyaddress);
            if(!empty($_source_text)) {
                $events[$i]['source_text'] = (string) trim($weeklyaddress);
            }
            else {
                $events[$i]['source_text'] = 'no result';
            }

            $_access_time = date('D, d M Y H:i:s T', $this->access_time);
            $events[$i]['access_datetime'] = (string) $this->_vd_date_format($_access_time);
            $events[$i]['parser_name'] = (string) $this->parser_name;
            $events[$i]['parser_version'] = (string) $this->parser_version;             
            $i++;
        }

        return $events;
    }
}//end of class

$parser = new PresidentWeeklyAddress;
$parser->run();
exit(0);
