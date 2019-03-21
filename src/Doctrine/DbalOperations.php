<?php
/**
 * Autumn Framework
 * 
 * @author Timandes White <timandes@php.net>
 * @license Apache-2.0
 */

namespace Autumn\Framework\Doctrine;

use \Generator;

use \Doctrine\DBAL\Driver\Connection;

/**
 * Dbal Operations
 */
interface DbalOperations
{
    /**
     * Query all rows
     * 
     * @return Generator
     */
    public function queryAll(string $sql, callable $rowMapper, string $primaryKey) : Generator;

    /**
     * Query rows
     * 
     * @return array
     */
    public function query(string $sql, callable $rowMapper, array $paramMap = []) : array;

    /**
     * Query one row
     * 
     * @return object|null
     */
    public function queryForObject(string $sql, callable $rowMapper, array $paramMap = []);

    /**
     * Insert, update or delete via prepared SQL
     * 
     * @return int last insert id (for insert) or number of rows affected(for update and delete)
     */
    public function update(string $sql, array $paramMap) : int;

    public function setConnection(Connection $connection);
}