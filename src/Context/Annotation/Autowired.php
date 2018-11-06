<?php
/**
 * Autumn Framework
 * 
 * @author Timandes White <timandes@php.net>
 * @license Apache-2.0
 */

namespace Autumn\Framework\Context\Annotation;

use \ReflectionProperty;
use \RuntimeException;
use \Doctrine\Common\Annotations\AnnotationReader;

use \Autumn\Framework\Context\ApplicationContext;
use \Autumn\Framework\Annotation\RestController;

/**
 * @Annotation
 */
class Autowired
{
    public $value = '';

    public function load(ApplicationContext $ctx, AnnotationReader $ar, ReflectionProperty $prop, $targetBean)
    {
        if (!$this->value) {
            throw new RuntimeException("Parameter value of @Autowired is required");
        }

        $bean = $ctx->getBeanByType($this->value);
        if (!$bean) {
            throw new RuntimeException("Cannot find bean with type {$this->value}");
        }

        $prop->setAccessible(true);
        $prop->setValue($targetBean, $bean);
    }
}