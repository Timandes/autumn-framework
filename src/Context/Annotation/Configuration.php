<?php
/**
 * Autumn Framework
 * 
 * @author Timandes White <timandes@php.net>
 * @license Apache-2.0
 */

namespace Autumn\Framework\Context\Annotation;

use \ReflectionClass;
use \ReflectionMethod;
use \Doctrine\Common\Annotations\AnnotationReader;

use \Autumn\Framework\Context\Annotation\Bean;

/**
 * @Annotation
 */
class Configuration
{
    public function load(AnnotationReader $ar
            , ReflectionClass $rc, $configuration)
    {
        $beans = [];
        $methods = $rc->getMethods();
        foreach ($methods as $method) {
            $pair = $this->loadMethodBean($ar, $method, $configuration);
            if (!$pair) {
                continue;
            }
            list($name, $bean) = $pair;
            $beans[$name] = $bean;
        }
        return $beans;
    }

    private function loadMethodBean(AnnotationReader $ar
            , ReflectionMethod $rm, $configuration)
    {
        $annotation = $ar->getMethodAnnotation($rm, Bean::class);
        if (!$annotation) {
            return [];
        }

        $name = $rm->getName();
        $bean = $rm->invoke($configuration);
        return [$name, $bean];
    }
}