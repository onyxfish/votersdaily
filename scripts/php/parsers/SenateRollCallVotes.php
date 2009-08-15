<?php

//require '../phputils/votersdaily.php';

class SenateRollCallVotes extends VotersDaily_Abstract
{
    protected $url = 'http://www.senate.gov/legislative/LIS/roll_call_lists/vote_menu_111_1.xml';
    protected $parser_name = 'Senate Roll Call Votes Scraper';
    protected $parser_version = '0.1';
    protected $parser_frequency = '6.0';
    protected $fields = array('start_time','end_time','title','description','branch','entity','source_url','source_text','access_datetime','parser_name','person_version');
    
    public function __construct()
    {
        parent::__construct();
    }

    public function run()
    {
        $events = $this->parse();
        $this->save($events, 'data/senaterollcallvotes.csv');
    }

    protected function parse()
    {
        $events = array();
        $access_time = time();
        
        $xml = $this->urlopen($this->url);
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
            $events[$i]['access_datetime'] = $access_time;
            $events[$i]['parser_name'] = $this->parser_name;
            $events[$i]['parser_version'] = $this->parser_version;            
        }


        return $events;
        
    }

    protected function save($arr, $fn)
    {
        $lines = array();
        foreach($arr as $v) {
           $lines[] = "\"" . implode ('","', $v). "\"\r\n";
        }
 
        $fp = fopen($fn, 'w');
        if(!$fp) {
            echo 'Unable to open $fn for output';
            exit();
        }
        fwrite($fp, implode(',', $this->fields)."\n");
        foreach($lines as $line) {
            fwrite($fp, $line);
        }
        fclose($fp);        
    }
}

//$parser = new SenateRollCallVotes();
//$parser->run();
