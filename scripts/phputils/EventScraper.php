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
    public $couchdbName = 'vd_events';

   
    //each scraper must have parser_name and parser_version
    //FIXME: parser_frequency is required in config remove it from scrapers
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
    
    //default way to get page data

    //FIXME: doesn't function as expected in foreach(){}
    //had to fall back to file_get_contents()
    protected function urlopen($url)
    {
        $userAgent = "robot: http://wiki.github.com/bouvard/votersdaily";
        $opts = array('http'=> array('method'=>"GET",'header'=>$userAgent));
        $context = stream_context_create($opts);
        $response = file_get_contents($this->url,false,$context);
        return $response;
    }

    //this method is executed after the scrape() i.e.
    //$events = $this->scrape(); - execute the scrape
    //$this->add_events($events); - pass the resultset to the storageEngine
    final protected function add_events($arr)
    {
       $fn = $this->couchdbName;
       StorageEngine::couchDbStore($arr, $fn);
    }

    //standard date format
    protected function _vd_date_format($date_str)
    {
        return strftime('%Y-%m-%dT%H:%M:%SZ',strtotime($date_str));
    }

    //method used to execute scrape() - you can do whatever in scrape() as long as you return the expected data fields.
    //http://wiki.github.com/bouvard/votersdaily/database-planning
    abstract public function run();

    //do whatever it take to get expected data.
    abstract protected function scrape();
    
}


class StorageEngine {
    protected static $fields = array('datetime','end_datetime','title','description','branch','entity','source_url','source_text','access_datetime','parser_name','person_version');

    public static function couchDbStore($arr, $dbname)
    {
        $options['host'] = "localhost";
        $options['port'] = 5984;

        $couchDB = new CouchDbSimple($options);
        //$resp = $couchDB->send("DELETE", "/".$dbname."/");

        //need to check to see if couchDB database is available before excuting
        //Chris and I talked about run.py being able to handle db 
        $resp = $couchDB->send("PUT", "/".$dbname);
        //var_dump($resp);
        
        //$i=1; //FYI:$i is being used to ensure we have a unique id. 
        foreach($arr as $data) {
            $couchdb_id = $data['couchdb_id'];

            //we no longer need couchdb_id and we don't want to save it.
            unset($data['couchdb_id']);

            $_data = json_encode($data);
            $id = (string) $data['datetime'].'-'.$data['branch'].'-'.$data['entity'].'-'. $data['title'];
            $resp = $couchDB->send("PUT", "/".$dbname."/".rawurlencode($couchdb_id), $_data);
           
            //for debug will remove once we have all data inserting as expected.
            //var_dump($resp);
        //$i++;
        }        
    }
}

class EntityName
{
    static public $whitehouse = 'White House';
    static public $senate = 'Senate';
    static public $house = 'House of Representatives';
    static public $sup = 'Supreme Court';
    static public $fec = 'Federal Election Commission';
}

class BranchName
{
    static public $executive = 'Executive';
    static public $legislative = 'Legislative';
    static public $judicial = 'Judicial';
    static public $other = 'Other';
}
