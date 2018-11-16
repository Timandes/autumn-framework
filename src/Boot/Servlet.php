<?php

namespace Autumn\Framework\Boot;

use \Autumn\Framework\Boot\Logging\LoggerFactory;

class Servlet
{
    private $server = null;

    private $requestMapping = [];

    private $logger = null;

    public function __construct($server)
    {
        $this->logger = LoggerFactory::getLog(Servlet::class);

        $this->server = $server;
        $this->server->on('request', [$this, 'onRequest']);
    }

    public function addRequestMapping($uri, $method, $callback)
    {
        $key = $method . ' ' . $uri;
        $this->requestMapping[$key] = $callback;
    }

    public function onRequest($request, $response)
    {
        $logContext = ['start_time' => microtime(true)];

        $logContext['request_method'] = $request->server['request_method'];
        $logContext['request_uri'] = $request->server['request_uri'];
        $key = $logContext['request_method'] . ' ' . $logContext['request_uri'];
        if (!isset($this->requestMapping[$key])) {
            $logContext['status_code'] = 404;
            $response->status($logContext['status_code']);
            $response->end();
            $this->accessLog($logContext);
            return;
        }

        $callback = $this->requestMapping[$key];
        try {
            call_user_func($callback, $request, $response);
            // TODO: Get status code from $response
            $logContext['status_code'] = 200;
        } catch (\Exception $e) {
            $logContext['status_code'] = 500;
        } finally {
            $this->accessLog($logContext);
        }
    }

    private function accessLog(array $context)
    {
        $message = "{request_method} {request_uri} {status_code} {request_time}";
        $context['request_time'] = number_format(microtime(true) - $context['start_time'], 3);
        $this->logger->info($message, $context);
    }
}