<?php

namespace Autumn\Boot;

use \Autumn\Context\ApplicationContext;
use \Autumn\Log\DefaultLogger;
use \swoole_http_server;
use \Exception;
use \Doctrine\Common\Annotations\AnnotationRegistry;
use \Autumn\Boot\Servlet;

class AutumnApplication
{
    const EXIT_SUCCESS = 0;
    const EXIT_FAILURE = 1;

    private $logger = null;

    public static function run(...$args)
    {
        AnnotationRegistry::registerLoader(function($class) {
            $namespace = 'Autumn\Annotation';
            if (strpos($class, $namespace) !== 0) {
                return false;
            }

            $rncn = str_replace($namespace . '\\', '', $class);
            $file = str_replace('\\', DIRECTORY_SEPARATOR, $rncn) . '.php';
            $path = realpath(__DIR__ . '/../../') . DIRECTORY_SEPARATOR . 'src' . DIRECTORY_SEPARATOR . 'Annotation' . DIRECTORY_SEPARATOR . $file;
            if (!is_file($path)) {
                return false;
            }
            require_once $path;
            return true;
        });

        $logger = new DefaultLogger();
        try {
            $application = new self();
            $application->setLogger($logger);
            return $application->start(...$args);
        } catch (Exception $e) {
            $logger->error($e->getMessage());
            return self::EXIT_FAILURE;
        }
    }

    public function setLogger($logger)
    {
        $this->logger = $logger;
    }

    private function start(...$args)
    {
        $httpServer = new swoole_http_server("127.0.0.1", 3028);
        $servlet = new Servlet($httpServer);

        $applicationContext = new ApplicationContext(...$args);
        $applicationContext->setLogger($this->logger);
        $applicationContext->setServer($servlet);
        $applicationContext->load();

        $httpServer->start();

        return self::EXIT_SUCCESS;
    }
}