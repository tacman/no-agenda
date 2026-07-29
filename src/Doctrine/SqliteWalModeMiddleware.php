<?php

namespace App\Doctrine;

use Doctrine\Bundle\DoctrineBundle\Attribute\AsMiddleware;
use Doctrine\DBAL\Driver;
use Doctrine\DBAL\Driver\AbstractSQLiteDriver;
use Doctrine\DBAL\Driver\Connection;
use Doctrine\DBAL\Driver\Middleware;
use Doctrine\DBAL\Driver\Middleware\AbstractDriverMiddleware;

/**
 * WAL journal mode lets sqlite serve reads while a write is in progress instead
 * of blocking behind the default rollback-journal's exclusive lock -- the main
 * risk of running sqlite in production with a web process and a scheduler/worker
 * process writing concurrently. busy_timeout makes a writer that does contend
 * with another writer retry for 5s instead of failing immediately with
 * "database is locked".
 *
 * Priority must be higher than doctrine-bundle's built-in middlewares (logging/
 * debug/idle_connection all run at priority 10) so this wraps the raw driver
 * FIRST -- middlewares apply in descending-priority order and each wraps the
 * previous result, so a lower priority here would receive an already-wrapped
 * generic Driver instance instead of the concrete AbstractSQLiteDriver, and the
 * instanceof check below would silently never match.
 */
#[AsMiddleware(priority: 100)]
final class SqliteWalModeMiddleware implements Middleware
{
    public function wrap(Driver $driver): Driver
    {
        if (!$driver instanceof AbstractSQLiteDriver) {
            return $driver;
        }

        return new class ($driver) extends AbstractDriverMiddleware {
            public function connect(array $params): Connection
            {
                $connection = parent::connect($params);

                $connection->exec('PRAGMA journal_mode=WAL');
                $connection->exec('PRAGMA busy_timeout=5000');

                return $connection;
            }
        };
    }
}
