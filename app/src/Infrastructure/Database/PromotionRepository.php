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

    public function getPromotionStatus(string $promotion_id): ?string
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
}
