<?php
/**
 * Autumn Framework
 * 
 * @author Timandes White <timandes@php.net>
 * @license Apache-2.0
 */

namespace Autumn\Framework\Context\Annotation;

use \ReflectionProperty;
use \ReflectionMethod;
use \RuntimeException;
use \Doctrine\Common\Annotations\AnnotationReader;

use \Autumn\Framework\Context\ApplicationContext;
use \Autumn\Framework\Annotation\RestController;
use \Autumn\Framework\Boot\Logging\LoggerFactory;

/**
 * @Annotation
 */
class Autowired
{
    public $value = '';

    private $logger = null;

    public function __construct()
    {
        $this->logger = LoggerFactory::getLog(Autowired::class);
    }

    public function load(ApplicationContext $ctx, AnnotationReader $ar, ReflectionProperty $prop, $targetBean)
    {
        if (!$this->value) {
            throw new RuntimeException("Parameter value of @Autowired is required");
        }

        $bean = $this->findBean($ctx, $this->value);

        $this->logger->info("Injecting {$this->value} to " . get_class($targetBean) . " ...");
        $prop->setAccessible(true);
        $prop->setValue($targetBean, $bean);
    }

    public function loadMethod(ApplicationContext $ctx, AnnotationReader $ar, ReflectionMethod $method, $targetBean)
    {
        $parameters =  $method->getParameters();
        $args = [];
        $types = [];
        foreach ($parameters as $param) {
            $rt = $param->getType();
            $type = $rt->__toString();
            $args[] = $this->findBean($ctx, $type);
            $types[] = $type;
        }

        $this->logger->info("Injecting " . implode(', ', $types) . " to " . get_class($targetBean) . " ...");
        call_user_func_array([$targetBean, $method->getName()], $args);
    }

    private function findBean(ApplicationContext $ctx, string $type)
    {
        $bean = $ctx->getBeanByType($type);
        if (!$bean) {
            $factoryBean = $ctx->getFactoryBeanByType($type);
            if (!$factoryBean) {
                throw new RuntimeException("Cannot find bean with type {$type}");
            }

            $bean = $factoryBean->getObject();
        }

        return $bean;
    }
}