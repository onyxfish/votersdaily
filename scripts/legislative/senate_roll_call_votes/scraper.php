#!/usr/bin/php -q
<?php
$PATH_TO_INCLUDES = dirname(dirname(dirname(__FILE__)));
require $PATH_TO_INCLUDES.'/phputils/EventScraper.php';
require $PATH_TO_INCLUDES.'/phputils/couchdb.php';
require $PATH_TO_INCLUDES.'/phputils/xml2array.php';

/*
 * Voters Daily: PHP - Senate Roll Call Votes Scraper
 * http://wiki.github.com/bouvard/votersdaily
 *
 * @author      Chauncey Thorn <chaunceyt@gmail.com>
 * Link: http://www.cthorn.com/
 *
 */

class SenateRollCallVotes extends EventScraper_Abstract
{
    protected $url = 'http://www.senate.gov/legislative/LIS/roll_call_lists/vote_menu_111_1.xml';
    public $parser_name = 'Senate Roll Call Votes Scraper';
    public $parser_version = '0.1';
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

        $scrape_start = microtime_float();

        $this->source_url = $this->url;        
        $xml = $this->urlopen($this->url);
        $this->access_time = time();
        $this->source_text = $xml;

        $response = simplexml_load_string($xml);
        
        $votes = $response->votes->vote;
        $total_events = sizeof($votes);
    
        for($i=0; $i< $total_events; $i++) {
            //getting source_text for this entry
            $source_text = '';
            $_votes = $votes[$i];
            foreach($_votes as $origin) {
                $source_text .= $origin;  
            }

            $description_str = 'Vote Number '.$votes[$i]->vote_number.' Issue ' . $votes[$i]->issue->A;
            $description_str .= ' Answering: ' . $votes[$i]->question . ' Results: ' .$votes[$i]->result;
            $description_str .=  ' Votes - yeas: ' .$votes[$i]->vote_tally->yeas. ' nays: ' .$votes[$i]->vote_tally->nays;
            //deal with date
            $start_date = (string) $votes[$i]->vote_date;
            list($day, $month) = explode('-', $start_date);
            $date_str = $month . ' '. $day.' 2009';

            $events[$i]['couchdb_id'] = (string)  $this->_vd_date_format($date_str) . ' - '.BranchName::$legislative.' - '.EntityName::$senate.' - ' . trim($votes[$i]->title);
            $events[$i]['datetime'] = $this->_vd_date_format($date_str);
            $events[$i]['end_datetime'] = null;
            $events[$i]['title'] = (string) trim($votes[$i]->title);
            $events[$i]['description'] = trim($description_str);
            $events[$i]['branch'] = BranchName::$legislative;
            $events[$i]['entity'] = EntityName::$senate;
            $events[$i]['vote_number'] = (string) $votes[$i]->vote_number;
            $events[$i]['vote_issue'] = (string) $votes[$i]->issue->A;
            $events[$i]['vote_question'] = (string) $votes[$i]->question;
            $events[$i]['vote_result'] = (string) $votes[$i]->result;
            $events[$i]['yes_votes'] = (int) trim($votes[$i]->vote_tally->yeas);
            $events[$i]['no_votes'] =  (int) trim($votes[$i]->vote_tally->nays);
            $events[$i]['source_url'] = $this->url;
            $events[$i]['source_text'] = (string) $source_text;
            $events[$i]['access_datetime'] = (string) $this->access_time;
            $events[$i]['parser_name'] = $this->parser_name;
            $events[$i]['parser_version'] = $this->parser_version; 
        }
        
        $scrape_end = microtime_float();
        
        $this->parser_runtime = bcsub($scrape_end, $scrape_start, 4);

        return $events;
    }
}

$parser = new SenateRollCallVotes;
$parser->run();

