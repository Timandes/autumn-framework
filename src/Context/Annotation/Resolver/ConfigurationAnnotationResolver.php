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
use \Autumn\Framework\Context\Annotation\Configuration;

/**
 * @Configuration Annotation Resolver
 */
class ConfigurationAnnotationResolver implements AnnotationResolver
{
    public function resolve(AnnotationReader $ar
            , ReflectionClass $rc, ApplicationContext $ctx)
    {
        $annotation = $ar->getClassAnnotation($rc, Configuration::class);
        if (!$annotation) {
            return [];
        }
        
        $configuration = $rc->newInstance();
        return $annotation->load($ar, $rc, $configuration);
    }
}