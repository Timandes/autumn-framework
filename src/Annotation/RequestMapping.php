<?php

namespace Autumn\Framework\Annotation;

use \ReflectionMethod;
use \ReflectionParameter;
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
        $me = $this;
        $server->addRequestMapping($this->value, $this->method, function($request, $response) use ($me, $rm, $controller) {
            return $me->requestHandler($request, $response, $controller, $rm);
        });
    }

    public function requestHandler($request, $response, $controller, ReflectionMethod $rm)
    {
        $model = $this->invokeControllerMethod($request, $controller, $rm);
        if (is_scalar($model))
            $responseString = $model;
        else
            $responseString = json_encode($model);

        $response->header['content-type'] = 'application/json';
        $response->end($responseString);
    }

    private function invokeControllerMethod($request, $controller, $rm)
    {
        $parameters = $rm->getParameters();
        $arguments = [];
        foreach ($parameters as $param) {
            $arguments[] = $this->getParamValue($request, $param);
        }
        return $rm->invokeArgs($controller, $arguments);
    }

    private function getParamValue($request, ReflectionParameter $param)
    {
        $name = $param->getName();
        $type = $param->getType();
        switch ($type) {
            case 'int':
                return (int)$request->get[$name]??0;
            case 'string':
            case null:
                return $request->get[$name]??'';
            default:
                return null;
        }
    }
}