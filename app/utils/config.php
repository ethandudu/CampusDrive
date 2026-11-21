<?php

// Every setting is read from the environment: set the values in the project's .env file
// (see .env.example for what each one does), docker-compose.yaml passes them to the php container.

$campusDriveEnv = static function (string $name, ?string $default = null): string {
    $value = getenv($name);
    if ($value === false || $value === '') {
        if ($default === null) {
            throw new RuntimeException("{$name} is not set, see .env");
        }
        return $default;
    }
    return $value;
};
$campusDriveList = static fn(string $value): array => array_values(array_filter(array_map('trim', explode(',', $value)), 'strlen'));
$campusDriveBool = static function (string $name): bool {
    $value = filter_var(getenv($name), FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
    if ($value === null) {
        throw new RuntimeException("{$name} must be true or false, see .env");
    }
    return $value;
};

// Database configuration
define('DB_HOST', $campusDriveEnv('MYSQL_HOST', 'mariadb'));
define('DB_NAME', $campusDriveEnv('MYSQL_DATABASE'));
define('DB_USER', $campusDriveEnv('MYSQL_USER'));
define('DB_PASSWORD', $campusDriveEnv('MYSQL_PASSWORD'));

// Mail configuration
define('MAIL_HOST', $campusDriveEnv('MAIL_HOST'));
define('MAIL_USERNAME', $campusDriveEnv('MAIL_USERNAME'));
define('MAIL_PASSWORD', $campusDriveEnv('MAIL_PASSWORD'));
define('MAIL_FROM', $campusDriveEnv('MAIL_FROM'));
define('MAIL_FROM_NAME', $campusDriveEnv('MAIL_FROM_NAME', 'CampusDrive'));
define('APP_BASE_URL', $campusDriveEnv('APP_BASE_URL', 'https://campusdrive.fr'));

// Utils
define('UNIVERSITY_EMAIL_DOMAINS', $campusDriveList($campusDriveEnv('UNIVERSITY_EMAIL_DOMAINS', '')));
define('admin_email', $campusDriveEnv('ADMIN_EMAIL', ''));

// Optional settings: only defined when their variable is set and not empty.
if ($campusDriveEnv('UPLOAD_DIR', '') !== '') {
    define('UPLOAD_DIR', $campusDriveEnv('UPLOAD_DIR'));
}
if ($campusDriveEnv('SESSION_COOKIE_SECURE', '') !== '') {
    define('SESSION_COOKIE_SECURE', $campusDriveBool('SESSION_COOKIE_SECURE'));
}
if ($campusDriveEnv('TRUSTED_PROXIES', '') !== '') {
    define('TRUSTED_PROXIES', $campusDriveList($campusDriveEnv('TRUSTED_PROXIES')));
}
if ($campusDriveEnv('BEHIND_CLOUDFLARE', '') !== '') {
    define('BEHIND_CLOUDFLARE', $campusDriveBool('BEHIND_CLOUDFLARE'));
}

unset($campusDriveEnv, $campusDriveList, $campusDriveBool);
