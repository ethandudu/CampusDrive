<?php

namespace CampusDrive\Infrastructure\Database;

use PDO;

abstract class DatabaseRepository
{
    private ?PDO $connection;

    public function __construct(?PDO $connection = null)
    {
        $this->connection = $connection;
    }

    protected function connection(): PDO
    {
        return $this->connection ??= DatabaseConnection::getConnection();
    }
}
