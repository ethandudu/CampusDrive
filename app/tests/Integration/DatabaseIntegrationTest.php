<?php

namespace Tests\Integration;

use PHPUnit\Framework\TestCase;
use Database;

require_once __DIR__ . '/../../utils/db.php';

/**
 * These tests exercise Database against a real MySQL/MariaDB instance using
 * the schema defined in init.sql. They are automatically skipped when no
 * database is reachable (e.g. running "composer test" locally without
 * Docker), and run for real in the GitHub Actions workflow, which starts a
 * MariaDB service and loads init.sql before executing the suite.
 */
final class DatabaseIntegrationTest extends TestCase
{
    protected function setUp(): void
    {
        $host = getenv('DB_HOST') ?: '127.0.0.1';
        $port = (int) (getenv('DB_PORT') ?: 3306);

        $socket = @fsockopen($host, $port, $errno, $errstr, 1);
        if ($socket === false) {
            $this->markTestSkipped("Database not reachable at {$host}:{$port} ({$errstr})");
        }
        fclose($socket);

        $_SESSION = [];
    }

    public function testCreateAndLoginUser(): void
    {
        $email = 'integration_' . bin2hex(random_bytes(4)) . '@example.com';
        $password = password_hash('Secret123!', PASSWORD_DEFAULT);

        $this->assertTrue(Database::createUser($email, $password, null));

        $user = Database::loginUser($email, 'Secret123!');
        $this->assertNotNull($user);
        $this->assertSame($email, $user['email']);
        $this->assertSame('student', $user['role']);
    }

    public function testLoginFailsWithWrongPassword(): void
    {
        $email = 'integration_' . bin2hex(random_bytes(4)) . '@example.com';
        $password = password_hash('Secret123!', PASSWORD_DEFAULT);

        Database::createUser($email, $password, null);

        $this->assertNull(Database::loginUser($email, 'WrongPassword!'));
    }

    public function testPromotionLifecycle(): void
    {
        $email = 'delegate_' . bin2hex(random_bytes(4)) . '@example.com';
        Database::createUser($email, password_hash('Secret123!', PASSWORD_DEFAULT), null);
        $user = Database::loginUser($email, 'Secret123!');

        $_SESSION['user_id'] = $user['id'];

        $promotionId = Database::createPromotion('Integration Test Promotion');
        $this->assertGreaterThan(0, $promotionId);
        $this->assertSame('pending', Database::getPromotionStatus($promotionId));

        $this->assertTrue(Database::attachUserToPromotion((int) $user['id'], $promotionId));
        $this->assertTrue(Database::updatePromotionStatus($promotionId, 'active'));
        $this->assertSame('active', Database::getPromotionStatus($promotionId));

        $details = Database::getPromotionDetails($promotionId);
        $this->assertSame('Integration Test Promotion', $details['name']);
    }

    public function testFolderCreationAndListing(): void
    {
        $email = 'folderowner_' . bin2hex(random_bytes(4)) . '@example.com';
        Database::createUser($email, password_hash('Secret123!', PASSWORD_DEFAULT), null);
        $user = Database::loginUser($email, 'Secret123!');
        $_SESSION['user_id'] = $user['id'];

        $promotionId = Database::createPromotion('Folder Test Promotion');

        $this->assertTrue(Database::createFolder($promotionId, null, 'Root Folder'));

        $folders = Database::getPromotionFolders($promotionId);
        $this->assertNotEmpty($folders);
        $this->assertSame('Root Folder', $folders[0]['name']);
    }
}
