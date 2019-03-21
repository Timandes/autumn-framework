<?php

namespace Autumn\Framework\Context\Listener;

use \Autumn\Framework\Context\ApplicationListener;
use \Autumn\Framework\Context\Event\ContextRefreshedEvent;

interface ContextRefreshedEventApplicationListener extends ApplicationListener
{
    public function onApplicationEvent(ContextRefreshedEvent $event);
}