<?php
abstract class VotersDaily_Abstract
{
    protected $parser_version;
    protected $parser_name;
    protected $couchdbName = 'phpvotedailydb';

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
    
    abstract public function run();
    abstract protected function scrape();
    abstract protected function add_events($arr, $fn);
    
}

