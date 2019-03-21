<?php
/**
 * Autumn Framework
 * 
 * @author Timandes White <timandes@php.net>
 * @license Apache-2.0
 */

namespace Autumn\Framework\Doctrine;

use \Autumn\Framework\Context\ApplicationContextAware;
use \Autumn\Framework\Context\ApplicationContext;
use \Autumn\Framework\Beans\FactoryBean;

use \Doctrine\DBAL\Configuration;
use \Doctrine\DBAL\DriverManager;
use \Doctrine\DBAL\Driver\Connection;

/**
 * DBAL Connection Factory Bean
 */
class DbalConnectionFactoryBean implements FactoryBean, ApplicationContextAware
{
    private $applicationContext = null;

    private $beanMap = [];

    private $connectionParams = [];

    public function __construct($connectionParams)
    {
        $this->connectionParams = $connectionParams;
    }

    public function getObject()
    {
        $servlet = $this->applicationContext->getServer();
        $subProcessId = $servlet->getSubProcessId();
        if (!isset($this->beanMap[$subProcessId])) {
            $config = new Configuration();
            $this->beanMap[$subProcessId] = DriverManager::getConnection($this->connectionParams, $config);
        }

        return $this->beanMap[$subProcessId];
    }

    public function getObjectType() : string
    {
        return Connection::class;
    }

    public function setApplicationContext(ApplicationContext $applicationContext)
    {
        $this->applicationContext = $applicationContext;
    }
}