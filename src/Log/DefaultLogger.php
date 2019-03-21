<?php

namespace Autumn\Framework\Log;

use Psr\Log\AbstractLogger;

class DefaultLogger extends AbstractLogger
{
    const COLOR_OFF     = 0;
    const COLOR_BLACK   = 30;
    const COLOR_RED     = 31;
    const COLOR_GREEN   = 32;
    const COLOR_YELLOW  = 33;
    const COLOR_BLUE    = 34;
    const COLOR_PURPLE  = 35;
    const COLOR_CYAN    = 36;
    const COLOR_WHITE   = 37;

    private $class = '';

    private $isatty = true;

    public function __construct($class = '')
    {
        $this->class = $class?$class:'-';
        $this->isatty = posix_isatty(STDOUT);
    }
    
    public function log($level, $message, array $context = array())
    {
        $message = $this->interpolate($message, $context);
        fprintf(STDOUT, "%s %s %d %s %s" . PHP_EOL, 
            date('Y-m-d H:i:s'), $this->colorizeLevel($level), getmypid(),
            $this->colorize($this->class, self::COLOR_CYAN), $message);
    }

    private function colorizeLevel(string $level)
    {
        $color = self::COLOR_OFF;
        switch ($level) {
            case 'warn':
            case 'warning':
                $color = self::COLOR_YELLOW;
                break;
            case 'info':
                $color = self::COLOR_BLUE;
                break;
            case 'error':
                $color = self::COLOR_RED;
                break;
            case 'debug':
                $color = self::COLOR_GREEN;
                break;
            default:
                return $level;
        }

        return $this->colorize($level, $color);
    }

    private function colorize(string $message, int $color): string
    {
        if (!$this->isatty) {
            return $message;
        }

        return "\033[{$color}m{$message}\033[0m";
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