#!/usr/bin/php -q
<?php
ini_set("display_errors", true);
error_reporting(E_ALL & ~E_NOTICE);
require 'phputils/votersdaily.php';

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
                echo 'Start to run the ' . $name . ' parser...'."\n";
                $parser = new $className;
                $parser->run();
                echo 'Finished'."\n\n";
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
