<?php

//require '../phputils/EventScraper.php';

class SenateRollCallVotes extends EventScraper_Abstract
{
    protected $url = 'http://www.senate.gov/legislative/LIS/roll_call_lists/vote_menu_111_1.xml';
    public $parser_name = 'Senate Roll Call Votes Scraper';
    public $parser_version = '0.1';
    public $parser_frequency = '6.0';
    protected $csv_filename = 'data/senaterollcallvotes.csv';
    protected $ical_filename = 'data/senaterollcallvotes.ics';
    
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
        $xml = $this->urlopen($this->url);
        $this->access_time = time();

        $response = simplexml_load_string($xml);
        
        $votes = $response->votes->vote;
        $total_events = sizeof($votes);
    
        for($i=0; $i< $total_events; $i++) {
            $description_str = 'Vote Number '.$votes[$i]->vote_number.' Issue ' . $votes[$i]->issue->A;
            $description_str .= ' Answering: ' . $votes[$i]->question . ' Results: ' .$votes[$i]->result;
            $description_str .=  ' Votes - yeas: ' .$votes[$i]->vote_tally->yeas. ' nays: ' .$votes[$i]->vote_tally->nays;

            //deal with date
            $start_date = (string) $votes[$i]->vote_date;
            list($day, $month) = explode('-', $start_date);
            $date_str = $month . ' '. $day.' 2009';
            $events[$i]['start_date'] = date('Y-m-d', strtotime($date_str));
            
            $events[$i]['end_date'] = '';
            $events[$i]['title'] = (string) $votes[$i]->title;
            $events[$i]['description'] = $description_str;
            $events[$i]['branch'] = 'Legislative';
            $events[$i]['entity'] = 'Senate';
            $events[$i]['source_url'] = $this->url;
            $events[$i]['source_text'] = '';
            $events[$i]['access_datetime'] = $this->access_time;
            $events[$i]['parser_name'] = $this->parser_name;
            $events[$i]['parser_version'] = $this->parser_version;            
        }
        return $events;
    }
}
