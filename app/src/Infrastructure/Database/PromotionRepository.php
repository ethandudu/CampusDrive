<?php

namespace CampusDrive\Infrastructure\Database;

use CampusDrive\Infrastructure\Storage\UploadStorage;
use InvalidArgumentException;
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

    public function deletePendingPromotion(string $promotion_id): bool
    {
        $stmt = $this->connection()->prepare("DELETE FROM promotions WHERE id = ? AND status = 'pending'");
        $stmt->execute([$promotion_id]);
        return $stmt->rowCount() > 0;
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

    public function getAllPromotions(): array
    {
        return $this->connection()->query('SELECT id, name, status FROM promotions ORDER BY name, id')->fetchAll();
    }

    public function renameForAdmin(string $promotionId, string $name): void
    {
        $name = trim($name);
        $storedName = InputSanitizer::sanitize($name);
        if ($storedName === null || mb_strlen($storedName) > 255 || preg_match('/[\x00-\x1F\x7F]/', $name)) {
            throw new InvalidArgumentException('admin_invalid_promotion_name');
        }
        $stmt = $this->connection()->prepare('UPDATE promotions SET name = ? WHERE id = ?');
        $stmt->execute([$storedName, $promotionId]);
        if ($stmt->rowCount() === 0 && $this->getPromotionDetails($promotionId) === null) {
            throw new InvalidArgumentException('admin_record_missing');
        }
    }

    public function replaceAmbassador(string $promotionId, ?string $oldUserId, string $newUserId): void
    {
        $connection = $this->connection();
        $connection->beginTransaction();
        try {
            $stmt = $connection->prepare('SELECT id FROM promotions WHERE id = ? FOR UPDATE');
            $stmt->execute([$promotionId]);
            if ($stmt->fetchColumn() === false) {
                throw new InvalidArgumentException('admin_record_missing');
            }
            $stmt = $connection->prepare('SELECT id, role, promotion_id, activation_token FROM users WHERE id IN (?, ?) ORDER BY id FOR UPDATE');
            $stmt->execute([$oldUserId ?? $newUserId, $newUserId]);
            $rows = [];
            foreach ($stmt->fetchAll() as $row) {
                $rows[(string) $row['id']] = $row;
            }
            $old = $oldUserId === null ? null : ($rows[$oldUserId] ?? null);
            $new = $rows[$newUserId] ?? null;
            if (($oldUserId !== null && (!$old || $old['role'] !== 'ambassador' || $old['promotion_id'] !== $promotionId))
                || !$new || $new['role'] !== 'student' || $new['promotion_id'] !== $promotionId || $new['activation_token'] !== null) {
                throw new InvalidArgumentException('admin_invalid_ambassador');
            }
            if ($old !== null) {
                $stmt = $connection->prepare("UPDATE users SET role = 'student' WHERE id = ?");
                $stmt->execute([$oldUserId]);
            }
            $stmt = $connection->prepare("UPDATE users SET role = 'ambassador' WHERE id = ?");
            $stmt->execute([$newUserId]);
            $connection->commit();
        } catch (\Throwable $e) {
            $connection->rollBack();
            throw $e;
        }
    }

    public function deleteForAdmin(string $promotionId, ?UploadStorage $storage = null): void
    {
        $connection = $this->connection();
        $connection->beginTransaction();
        try {
            $stmt = $connection->prepare('SELECT id FROM promotions WHERE id = ? FOR UPDATE');
            $stmt->execute([$promotionId]);
            if ($stmt->fetchColumn() === false) {
                throw new InvalidArgumentException('admin_record_missing');
            }
            $stmt = $connection->prepare('SELECT file_path FROM files WHERE promotion_id = ? FOR UPDATE');
            $stmt->execute([$promotionId]);
            $storage ??= new UploadStorage();
            $paths = $stmt->fetchAll(\PDO::FETCH_COLUMN);
            if ($paths && (!is_dir($storage->directory()) || !is_readable($storage->directory()))) {
                throw new RuntimeException('Upload directory is unavailable.');
            }
            foreach ($paths as $path) {
                $storage->delete((string) $path);
            }
            $stmt = $connection->prepare('DELETE FROM promotions WHERE id = ?');
            $stmt->execute([$promotionId]);
            $connection->commit();
        } catch (\Throwable $e) {
            $connection->rollBack();
            throw $e;
        }
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
