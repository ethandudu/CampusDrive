<?php

namespace Tests\Unit;

use CampusDrive\Infrastructure\Database\SettingsRepository;
use PDO;
use PHPUnit\Framework\TestCase;

final class SettingsRepositoryTest extends TestCase
{
    public function testAnnouncementIsReturnedOnlyWhenEnabledAndNonEmpty(): void
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->exec('CREATE TABLE settings (setting_key TEXT PRIMARY KEY, value TEXT NOT NULL)');
        $repository = new SettingsRepository($pdo);

        $this->assertNull($repository->getEnabledAnnouncement());

        $pdo->exec("INSERT INTO settings (setting_key, value) VALUES ('announcement_text', 'Important update')");
        $pdo->exec("INSERT INTO settings (setting_key, value) VALUES ('announcement_enabled', 'false')");
        $this->assertNull($repository->getEnabledAnnouncement());

        $pdo->exec("UPDATE settings SET value = 'true' WHERE setting_key = 'announcement_enabled'");
        $this->assertSame('Important update', $repository->getEnabledAnnouncement());

        $pdo->exec("UPDATE settings SET value = '   ' WHERE setting_key = 'announcement_text'");
        $this->assertNull($repository->getEnabledAnnouncement());
    }
}
