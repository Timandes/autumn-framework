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
        $message = $this->interpolate($message, $context);
        fprintf(STDOUT, "%s %s %d %s %s" . PHP_EOL
                , date('Y-m-d H:i:s'), $level, getmypid()
                , $this->class, $message);
    }

    /**
     * Interpolates context values into the message placeholders.
     */
    function interpolate($message, array $context = array())
    {
        // build a replacement array with braces around the context keys
        $replace = array();
        foreach ($context as $key => $val) {
            $replace['{' . $key . '}'] = $val;
        }

        // interpolate replacement values into the message and return
        return strtr($message, $replace);
    }
}