<?php
/**
 * Autumn Framework
 * 
 * @author Timandes White <timandes@php.net>
 * @license Apache-2.0
 */

namespace Autumn\Framework\Beans\Factory\Support;

/**
 * Interface for registries that hold bean definitions.
*/
interface BeanDefinitionRegistry
{
    public function containsBeanDefinition(string $beanName) : bool;
    public function getBeanDefinition(string $beanName) : array;
    public function registerBeanDefinition(string $beanName, array $beanDefinition);
    public function removeBeanDefinition(string $beanName);
}