<?php
//require '../phputils/votersdaily.php';

class WhiteHouseNominations extends VotersDaily_Abstract
{
    
    protected $url = 'http://www.socrata.com/views/n5m4-mism/rows.xml?accessType=API';
    protected $parser_name = 'White House Nominations Scraper';
    protected $parser_version = '0.1';
    protected $parser_frequency = '6.0';
    protected $csv_filename = 'data/whitehousenominations.csv';


    public function __construct()
    {
        parent::__construct();
        $this->year = date("Y");
    }

    public function run()
    {
        $events = $this->scrape();
        //print_r($events);
        $this->add_events($events, $this->couchdbName);
    }
    
    protected function scrape()
    {
        $events = array();
        $access_time = time();
        $string = $this->urlopen($this->url);
        $xml = new SimpleXMLElement($string);
        //print_r($xml);
        
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
            $events[$i]['access_datetime'] = $access_time;
            $events[$i]['parser_name'] = $this->parser_name;
            $events[$i]['parser_version'] = $this->parser_version;            
        }

        return $events;
    }

    protected function add_events($arr, $fn)
    {
        switch($this->storageEngine) {
            case 'couchdb' :
                StorageEngine::couchDbStore($arr, $fn);
                break;
            default :
                unset($fn);
                $fn = $this->csv_filename;
                StorageEngine::csvStore($arr, $fn);
                break;
        }
    }

}

//$parser = new WhiteHouseNominations;
//$parser->run();
