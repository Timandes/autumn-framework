<?php
/**
 * Autumn Framework
 *
 * @author Timandes White <timandes@php.net>
 * @license Apache-2.0
 */

namespace Autumn\Framework\Context;

use \RuntimeException;
use \ReflectionClass;
use \ReflectionMethod;
use \Doctrine\Common\Annotations\AnnotationReader;

use \Autumn\Framework\Context\Annotation\Autowired;
use \Autumn\Framework\Boot\Logging\LoggerFactory;
use \Autumn\Framework\Beans\FactoryBean;
use \Autumn\Framework\Context\Annotation\Configuration;
use \Autumn\Framework\Context\Annotation\Bean;
use \Autumn\Framework\Stereotype\Proxy\ComponentProxy;
use \Autumn\Framework\Context\ApplicationContextAware;
use \Autumn\Framework\Context\ApplicationListener;
use \Autumn\Framework\Context\Support\GenericApplicationContext;

class ApplicationContext extends GenericApplicationContext
{
    private $applicationClassName = '';

    private $argc = 0;

    private $argv = [];

    private $srcNamespace = '';

    private $rootDir = '';

    private $logger = null;

    private $server = null;

    private $annotationResolvers = [];

    public function __construct(...$args)
    {
        parent::__construct();
        $this->parseArgs($args);
        $this->logger = LoggerFactory::getLog(self::class);
        $this->initializeAnnotationResolvers();
    }

    private function initializeAnnotationResolvers()
    {
        $this->annotationResolvers = [
            new Annotation\Resolver\RestControllerAnnotationResolver(),
            //new Annotation\Resolver\ConfigurationAnnotationResolver(),
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

        $this->refresh();
    }

    private function initializeMessageConvertersAsBean()
    {
        $messageConverters = [];
        if (class_exists("\MintWare\JOM\ObjectMapper")) {
            $messageConverters[] = new \Autumn\Framework\Http\Converter\Json\MintWareJsonMessageConverter();
        } else {
            $messageConverters[] = new \Autumn\Framework\Http\Converter\Json\PhpJsonMessageConverter();
        }
        $this->registerBeanDefinition('messageConverters', ['messageConverters', $messageConverters]);
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
        $this->logger->info("Scanning file {$path} ...");

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
                $this->registerBeanDefinition($name, [$name, $bean]);
            }
        }
        $this->resolveConfigurationClass($rc, $annotationReader);

        // ApplicationListener
        if ($rc->isSubclassOf(ApplicationListener::class)) {
            $bean = $rc->newInstance();
            $name = 'applicationListener' . spl_object_hash($bean);
            $this->registerBeanDefinition($name, [$name, $bean]);
        }
    }

    private function resolveConfigurationClass(ReflectionClass $rc, AnnotationReader $annotationReader)
    {
        $annotation = $annotationReader->getClassAnnotation($rc, Configuration::class);
        if (!$annotation) {
            return;
        }

        $configuration = $rc->newInstance();
        $this->loadConfigurationClass($rc, $annotationReader, $configuration);
    }

    private function loadConfigurationClass(ReflectionClass $rc, AnnotationReader $annotationReader, $configuration)
    {
        $methods = $rc->getMethods();
        foreach ($methods as $method) {
            $this->loadMethodBean($annotationReader, $method, $configuration);
        }
    }

    private function loadMethodBean(AnnotationReader $annotationReader, ReflectionMethod $method, $configuration)
    {
        $annotation = $annotationReader->getMethodAnnotation($method, Bean::class);
        if (!$annotation) {
            return;
        }

        $name = $method->getName();
        $bean = $method->invoke($configuration);
        if ($bean instanceof ApplicationContextAware) {
            $bean->setApplicationContext($this);
        }

        if ($bean) {
            $this->registerBeanDefinition($name, [$name, $bean]);
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

    public function getServer()
    {
        return $this->server;
    }
}
