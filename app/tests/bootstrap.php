<?php

/**
 * PHPUnit bootstrap file.
 *
 * Loads Composer's autoloader and utils/config.php, which reads its settings from
 * environment variables (see .env.example, and the GitHub Actions workflow for CI).
 */

require __DIR__ . '/../vendor/autoload.php';

// Loaded once here so every test can rely on the DB_* constants being
// defined without requiring utils/config.php (a procedural file, not a
// PSR-4 class) from each individual test.
require_once __DIR__ . '/../utils/config.php';
