<?php

namespace Autumn\Framework\Log;

use Psr\Log\AbstractLogger;

class DefaultLogger extends AbstractLogger
{
    private $class = '';

    public function __construct($class = '')
    {
        $this->class = $class?$class:'-';
    }
    
    public function log($level, $message, array $context = array())
    {
        fprintf(STDOUT, "%s %s %d %s %s" . PHP_EOL
                , date('Y-m-d H:i:s'), $level, getmypid()
                , $this->class, $message);
    }
}