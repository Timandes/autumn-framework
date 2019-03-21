<?php
/**
 * Autumn Framework
 * 
 * @author Timandes White <timandes@php.net>
 * @license Apache-2.0
 */

namespace Autumn\Framework\Context\Support;

use \Doctrine\Common\Annotations\AnnotationReader;
use \RuntimeException;
use \ReflectionClass;
use \ReflectionMethod;

use \Autumn\Framework\Beans\FactoryBean;
use \Autumn\Framework\Beans\Factory\BeanFactory;
use \Autumn\Framework\Beans\Factory\Support\BeanDefinitionRegistry;
use \Autumn\Framework\Boot\Logging\LoggerFactory;
use \Autumn\Framework\Context\Annotation\Autowired;
use \Autumn\Framework\Context\ApplicationListener;
use \Autumn\Framework\Context\Event\ContextRefreshedEvent;
use \Autumn\Framework\Context\Listener\ContextRefreshedEventApplicationListener;

/**
 * Implements the BeanDefinitionRegistry interface in order to allow for applying any bean definition readers to it.
 */
class GenericApplicationContext implements BeanDefinitionRegistry, BeanFactory
{
    private $beans = [];

    /** @var array type (with \\ prefix) => bean */
    private $primaryBeans = [];

    private $factoryBeans = [];

    private $dependencyTree = null;
    private $typeToDefinitionMap = [];
    private $nameToDefinitionMap = [];

    private $annotationReader = null;

    private $logger = null;

    private $applicationListeners = [];

    public function __construct()
    {
        $this->dependencyTree = new DependencyTree();
        $this->annotationReader = new AnnotationReader();
        $this->logger = LoggerFactory::getLog(self::class);
    }

/* {{{ BeanDefinitionRegistry */

    public function containsBeanDefinition(string $beanName) : bool
    {
        return isset($this->nameToDefinitionMap[$beanName]);
    }

    public function getBeanDefinition(string $beanName) : array
    {
        return $this->containsBeanDefinition($beanName)?$this->nameToDefinitionMap[$beanName]:[];
    }

    public function registerBeanDefinition(string $beanName, array $beanDefinition)
    {
        $bean = $beanDefinition[1];

        $context = array(
            'name' => $beanName,
        );
        $this->logger->debug("Appending bean definition {name} to registry ...", $context);

        if (!is_object($bean)) {
            $type = gettype($bean);
            $beanDefinition[] = $type;
            $this->addBeanDefinitionDirectly($beanName, $type, $beanDefinition);
            return;
        }

        if ($bean instanceof FactoryBean) {
            $type = $bean->getObjectType();
        } else {
            $type = get_class($bean);
        }
        $dependencies = $this->findDependencies($type);
        if ($dependencies) {
            $context['dependency_list'] = implode(', ', $dependencies);
        } else {
            $context['dependency_list'] = 'nothing';
        }
        $this->logger->debug("Bean {name} depends on {dependency_list}", $context);
        $beanDefinition[] = $this->toTypeKey($type);

        $rootClasses = $this->findRootClasses($type);
        foreach ($rootClasses as $beanType) {
            $beanType = $this->toTypeKey($beanType);
            $this->addBeanDefinitionDirectly($beanName, $beanType, $beanDefinition, $dependencies);
        }
    }

    private function addBeanDefinitionDirectly($beanName, $beanType, $beanDefinition, $dependencies = [])
    {
        $this->dependencyTree->addDependency($beanType, 0, $dependencies);
        $this->typeToDefinitionMap[$beanType] = $beanDefinition;
        $this->nameToDefinitionMap[$beanName] = $beanDefinition;
    }

    private function findDependencies(string $type)
    {
        $dependencyMap = [];

        $rc = new ReflectionClass($type);
        $properties = $rc->getProperties();
        foreach ($properties as $prop) {
            $annotation = $this->annotationReader->getPropertyAnnotation($prop, Autowired::class);
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
            $dependencyMap = array_merge($dependencyMap, $this->findDependenciesOfMethod($method));
        }

        return array_keys($dependencyMap);
    }

