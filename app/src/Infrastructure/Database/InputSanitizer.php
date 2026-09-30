<?php

namespace CampusDrive\Infrastructure\Database;

final class InputSanitizer
{
    public static function sanitize(?string $input): ?string
    {
        if (empty($input)) {
            return null;
        }

        return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
    }

    public static function isValidEmail(string $email): bool
    {
        return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
    }
}
