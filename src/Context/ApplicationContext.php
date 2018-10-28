<?php

namespace Autumn\Context;

use \RuntimeException;
use \Autumn\Log\NullLogger;
use \ReflectionClass;
use \Doctrine\Common\Annotations\AnnotationReader;
use \Autumn\Annotation\RestController;

class ApplicationContext
{
    private $applicationClassName = '';

    private $argc = 0;

    private $argv = [];

    private $srcNamespace = '';

    private $rootDir = '';

    private $logger = null;

    private $server = null;

    public function __construct(...$args)
    {
        $this->parseArgs($args);
        $this->logger = new NullLogger();
    }

    public function setLogger($logger)
    {
        $this->logger = $logger;
    }

    public function setServer($server)
    {
        $this->server = $server;
    }

    public function load()
    {
        $this->rootDir = $this->getRootDir();
        $this->findSrcNamespace();
        $this->loadAnnotations($this->srcNamespace, $this->rootDir . DIRECTORY_SEPARATOR . 'src');
    }

    public function loadAnnotations($namespace, $dir)
    {
        foreach ($this->getSubDirectories($dir) as $path) {
            $name = basename($path, '.php');
            $ns = $namespace . '\\' . $name;

            if (is_dir($path)) {
                $this->loadAnnotations($ns, $path);
            } else {
                $this->loadAnnotationsFromFile($ns, $path);
            }
        }
    }

    private function loadAnnotationsFromFile($namespace, $path)
    {
        $this->logger->info("Loading {$path} ...");

        require_once $path;
        $fqcn = '\\' . $namespace;

        $annotationReader = new AnnotationReader();

        $rc = new ReflectionClass($fqcn);
        $restController = $annotationReader->getClassAnnotation($rc, RestController::class);
        if ($restController) {
            $controller = $rc->newInstance();
            $restController->load($this->server, $annotationReader, $rc, $controller);
        }
    }

    private function getSubDirectories($dir)
    {
        $handle = opendir($dir);
        if (!$handle) {
            throw new RuntimeException("Fail to open directory {$dir}");
        }
        while (false !== ($file = readdir($handle))) {
            if (in_array($file, ['.', '..'])) {
                continue;
            }

            $path = $dir . DIRECTORY_SEPARATOR . $file;
            yield $path;
        }
        closedir($handle);
    }

    private function getRootDir()
    {
        $bt = debug_backtrace();

        $rootTraceItem = array_pop($bt);
        $path = $rootTraceItem['file'];
        return dirname($path);
    }

    private function parseArgs($args)
    {
        if (count($args) < 3) {
            $this->applicationClassName = '';
            $this->argc = $args[0];
            $this->argv = $args[1];
        } else {
            $this->applicationClassName = $args[0];
            $this->argc = $args[1];
            $this->argv = $args[2];
        }
    }

    private function findSrcNamespace()
    {
        $composerLoader = new ComposerLoader($this->rootDir);
        $composerMeta = $composerLoader->load();
        
        foreach ($composerMeta['autoload']['psr-4'] as $namespace => $path) {
            if (preg_match('/^src\/$/', $path)) {
                $this->srcNamespace = rtrim($namespace, '\\');
            }
        }

        if (!$this->srcNamespace) {
            throw new RuntimeException("Fail to find namespace of directory src/");
        }
    }
}