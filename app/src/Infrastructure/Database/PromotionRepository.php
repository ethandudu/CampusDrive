<?php

namespace CampusDrive\Infrastructure\Database;

use RuntimeException;

final class PromotionRepository extends DatabaseRepository
{
    public function createPromotion(string $name): string
    {
        $promotionId = $this->connection()->query('SELECT UUID()')->fetchColumn();
        if (!is_string($promotionId)) {
            throw new RuntimeException('Could not generate a promotion UUID.');
        }

        $stmt = $this->connection()->prepare("INSERT INTO promotions (id, name, status, created_by) VALUES (?, ?, 'pending', ?)");
        $stmt->execute([$promotionId, InputSanitizer::sanitize($name), $_SESSION['user_id']]);
        return $promotionId;
    }

    public function updatePromotionStatus(string $promotion_id, string $status): bool
    {
        $stmt = $this->connection()->prepare("UPDATE promotions SET status = ? WHERE id = ?");
        return $stmt->execute([InputSanitizer::sanitize($status), $promotion_id]);
    }

    public function attachUserToPromotion(string $user_id, string $promotion_id): bool
    {
        $stmt = $this->connection()->prepare("UPDATE users SET promotion_id = ? WHERE id = ?");
        return $stmt->execute([InputSanitizer::sanitize($promotion_id), $user_id]);
    }

    public function getPromotionStatus(?string $promotion_id): ?string
    {
        $stmt = $this->connection()->prepare("SELECT status FROM promotions WHERE id = ?");
        $stmt->execute([InputSanitizer::sanitize($promotion_id)]);
        $result = $stmt->fetch();
        return $result ? $result['status'] : null;
    }

    public function getPromotionDetails(string $promotion_id): ?array
    {
        $stmt = $this->connection()->prepare("SELECT * FROM promotions WHERE id = ?");
        $stmt->execute([$promotion_id]);
        return $stmt->fetch() ?: null;
    }

    public function getPendingPromotions(): array
    {
        $stmt = $this->connection()->query("SELECT * FROM promotions WHERE status = 'pending' ORDER BY created_at DESC");
        return $stmt->fetchAll();
    }

    public function getTotalPromotions(): int
    {
        $stmt = $this->connection()->query("SELECT COUNT(*) FROM promotions");
        return (int) $stmt->fetchColumn();
    }

    public function getEnabledAnnouncement(string $promotion_id): ?string
    {
        $stmt = $this->connection()->prepare(
            "SELECT announcement_text, announcement_enabled FROM promotions WHERE id = ?"
        );
        $stmt->execute([$promotion_id]);
        $announcement = $stmt->fetch();
        if (!$announcement) {
            return null;
        }

        $text = trim($announcement['announcement_text'] ?? '');
        if (empty($announcement['announcement_enabled']) || $text === '') {
            return null;
        }

        return $text;
    }

    public function getAnnouncementDetails(string $promotion_id): ?array
    {
        $stmt = $this->connection()->prepare(
            "SELECT announcement_text, announcement_enabled FROM promotions WHERE id = ?"
        );
        $stmt->execute([$promotion_id]);
        return $stmt->fetch() ?: null;
    }

    public function updateAnnouncement(string $promotion_id, string $announcement_text, bool $announcement_enabled): bool
    {
        $stmt = $this->connection()->prepare(
            "UPDATE promotions SET announcement_text = ?, announcement_enabled = ? WHERE id = ?"
        );
        $stmt->bindValue(1, InputSanitizer::sanitize($announcement_text), \PDO::PARAM_STR);
        $stmt->bindValue(2, $announcement_enabled ? 1 : 0, \PDO::PARAM_INT);
        $stmt->bindValue(3, InputSanitizer::sanitize($promotion_id), \PDO::PARAM_STR);
        return $stmt->execute();
    }

    public function renamePromotionFile(string $file_id, string $promotion_id, string $original_name): bool
    {
        $original_name = trim($original_name);
        if (preg_match('/\A.{1,255}\z/us', $original_name) !== 1 || preg_match('/[\x00-\x1F\x7F]/', $original_name) === 1) {
            return false;
        }

        $fileStmt = $this->connection()->prepare(
            "SELECT id FROM files WHERE id = ? AND promotion_id = ? AND status = 'approved'"
        );
        $fileStmt->execute([$file_id, $promotion_id]);
        if (!$fileStmt->fetch()) {
            return false;
        }

        $stmt = $this->connection()->prepare(
            "UPDATE files SET original_name = ? WHERE id = ? AND promotion_id = ? AND status = 'approved'"
        );
        return $stmt->execute([$original_name, $file_id, $promotion_id]);
    }
}
