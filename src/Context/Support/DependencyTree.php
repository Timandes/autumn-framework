<?php
/**
 * Autumn Framework
 * 
 * @author Timandes White <timandes@php.net>
 * @license Apache-2.0
 */

namespace Autumn\Framework\Context\Support;

use \bultonFr\DependencyTree\DependencyTree as ParentDependencyTree;

/**
 * Derivative of DependencyTree
 */
class DependencyTree extends ParentDependencyTree
{
    /**
     * @return DependencyTree : Current instance
     */
    public function removeDependency($name)
    {
        unset($this->dependencies[$name]);

        return $this;
    }
}