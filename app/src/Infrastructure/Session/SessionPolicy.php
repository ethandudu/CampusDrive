<?php

namespace CampusDrive\Infrastructure\Session;

/**
 * Rules deciding whether an authenticated session can still be trusted.
 */
final class SessionPolicy
{
    /** Seconds without any request before the session is dropped. */
    public const IDLE_TIMEOUT = 3600;

    /** Seconds after login before the user has to sign in again, whatever the activity. */
    public const ABSOLUTE_TIMEOUT = 43200;

    /**
     * Changes whenever the stored password hash changes, which invalidates every
     * session opened with the previous password.
     */
    public static function fingerprint(string $passwordHash): string
    {
        return hash('sha256', $passwordHash);
    }

    /**
     * @param array<string, mixed> $session
     */
    public static function isExpired(array $session, int $now): bool
    {
        $authTime = $session['auth_time'] ?? null;
        $lastActivity = $session['last_activity'] ?? null;

        if (!is_int($authTime) || !is_int($lastActivity)) {
            return true;
        }

        return $now - $authTime > self::ABSOLUTE_TIMEOUT || $now - $lastActivity > self::IDLE_TIMEOUT;
    }

    /**
     * @param array<string, mixed> $session
     * @param array<string, mixed>|null $user Current database state of the session's user.
     */
    public static function matchesUser(array $session, ?array $user): bool
    {
        $expected = $session['auth_fingerprint'] ?? null;

        if ($user === null || !is_string($expected) || !isset($user['password'])) {
            return false;
        }

        return hash_equals($expected, self::fingerprint((string) $user['password']));
    }

    /**
     * @param array<string, mixed> $server
     * @param bool|null $configured Explicit SESSION_COOKIE_SECURE setting, which wins over detection.
     */
    public static function shouldUseSecureCookie(array $server, ?bool $configured = null): bool
    {
        if ($configured !== null) {
            return $configured;
        }

        if (!empty($server['HTTPS']) && strtolower((string) $server['HTTPS']) !== 'off') {
            return true;
        }

        // TLS is usually terminated by a reverse proxy, which reports the original scheme.
        $forwarded = strtolower(trim(explode(',', (string) ($server['HTTP_X_FORWARDED_PROTO'] ?? ''))[0]));

        return $forwarded === 'https';
    }
}
