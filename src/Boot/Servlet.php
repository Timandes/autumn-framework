<?php

namespace Autumn\Framework\Boot;

class Servlet
{
    private $server = null;

    private $requestMapping = [];

    public function __construct($server)
    {
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
        $method = $request->server['request_method'];
        $uri = $request->server['request_uri'];
        $key = $method . ' ' . $uri;
        if (!isset($this->requestMapping[$key])) {
            $response->status(404);
            $response->end();
            return;
        }

        $callback = $this->requestMapping[$key];
        call_user_func($callback, $request, $response);
    }
}