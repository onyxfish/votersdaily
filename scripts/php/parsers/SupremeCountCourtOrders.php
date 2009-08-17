<?php
//require '../phputils/EventScraper.php';

class SupremeCountCourtOrders extends EventScraper_Abstract
{
    protected $url = 'http://www.supremecourtus.gov/orders/08ordersofthecourt.html';
    protected $parser_name = 'Supreme Court 2008 Court Orders Scraper';
    protected $parser_version = '0.1';
    protected $parser_frequency = '6.0';
    protected $csv_filename = 'data/supremecourtcourtorders.csv';

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
        $events = array();

        $response = $this->urlopen($this->url);
        $access_time = time();

        preg_match_all('#<table[^>]*>(.+?)<\/table>#is',$response,$matches);
       
        foreach($matches as $data) {
            $i=0;
            foreach($data as $row) {
                $row = trim($row);

                 preg_match('#<TABLE[^>]*>(.+?)<\/TABLE>#is',$row,$matches);
                 preg_match_all('#<TR[^>]*>(.+?)<\/TR>#is', $matches[1], $trRows);
                 
                 foreach($trRows[1] as $row) {
                    preg_match_all('#<TD[^>]*>(.*?)</TD>#is',$row, $data);
                    $_date_tmp = str_replace('/','-',trim($data[1][0]));
                    list($month,$day,$year) = explode('-',$_date_tmp);

                    $date_str = '20'.$year.'-'.$month.'-'.$day;

                    $title_url = $data[1][1];
                    $title = strip_tags($data[1][1]);
                    
                    $description = strip_tags($matches[1], '<a>');
                    $description = strip_tags($description, '<a>');
                    $description = str_replace(array('<a name='.$calendar_day[1].'></a>','\r'),'',$description);

                    $events[$i]['start_date'] = $date_str;
                    $events[$i]['end_date'] = null;
                    $events[$i]['title'] = $title;
                    $events[$i]['description'] = $title_url;
                    $events[$i]['branch'] = 'Judicial';
                    $events[$i]['entity'] = 'Supreme Court';
                    $events[$i]['source_url'] = $this->url;
                    $events[$i]['source_text'] = $event;
                    $events[$i]['access_datetime'] = $access_time;
                    $events[$i]['parser_name'] = $this->parser_name;
                    $events[$i]['parser_version'] = $this->parser_version;
                    $i++;
                 }
            }
        }
        return $events;
    }
}
