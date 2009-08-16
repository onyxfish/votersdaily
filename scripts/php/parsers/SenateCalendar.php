<?php
//require '../phputils/votersdaily.php';

class SenateCalendar extends VotersDaily_Abstract
{
    protected $url = 'http://democrats.senate.gov/calendar/2009-08.html';
    protected $parser_name = 'Senate Calendar Scraper';
    protected $parser_version = '0.1';
    protected $parser_frequency = '6.0';
    protected $fields = array('start_time','end_time','title','description','branch','entity','source_url','source_text','access_datetime','parser_name','person_version');

    public function __construct()
    {
        parent::__construct();
    }

    public function run()
    {
        $events = $this->scrape();
        //print_r($events); 
        $this->add_events($events, 'data/senatecalendar.csv');
    }

    protected function scrape()
    {
        $events = array();

        $response = $this->urlopen($this->url);
        $access_time = time();


        preg_match_all('#<table[^>]*>(.+?)<\/table>#is',$response,$matches);
        //print_r($matches);
       
        foreach($matches as $data) {
            $i=0;
            foreach($data as $row) {
                $row = trim($row);
                if(preg_match('/Convenes: /is',$row)) {

                    preg_match('#<table[^>]*>(.+?)<\/table>#is',$row,$matches);
                    preg_match('#<a name=(.*?)>#is', $matches[1], $calendar_day);
                    $_date_str = explode('/',$this->url);
                    list($date_str, $ext) = explode('.', $_date_str[4]);

                    if(!empty($calendar_day[1])) {
                        $description = strip_tags($matches[1], '<a>');
                        $description = strip_tags($description, '<a>');
                        $description = str_replace(array('<a name='.$calendar_day[1].'></a>','\r'),'',$description);

                        $events[$i]['start_date'] = $date_str.'-'.$calendar_day[1];
                        $events[$i]['end_date'] = null;
                        $events[$i]['title'] = 'Senate Calendar';
                        $events[$i]['description'] = str_replace(array("\r\n"), ' ', substr($description,1));
                        $events[$i]['branch'] = 'Legislative';
                        $events[$i]['entity'] = 'Senate';
                        $events[$i]['source_url'] = $this->url;
                        $events[$i]['source_text'] = $event;
                        $events[$i]['access_datetime'] = '';
                        $events[$i]['parser_name'] = $this->parser_name;
                        $events[$i]['parser_version'] = $this->parser_version;

                    }
                }

            $i++;
            }
        }
        return $events;
    }

    protected function add_events($arr, $fn)
    {
        //print_r($arr);
        $lines = array();
        foreach($arr as $v) {
           $lines[] =  "\"" . implode ('","', $v). "\"\n";
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
