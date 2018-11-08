<?php

namespace Autumn\Framework\Annotation;

use \ReflectionClass;
use \Doctrine\Common\Annotations\AnnotationReader;

use \Autumn\Framework\Context\ApplicationContext;

/**
 * @Annotation
 */
class RestController
{
    public function load(ApplicationContext $ctx, AnnotationReader $ar, ReflectionClass $rc, $controller)
    {
        $methods = $rc->getMethods();
        foreach ($methods as $method) {
            $requestMappingAnnotation = $ar->getMethodAnnotation($method, RequestMapping::class);
            if (!$requestMappingAnnotation) {
                continue;
            }

            $requestMappingAnnotation->load($ctx, $ar, $method, $controller);
        }
    }
}