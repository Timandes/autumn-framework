<?php

namespace Autumn\Framework\Log;

use Psr\Log\AbstractLogger;

class NullLogger extends AbstractLogger
{
    public function log($level, $message, array $context = array())
    {
        return;
    }
}