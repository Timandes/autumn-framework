<?php
/**
 * Autumn Framework
 * 
 * @author Timandes White <timandes@php.net>
 * @license Apache-2.0
 */

namespace Autumn\Framework\Boot\Logging;

use \Autumn\Framework\Log\DefaultLogger;

/**
 * Logger Factory
 */
class LoggerFactory
{
    public static function create($class)
    {
        return new DefaultLogger($class);
    }
}