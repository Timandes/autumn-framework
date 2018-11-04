<?php

namespace Autumn\Framework\Context;

use \RuntimeException;
use \ReflectionClass;
use \Doctrine\Common\Annotations\AnnotationReader;
use \Autumn\Framework\Annotation\RestController;
use \Autumn\Framework\Boot\Logging\LoggerFactory;

class ApplicationContext
{
    private $applicationClassName = '';

    private $argc = 0;

    private $argv = [];

    private $srcNamespace = '';

    private $rootDir = '';

    private $logger = null;

    private $server = null;

    private $beans = [];

    private $primaryBeans = [];

    private $annotationResolvers = [];

    public function __construct(...$args)
    {
        $this->parseArgs($args);
        $this->logger = LoggerFactory::create(ApplicationContext::class);
        $this->initializeAnnotationResolvers();
    }

    private function initializeAnnotationResolvers()
    {
        $this->annotationResolvers = [
            new Annotation\Resolver\RestControllerAnnotationResolver(),
            new Annotation\Resolver\ConfigurationAnnotationResolver(),
        ];
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

        if (!file_exists($path)) {
            throw new RuntimeException("Cannot find file {$path}");
        }
        require_once $path;

        $fqcn = '\\' . $namespace;
        if (!$this->targetExists($fqcn, false)) {
            throw new RuntimeException("Class {$fqcn} cannot be loaded from file {$path}");
        }

        $annotationReader = new AnnotationReader();

        $rc = new ReflectionClass($fqcn);
        foreach ($this->annotationResolvers as $resolver) {
            $beans = $resolver->resolve($annotationReader, $rc, $this);
            foreach ($beans as $name => $bean) {
                $type = get_class($bean);
                $type = $this->findRootClass($type);

                $this->primaryBeans[$type] = $bean;
                $this->beans[$name] = $bean;
                $this->logger->info("Bean {$name} loaded");
            }
        }
    }

    private function targetExists($fqcn)
    {
        return (interface_exists($fqcn)
                || class_exists($fqcn));
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
            if (preg_match('/^src\/?$/', $path)) {
                $this->srcNamespace = trim($namespace, '\\');
            }
        }

        if (!$this->srcNamespace) {
            throw new RuntimeException("Fail to find namespace of directory src/");
        }
    }

    public function getBean(string $name)
    {
        if (!isset($this->beans[$name])) {
            return null;
        }

        return $this->beans[$name];
    }

    protected function getBeanByType(string $type)
    {
        if (!isset($this->primaryBeans[$type])) {
            return null;
        }

        return $this->primaryBeans[$type];
    }

    private function findRootClass(string $name)
    {
        while ($parent = get_parent_class($name)) {
            $name = $parent;
        }
        return $name;
    }

    public function getServer()
    {
        return $this->server;
    }
}