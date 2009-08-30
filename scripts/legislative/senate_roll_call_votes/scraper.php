#!/usr/bin/php -q
<?php
$PATH_TO_INCLUDES = dirname(dirname(dirname(__FILE__)));
require $PATH_TO_INCLUDES.'/phputils/EventScraper.php';
require $PATH_TO_INCLUDES.'/phputils/couchdb.php';

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
                $source_text .= trim($origin);  
            }


            $description_str = 'Vote Number '.$votes[$i]->vote_number.' Issue ' . $votes[$i]->issue->A;
            $description_str .= ' Answering: ' . $votes[$i]->question . ' Results: ' .$votes[$i]->result;
            $description_str .=  ' Votes - yeas: ' .$votes[$i]->vote_tally->yeas. ' nays: ' .$votes[$i]->vote_tally->nays;
            //deal with date
            $start_date = (string) $votes[$i]->vote_date;
            list($day, $month) = explode('-', $start_date);
            $date_str = $month . ' '. $day.' 2009';
            
            //hack to get the url for vote and issue
            $cong_session = '111';
            $vote_url = 'http://thomas.loc.gov/cgi-bin/bdquery/z?d'.$cong_session.':';

            list($_issue_part1, $part2) = explode(' ', $votes[$i]->issue->A);
            $part1 = str_replace('.','',$_issue_part1);

            switch($part1) {
                case 'S' :
                    $_part1_str = 'SN';
                    break;
                case 'SJRes' :
                    $_part1_str = 'SJ';
                    break;
                case 'SAmdt' :
                    $_part1_str = 'SP';
                    break;
                case 'HR' : 
                    $_part1_str = 'HR';
                    break;

                default :
            }

            
            if(!strlen(trim($part2)) != 0 || strlen(trim($part2)) < 5) {
                $total_under = (5 - sizeof(trim($part2)));
                $padding='0';
                $_vote_issue_url = $vote_url.$_part1_str.str_repeat($padding,$total_under).trim($part2).':';
            }
            else {
                $_vote_issue_url = null; 
            }

            $_xml_source = '
<vote>
<vote_number>'.$votes[$i]->vote_number.'</vote_number>
<vote_date>'.$votes[$i]->vote_date.'</vote_date>
<issue><A HREF="'.$_vote_issue_url.'">'.$votes[$i]->issue->A.'</A></issue>
<question>'.$votes[$i]->question.'</question>
<result>'.$votes[$i]->result.'</result>
<vote_tally>
<yeas>'.$votes[$i]->vote_tally->yeas.'</yeas>
<nays>'.$votes[$i]->vote_tally->yeas.'</nays>
</vote_tally>
<title>'.trim($votes[$i]->title).'</title>
</vote>';

            $events[$i]['couchdb_id'] = (string)  $this->_vd_date_format($date_str) . ' - '.BranchName::$legislative.' - '.EntityName::$senate.' - ' . $this->_escape_str($votes[$i]->title, 'title');
            $events[$i]['datetime'] = $this->_vd_date_format($date_str);
            $events[$i]['end_datetime'] = null;
            $events[$i]['title'] = (string) $this->_escape_str($votes[$i]->title);
            $events[$i]['description'] = $this->_escape_str($description_str);
            $events[$i]['branch'] = BranchName::$legislative;
            $events[$i]['entity'] = EntityName::$senate;
            $events[$i]['vote_number'] = (string) $votes[$i]->vote_number;
            $events[$i]['vote_issue'] = (string) $votes[$i]->issue->A;
            $events[$i]['vote_issue_url'] = $_vote_issue_url;
            $events[$i]['vote_question'] = (string) $votes[$i]->question;
            $events[$i]['vote_result'] = (string) $votes[$i]->result;
            $events[$i]['yes_votes'] = (int) trim($votes[$i]->vote_tally->yeas);
            $events[$i]['no_votes'] =  (int) trim($votes[$i]->vote_tally->nays);
            $events[$i]['source_url'] = $this->url;

            //ensure we don't have an empty field.
            //issue#21 fix
            
            
            
            $source_text = trim($_xml_source);
            if(!empty($source_text)) {
                $events[$i]['source_text'] = (string) $source_text;
            }
            else {
                $events[$i]['source_text'] = null;
            }
            
            $_access_time = date('D, d M Y H:i:s T', $this->access_time);
            $events[$i]['access_datetime'] = (string) $this->_vd_date_format($_access_time);
            $events[$i]['parser_name'] = $this->parser_name;
            $events[$i]['parser_version'] = $this->parser_version; 
        }
        
        $scrape_end = microtime_float();
        $this->parser_runtime = round(($scrape_end - $scrape_start), 4);

        return $events;
    }
}

$parser = new SenateRollCallVotes;
$parser->run();
exit(0);
