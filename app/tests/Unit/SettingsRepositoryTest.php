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

    public function testSavingAnnouncementCreatesMissingSettingsAndSafelyStoresText(): void
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->exec('CREATE TABLE settings (setting_key TEXT PRIMARY KEY, value TEXT NOT NULL)');
        $repository = new SettingsRepository($pdo);

        $repository->updateAnnouncement('  <script>alert("x")</script> & hello  ', true);
        $this->assertSame(
            ['text' => '&lt;script&gt;alert(&quot;x&quot;)&lt;/script&gt; &amp; hello', 'enabled' => true],
            $repository->getAnnouncementDetails()
        );
        $this->assertSame($repository->getAnnouncementDetails()['text'], $repository->getEnabledAnnouncement());

        $repository->updateAnnouncement('New text', false);
        $this->assertSame(['text' => 'New text', 'enabled' => false], $repository->getAnnouncementDetails());
        $this->assertNull($repository->getEnabledAnnouncement());
        $repository->updateAnnouncement('New text', false);
        $this->assertSame(2, (int) $pdo->query('SELECT COUNT(*) FROM settings')->fetchColumn());
        $repository->updateAnnouncement('  ', true);
        $this->assertNull($repository->getEnabledAnnouncement());
    }

    public function testOversizedAnnouncementDoesNotChangeStoredValues(): void
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->exec('CREATE TABLE settings (setting_key TEXT PRIMARY KEY, value TEXT NOT NULL)');
        $repository = new SettingsRepository($pdo);
        $repository->updateAnnouncement('Original', false);

        try {
            $repository->updateAnnouncement(str_repeat('x', 10001), true);
            $this->fail('Expected announcement length validation.');
        } catch (\InvalidArgumentException $e) {
            $this->assertSame('announcement_too_long', $e->getMessage());
        }
        $this->assertSame(['text' => 'Original', 'enabled' => false], $repository->getAnnouncementDetails());
        $repository->updateAnnouncement(str_repeat('&', 10000), true);
        $this->assertSame(str_repeat('&amp;', 10000), $repository->getEnabledAnnouncement());
    }

    public function testFailedUpdateRollsBackBothSettings(): void
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->exec('CREATE TABLE settings (setting_key TEXT PRIMARY KEY, value TEXT NOT NULL)');
        $repository = new SettingsRepository($pdo);
        $repository->updateAnnouncement('Original', false);
        $pdo->exec("CREATE TRIGGER reject_enabled BEFORE UPDATE ON settings WHEN NEW.setting_key = 'announcement_enabled' BEGIN SELECT RAISE(FAIL, 'test failure'); END");
        try {
            $repository->updateAnnouncement('Changed', true);
            $this->fail('Expected database failure.');
        } catch (\PDOException $e) {
            $this->assertStringContainsString('test failure', $e->getMessage());
        }
        $this->assertSame(['text' => 'Original', 'enabled' => false], $repository->getAnnouncementDetails());
    }
}
