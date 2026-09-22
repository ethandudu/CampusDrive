<?php

/**
 * PHPUnit bootstrap file.
 *
 * Loads Composer's autoloader and makes sure a utils/config.php exists so the
 * application classes (which require it) can be loaded in CI, where the real
 * (gitignored) config.php is never present. Environment variables can be used
 * to point the tests at a database service (see the GitHub Actions workflow).
 */

require __DIR__ . '/../vendor/autoload.php';

$configFile = __DIR__ . '/../utils/config.php';

if (!file_exists($configFile)) {
    $dbHost = getenv('DB_HOST') ?: '127.0.0.1';
    $dbName = getenv('DB_NAME') ?: 'campusdrive_test';
    $dbUser = getenv('DB_USER') ?: 'campususer';
    $dbPassword = getenv('DB_PASSWORD') ?: 'campuspassword';

    $contents = <<<PHP
<?php

// Auto-generated for tests, see tests/bootstrap.php
define('DB_HOST', '{$dbHost}');
define('DB_NAME', '{$dbName}');
define('DB_USER', '{$dbUser}');
define('DB_PASSWORD', '{$dbPassword}');

define('MAIL_HOST', 'localhost');
define('MAIL_USERNAME', 'test@example.com');
define('MAIL_PASSWORD', 'test');
define('MAIL_FROM', 'test@example.com');
define('MAIL_FROM_NAME', 'CampusDrive Test');

define('UNIVERSITY_EMAIL_DOMAINS', ['example.com']);

PHP;

    file_put_contents($configFile, $contents);
}
