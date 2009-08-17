<?php

//require '../phputils/votersdaily.php';

class HouseSchedule extends EventScraper_Abstract
{
    protected $url = 'http://www.house.gov/house/House_Calendar.shtml';
    protected $parser_name = 'House Schedule Scraper';
    protected $parser_version = '0.1';
    protected $parser_frequency = '6.0';
    protected $csv_filename = 'data/houseschedule.csv';
    //protected $fields = array('start_time','end_time','title','description','branch','entity','source_url','source_text','access_datetime','parser_name','person_version');

    public function __construct()
    {
        parent::__construct();
    }

    public function run()
    {
        $events = $this->scrape();
        $this->add_events($events, $this->couchdbName);
    }

    protected function scrape()
    {
        $response = $this->urlopen($this->url);

        preg_match('#<table[^>]*>(.+?)<\/table>#is',$response,$matches);
        preg_match_all('#<tr>(.+?)<\/tr>#is',$matches[1],$data);

        $events = array();
        $i=0;

        foreach($data[1] as $event) {

            $event = str_replace(array("\r\n",'  :  ',' :  '),':',strip_tags(trim($event)));
            list($date, $text_str) = explode(':', $event); //causing warning
            $date_str = explode('-',trim($date));

            //start getting required data

            if(sizeof($date_str) > 1) {
                $events[$i]['start_date'] = date('Y-m-d', strtotime(trim($date_str[0])));
                $events[$i]['end_date'] = date('Y-m-d', strtotime(trim($date_str[1])));
            }
            else {
                $events[$i]['start_date'] = date('m-d-Y', strtotime(trim($date_str[0])));
                $events[$i]['end_date'] = null;
            }

            $events[$i]['title'] = 'House Schedule';
            $events[$i]['description'] = 'None';
            $events[$i]['branch'] = 'Legislative';
            $events[$i]['entity'] = 'House of Representatives';
            $events[$i]['source_url'] = $this->url;
            $events[$i]['source_text'] = $event;
            $events[$i]['access_datetime'] = '';
            $events[$i]['parser_name'] = $this->parser_name;
            $events[$i]['parser_version'] = $this->parser_version;

            $i++;
        }

        return $events;
    }
}

//$parser = new HouseSchedule;
//$parser->run();
?>
