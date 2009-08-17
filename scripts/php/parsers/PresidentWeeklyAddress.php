<?php
//require '../phputils/EventScraper.php';

class PresidentWeeklyAddress extends EventScraper_Abstract
{
    
    protected $url = 'http://www.whitehouse.gov/feed/blog/';
    public $parser_name = 'President Weekly Address Scraper';
    public $parser_version = '0.1';
    public $parser_frequency = '6.0';
    protected $csv_filename = 'data/presidentweeklyaddress.csv';


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

        $xml = new SimpleXMLElement($response);
        $weeklyaddress = $xml->entry;

        $i=0;
        foreach($xml->entry as $weeklyaddress) {
            $description_str = 'Author: '.$weeklyaddress->author->name.' <a href="'.$weeklyaddress->link->attributes()->href.'">'.$weeklyaddress->title.'</a>';
            $events[$i]['start_time'] = (string) date("Y-m-d m:i:s", strtotime($weeklyaddress->updated));
            $events[$i]['end_data'] = '';
            $events[$i]['title'] = (string) $weeklyaddress->title;
            $events[$i]['description'] = $description_str;
            $events[$i]['branch'] = 'Executive';
            $events[$i]['entity'] = 'President WhiteHouse';
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
