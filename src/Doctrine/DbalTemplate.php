<?php
/**
 * Autumn Framework
 * 
 * @author Timandes White <timandes@php.net>
 * @license Apache-2.0
 */

namespace Autumn\Framework\Doctrine;

use \Generator;
use \RuntimeException;
use \Doctrine\DBAL\Driver\Connection;

use \Autumn\Framework\Context\Annotation\Autowired;

/**
 * Dbal Template
 */
class DbalTemplate implements DbalOperations
{
    private $connection = null;

    /**
     * @Autowired
     */
    public function setConnection(Connection $connection)
    {
        $this->connection = $connection;
    }

    public function queryAll(string $sql, callable $rowMapper, string $primaryKey) : Generator
    {
        $pkValue = 0;
        do {
            $paramMap = [$primaryKey => $pkValue];
            $rowSet = $this->queryForRaw($sql, $paramMap);
            if (!$rowSet) {
                return;
            }

            foreach ($rowSet as $row) {
                if (!isset($row[$primaryKey])) {
                    throw new RuntimeException("Primary key {$primaryKey} is missing in SQL {$sql}");
                }

                yield call_user_func($rowMapper, $row);
                $pkValue = max($pkValue, $row[$primaryKey]);
            }
        } while(true);
    }

    public function query(string $sql, callable $rowMapper, array $paramMap = []) : array
    {
        $rowSet = $this->queryForRaw($sql, $paramMap);
        return array_map($rowMapper, $rowSet);
    }
    
    public function queryForRaw(string $sql, array $paramMap = []): array
    {
        $stmt = $this->connection->prepare($sql);

        foreach ($paramMap as $k => $v) {
            $stmt->bindValue($k, $v);
        }
        $stmt->execute();

        return $stmt->fetchAll();
    }

    public function queryForObject(string $sql, callable $rowMapper, array $paramMap = [])
    {
        $objectList = $this->query($sql, $paramMap, $rowMapper);
        if (!$objectList) {
            return null;
        } else {
            return $objectList[0];
        }
    }


    public function update(string $sql, array $paramMap) : int
    {
        $stmt = $this->connection->prepare($sql);

        foreach ($paramMap as $k => $v) {
            $stmt->bindValue($k, $v);
        }

        if (stripos($sql, 'insert') === 0) {
            $stmt->execute();
            return $this->connection->lastInsertId();
        } else {
            return $this->connection->exec($stmt);
        }
    }
}