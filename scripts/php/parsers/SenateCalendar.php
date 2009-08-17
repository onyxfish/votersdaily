<?php
//require '../phputils/EventScraper.php';

class SenateCalendar extends EventScraper_Abstract
{
    protected $url = 'http://democrats.senate.gov/calendar/2009-08.html';
    public $parser_name = 'Senate Calendar Scraper';
    public $parser_version = '0.1';
    public $parser_frequency = '6.0';
    protected $csv_filename = 'data/senatecalendar.csv';
    protected $ical_filename = 'data/senatecalendar.ics';

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
        $response = $this->urlopen($this->url);
        $this->access_time = time();

        preg_match_all('#<table[^>]*>(.+?)<\/table>#is',$response,$matches);
       
        foreach($matches as $data) {
            $i=0;
            foreach($data as $row) {
                $row = trim($row);
                //this could cause problems need to monitor
                //hopefully I'll get a bug notice
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
                        $events[$i]['access_datetime'] = $this->access_time;
                        $events[$i]['parser_name'] = $this->parser_name;
                        $events[$i]['parser_version'] = $this->parser_version;

                    }
                }

            $i++;
            }
        }
        return $events;
    }
}
