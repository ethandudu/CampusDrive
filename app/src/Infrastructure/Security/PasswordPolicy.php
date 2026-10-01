<?php

namespace CampusDrive\Infrastructure\Security;

/**
 * Server-side password rules. They mirror the checks done in the browser on the
 * registration and settings pages, which can be bypassed.
 */
final class PasswordPolicy
{
    public const MIN_LENGTH = 8;

    /** Bytes, which also keeps the password under bcrypt's 72 bytes limit. */
    public const MAX_LENGTH = 64;

    /**
     * @return string|null Translation key describing the first violated rule, or null if the password is acceptable.
     */
    public static function violation(string $password): ?string
    {
        if (strlen($password) < self::MIN_LENGTH || strlen($password) > self::MAX_LENGTH) {
            return 'password_length';
        }

        if (!preg_match('/[A-Z]/', $password)) {
            return 'password_uppercase';
        }

        if (!preg_match('/[a-z]/', $password)) {
            return 'password_lowercase';
        }

        if (!preg_match('/[0-9]/', $password)) {
            return 'password_number';
        }

        if (!preg_match('/[!@#$%^&*()+-]/', $password)) {
            return 'password_special';
        }

        return null;
    }
}
