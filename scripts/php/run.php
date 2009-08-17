#!/usr/bin/php -q
<?php
function microtime_float()
{
    list($utime, $time) = explode(" ", microtime());
    return ((float)$utime + (float)$time);
}

$script_start = microtime_float();
ini_set("display_errors", true);
error_reporting(E_ALL & ~E_NOTICE);
require 'phputils/EventScraper.php';
require 'phputils/couchdb.php';

class ScraperScheduler {

    public static function run($engine = null)
    {
        if(!is_dir('parsers')) {
            throw new Exception('parsers folder does not exist');
        }

        if((!is_dir('data')) && !is_writable('data')) {
            throw new Exception('data directory does not exists or is not writeable');
        }

        $files = glob('parsers/*.php');

        foreach($files as $file) {
            require './'.$file;
            $_file_str = str_replace('parsers/','',$file);
            list($name,$ext) = explode('.',$_file_str);
            $className = ucfirst($name);
            
            //let make sure this Scraper meets the base requirements

            // Instanciate new parser class. 
            eval ( '$'.$name.' =& new '.$className.'(null);' );
            if(method_exists(${$name}, 'run')) {
                $scrape_start = microtime_float();
                $parser = new $className;

                echo 'Running Parser: ' . $parser->parser_name . '...'."\n";

                //setup loggin array
                $scrape_log['parser_name'] = $parser->parser_name;
                $scrape_log['parser_version'] = $parser->parser_version;


                if($engine) {
                    $parser->storageEngine = $engine;
                }

                $parser->run();

                //value available only after scrape

                $scrape_log['url'] = $parser->source_url;
                $scrape_log['source_text'] = null;
                $scrape_log['access_datetime'] = $parser->access_time;
                //deal with logging here

                $script_end = microtime_float();
                echo "Parse completed in ".bcsub($script_end, $script_start, 4)." seconds."."\n\n";                
            }
            else {
                echo 'The following parser did not execute: ' . $className."\n";
                //return false;
            }
            unset($parser);
        }
        unset($files);
    }        
}


$engine_options = array('couchdb','csv');
if(isset($argv[1]) && in_array($argv[1], $engine_options)) {
    $engine= $argv[1];
    echo "Using ".$engine." as Storage Engine...\n\n";
}
else {
    $engine=null;
}

ScraperScheduler::run($engine);

$script_end = microtime_float();
echo "Script executed in ".bcsub($script_end, $script_start, 4)." seconds."."\n\n";

