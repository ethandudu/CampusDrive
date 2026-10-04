<?php

// Database configuration
define('DB_HOST', 'mariadb');
define('DB_NAME', 'campusdrive');
define('DB_USER', 'campususer');
define('DB_PASSWORD', 'campuspassword');

// Mail configuration
define('MAIL_HOST', 'ssl0.ovh.net');
define('MAIL_USERNAME', 'noreply@campusdrive.fr');
define('MAIL_PASSWORD', 'CampusPassword');
define('MAIL_FROM', 'noreply@campusdrive.fr');
define('MAIL_FROM_NAME', 'CampusDrive');
define('APP_BASE_URL', getenv('APP_BASE_URL') ?: 'https://campusdrive.fr');

// Utils
define('UNIVERSITY_EMAIL_DOMAINS', []);
define('admin_email', '');
// OPTIONAL: define('UPLOAD_DIR', '');

// OPTIONAL: force the Secure flag on the session cookie. By default it is enabled when the request
// is served over HTTPS (or behind a proxy sending X-Forwarded-Proto: https). Enable it in production.
// define('SESSION_COOKIE_SECURE', true);