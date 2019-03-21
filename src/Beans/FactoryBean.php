<?php
/**
 * Autumn Framework
 * 
 * @author Timandes White <timandes@php.net>
 * @license Apache-2.0
 */

namespace Autumn\Framework\Beans;

/**
 * Interface to be implemented by objects used within a BeanFactory which are themselves factories for individual objects. If a bean implements this interface, it is used as a factory for an object to expose, not directly as a bean instance that will be exposed itself.
 */
interface FactoryBean
{
    /**
     * Return an instance (possibly shared or independent) of the object managed by this factory.
     */
    public function getObject();

    /**
     * Return the type of object that this FactoryBean creates, or null if not known in advance.
     */
    public function getObjectType() : string;
}