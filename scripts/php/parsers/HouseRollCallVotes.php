<?php
//require '../phputils/EventScrapper.php';

class HouseRollCallVotes extends EventScraper_Abstract
{
    
    protected $url = 'http://clerk.house.gov/evs/2009/index.asp';
    protected $parser_name = 'House Roll Call Votes Scraper';
    protected $parser_version = '0.1';
    protected $parser_frequency = '6.0';
    protected $csv_filename = 'data/houserollcallvotes.csv';

    //protected $fields = array('start_time','end_time','title','description','branch','entity','source_url','source_text','access_datetime','parser_name','person_version');

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
        $response = $this->urlopen($this->url);

        $access_time = time();
        preg_match_all('#<A HREF="ROLL(.*?)">#is',$response, $otherLinks);

        $voteLinks[] = $this->url;
        foreach($otherLinks[1] as $otherLink) {
             $voteLinks[] = 'http://clerk.house.gov/evs/2009/ROLL'.$otherLink;
        }
        asort($voteLinks);
        //print_r($voteLinks);
        $events = array();
        //echo $response;

        foreach($voteLinks as $voteLink) {
            $page_response = file_get_contents($voteLink);
            //get title
            preg_match('#<TITLE>(.*?)<\/TITLE>#is',$page_response, $title);

            preg_match('#<TABLE[^>]*>(.+?)<\/TABLE>#is',$page_response,$matches);
            preg_match_all('#<TR>(.+?)<\/TR>#is',$matches[1],$data);

            //print_r($data[1]);
            $i=0;
            foreach($data[1] as $event) {
                $event = str_replace(array("\r\n",'  :  ',' :  '),':',strip_tags(trim($event)));
                $event_arr = explode(':', $event);
                list($day, $month) = explode('-', $event_arr[1]);
                //format date
                $date_str = $month . ' '. $day.' '.$this->year ;
                $events[$i]['start_date'] = date('Y-m-d', strtotime($date_str));
                $events[$i]['end_data'] = '';
                $events[$i]['title'] = $title[1];
            
                if($event_arr[6] == 'F') {
                    $status = 'Failed';
                }
                else if($event_arr[6] == 'P') {
                    $status = 'Passed';
                }
                
                //NOTICE: this block of code is causing notices $event_arr index .. 
                $description_str = 'Roll Call # '.$event_arr[0] . ' ' . $event_arr[3] . ' - ' . $event_arr[5] . ' ' . $event_arr[7] .' ('.$status.')';
                $description_str .= ' Links:  http://clerk.house.gov/cgi-bin/vote.asp?year=2009&rollnumber='.$event_arr[0];
                $bill_str = str_replace(' ','.',$event_arr[3]);
                $description_str .= ' http://thomas.loc.gov/cgi-bin/bdquery/z?d111:'.strtolower($bill_str).':';

                $events[$i]['description'] = $description_str;
                $events[$i]['branch'] = 'Legislative';
                $events[$i]['entity'] = 'House of Representatives';
                $events[$i]['source_url'] = $this->url;
                $events[$i]['source_text'] = $event;
                $events[$i]['access_datetime'] = $access_time;
                $events[$i]['parser_name'] = $this->parser_name;
                $events[$i]['parser_version'] = $this->parser_version;
                //print_r($event_arr);

                $i++;
            }
        }
        return $events;
    }
}
//$parser = new HouseRollCallVotes;
//$parser->run();
