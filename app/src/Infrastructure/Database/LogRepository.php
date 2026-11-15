<?php

namespace CampusDrive\Infrastructure\Database;

use CampusDrive\Infrastructure\Security\ClientIpResolver;
use Throwable;

final class LogRepository extends DatabaseRepository
{
    public const ACTION_LOGIN = 'login';
    public const ACTION_REGISTER = 'register';
    public const ACTION_LOGS_ACCESS = 'logs_access';

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

    /**
     * @param array{search?: string, action?: string, status?: string} $filters
     */
    public function getLogs(int $start = 0, int $limit = 100, array $filters = []): array
    {
        [$where, $params] = $this->buildFilter($filters);
        $stmt = $this->connection()->prepare(
            "SELECT id, user_id, user_email, action, status, ip_address, created_at FROM logs $where ORDER BY created_at DESC, id DESC LIMIT ?, ?"
        );
        $i = 1;
        foreach ($params as $param) {
            $stmt->bindValue($i++, $param);
        }
        $stmt->bindValue($i++, max(0, $start), \PDO::PARAM_INT);
        $stmt->bindValue($i, max(1, $limit), \PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * @param array{search?: string, action?: string, status?: string} $filters
     */
    public function countLogs(array $filters = []): int
    {
        [$where, $params] = $this->buildFilter($filters);
        $stmt = $this->connection()->prepare("SELECT COUNT(*) FROM logs $where");
        $stmt->execute($params);
        return (int) $stmt->fetchColumn();
    }

    /** @return string[] */
    public function getActions(): array
    {
        return $this->connection()->query("SELECT DISTINCT action FROM logs ORDER BY action")->fetchAll(\PDO::FETCH_COLUMN);
    }

    private function buildFilter(array $filters): array
    {
        $conditions = [];
        $params = [];
        if (($filters['search'] ?? '') !== '') {
            $like = '%' . addcslashes($filters['search'], '\\%_') . '%';
            $conditions[] = '(user_email LIKE ? OR ip_address LIKE ? OR user_id LIKE ?)';
            array_push($params, $like, $like, $like);
        }
        if (($filters['action'] ?? '') !== '') {
            $conditions[] = 'action = ?';
            $params[] = $filters['action'];
        }
        if (in_array($filters['status'] ?? '', [self::STATUS_SUCCESS, self::STATUS_FAILURE], true)) {
            $conditions[] = 'status = ?';
            $params[] = $filters['status'];
        }
        return [$conditions ? 'WHERE ' . implode(' AND ', $conditions) : '', $params];
    }

    public function logLogin(bool $success, ?string $userId, ?string $userEmail): void
    {
        $this->log(self::ACTION_LOGIN, $success, $userId, $userEmail);
    }

    public function logRegister(bool $success, ?string $userEmail): void
    {
        $this->log(self::ACTION_REGISTER, $success, null, $userEmail);
    }

    public function logLogout(?string $userId, ?string $userEmail): void
    {
        $this->log('logout', true, $userId, $userEmail);
    }

    public function logPasswordResetRequest(?string $userEmail): void
    {
        $this->log('password_reset_request', true, null, $userEmail);
    }

    public function logPasswordReset(?string $userId, ?string $userEmail): void
    {
        $this->log('password_reset', true, $userId, $userEmail);
    }

    public function logLogsAccess(?string $userId, ?string $userEmail): void
    {
        $this->log(self::ACTION_LOGS_ACCESS, true, $userId, $userEmail);
    }

    public function logFileUpload(bool $success, ?string $userId, ?string $userEmail): void
    {
        $this->log('file_upload', $success, $userId, $userEmail);
    }
}

