<?php
/**
 * Autumn Framework
 * 
 * @author Timandes White <timandes@php.net>
 * @license Apache-2.0
 */

namespace Autumn\Framework\Http\Converter;

/**
 * Message Converter
 */
interface MessageConverter
{
    /**
     * Convert source message to target message
     */
    public function write($source) : string;
}