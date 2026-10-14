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

// OPTIONAL: IP addresses or CIDR ranges of reverse proxies whose X-Forwarded-For header can be trusted when logging
// the client IP. Leave undefined if the app is not behind a proxy.
// define('TRUSTED_PROXIES', ['172.18.0.1', '10.0.0.0/8']);

// OPTIONAL: set to true when the app is served through Cloudflare. CF-Connecting-IP is then used for the logged IP,
// but only for requests coming from Cloudflare's IP ranges (or from a TRUSTED_PROXIES entry, e.g. a tunnel/local proxy).
// define('BEHIND_CLOUDFLARE', true);
