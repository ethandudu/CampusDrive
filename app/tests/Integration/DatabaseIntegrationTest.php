<?php

namespace Tests\Integration;

use PHPUnit\Framework\TestCase;
use CampusDrive\Infrastructure\Database\DatabaseConnection;
use CampusDrive\Infrastructure\Database\FileRepository;
use CampusDrive\Infrastructure\Database\PromotionRepository;
use CampusDrive\Infrastructure\Database\UserRepository;

require_once __DIR__ . '/../../utils/db.php';

/**
 * These tests exercise the database repositories against a real MySQL/MariaDB
 * instance using the schema defined in init.sql. They are skipped when no
 * database is reachable (e.g. running "composer test" locally without
 * Docker), and run for real in the GitHub Actions workflow, which starts a
 * MariaDB service and loads init.sql before executing the suite.
 */
final class DatabaseIntegrationTest extends TestCase
{
    private UserRepository $users;
    private PromotionRepository $promotions;
    private FileRepository $files;

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
        $pdo = DatabaseConnection::getConnection();
        $this->users = new UserRepository($pdo);
        $this->promotions = new PromotionRepository($pdo);
        $this->files = new FileRepository($pdo);
    }

    public function testCreateAndLoginUser(): void
    {
        $email = 'integration_' . bin2hex(random_bytes(4)) . '@example.com';
        $password = password_hash('Secret123!', PASSWORD_DEFAULT);

        $this->assertTrue($this->users->createUser($email, $password, null, 'student'));

        $user = $this->users->loginUser($email, 'Secret123!');
        $this->assertNotNull($user);
        $this->assertSame($email, $user['email']);
        $this->assertSame('student', $user['role']);
    }

    public function testLoginFailsWithWrongPassword(): void
    {
        $email = 'integration_' . bin2hex(random_bytes(4)) . '@example.com';
        $password = password_hash('Secret123!', PASSWORD_DEFAULT);

        $this->users->createUser($email, $password, null, 'student');

        $this->assertNull($this->users->loginUser($email, 'WrongPassword!'));
    }

    public function testPromotionLifecycle(): void
    {
        $email = 'delegate_' . bin2hex(random_bytes(4)) . '@example.com';
        $this->users->createUser($email, password_hash('Secret123!', PASSWORD_DEFAULT), null, 'delegate');
        $user = $this->users->loginUser($email, 'Secret123!');

        $_SESSION['user_id'] = $user['id'];

        $promotionId = $this->promotions->createPromotion('Integration Test Promotion');
        $this->assertMatchesRegularExpression(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-[1-5][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i',
            $promotionId
        );
        $this->assertSame('pending', $this->promotions->getPromotionStatus($promotionId));

        $this->assertTrue($this->promotions->updatePromotionStatus($promotionId, 'active'));
        $this->assertSame('active', $this->promotions->getPromotionStatus($promotionId));

        $details = $this->promotions->getPromotionDetails($promotionId);
        $this->assertSame('Integration Test Promotion', $details['name']);
    }

    public function testFolderCreationAndListing(): void
    {
        $email = 'folderowner_' . bin2hex(random_bytes(4)) . '@example.com';
        $this->users->createUser($email, password_hash('Secret123!', PASSWORD_DEFAULT), null, 'delegate');
        $user = $this->users->loginUser($email, 'Secret123!');
        $_SESSION['user_id'] = $user['id'];

        $promotionId = $this->promotions->createPromotion('Folder Test Promotion');

        $folderId = $this->files->createFolder($promotionId, null, 'Root Folder');
        $this->assertIsString($folderId);
        $this->assertMatchesRegularExpression(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-[1-5][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i',
            $folderId
        );

        $folders = $this->files->getPromotionFolders($promotionId);
        $this->assertNotEmpty($folders);
        $this->assertSame('Root Folder', $folders[0]['name']);
    }
}
