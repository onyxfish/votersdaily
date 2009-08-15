<?php
require 'phputils/votersdaily.php';

class ScraperScheduler {

    public function run()
    {
        
        $files = glob('parsers/*.php');

        foreach($files as $file) {
            require './'.$file;
            $_file_str = str_replace('parsers/','',$file);
            list($name,$ext) = explode('.',$_file_str);
            $className = ucfirst($name);
        

            // Instanciate new parser class. 
            // execute run method
            eval ( '$'.$name.' =& new '.$className.'(null);' );
            if(method_exists(${$name}, 'run')) {
                echo 'Start to run the ' . $name . ' parser'."\n";
                $parser = new $className;
                $parser->run();
                echo 'Finished'."\n";
            }
            else {
                echo 'The following parser did not execute: ' . $className."\n";
                //return false;
            }
        
        }
    }        
}

$scraper = new ScraperScheduler();
$scraper->run();
