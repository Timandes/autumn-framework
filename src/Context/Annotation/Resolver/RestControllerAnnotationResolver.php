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
use \Autumn\Framework\Annotation\RestController;

/**
 * @RestController Annotation Resolver
 */
class RestControllerAnnotationResolver implements AnnotationResolver
{
    public function resolve(AnnotationReader $ar
            , ReflectionClass $rc, ApplicationContext $ctx)
    {
        $restController = $ar->getClassAnnotation($rc, RestController::class);
        if (!$restController) {
            return [];
        }
        
        $controller = $rc->newInstance();
        $restController->load($ctx->getServer(), $ar, $rc, $controller);

        $name = "controller" . spl_object_hash($controller);
        return [$name => $controller];
    }
}