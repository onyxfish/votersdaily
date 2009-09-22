<?php
/*
 * Voters Daily: PHP EventScraper_Abstract Class
 * http://wiki.github.com/bouvard/votersdaily
 *
 * @author      Chauncey Thorn <chaunceyt@gmail.com>
 * Link: http://www.cthorn.com/
 *
 */
ini_set("display_errors", true);
error_reporting(E_ALL & ~E_NOTICE);
date_default_timezone_set('UTC');

abstract class EventScraper_Abstract
{
    public $parser_version;
    public $parser_name;
    public $access_time;
    public $source_url;
    public $source_text;
    public $parser_frequency;

    //do not edit these values
    
    public $defaultEventsDbName = 'vd_events';
    public $defaultLogsDbName = 'vd_logs';

    //end of do not edit these values

    //default runtime params
    //these value may be changed via cli by run.py
    
    
    public $storageEngine = 'couchdb'; // --engine
    public $dbprefix = null; // --dbprefix

    public $couchdbServer = 'localhost'; // --server
    public $couchdbPort = 5984; // --port

    public $couchdbName = 'vd_events'; // --eventdb
    public $couchdbLogDb = 'vd_logs'; // --logdb

    public $appDebug = 'false'; // --debug
   
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
        if(!is_array($arr)) {
            $_err_message_ = 'The method add_events expects an array. Review your scrape method.';
            throw new Exception($_err_message_);
        }
        
        $params = $_SERVER[ "argv" ];//TODO: make safer
        $this->getRunTimeParams($params);
        $eventdb = $this->couchdbName;
        $server = $this->couchdbServer;
        $port = $this->couchdbPort;
        $dbprefix = $this->dbPrefix;

        //DEBUGMODE
        if($this->appDebug) {

            if($dbprefix.$eventdb != $this->defaultEventsDbName) {

                if(StorageEngine::couchDb_EXISTS($dbprefix.$eventdb, $server, $port)) {

                    StorageEngine::couchDb_DROP($dbprefix.$eventdb, $server, $port);
                    StorageEngine::couchDb_CREATE($dbprefix.$eventdb, $server, $port);

                }
                else {

                    StorageEngine::couchDb_CREATE($dbprefix.$eventdb, $server, $port);
                }
            }
        } 
        
        $current_doc_count = StorageEngine::couchDbDocCount($dbprefix.$eventdb, $server, $port);
        $resp = StorageEngine::couchDbStore($arr, $dbprefix.$eventdb, $server, $port);
        //var_dump($resp);
        $new_doc_count = StorageEngine::couchDbDocCount($dbprefix.$eventdb, $server, $port);
        
        $doc_count = ($new_doc_count - $current_doc_count);

        //logging.

        $logdb = $this->couchdbLogDb;
        $scrape_log['parser_name'] = (string) $this->parser_name;
        $scrape_log['parser_version'] = (string) $this->parser_version;
        $scrape_log['url'] = (string) $this->source_url;
        $scrape_log['source_text'] = (string) $this->source_text;
        list($_month, $_day, $_year) = explode('-',date("m-d-Y", $this->access_time));
        $final_date_str = strftime('%Y-%m-%dT%H:%M:%SZ', mktime(0, 0, 0, $_month, $_day, $_year));
        $scrape_log['access_datetime'] = (string) $final_date_str;
        $scrape_log['parser_runtime'] = (float) $this->parser_runtime;
        $scrape_log['insert_count'] = (int) $doc_count;
        $scrape_log['result'] = (string) 'success';
        $scrape_log['traceback'] = null;
		//print_r($scrape_log);

        if($this->appDebug) { 
            if($logdb != $this->defaultLogsDbName) {
                if(StorageEngine::couchDb_EXISTS($dbprefix.$eventdb, $server, $port)) {

                    StorageEngine::couchDb_DROP($dbprefix.$logdb, $server, $port);
                    StorageEngine::couchDb_CREATE($dbprefix.$logdb, $server, $port);

                }
                else {

                    StorageEngine::couchDb_CREATE($dbprefix.$logdb, $server, $port);
                }
            }

        }

