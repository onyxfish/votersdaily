<?php
//require '../phputils/EventScraper.php';

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

        $xml = new SimpleXMLElement($response);
        
        $nominations = $xml->rows;
        $total_nominations = sizeof($nominations->row);

        for($i=0; $i < $total_nominations; $i++) {
            $description_str = 'Nomination: ' .$nominations->row[$i]->name . ' ' . $nominations->row[$i]->agency->attributes()->description;
            $description_str .= $nominations->row[$i]->position . ' confirmed: (' . $nominations->row[$i]->confirmed . ') holdover:  (' . $nominations->row[$i]->holdover.')';
            
            $events[$i]['start_time'] = (string) $nominations->row[$i]->formal_nomination_date;
            $events[$i]['end_data'] = (string) $nominations->row[$i]->confirmation_vote;
            $events[$i]['title'] = 'Nomination: ' . $nominations->row[$i]->position;
            $events[$i]['description'] = $description_str;
            $events[$i]['branch'] = 'Executive';
            $events[$i]['entity'] = 'President WhiteHouse';
            $events[$i]['source_url'] = $this->url;
            $events[$i]['source_text'] = $event;
            $events[$i]['access_datetime'] = $this->access_time;
            $events[$i]['parser_name'] = $this->parser_name;
            $events[$i]['parser_version'] = $this->parser_version;            
        }

        return $events;
    }
}
