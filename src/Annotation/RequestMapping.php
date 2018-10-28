<?php

namespace Autumn\Annotation;

use \ReflectionMethod;
use \Doctrine\Common\Annotations\AnnotationReader;

/**
 * @Annotation
 */
class RequestMapping
{
    public $value = '';

    public $method = 'GET';

    public function load($server, AnnotationReader $ar, ReflectionMethod $rm, $controller)
    {
        $server->addRequestMapping($this->value, $this->method, function($request, $response) use ($rm, $controller) {
            $model = $rm->invoke($controller);
            if (is_scalar($model))
                $responseString = $model;
            else
                $responseString = json_encode($model);

            $response->header['content-type'] = 'application/json';
            $response->end($responseString);
        });
    }
}