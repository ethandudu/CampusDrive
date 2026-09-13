<?php

class Database
{
    private static ?PDO $instance = null;

    private const HOST = 'mariadb';
    private const DBNAME = 'campusdrive';
    private const USER = 'campususer';
    private const PASSWORD = 'campuspassword';

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
                        self::HOST,
                        self::DBNAME
                    ),
                    self::USER,
                    self::PASSWORD,
                    [
                        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                        PDO::ATTR_EMULATE_PREPARES => false
                    ]
                );
            } catch (PDOException $e) {
                die('Erreur de connexion à la base de données : ' . $e->getMessage());
            }
        }

        return self::$instance;
    }

    private static function sanitizeInput(string $input): string
    {
        return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
    }

    private static function validateEmail(string $email): bool
    {
        return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
    }
}