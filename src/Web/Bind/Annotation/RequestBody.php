<?php

namespace Autumn\Framework\Web\Bind\Annotation;

use \ReflectionMethod;
use \ReflectionParameter;
use \Doctrine\Common\Annotations\AnnotationReader;

use \Autumn\Framework\Context\ApplicationContext;

/**
 * @Annotation
 */
class RequestBody
{
    public $value = '';
}