<?php

namespace CampusDrive\Infrastructure\Database;

final class SettingsRepository extends DatabaseRepository
{
    public function getAnnouncementDetails(): array
    {
        $stmt = $this->connection()->prepare(
            "SELECT setting_key, value FROM settings WHERE setting_key IN ('announcement_enabled', 'announcement_text')"
        );
        $stmt->execute();
        $settings = $stmt->fetchAll(\PDO::FETCH_KEY_PAIR);

        return [
            'text' => $settings['announcement_text'] ?? '',
            'enabled' => ($settings['announcement_enabled'] ?? 'false') === 'true',
        ];
    }

    public function getEnabledAnnouncement(): ?string
    {
        $settings = $this->getAnnouncementDetails();
        $announcement = trim($settings['text']);
        if (!$settings['enabled'] || $announcement === '') {
            return null;
        }

        return $announcement;
    }

    public function updateAnnouncement(string $text, bool $enabled): void
    {
        $text = trim($text);
        if (mb_strlen($text, 'UTF-8') > 10000) {
            throw new \InvalidArgumentException('announcement_too_long');
        }
        $text = htmlspecialchars($text, ENT_QUOTES, 'UTF-8');

        $connection = $this->connection();
        $connection->beginTransaction();
        try {
            foreach (['announcement_text' => $text, 'announcement_enabled' => $enabled ? 'true' : 'false'] as $key => $value) {
                $stmt = $connection->prepare('UPDATE settings SET value = ? WHERE setting_key = ?');
                $stmt->execute([$value, $key]);
                if ($stmt->rowCount() === 0) {
                    $exists = $connection->prepare('SELECT 1 FROM settings WHERE setting_key = ?');
                    $exists->execute([$key]);
                    if ($exists->fetchColumn() === false) {
                        $insert = $connection->prepare('INSERT INTO settings (setting_key, value) VALUES (?, ?)');
                        $insert->execute([$key, $value]);
                    }
                }
            }
            $connection->commit();
        } catch (\Throwable $e) {
            $connection->rollBack();
            throw $e;
        }
    }
}
