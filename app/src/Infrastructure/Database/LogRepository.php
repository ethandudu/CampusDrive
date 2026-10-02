<?php

namespace CampusDrive\Infrastructure\Database;

final class LogRepository extends DatabaseRepository
{
    public function insertLog(string $action, ?string $userId = null): void
    {
        $stmt = $this->connection()->prepare("INSERT INTO logs (action, user_id, created_at, ip_address) VALUES (?, ?, NOW(), ?)");
        $stmt->execute([
            InputSanitizer::sanitize($action),
            $userId,
            InputSanitizer::sanitize($_SERVER['REMOTE_ADDR'])
        ]);
    }
}
