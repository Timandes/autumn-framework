<?php
/**
 * Autumn Framework
 * 
 * @author Timandes White <timandes@php.net>
 * @license Apache-2.0
 */

namespace Autumn\Framework\Stereotype\Proxy;

/**
 * Implementation of method __call() for proxy classes
 */
trait ProxyMagicCall
{
    public function __call($method, $args)
    {
        if (!method_exists($this->bean, $method)) {
            throw new BadMethodCallException("Unknown method {$method}");
        }

        return call_user_func([$this->bean, $method], $args);
    }
}