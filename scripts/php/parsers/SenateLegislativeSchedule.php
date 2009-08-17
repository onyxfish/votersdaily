<?php
// require '../phputils/votersdaily.php';

class SenateLegislativeSchedule extends EventScraper_Abstract
{
    
    protected $url = 'http://www.senate.gov/pagelayout/legislative/one_item_and_teasers/2009_schedule.htm';
    protected $parser_name = 'Senate Legislative Schedule Scraper';
    protected $parser_version = '0.1';
    protected $parser_frequency = '6.0';
    protected $csv_filename = 'data/senatelegislativeschedule.csv';


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
        $response = $this->urlopen($this->url);
        //preg_match('#<!-- BEGIN MAIN -->(.*?)<!-- END MAIN -->#is',$response, $matches);
        preg_match('#<table border="1" align="left">(.+?)<\/table>#is',$response,$data);
        preg_match_all('#<tr>(.+?)<\/tr>#is',$data[1],$tdData);

        //print_r($tdData[1]);
        $i=0;
        foreach($tdData[1] as $row) {
            preg_match_all('#<td[^>]*>(.+?)<\/td>#is',$row,$tdTmp);
            list($start_str, $end_str) = explode('-',$tdTmp[1][0]);
            //list($month, $until) = explode(' ',$tdTmp[1][0]);

            if(!empty($start_str) && $start_str != 'TBD') {
                $start_date = $start_str.' 2009';
                list($start_month, $start_day) = explode(' ', $start_str);
            


                if(isset($end_str) && !empty($end_str)) {
                    list($month, $day) = explode(' ',trim($end_str));
                    if(!empty($month)) {
                        $_end_date = $end_str .' 2009';
                    
                    }
                    else {
                        $_end_date = $start_month . ' ' . $day . ' 2009';
                    }
                    $end_date = date('Y-m-d', strtotime($_end_date));
                }
                else {
                    $end_date = null;
                }
            }
            $events[$i]['start_date'] = date('Y-m-d', strtotime($start_date));
            $events[$i]['end_date'] = $end_date;
            $events[$i]['title'] = (string) $tdTmp[1][1] . ' ' . $tdTmp[2];
            $events[$i]['description'] = '';
            $events[$i]['branch'] = 'Legislative';
            $events[$i]['entity'] = 'Senate';
            $events[$i]['source_url'] = $this->url;
            $events[$i]['source_text'] = '';
            $events[$i]['access_datetime'] = $access_time;
            $events[$i]['parser_name'] = $this->parser_name;
            $events[$i]['parser_version'] = $this->parser_version;            
            $i++;
        }

        return $events;
    }
}

//$parser = new SenateLegislativeSchedule;
//$parser->run();
