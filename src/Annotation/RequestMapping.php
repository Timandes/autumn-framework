<?php

namespace Autumn\Framework\Annotation;

use \ReflectionMethod;
use \ReflectionParameter;
use \Doctrine\Common\Annotations\AnnotationReader;

use \Autumn\Framework\Context\ApplicationContext;
use \Autumn\Framework\Web\Bind\Annotation\RequestBody;

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
        $server->addRequestMapping($this->value, $this->method, function($request, $response) use ($me, $ctx, $ar, $rm, $controller) {
            return $me->requestHandler($ctx, $ar, $request, $response, $controller, $rm);
        });
    }

    public function requestHandler(ApplicationContext $ctx, AnnotationReader $ar, $request, $response, $controller, ReflectionMethod $rm)
    {
        $requestBodyAnnotation = $ar->getMethodAnnotation($rm, RequestBody::class);
        if ($requestBodyAnnotation) {
            $requestBodyParameterName = $requestBodyAnnotation->value;
        } else {
            $requestBodyParameterName = '';
        }
        $message = $this->invokeControllerMethod($request, $controller, $rm, $requestBodyParameterName);

        $message = $this->convertMessage($ctx, $message);

        $response->header['content-type'] = 'application/json';
        $response->end($message);
    }

    private function convertMessage(ApplicationContext $ctx, $message)
    {
        if (is_null($message)) {
            return $message;
        }

        $messageConverters = $ctx->getBean('messageConverters');
        foreach ($messageConverters as $converter) {
            $message = $converter->write($message);
        }

        return $message;
    }

    private function invokeControllerMethod($request, $controller, ReflectionMethod $rm, string $requestBodyParameterName)
    {
        $parameters = $rm->getParameters();
        $arguments = [];
        foreach ($parameters as $param) {
            if ($param->getName() == $requestBodyParameterName) {
                $arguments[] = $this->getRequestBody($request);
            } else {
                $arguments[] = $this->getParamValue($request, $param);
            }
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

    private function getRequestBody($request) : string
    {
        return $request->rawContent();
    }
}