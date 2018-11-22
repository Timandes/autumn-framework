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
use \bultonFr\DependencyTree\DependencyTree;

use \Autumn\Framework\Context\Annotation\Bean;
use \Autumn\Framework\Context\ApplicationContext;
use \Autumn\Framework\Stereotype\Proxy\ComponentProxy;

/**
 * @Annotation
 */
class Configuration
{
    private $dependencyTree = null;

    /** $ */
    private $beanMap = [];

    public function __construct()
    {
        $this->dependencyTree = new DependencyTree();
    }

    public function load(ApplicationContext $ctx
            , AnnotationReader $ar
            , ReflectionClass $rc, $configuration)
    {
        $beans = [];
        $methods = $rc->getMethods();
        foreach ($methods as $method) {
            $pair = $this->loadMethodBean($ctx, $ar, $method, $configuration);
            if (!$pair) {
                continue;
            }
            list($name, $bean) = $pair;
            $beans[$name] = $bean;
        }
        return $beans;
    }

    private function loadMethodBean(ApplicationContext $ctx
            , AnnotationReader $ar
            , ReflectionMethod $rm, $configuration)
    {
        $annotation = $ar->getMethodAnnotation($rm, Bean::class);
        if (!$annotation) {
            return [];
        }

        $name = $rm->getName();
        $bean = $rm->invoke($configuration);
        $bean = new ComponentProxy($ctx, $ar, $bean);
        return [$name, $bean];
    }
}