    private function findDependenciesOfMethod(ReflectionMethod $method)
    {
        $dependencyMap = [];
        if (!$this->annotationReader->getMethodAnnotation($method, Autowired::class)) {
            return $dependencyMap;
        }

        $parameters =  $method->getParameters();
        foreach ($parameters as $param) {
            $rt = $param->getType();
            $dependencyMap[$this->toTypeKey($rt->__toString())] = true;
        }

        return $dependencyMap;
    }

    public function removeBeanDefinition(string $beanName)
    {
        $beanDefinition = $this->getBeanDefinition($beanName);
        if (!$beanDefinition) {
            return;
        }

        $type = $beanDefinition[2];
        $this->dependencyTree->removeDependency($type);
        unset($this->typeToDefinitionMap[$type]);
        unset($this->nameToDefinitionMap[$beanName]);
    }

/* }}} */

/* {{{ BeanFactory */

    public function getBean(string $name)
    {
        if (!isset($this->beans[$name])) {
            return null;
        }

        return $this->beans[$name];
    }

    public function getBeanByType(string $type)
    {
        $type = $this->toTypeKey($type);
        $bean = $this->getBeanByTypeFrom($type, $this->primaryBeans);
        if (!$bean) {
            $beanFactory = $this->getFactoryBeanByType($type);
            if (!$beanFactory) {
                return null;
            }

            return $beanFactory->getObject();
        }

        return $bean;
    }

    private function getBeanByTypeFrom(string $type, array $typeToDefinitionMap)
    {
        $type = $this->toTypeKey($type);
        $rootClasses = $this->findRootClasses($type);
        foreach ($rootClasses as $beanType) {
            $bean = $this->getBeanByExactType($beanType, $typeToDefinitionMap);
            if ($bean) {
                return $bean;
            }
        }

        return null;
    }

    public function getFactoryBeanByType(string $type)
    {
        return $this->getBeanByTypeFrom($type, $this->factoryBeans);
    }

    private function getBeanByExactType(string $type, array $typeToDefinitionMap)
    {
        $type = $this->toTypeKey($type);
        if (!isset($typeToDefinitionMap[$type])) {
            return null;
        }

        return $typeToDefinitionMap[$type];
    }
    
/* }}} */

/* {{{ refresh() */
    /**
     * Load or refresh the persistent representation of the configuration, which might an XML file, properties file, or relational database schema.
     */
    public function refresh()
    {
        $this->loadBeansInDependencyTree($this->dependencyTree->generateTree());
        foreach ($this->applicationListeners as $listener) {
            if ($listener instanceof ContextRefreshedEventApplicationListener) {
                $listener->onApplicationEvent(new ContextRefreshedEvent());
            }
        }
    }

    private function loadBeansInDependencyTree(array $tree)
    {
        foreach ($tree as $node) {
            if (is_array($node)) {
                $this->loadBeansInDependencyTree($node);
            } else {
                list($name, $bean) = $this->typeToDefinitionMap[$node];
                $this->inject($bean);
                $this->setBean($name, $bean);
                if ($bean instanceof ApplicationListener) {
                    $this->applicationListeners[] = $bean;
                }
            }
        }
    }

    protected function inject($bean)
    {
        // Skip non-object bean
        if (!is_object($bean)) {
            return;
        }
        
        $rc = new ReflectionClass($bean);
        $properties = $rc->getProperties();
        foreach ($properties as $prop) {
            $annotation = $this->annotationReader->getPropertyAnnotation($prop, Autowired::class);
            if (!$annotation) {
                continue;
            }
            $annotation->load($this, $this->annotationReader, $prop, $bean);
        }

        $methods = $rc->getMethods();
        foreach ($methods as $method) {
            $annotation = $this->annotationReader->getMethodAnnotation($method, Autowired::class);
            if (!$annotation) {
                continue;
            }
            $annotation->loadMethod($this, $this->annotationReader, $method, $bean);
        }
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

    private function toTypeKey(string $type)
    {
        return '\\' . ltrim($type, '\\');
    }
/* }}} */
}