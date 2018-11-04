<?php

namespace Autumn\Framework\Annotation;

use \ReflectionClass;
use \Doctrine\Common\Annotations\AnnotationReader;

/**
 * @Annotation
 */
class RestController
{
    public function load($server, AnnotationReader $ar, ReflectionClass $rc, $controller)
    {
        $methods = $rc->getMethods();
        foreach ($methods as $method) {
            $requestMappingAnnotation = $ar->getMethodAnnotation($method, RequestMapping::class);
            if (!$requestMappingAnnotation) {
                continue;
            }

            $requestMappingAnnotation->load($server, $ar, $method, $controller);
        }
    }
}