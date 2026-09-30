<?php

use CampusDrive\Infrastructure\Database\UserRepository;
use CampusDrive\Infrastructure\Session\SessionPolicy;

require_once __DIR__ . '/db.php';

/**
 * Opens an authenticated session for a user row (as returned by UserRepository).
 * The session ID and CSRF token are rotated so a pre-login ID can never be reused.
 *
 * @param array{id: string, role: string, promotion_id: ?string, password: string} $user
 */
function startAuthenticatedSession(array $user): void
{
    session_regenerate_id(true);

    $_SESSION['user_id'] = $user['id'];
    $_SESSION['role'] = $user['role'];
    $_SESSION['promotion_id'] = $user['promotion_id'];
    $_SESSION['auth_fingerprint'] = SessionPolicy::fingerprint($user['password']);
    $_SESSION['auth_time'] = $_SESSION['last_activity'] = time();
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

function destroySession(): void
{
    $_SESSION = [];

    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', [
            'expires' => time() - 42000,
            'path' => $params['path'],
            'domain' => $params['domain'],
            'secure' => $params['secure'],
            'httponly' => $params['httponly'],
            'samesite' => $params['samesite'],
        ]);
    }

    session_destroy();
}

if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    ini_set('session.gc_maxlifetime', (string) SessionPolicy::IDLE_TIMEOUT);

    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'secure' => SessionPolicy::shouldUseSecureCookie(
            $_SERVER,
            defined('SESSION_COOKIE_SECURE') ? (bool) constant('SESSION_COOKIE_SECURE') : null
        ),
        'httponly' => true,
        'samesite' => 'Lax',
    ]);

    session_start();

    if (isset($_SESSION['user_id'])) {
        // Revalidate on every request: the account may have been deleted, demoted,
        // or its password changed since this session was opened.
        $state = SessionPolicy::isExpired($_SESSION, time())
            ? null
            : (new UserRepository())->getSessionState((string) $_SESSION['user_id']);

        if (SessionPolicy::matchesUser($_SESSION, $state)) {
            $_SESSION['role'] = $state['role'];
            $_SESSION['promotion_id'] = $state['promotion_id'];
            $_SESSION['last_activity'] = time();
        } else {
            $_SESSION = [];
            session_regenerate_id(true);
        }
    }
}