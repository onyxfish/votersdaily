#!/usr/bin/php -q
<?php
$PATH_TO_INCLUDES = dirname(dirname(dirname(__FILE__)));
require $PATH_TO_INCLUDES.'/phputils/EventScraper.php';
require $PATH_TO_INCLUDES.'/phputils/couchdb.php';

/*
 * Voters Daily: PHP - White House Nominations Scraper
 * http://wiki.github.com/bouvard/votersdaily
 *
 * @author      Chauncey Thorn <chaunceyt@gmail.com>
 * Link: http://www.cthorn.com/
 *
 */


class WhiteHouseNominations extends EventScraper_Abstract
{
    
    protected $url = 'http://www.socrata.com/views/n5m4-mism/rows.xml?accessType=API';
    public $parser_name = 'White House Nominations Scraper';
    public $parser_version = '1.0';
    public $parser_frequency = '6.0';


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
        $events['couchdb'] = array();

        $scrape_start = microtime_float();

        $this->source_url = $this->url;
        $response = $this->urlopen($this->url);
        $this->access_time = time();
        $this->source_text = $response;

        $xml = new SimpleXMLElement($response);
        
        $nominations = $xml->rows;
        $total_nominations = sizeof($nominations->row);

        for($i=0; $i < $total_nominations; $i++) {
            $row_id = $nominations->row[$i]->attributes()->_id;
            $row_uuid = $nominations->row[$i]->attributes()->_uuid;
            $row_position = $nominations->row[$i]->attributes()->_position;

            $description_str = 'Nomination: ' .$nominations->row[$i]->name . ' ' . $nominations->row[$i]->agency->attributes()->description;
            $description_str .= $nominations->row[$i]->position . ' confirmed: (' . $nominations->row[$i]->confirmed . ')';
            $description_str .= ' holdover:  (' . $nominations->row[$i]->holdover.')';
           
            $_date_str = (string) $nominations->row[$i]->formal_nomination_date;
            list($_month,$_day,$_year) = explode('/', $_date_str);
            //if we don't have a date disregard
            if(!empty($_month) && !empty($_day) && !empty($_year)) {

                $_year = (int) $_year;
                $final_date_str = strftime('%Y-%m-%dT%H:%M:%SZ', mktime(0, 0, 0, $_month, $_day, $_year));
                list($e_year, $e_month, $e_day) =  explode('-', date('Y-m-d', (int) $nominations->row[$i]->confirmation_vote));
                
                $end_date_value = strftime('%Y-%m-%dT%H:%M:%SZ', mktime(0,0,0,$e_month, $e_day,$e_year));
                $events[$i]['couchdb_id'] = (string) $final_date_str . ' -  ' .BranchName::$executive.'  - '.EntityName::$whitehouse.' - Nomination of '.$nominations->row[$i]->name.' for ' . $this->_escape_str($nominations->row[$i]->position, 'title');
                $events[$i]['datetime'] = (string) $final_date_str;
                $events[$i]['end_datetime'] = (string) $end_date_value;
                $events[$i]['title'] = (string) 'Nomination: ' . $this->_escape_str($nominations->row[$i]->position);
                $events[$i]['description'] = (string) $this->_escape_str($description_str);
                $events[$i]['branch'] = (string) BranchName::$executive;
                $events[$i]['entity'] = (string) EntityName::$whitehouse;
                $events[$i]['nominee'] = (string) $nominations->row[$i]->name;
                $events[$i]['position'] = (string) $nominations->row[$i]->position;
                
                if($nominations->row[$i]->confirmed == 'true') {
                    $events[$i]['is_confirmed'] = true;
                }
                else {
                    $events[$i]['is_confirmed'] = false;
                }

                if($nominations->row[$i]->holdover == 'true') {
                    $events[$i]['is_holdover'] = true;
                }
                else {
                    $events[$i]['is_holdover'] = false;
                }

                $events[$i]['source_url'] = (string) $this->url;

$_xml_string = '
<row _id="'.$row_id.'" _uuid="'.$row_uuid.'" _position="'.$row_position.'">
<name>' .$nominations->row[$i]->name . '</name>
<agency url="http://whitehouse.gov/" description="NCH"/>
<position>'.$nominations->row[$i]->position.'</position>
<formal_nomination_date>'.$nominations->row[$i]->formal_nomination_date.'</formal_nomination_date>
<confirmed>'.$nominations->row[$i]->confirmed.'</confirmed>
<holdover>'.$nominations->row[$i]->holdover.'</holdover>
<_tags/>
</row>';                
                $events[$i]['source_text'] = trim($_xml_string);

                $_access_time = date('D, d M Y H:i:s T', $this->access_time);
                $events[$i]['access_datetime'] = (string) $this->_vd_date_format($_access_time);
                $events[$i]['parser_name'] = (string) $this->parser_name;
                $events[$i]['parser_version'] = (string) $this->parser_version;            
            } //if 
        }
        
        $scrape_end = microtime_float();
        $this->parser_runtime = round(($scrape_end - $scrape_start), 4);

        return $events;
    }
}//end of class

$parser = new WhiteHouseNominations;
$parser->run();
exit(0);
