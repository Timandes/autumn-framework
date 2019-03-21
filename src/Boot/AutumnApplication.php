<?php

namespace Autumn\Framework\Boot;

use \Doctrine\Common\Annotations\AnnotationRegistry;
use \Exception;
use \swoole_http_server;
use \Autumn\Framework\Boot\Servlet;
use \Autumn\Framework\Context\ApplicationContext;
use \Autumn\Framework\Log\DefaultLogger;

class AutumnApplication
{
    const EXIT_SUCCESS = 0;
    const EXIT_FAILURE = 1;

    private $logger = null;

    public static function run(...$args)
    {
        AnnotationRegistry::registerLoader(function($class) {
            $frameworkNsPrefix = 'Autumn\Framework\\';
            $annotationNamespaces = ['Annotation', 'Context\Annotation', 'Web\Bind\Annotation'];
            $nsSufix = null;
            foreach ($annotationNamespaces as $nss) {
                $namespace = $frameworkNsPrefix . $nss;
                if (strpos($class, $namespace) === 0) {
                    $nsSufix = $nss;
                    break;
                }
            }
            if (!$nsSufix) {
                return false;
            }

            $rncn = str_replace($namespace . '\\', '', $class);
            $nsSuffixPath = str_replace('\\', DIRECTORY_SEPARATOR, $nss);
            $file = str_replace('\\', DIRECTORY_SEPARATOR, $rncn) . '.php';
            $path = realpath(__DIR__ . '/../../') . DIRECTORY_SEPARATOR . 'src' . DIRECTORY_SEPARATOR . $nsSuffixPath . DIRECTORY_SEPARATOR . $file;
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
            $logger->error($e->getMessage() . PHP_EOL . $e->getTraceAsString());
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
        $applicationContext->setServer($servlet);
        $applicationContext->load();

        $httpServer->start();

        return self::EXIT_SUCCESS;
    }
}