        StorageEngine::couchDbLog($scrape_log, $dbprefix.$logdb, $server, $port); 

    }

    //standard date format
    //note: be careful relying on this - it doesn't work in all cases.
    //we may have to just enforce the format and let the author of scraper take ownership of datetime and end_datetime format.
    protected function _vd_date_format($date_str)
    {
        return strftime('%Y-%m-%dT%H:%M:%SZ',strtotime($date_str));
    }

    //central location for escaping strings
    protected function _escape_str($str, $fieldtype="desc") 
    {
        switch($fieldtype) {
            case 'title' :
                //$result_str =  str_replace('\'','&#039;',trim(strip_tags($str)));
                $result_str =  htmlspecialchars(trim(strip_tags($str)));
                break;
            default :
                $result_str =  htmlspecialchars(trim(strip_tags($str)));
        }

        return $result_str;

    }

    //get CLI params passed by run.py if any
    //FIXME: restrict to only deal with predefined params.
    public function getRunTimeParams($arr)
    {
        if(!is_array($arr)) {
            list($param, $value) = explode('=',$arr);
            $this->_set_getopt_params($param, $value);
        }
        else {
            foreach($arr as $getopt) {
                list($param, $value) = explode('=',$getopt);
                $this->_set_getopt_params($param, $value);
            }
        }
    }

    // set commandline options coming from run.py
    protected function _set_getopt_params($param, $value)
    {
        //default 'vd_events
        if($param === '--engine') {
            $this->storageEngine = $value;
        }

        //default http://localhost:5984/
        if($param === '--server') {
            $this->couchdbServer = $value;
        }

        //default vd_events
        if($param === '--eventdb') {
           $this->couchdbName = $value;
        }

        //default vd_logs
        if($param === '--logdb') {
           $this->couchdbLogDb = $value;
        }

        //default debug
        if($param === '--debug') {
           $this->appDebug = true;
        }

        //default dbprefix
        if($param === '--dbprefix') {
           $this->dbPrefix = $value;
        }
    }

    //method used to execute scrape() - you can do whatever in scrape() as long as you return the expected data fields.
    //http://wiki.github.com/bouvard/votersdaily/database-planning
    abstract public function run();

    //do whatever it take to get expected data.
    abstract protected function scrape();
    
}


class StorageEngine 
{
    public static function couchDbDocCount($dbname, $server, $port)
    {
        $options['host'] = $server;
        $options['port'] = $port;

        $couchDB = new CouchDbSimple($options);
        $resp = $couchDB->send("GET", "/".$dbname."/");
        $results = json_decode($resp);
        return $results->doc_count;
    }


    public static function couchDb_EXISTS($dbname, $server, $port)
    {
        $options['host'] = $server;
        $options['port'] = $port;

        $couchDB = new CouchDbSimple($options);
        $resp = $couchDB->send("GET", "/".$dbname."/");
        //var_dump($resp);
        
    }

    public static function couchDb_CREATE($dbname, $server, $port)
    {
        $options['host'] = $server;
        $options['port'] = $port;

        $couchDB = new CouchDbSimple($options);
        $resp = $couchDB->send("PUT", "/".$dbname."/");
        //var_dump($resp);
        
    }

    public static function couchDb_DROP($dbname, $server, $port)
    {
        $options['host'] = $server;
        $options['port'] = $port;

        $couchDB = new CouchDbSimple($options);
        $resp = $couchDB->send("DELETE", "/".$dbname."/");
        //var_dump($resp);

    }

    public static function couchDbStore($arr, $dbname, $server, $port)
    {
        $options['host'] = $server;
        $options['port'] = $port;

        $couchDB = new CouchDbSimple($options);
        //$resp = $couchDB->send("GET", "/".$dbname."/");
        //var_dump($resp);

        //need to check to see if couchDB database is available before excuting
        //Chris and I talked about run.py being able to handle db 
        $resp = $couchDB->send("PUT", "/".$dbname);
        //var_dump($resp);
        
        foreach($arr as $data) {

            //get the couchdb_id from $data and then unset it.
            $couchdb_id = $data['couchdb_id'];

            //we no longer need couchdb_id and we don't want to save it.
            unset($data['couchdb_id']);

            //encode the date for couchdb
            $_data = json_encode($data);

            //store the data
            $resp = $couchDB->send("PUT", "/".$dbname."/".rawurlencode($couchdb_id), $_data);
            //var_dump($resp);
           
            //for debug will remove once we have all data inserting as expected.
            //$_results[] = de($resp);
        }        
            return $resp;
    }

    public static function couchDbLog($arr, $logdb, $server, $port)
    {
        $options['host'] = $server;
        $options['port'] = $port;
        $couchDB = new CouchDbSimple($options);
        //$resp = $couchDB->send("GET", "/".$logdb."/");
        //var_dump($resp);

        $resp = $couchDB->send("PUT", "/".$logdb);

        $_data = json_encode($arr);
        $right_now = date('D, d M Y H:i:s T');
        $logdb_id = strftime('%Y-%m-%dT%H:%M:%SZ',strtotime($right_now)) . ' - ' .$arr['parser_name']. ' - ' . $arr['parser_version'];
        $resp = $couchDB->send("PUT", "/".$logdb."/".rawurlencode($logdb_id), $_data);
        //var_dump($resp);

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

function microtime_float()
{
    list($utime, $time) = explode(" ", microtime());
    return ((float)$utime + (float)$time);
}


