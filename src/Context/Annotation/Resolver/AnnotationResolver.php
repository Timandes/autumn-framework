<?php
/**
 * Autumn Framework
 * 
 * @author Timandes White <timandes@php.net>
 * @license Apache-2.0
 */

namespace Autumn\Framework\Context\Annotation\Resolver;

use \ReflectionClass;
use \Doctrine\Common\Annotations\AnnotationReader;

use \Autumn\Framework\Context\ApplicationContext;

/**
 * Annotation Resolver
 */
interface AnnotationResolver
{
    /**
     * Resolve annotation
     * 
     * @return array Beans
     */
    public function resolve(AnnotationReader $ar
            , ReflectionClass $rc, ApplicationContext $ctx);
}