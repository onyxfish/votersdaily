#!/usr/bin/php -q
<?php
$PATH_TO_INCLUDES = dirname(dirname(dirname(__FILE__)));
require $PATH_TO_INCLUDES.'/phputils/EventScraper.php';
require $PATH_TO_INCLUDES.'/phputils/couchdb.php';

/*
 * Voters Daily: PHP - Supreme Court 2008 Court Orders Scraper
 * http://wiki.github.com/bouvard/votersdaily
 *
 * @author      Chauncey Thorn <chaunceyt@gmail.com>
 * Link: http://www.cthorn.com/
 *
 */

class SupremeCourtOrders extends EventScraper_Abstract
{
    protected $url = 'http://www.supremecourtus.gov/orders/08ordersofthecourt.html';
    public $parser_name = 'Supreme Court 2008 Court Orders Scraper';
    public $parser_version = '1.0';
    public $parser_frequency = '6.0';

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
        $this->source_text = $response;

        preg_match_all('#<table[^>]*>(.+?)<\/table>#is',$response,$matches);
       
        foreach($matches as $data) {
            $i=0;
            foreach($data as $row) {
                $row = trim($row);

                 preg_match('#<TABLE[^>]*>(.+?)<\/TABLE>#is',$row,$matches);
                 preg_match_all('#<TR[^>]*>(.+?)<\/TR>#is', $matches[1], $trRows);
                 
                 foreach($trRows[1] as $row) {
                    preg_match_all('#<TD[^>]*>(.*?)</TD>#is',$row, $data);
                    
                    $source_text = '';
                    foreach($data[0] as $origin) {
                        $source_text .= $origin ."\n";
                    }

                    $_date_tmp = str_replace('/','-',trim($data[1][0]));
                    list($month,$day,$year) = explode('-',$_date_tmp);

                    //FIX: for some reason $year may contain 20www.supremecourtus.gov
                    //only losing a few of the entries
                    if(ctype_digit($year)) {

                        $_year_tmp = (int) '20'.$year;
                        $date_str = strftime('%Y-%m-%dT%H:%M:%SZ', mktime(0, 0, 0, $month, $day, $_year_tmp));

                        $title_url = $data[1][1];
                        $title = strip_tags($data[1][1]);
                    
                        $description = strip_tags($matches[1], '<a>');
                        $description = strip_tags($description, '<a>');
                        $description = str_replace(array('<a name='.$calendar_day[1].'></a>','\r'),'',$description);
                        $events[$i]['couchdb_id'] = (string) $date_str . ' - '.BranchName::$judicial.' - '.EntityName::$sup.' - '. $this->_escape_str($title);
                        $events[$i]['datetime'] = (string) $date_str;
                        $events[$i]['end_datetime'] = null;
                        $events[$i]['title'] = (string) trim($title);
                        $events[$i]['description'] = (string) $this->_escape_str($title_url);
                        $events[$i]['branch'] = (string) BranchName::$judicial;
                        $events[$i]['entity'] = (string) EntityName::$sup;
                        $events[$i]['source_url'] = (string) $this->url;
                        $events[$i]['source_text'] = (string) $source_text;

                        $_access_time = date('D, d M Y H:i:s T', $this->access_time);
                        $events[$i]['access_datetime'] = (string) $this->_vd_date_format($_access_time);
                        $events[$i]['parser_name'] = (string) $this->parser_name;
                        $events[$i]['parser_version'] = (string) $this->parser_version;
                    
                        $i++;
                    }//end if ctype_digit check

                 }
            }
        }
        return $events;
    }
}

//main
$parser = new SupremeCourtOrders;
$parser->run();
exit(0);
