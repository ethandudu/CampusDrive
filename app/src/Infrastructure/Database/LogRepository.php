<?php

namespace CampusDrive\Infrastructure\Database;

use CampusDrive\Infrastructure\Security\ClientIpResolver;
use Throwable;

final class LogRepository extends DatabaseRepository
{
    public const ACTION_LOGIN = 'login';
    public const ACTION_REGISTER = 'register';

    public const STATUS_SUCCESS = 'success';
    public const STATUS_FAILURE = 'failure';

    /**
     * Records an action performed by a user. Logging never interrupts the calling flow.
     */
    public function log(string $action, bool $success, ?string $userId = null, ?string $userEmail = null): void
    {
        try {
            $email = InputSanitizer::sanitize($userEmail);
            $stmt = $this->connection()->prepare(
                "INSERT INTO logs (user_id, user_email, action, status, ip_address) VALUES (?, ?, ?, ?, ?)"
            );
            $stmt->execute([
                $userId,
                $email !== null ? mb_substr($email, 0, 255) : null,
                $action,
                $success ? self::STATUS_SUCCESS : self::STATUS_FAILURE,
                $this->clientIp(),
            ]);
        } catch (Throwable $e) {
            error_log('CampusDrive: unable to write log entry: ' . $e->getMessage());
        }
    }

    private function clientIp(): string
    {
        return substr(ClientIpResolver::resolve(
            $_SERVER,
            defined('TRUSTED_PROXIES') ? (array) TRUSTED_PROXIES : [],
            defined('BEHIND_CLOUDFLARE') && BEHIND_CLOUDFLARE === true
        ), 0, 45);
    }

    public function logLogin(bool $success, ?string $userId, ?string $userEmail): void
    {
        $this->log(self::ACTION_LOGIN, $success, $userId, $userEmail);
    }

    public function logRegister(bool $success, ?string $userEmail): void
    {
        $this->log(self::ACTION_REGISTER, $success, null, $userEmail);
    }
}

