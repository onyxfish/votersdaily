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
require 'phputils/votersdaily.php';
require 'phputils/couchdb.php';

class ScraperScheduler {

    public static function run()
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
                echo 'Running Parser: ' . $name . '...'."\n";
                $scrape_start = microtime_float();

                $parser = new $className;
                $parser->run();

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




ScraperScheduler::run();

$script_end = microtime_float();
echo "Script executed in ".bcsub($script_end, $script_start, 4)." seconds."."\n\n";

