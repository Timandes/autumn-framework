<?php
/**
 * Autumn Framework
 * 
 * @author Timandes White <timandes@php.net>
 * @license Apache-2.0
 */

namespace Autumn\Framework\Stereotype\Proxy;

use \BadMethodCallException;
use \ReflectionClass;

use \Autumn\Framework\Context\Annotation\Autowired;
use \Autumn\Framework\Context\ApplicationContext;

use \Doctrine\Common\Annotations\AnnotationReader;

/**
 * Proxy for beans with @Component annotation
 */
class ComponentProxy
{
    private $bean = null;

    public function __construct(ApplicationContext $ctx, AnnotationReader $annotationReader, $bean)
    {
        $this->bean = $bean;
        $this->inject($ctx, $annotationReader, $bean);
    }

    private function inject(ApplicationContext $ctx, AnnotationReader $annotationReader, $bean)
    {
        $rc = new ReflectionClass($bean);
        $properties = $rc->getProperties();
        foreach ($properties as $prop) {
            $annotation = $annotationReader->getPropertyAnnotation($prop, Autowired::class);
            if (!$annotation) {
                continue;
            }
            $annotation->load($ctx, $annotationReader, $prop, $bean);
        }

        $methods = $rc->getMethods();
        foreach ($methods as $method) {
            $annotation = $annotationReader->getPropertyAnnotation($prop, Autowired::class);
            if (!$annotation) {
                continue;
            }
            $annotation->loadMethod($ctx, $annotationReader, $method, $bean);
        }
    }

    use ProxyMagicCall;
}