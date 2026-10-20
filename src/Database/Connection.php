<?php

declare(strict_types=1);

namespace Dantweb\OxidPerformanceTests\Database;

use PDO;

/** PDO to the SDK's MySQL as seen from the PHP container (PERF_DB_HOST / _USER / _PASSWORD override it). */
final class Connection
{
    public static function create(string $database = PerfDatabase::NAME): PDO
    {
        $host = getenv('PERF_DB_HOST') ?: 'mysql';

        return new PDO(
            "mysql:host=$host;dbname=$database;charset=utf8",
            getenv('PERF_DB_USER') ?: 'root',
            getenv('PERF_DB_PASSWORD') ?: 'root',
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::MYSQL_ATTR_LOCAL_INFILE => true,
            ],
        );
    }
}
