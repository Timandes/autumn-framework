<?php

namespace Autumn\Framework\Context;

use \RuntimeException;
use \ReflectionClass;
use \Doctrine\Common\Annotations\AnnotationReader;
use \Autumn\Framework\Context\Annotation\Autowired;
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

    /** @var array type (with \\ prefix) => bean */
    private $primaryBeans = [];

    private $annotationResolvers = [];

    public function __construct(...$args)
    {
        $this->parseArgs($args);
        $this->logger = LoggerFactory::getLog(self::class);
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

        $annotationReader = new AnnotationReader();
        $this->loadAnnotations($annotationReader, $this->srcNamespace, $this->rootDir . DIRECTORY_SEPARATOR . 'src');
        $this->initializeMessageConvertersAsBean();
        $this->autowire($annotationReader);
    }

    private function initializeMessageConvertersAsBean()
    {
        $messageConverters = [];
        if (class_exists("\MintWare\JOM\ObjectMapper")) {
            $messageConverters[] = new \Autumn\Framework\Http\Converter\Json\MintWareJsonMessageConverter();
        } else {
            $messageConverters[] = new \Autumn\Framework\Http\Converter\Json\PhpJsonMessageConverter();
        }
        $this->setBean('messageConverters', $messageConverters);
    }

    private function loadAnnotations(AnnotationReader $ar, $namespace, $dir)
    {
        foreach ($this->getSubDirectories($dir) as $path) {
            $name = basename($path, '.php');
            $ns = $namespace . '\\' . $name;

            if (is_dir($path)) {
                $this->loadAnnotations($ar, $ns, $path);
            } else {
                $this->loadAnnotationsFromFile($ar, $ns, $path);
            }
        }
    }

    private function loadAnnotationsFromFile(AnnotationReader $annotationReader, $namespace, $path)
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

        $rc = new ReflectionClass($fqcn);
        foreach ($this->annotationResolvers as $resolver) {
            $beans = $resolver->resolve($annotationReader, $rc, $this);
            foreach ($beans as $name => $bean) {
                $this->setBean($name, $bean);
            }
        }
    }

    private function setBean($name, $bean)
    {
        if (is_object($bean)) {
            $type = get_class($bean);

            $rootClasses = $this->findRootClasses($type);
            foreach ($rootClasses as $beanType) {
                $beanType = '\\' . ltrim($beanType, '\\');
                $this->primaryBeans[$beanType] = $bean;
            }

            $withTypes = '(' . implode(', ', $rootClasses) . ')';
        } else {
            $withTypes = '';
        }
        $this->beans[$name] = $bean;

        $this->logger->info("Bean {$name}{$withTypes} loaded");
    }

    private function autowire(AnnotationReader $ar)
    {
        foreach ($this->beans as $bean) {
            if (!is_object($bean)) {
                continue;
            }
            $rc = new ReflectionClass($bean);
            $properties = $rc->getProperties();
            foreach ($properties as $prop) {
                $annotation = $ar->getPropertyAnnotation($prop, Autowired::class);
                if (!$annotation) {
                    continue;
                }
    
                $annotation->load($this, $ar, $prop, $bean);
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

    public function getBeanByType(string $type)
    {
        $rootClasses = $this->findRootClasses($type);
        foreach ($rootClasses as $beanType) {
            $bean = $this->getBeanByExactType($beanType);
            if ($bean) {
                return $bean;
            }
        }

        return null;
    }

    private function getBeanByExactType(string $type)
    {
        $type = '\\' . ltrim($type, '\\');
        if (!isset($this->primaryBeans[$type])) {
            return null;
        }

        return $this->primaryBeans[$type];
    }

    private function findRootClasses(string $name) : array
    {
        $interfaces = class_implements($name);
        if ($interfaces) {
            return array_values($interfaces);
        }

        $parents = class_parents($name);
        if (!$parents) {
            return [$name];
        }

        return array_pop($parents);
    }

    public function getServer()
    {
        return $this->server;
    }
}