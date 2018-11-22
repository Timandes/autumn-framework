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
use \bultonFr\DependencyTree\DependencyTree;

use \Autumn\Framework\Context\Annotation\Autowired;
use \Autumn\Framework\Boot\Logging\LoggerFactory;
use \Autumn\Framework\Beans\FactoryBean;
use \Autumn\Framework\Context\Annotation\Configuration;
use \Autumn\Framework\Context\Annotation\Bean;
use \Autumn\Framework\Stereotype\Proxy\ComponentProxy;
use \Autumn\Framework\Context\ApplicationContextAware;
use \Autumn\Framework\Context\ApplicationListener;
use \Autumn\Framework\Context\Listener\ContextRefreshedEventApplicationListener;
use \Autumn\Framework\Context\Event\ContextRefreshedEvent;

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

    private $factoryBeans = [];

    private $annotationResolvers = [];

    private $dependencyTree = null;
    private $beanMap = [];

    private $applicationListeners = [];

    public function __construct(...$args)
    {
        $this->parseArgs($args);
        $this->logger = LoggerFactory::getLog(self::class);
        $this->initializeAnnotationResolvers();
        $this->dependencyTree = new DependencyTree();
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

        // FIXME: BeanDefinitionRegistry::refresh();
        $this->logger->debug("BeanDefinitionRegistry::refresh() ...");
        $this->loadBeansInDependencyTree($annotationReader, $this->dependencyTree->generateTree());
        foreach ($this->applicationListeners as $listener) {
            $this->inject($annotationReader, $listener);
        }
        foreach ($this->applicationListeners as $listener) {
            if ($listener instanceof ContextRefreshedEventApplicationListener) {
                $listener->onApplicationEvent(new ContextRefreshedEvent());
            }
        }

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
        $this->resolveConfigurationClass($rc, $annotationReader);

        // ApplicationListener
        if ($rc->isSubclassOf(ApplicationListener::class)) {
            $this->applicationListeners[] = $rc->newInstance();
        }
    }

    private function loadBeansInDependencyTree(AnnotationReader $annotationReader, array $tree)
    {
        foreach ($tree as $node) {
            if (is_array($node)) {
                $this->loadBeansInDependencyTree($annotationReader, $node);
            } else {
                list($name, $bean) = $this->beanMap[$node];
                $this->inject($annotationReader, $bean);
                $this->setBean($name, $bean);
            }
        }
    }

    private function inject(AnnotationReader $annotationReader, $bean)
    {
        $rc = new ReflectionClass($bean);
        $properties = $rc->getProperties();
        foreach ($properties as $prop) {
            $annotation = $annotationReader->getPropertyAnnotation($prop, Autowired::class);
            if (!$annotation) {
                continue;
            }
            $annotation->load($this, $annotationReader, $prop, $bean);
        }

        $methods = $rc->getMethods();
        foreach ($methods as $method) {
            $annotation = $annotationReader->getMethodAnnotation($method, Autowired::class);
            if (!$annotation) {
                continue;
            }
            $annotation->loadMethod($this, $annotationReader, $method, $bean);
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
        if ($bean instanceof FactoryBean) {
            $this->setBean($name, $bean);
        } else {
            $this->appendToDependencyTree($annotationReader, $name, $bean);
        }
    }

    private function appendToDependencyTree(AnnotationReader $annotationReader, string $name, $bean)
    {
        $context = array(
            'name' => $name,
        );
        $this->logger->debug("Appending bean definition {name} to registry ...", $context);

        $type = get_class($bean);
        $dependencies = $this->findDependencies($annotationReader, $type);
        $context['dependency_list'] = implode(', ', $dependencies);
        $this->logger->debug("Bean {name} depends on {dependency_list}", $context);

        $rootClasses = $this->findRootClasses($type);
        foreach ($rootClasses as $beanType) {
            $beanType = $this->toTypeKey($beanType);
            $this->dependencyTree->addDependency($beanType, 0, $dependencies);
            $this->beanMap[$beanType] = [$name, $bean];
        }
    }

    private function toTypeKey(string $type)
    {
        return '\\' . ltrim($type, '\\');
    }

    private function findDependencies(AnnotationReader $annotationReader, string $type)
    {
        $dependencyMap = [];

        $rc = new ReflectionClass($type);
        $properties = $rc->getProperties();
        foreach ($properties as $prop) {
            $annotation = $annotationReader->getPropertyAnnotation($prop, Autowired::class);
            if (!$annotation) {
                continue;
            }
            if (!$annotation->value) {
                throw new RuntimeException("Parameter value of @Autowired is required");
            }
            $dependencyMap[$this->toTypeKey($annotation->value)] = true;
        }

        $methods = $rc->getMethods();
        foreach ($methods as $method) {
            $dependencyMap = array_merge($dependencyMap, $this->findDependenciesOfMethod($annotationReader, $method));
        }

        return array_keys($dependencyMap);
    }

    private function findDependenciesOfMethod(AnnotationReader $annotationReader, ReflectionMethod $method)
    {
        $dependencyMap = [];
        if (!$annotationReader->getMethodAnnotation($method, Autowired::class)) {
            return $dependencyMap;
        }

        $parameters =  $method->getParameters();
        foreach ($parameters as $param) {
            $rt = $param->getType();
            $dependencyMap[$this->toTypeKey($rt->__toString())] = true;
        }

        return $dependencyMap;
    }

    private function setBean($name, $bean)
    {
        if (is_object($bean)) {
            if ($bean instanceof FactoryBean) {
                $type = $bean->getObjectType();
            } else {
                $type = get_class($bean);
            }

            $rootClasses = $this->findRootClasses($type);
            foreach ($rootClasses as $beanType) {
                $beanType = $this->toTypeKey($beanType);
                if ($bean instanceof FactoryBean) {
                    $this->factoryBeans[$beanType] = $bean;
                } else {
                    $this->primaryBeans[$beanType] = $bean;
                }
            }

            $withTypes = '(' . implode(', ', $rootClasses) . ')';
        } else {
            $withTypes = '';
        }
        $this->beans[$name] = $bean;

        if ($bean instanceof FactoryBean) {
            $this->logger->info("FactoryBean {$name}{$withTypes} loaded");
        } else {
            $this->logger->info("Bean {$name}{$withTypes} loaded");
        }
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
        return $this->getBeanByTypeFrom($type, $this->primaryBeans);
    }

    public function getFactoryBeanByType(string $type)
    {
        return $this->getBeanByTypeFrom($type, $this->factoryBeans);
    }

    private function getBeanByTypeFrom(string $type, array $beanMap)
    {
        $rootClasses = $this->findRootClasses($type);
        foreach ($rootClasses as $beanType) {
            $bean = $this->getBeanByExactType($beanType, $beanMap);
            if ($bean) {
                return $bean;
            }
        }

        return null;
    }

    private function getBeanByExactType(string $type, array $beanMap)
    {
        $type = $this->toTypeKey($type);
        if (!isset($beanMap[$type])) {
            return null;
        }

        return $beanMap[$type];
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