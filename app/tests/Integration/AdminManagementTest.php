<?php

namespace Tests\Integration;

use CampusDrive\Infrastructure\Database\FileRepository;
use CampusDrive\Infrastructure\Database\PromotionRepository;
use CampusDrive\Infrastructure\Database\UserRepository;
use CampusDrive\Infrastructure\Session\SessionPolicy;
use CampusDrive\Infrastructure\Storage\UploadStorage;
use InvalidArgumentException;
use PDO;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class AdminManagementTest extends TestCase
{
    private PDO $db;
    private UserRepository $users;
    private PromotionRepository $promotions;
    private FileRepository $files;
    private array $userIds = [];
    private array $promotionIds = [];
    private string $directory;

    protected function setUp(): void
    {
        $host = getenv('ADMIN_TEST_DB_HOST') ?: (getenv('DB_HOST') ?: '127.0.0.1');
        $socket = @fsockopen($host, 3306, $errno, $errstr, 1);
        if ($socket === false) {
            $this->markTestSkipped("Database not reachable at {$host}:3306 ({$errstr})");
        }
        fclose($socket);
        $this->db = new PDO(
            sprintf('mysql:host=%s;dbname=%s;charset=utf8mb4', $host, getenv('ADMIN_TEST_DB_NAME') ?: DB_NAME),
            getenv('ADMIN_TEST_DB_USER') ?: DB_USER,
            getenv('ADMIN_TEST_DB_PASSWORD') ?: DB_PASSWORD,
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC, PDO::ATTR_EMULATE_PREPARES => false]
        );
        $this->users = new UserRepository($this->db);
        $this->promotions = new PromotionRepository($this->db);
        $this->files = new FileRepository($this->db);
        $this->directory = sys_get_temp_dir() . '/campusdrive_admin_' . bin2hex(random_bytes(6));
        mkdir($this->directory);
    }

    protected function tearDown(): void
    {
        if (!isset($this->db)) {
            return;
        }
        foreach ($this->promotionIds as $id) {
            $stmt = $this->db->prepare('DELETE FROM promotions WHERE id = ?');
            $stmt->execute([$id]);
        }
        foreach ($this->userIds as $id) {
            $this->users->deleteUser($id);
        }
        foreach (glob($this->directory . '/*') ?: [] as $path) {
            unlink($path);
        }
        rmdir($this->directory);
    }

    private function user(string $role = 'student', ?string $promotionId = null): array
    {
        $email = 'admin_test_' . bin2hex(random_bytes(8)) . '@example.com';
        $created = $this->users->createUser($email, password_hash('Password123!', PASSWORD_DEFAULT), $promotionId, $role);
        if ($created[1] !== null) {
            $this->users->activateUser($created[1]);
        }
        $user = $this->users->loginUser($email, 'Password123!')[0];
        $this->userIds[] = (string) $user['id'];
        return $user;
    }

    private function promotion(array $owner): string
    {
        $_SESSION['user_id'] = $owner['id'];
        $id = $this->promotions->createPromotion('Test promotion');
        $this->promotionIds[] = $id;
        return $id;
    }

    private function assertInvalid(string $message, callable $action): void
    {
        try {
            $action();
            $this->fail('Expected invalid argument: ' . $message);
        } catch (InvalidArgumentException $e) {
            $this->assertSame($message, $e->getMessage());
        }
    }

    public function testEmailChangesValidateUniquenessAndInvalidateResetTokens(): void
    {
        $first = $this->user();
        $second = $this->user();
        $hash = hash('sha256', 'token');
        $this->assertSame($first['email'], $this->users->createPasswordResetTokenForUser($first['id'], $hash, time() + 3600));
        $this->assertNull($this->users->createPasswordResetTokenForUser('missing', hash('sha256', 'other'), time() + 3600));
        $this->assertInvalid('invalid_admin_email', fn() => $this->users->updateEmail($first['id'], 'invalid'));
        $this->assertInvalid('admin_email_taken', fn() => $this->users->updateEmail($first['id'], $second['email']));
        $newEmail = 'changed_' . bin2hex(random_bytes(8)) . '@example.com';
        $this->assertTrue($this->users->updateEmail($first['id'], $newEmail));
        $this->assertSame($newEmail, $this->users->getUserDetails($first['id'])['email']);
        $this->assertFalse($this->users->resetPasswordWithToken($hash, 'NewPassword123!'));
        $this->assertInvalid('admin_record_missing', fn() => $this->users->updateEmail('missing', $newEmail));
    }

    public function testAmbassadorMovesAndReplacementsRespectMembership(): void
    {
        $old = $this->user('ambassador');
        $promotion = $this->promotion($old);
        $this->users->updatePromotionForAdmin($old['id'], $promotion, true);
        $other = $this->user('ambassador', $promotion);
        $student = $this->user('student', $promotion);
        $outsider = $this->user();
        $this->assertInvalid('admin_confirm_ambassador_move', fn() => $this->users->updatePromotionForAdmin($old['id'], null, false));
        $this->assertInvalid('admin_record_missing', fn() => $this->users->updatePromotionForAdmin($student['id'], 'missing', false));
        $this->assertInvalid('admin_invalid_ambassador', fn() => $this->promotions->replaceAmbassador($promotion, $old['id'], $outsider['id']));
        $this->promotions->replaceAmbassador($promotion, $old['id'], $student['id']);
        $this->assertSame('student', $this->users->getUserDetails($old['id'])['role']);
        $this->assertSame('ambassador', $this->users->getUserDetails($student['id'])['role']);
        $this->assertSame('ambassador', $this->users->getUserDetails($other['id'])['role']);
        $this->users->updatePromotionForAdmin($student['id'], null, true);
        $this->assertNull($this->users->getUserDetails($student['id'])['promotion_id']);
    }

    public function testDeletionRetainsOrRemovesFilesAsAppropriate(): void
    {
        $owner = $this->user('ambassador');
        $promotion = $this->promotion($owner);
        $this->users->updatePromotionForAdmin($owner['id'], $promotion, true);
        $student = $this->user('student', $promotion);
        $session = ['auth_fingerprint' => SessionPolicy::fingerprint($student['password'])];
        file_put_contents($this->directory . '/test.pdf', 'test');
        $this->files->createFileRecord($student['id'], $promotion, 'test.pdf', 'test.pdf', 'application/pdf');
        $this->assertInvalid('admin_cannot_delete_self', fn() => $this->users->deleteUserForAdmin($student['id'], $student['id']));
        $this->assertTrue($this->users->deleteUserForAdmin($student['id'], $owner['id']));
        $this->assertFalse(SessionPolicy::matchesUser($session, $this->users->getSessionState($student['id'])));
        $pending = $this->files->getPromotionPendingFiles($promotion);
        $this->assertCount(1, $pending);
        $this->assertNull($pending[0]['uploader_email']);
        $this->files->approvePromotionFile($pending[0]['id']);
        $this->assertCount(1, $this->files->getPromotionFolderFiles(null, $promotion)['files']);
        $this->assertCount(1, $this->files->searchFiles('test', $promotion));
        $this->promotions->renameForAdmin($promotion, 'Renamed');
        $this->assertSame('Renamed', $this->promotions->getPromotionDetails($promotion)['name']);
        $this->assertInvalid('admin_invalid_promotion_name', fn() => $this->promotions->renameForAdmin($promotion, ' '));
        try {
            $this->promotions->deleteForAdmin($promotion, new UploadStorage($this->directory . '/missing'));
            $this->fail('Expected storage failure');
        } catch (RuntimeException $e) {
            $this->assertNotNull($this->promotions->getPromotionDetails($promotion));
        }
        $this->promotions->deleteForAdmin($promotion, new UploadStorage($this->directory));
        $this->assertFileDoesNotExist($this->directory . '/test.pdf');
        $this->assertNull($this->promotions->getPromotionDetails($promotion));
        $this->assertNull($this->users->getUserDetails($owner['id'])['promotion_id']);
        $this->assertSame('ambassador', $this->users->getUserDetails($owner['id'])['role']);
    }
}
