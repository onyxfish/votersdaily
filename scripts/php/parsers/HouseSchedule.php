#!/usr/bin/php -q
<?php

require '../phputils/votersdaily.php';

class HouseSchedule extends VotersDaily_Abstract
{
    protected $db = null;
    protected $url = 'http://www.house.gov/house/House_Calendar.shtml';
    protected $parser_name = 'VotersDaily_PHP';
    protected $parser_version = '0.1';
    protected $fields = array('start_time','end_time','title','description','branch','entity','source_url','source_text','access_datetime','parser_name','person_version');

    public function __construct()
    {
    }

    public function run()
    {
        $events = $this->parse();
        $this->save($events, '../data/houseschedule.csv');
    }

    protected function parse()
    {
        $response = file_get_contents($this->url);

        preg_match('#<table[^>]*>(.+?)<\/table>#is',$response,$matches);
        preg_match_all('#<tr>(.+?)<\/tr>#is',$matches[1],$data);

        $events = array();
        $i=0;

        foreach($data[1] as $event) {

            $event = str_replace(array("\r\n",'  :  ',' :  '),':',strip_tags(trim($event)));
            list($date, $text_str) = explode(':', $event);
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

    protected function save($arr, $fn)
    {
        //print_r($arr);
        $lines = array();
        foreach($arr as $v) {
           $lines[] =  "\"" . implode ('","', $v). "\"\r\n";
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

       // print_r($lines);
    }
}

$houseSchedule = new HouseSchedule;
$houseSchedule->run();
?>
