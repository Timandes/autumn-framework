<?php
/**
 * Autumn Framework
 * 
 * @author Timandes White <timandes@php.net>
 * @license Apache-2.0
 */

namespace Autumn\Framework\Swoole\Coroutine\MySql;

use \Generator;
use \RuntimeException;
use \Swoole\Coroutine\MySQL;

/**
 * MySql Template
 */
class MySqlTemplate implements MySqlOperations
{
    const TIMEOUT = 10;

    private $serverInfo = [];
    private $mySql = null;

    public function __construct($serverInfo)
    {
        $this->serverInfo = array_merge($this->getDefaultServerInfo(), $serverInfo);
        $this->mySql = new MySQL();
    }

    public function queryAll(string $sql, callable $rowMapper, string $primaryKey) : Generator
    {
        $pkValue = 0;
        do {
            $args = [$pkValue];
            $rowSet = $this->query($sql, $rowMapper, $args);
            if (!$rowSet) {
                return;
            }

            foreach ($rowSet as $row) {
                if (!isset($row[$primaryKey])) {
                    throw new RuntimeException("Primary key {$primaryKey} is missing in SQL {$sql}");
                }

                yield $row;
                $pkValue = max($pkValue, $row[$primaryKey]);
            }
        } while(true);
    }

    public function query(string $sql, callable $rowMapper, array $args = []) : array
    {
        $this->connect();

        $stmt = $this->mySql->prepare($sql, self::TIMEOUT);
        if (!$stmt) {
            throw new RuntimeException($this->mySQL->error, $this->mySQL->errno);
        }

        $r = $stmt->execute($args, self::TIMEOUT);
        if (!$r) {
            throw new RuntimeException($this->mySQL->error, $this->mySQL->errno);
        }
        
        return array_map($rowMapper, $stmt->fetchAll());
    }

    public function queryForObject(string $sql, callable $rowMapper, array $args = [])
    {
        $this->connect();

        $objectList = $this->query($sql, $args, $rowMapper);
        if (!$objectList) {
            return null;
        } else {
            return $objectList[0];
        }
    }


    public function update(string $sql, array $args) : int
    {
        $this->connect();

        $stmt = $this->mySql->prepare($sql, self::TIMEOUT);
        if (!$stmt) {
            throw new RuntimeException($this->mySQL->error, $this->mySQL->errno);
        }

        $r = $stmt->execute($args, self::TIMEOUT);
        if (!$r) {
            throw new RuntimeException($this->mySQL->error, $this->mySQL->errno);
        }

        if (stripos($sql, 'insert') === 0) {
            return $this->mySql->insert_id;
        } else {
            return $this->mySql->affected_rows;
        }
    }

    private function connect()
    {
        if ($this->mySql->connected) {
            return;
        }

        $r = $this->mySql->connect($this->serverInfo);
        if (!$r) {
            $host = $this->serverInfo['host']??'';
            $port = $this->serverInfo['port']??0;
            throw new RuntimeException("Fail to connect to {$host}:{$port}");
        }
    }

    private function getDefaultServerInfo()
    {
        return array(
            'port' => 3306,
            'timeout' => self::TIMEOUT,
            'charset' => 'utf8',
            'strict_type' => false,
            'fetch_mode' => true,
        );
    }
}