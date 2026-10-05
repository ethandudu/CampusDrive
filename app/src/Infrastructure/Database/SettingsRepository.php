<?php

namespace CampusDrive\Infrastructure\Database;

final class SettingsRepository extends DatabaseRepository
{
    public function getEnabledAnnouncement(): ?string
    {
        $stmt = $this->connection()->prepare(
            "SELECT setting_key, value FROM settings WHERE setting_key IN ('announcement_enabled', 'announcement_text')"
        );
        $stmt->execute();
        $settings = $stmt->fetchAll(\PDO::FETCH_KEY_PAIR);

        $announcement = trim($settings['announcement_text'] ?? '');
        if (($settings['announcement_enabled'] ?? 'false') !== 'true' || $announcement === '') {
            return null;
        }

        return $announcement;
    }
}
