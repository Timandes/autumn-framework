<?php
/**
 * Autumn Framework
 * 
 * @author Timandes White <timandes@php.net>
 * @license Apache-2.0
 */

namespace Autumn\Framework\Context;

/**
 * Interface to be implemented by any object that wishes to be notified of the ApplicationContext that it runs in.
 */
interface ApplicationContextAware
{
    /**
     * Set the ApplicationContext that this object runs in.
     */
    public function setApplicationContext(ApplicationContext $applicationContext);
}
