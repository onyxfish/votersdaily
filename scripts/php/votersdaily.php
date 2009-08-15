<?php
abstract class VotersDaily_Abstract
{

    public function __construct()
    {
    }
    
    
    abstract public function run();
    abstract protected function parse();
    abstract protected function save($arr, $fn);
    
}

