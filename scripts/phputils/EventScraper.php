<?php
abstract class EventScraper_Abstract
{
    public $parser_version;
    public $parser_name;
    public $access_time;
    public $source_url;
    public $source_text;
    public $parser_frequency;
    public $storageEngine = 'couchdb';
    public $couchdbName = 'phpvotedailydb';

    public function __construct()
    {
        if(!property_exists($this, 'parser_name')) {
            throw new Exception('EventScrapers must have a name property');
        }

        if(!property_exists($this, 'parser_version')) {
            throw new Exception('EventScrapers must have a version property');
        }

        if(!property_exists($this, 'parser_frequency')) {
            throw new Exception('EventScrapers must have a frequency property');
        }
    }
    
    protected function urlopen($url)
    {
        $userAgent = "robot: http://wiki.github.com/bouvard/votersdaily";
        $opts = array('http'=> array('method'=>"GET",'header'=>$userAgent));
        $context = stream_context_create($opts);
        $response = file_get_contents($this->url,false,$context);
        return $response;
    }

    protected function add_events($arr)
    {
        switch($this->storageEngine) {
            case 'couchdb' :
                $fn = $this->couchdbName;
                StorageEngine::couchDbStore($arr, $fn);
                break;

            case 'ical' :
                $fn = $this->ical_filename;
                StorageEngine::icalStore($arr, $fn);
                break;

            default :
                $fn = $this->csv_filename;
                StorageEngine::csvStore($arr, $fn);
                break;
        }
        
    }
    
    abstract public function run();
    abstract protected function scrape();
    
}


class StorageEngine {
    protected static $fields = array('start_time','end_time','title','description','branch','entity','source_url','source_text','access_datetime','parser_name','person_version');

    public static function couchDbStore($arr, $dbname)
    {
        $options['host'] = "localhost";
        $options['port'] = 5984;

        $couchDB = new CouchDbSimple($options);
        //$resp = $couchDB->send("DELETE", "/".$dbname."/");

        $resp = $couchDB->send("PUT", "/".$dbname);
        //var_dump($resp);
        foreach($arr as $data) {
            $_data = json_encode($data);
            //$id = md5(uniqid(mt_rand(), true));
            $id = md5($data['start_datetime'].'-'.$data['branch'].'-'.$data['entity'].'-'.$data['title']);
            $resp = $couchDB->send("PUT", "/".$dbname."/".$id, $_data);
            //var_dump($resp);

        }        
    }
    
    public static function csvStore($arr, $fn)
    {
        $lines = array();

        foreach($arr as $v) {
            $lines[] = "\"" . implode ('","', $v). "\"\n";
        }
        $fp = fopen($fn, 'w');
        if(!$fp) {
            echo 'Unable to open $fn for output';
            exit();
        }
        fwrite($fp, implode(',', self::$fields)."\n");

        foreach($lines as $line) {
            fwrite($fp, $line);
        }        
    }

    public static function icalStore($arr, $fn)
    {
        $ical_events = '';
        $space = '    ';
        foreach($arr as $event) {
            $start_time = date('Ymd\THis', strtotime($event['start_time']));
            if(trim($arr['end_time']) != ' ') {
                $end_time = date('Ymd\THis', $event['end_time']);
            }
            $summary = $event['title'];
            $content = str_replace(',', '\,', str_replace('\\', '\\\\', str_replace(array("\n","\r"), "\n" . $space, strip_tags($event['description']))));
            $ical_events .=<<<ICAL_EVENT
BEGIN:VEVENT
DTSTART:$start_time
DTEND:$end_time
SUMMARY:$summary
DESCRIPTION:$content
END:VEVENT

ICAL_EVENT;
                
        }
        $ical_content = <<<CONTENT
BEGIN:VCALENDAR
VERSION:2.0
PRODID:-//EventScraper//NONSGML v1.0//EN
X-WR-CALNAME:EventScraper'&#8217;s Results
X-WR-TIMEZONE:US/Eastern
X-ORIGINAL-URL:{$source_url}
X-WR-CALDESC:Events from {$parser_name}
CALSCALE:GREGORIAN
METHOD:PUBLISH
{$ical_events}END:VCALENDAR
CONTENT;
       
        $open_ical_file = fopen($fn, "w");
        fwrite($open_ical_file, $ical_content);
        fclose($open_ical_file);
    }
}
