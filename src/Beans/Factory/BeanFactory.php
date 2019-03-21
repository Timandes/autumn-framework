<?php
/**
 * Autumn Framework
 * 
 * @author Timandes White <timandes@php.net>
 * @license Apache-2.0
 */

namespace Autumn\Framework\Beans\Factory;

/**
 * The root interface for accessing a Spring bean container.
 */
interface BeanFactory
{
    public function getBean(string $name);
    public function getBeanByType(string $type);
}