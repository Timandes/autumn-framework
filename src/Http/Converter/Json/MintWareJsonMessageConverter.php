<?php
/**
 * Autumn Framework
 * 
 * @author Timandes White <timandes@php.net>
 * @license Apache-2.0
 */

namespace Autumn\Framework\Http\Converter\Json;

use \Autumn\Framework\Http\Converter\MessageConverter;
use \MintWare\JOM\ObjectMapper;

/**
 * MintWare Json Message Converter
 */
class MintWareJsonMessageConverter implements MessageConverter
{
    private $objectMapper = null;
    
    public function __construct()
    {
        $this->objectMapper = new ObjectMapper();
    }

    public function write($source) : string
    {
        if (is_scalar($source)) {
            return json_encode($source);
        }
        if (is_array($source)) {
            $array = [];
            foreach ($source as $item) {
                $array[] = $this->write($item);
            }
            return '[' . implode(',', $array) . ']';
        }

        return $this->objectMapper->objectToJson($source);
    }
}