<?php

namespace Autumn\Framework\Annotation;

use \ReflectionMethod;
use \ReflectionParameter;
use \Doctrine\Common\Annotations\AnnotationReader;

use \Autumn\Framework\Context\ApplicationContext;

/**
 * @Annotation
 */
class RequestMapping
{
    public $value = '';

    public $method = 'GET';

    public function load(ApplicationContext $ctx, AnnotationReader $ar, ReflectionMethod $rm, $controller)
    {
        $server = $ctx->getServer();

        $me = $this;
        $server->addRequestMapping($this->value, $this->method, function($request, $response) use ($me, $ctx, $rm, $controller) {
            return $me->requestHandler($ctx, $request, $response, $controller, $rm);
        });
    }

    public function requestHandler(ApplicationContext $ctx, $request, $response, $controller, ReflectionMethod $rm)
    {
        $message = $this->invokeControllerMethod($request, $controller, $rm);

        $messageConverters = $ctx->getBean('messageConverters');
        foreach ($messageConverters as $converter) {
            $message = $converter->write($message);
        }

        $response->header['content-type'] = 'application/json';
        $response->end($message);
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