<?php

namespace Tests\Unit;

use CampusDrive\Infrastructure\Database\UserRepository;
use PDO;
use PHPUnit\Framework\TestCase;

final class PasswordResetRepositoryTest extends TestCase
{
    private PDO $pdo;
    private UserRepository $users;

    protected function setUp(): void
    {
        if (!in_array('sqlite', PDO::getAvailableDrivers(), true)) {
            $this->markTestSkipped('pdo_sqlite is not available.');
        }

        $this->pdo = new PDO('sqlite::memory:', null, null, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
        $this->pdo->exec('CREATE TABLE users (id TEXT PRIMARY KEY, email TEXT, password TEXT)');
        $this->pdo->exec(
            'CREATE TABLE password_reset_tokens (
                token_hash TEXT PRIMARY KEY,
                user_id TEXT NOT NULL,
                expires_at INTEGER NOT NULL,
                created_at TEXT DEFAULT CURRENT_TIMESTAMP
            )'
        );
        $this->pdo->exec("INSERT INTO users (id, email, password) VALUES ('user-1', 'user@example.com', 'old-hash')");
        $this->users = new UserRepository($this->pdo);
    }

    public function testUnknownEmailDoesNotCreateResetToken(): void
    {
        $this->assertNull($this->users->createPasswordResetToken('unknown@example.com', str_repeat('a', 64), time() + 3600));
        $this->assertSame(0, (int) $this->pdo->query('SELECT COUNT(*) FROM password_reset_tokens')->fetchColumn());
    }

    public function testNewRequestInvalidatesThePreviousResetToken(): void
    {
        $this->users->createPasswordResetToken('user@example.com', str_repeat('a', 64), time() + 3600);
        $this->users->createPasswordResetToken('user@example.com', str_repeat('b', 64), time() + 3600);

        $this->assertSame(1, (int) $this->pdo->query('SELECT COUNT(*) FROM password_reset_tokens')->fetchColumn());
        $this->assertSame(str_repeat('b', 64), $this->pdo->query('SELECT token_hash FROM password_reset_tokens')->fetchColumn());
    }

    public function testResetConsumesTokenAndChangesPasswordOnce(): void
    {
        $this->users->createPasswordResetToken('user@example.com', str_repeat('a', 64), time() + 3600);

        $this->assertTrue($this->users->resetPasswordWithToken(str_repeat('a', 64), 'NewSecret123!'));
        $this->assertFalse($this->users->resetPasswordWithToken(str_repeat('a', 64), 'AnotherSecret123!'));

        $storedPassword = $this->pdo->query("SELECT password FROM users WHERE id = 'user-1'")->fetchColumn();
        $this->assertTrue(password_verify('NewSecret123!', $storedPassword));
        $this->assertSame(0, (int) $this->pdo->query('SELECT COUNT(*) FROM password_reset_tokens')->fetchColumn());
    }

    public function testExpiredTokenCannotResetPassword(): void
    {
        $this->users->createPasswordResetToken('user@example.com', str_repeat('a', 64), time() - 1);

        $this->assertFalse($this->users->resetPasswordWithToken(str_repeat('a', 64), 'NewSecret123!'));
        $this->assertSame('old-hash', $this->pdo->query("SELECT password FROM users WHERE id = 'user-1'")->fetchColumn());
    }
}
