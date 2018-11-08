<?php
/**
 * Autumn Framework
 * 
 * @author Timandes White <timandes@php.net>
 * @license Apache-2.0
 */

namespace Autumn\Framework\Http\Converter\Json;

use \Autumn\Framework\Http\Converter\MessageConverter;

/**
 * PHP Json Message Converter
 */
class PhpJsonMessageConverter implements MessageConverter
{
    public function write($source) : string
    {
        return json_encode($source);
    }
}