<?php

namespace CampusDrive\Infrastructure\Database;

use PDO;
use PDOException;

final class DatabaseConnection
{
    private static ?PDO $instance = null;

    private function __construct()
    {
    }

    public static function getConnection(): PDO
    {
        if (self::$instance === null) {
            try {
                self::$instance = new PDO(
                    sprintf(
                        'mysql:host=%s;dbname=%s;charset=utf8mb4',
                        DB_HOST,
                        DB_NAME
                    ),
                    DB_USER,
                    DB_PASSWORD,
                    [
                        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                        PDO::ATTR_EMULATE_PREPARES => false
                    ]
                );
            } catch (PDOException $e) {
                error_log('Database connection failed: ' . $e->getMessage());
                http_response_code(503);
                die('Service temporairement indisponible.');
            }
        }

        return self::$instance;
    }
}
