<?php

namespace Autumn\Log;

use \Psr\Log\AbstractLogger;

class DefaultLogger extends AbstractLogger
{
    public function log($level, $message, array $context = array())
    {
        fprintf(STDOUT, "[%s] %s" . PHP_EOL, $level, $message);
    }
}