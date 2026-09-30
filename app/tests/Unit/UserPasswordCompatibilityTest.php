<?php

namespace Tests\Unit;

use CampusDrive\Infrastructure\Database\InputSanitizer;
use CampusDrive\Infrastructure\Database\UserRepository;
use CampusDrive\Infrastructure\Session\SessionPolicy;
use PDO;
use PHPUnit\Framework\TestCase;

/**
 * Login and password change used to hash the HTML-encoded password whereas registration
 * hashed the raw one. Both kinds of hashes must keep working, without encoding anymore.
 */
final class UserPasswordCompatibilityTest extends TestCase
{
    private const SPECIAL_PASSWORD = 'Pass&word<1>"x\'!';

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
        $this->pdo->exec('CREATE TABLE users (id TEXT PRIMARY KEY, email TEXT, password TEXT, role TEXT, promotion_id TEXT)');
        $this->users = new UserRepository($this->pdo);
    }

    private function insertUser(string $id, string $hash): void
    {
        $this->pdo->prepare('INSERT INTO users (id, email, password, role) VALUES (?, ?, ?, ?)')
            ->execute([$id, $id . '@example.com', $hash, 'student']);
    }

    private function storedHash(string $id): string
    {
        $stmt = $this->pdo->prepare('SELECT password FROM users WHERE id = ?');
        $stmt->execute([$id]);

        return (string) $stmt->fetchColumn();
    }

    public function testUserRegisteredWithSpecialCharactersCanLogIn(): void
    {
        $this->insertUser('registered', password_hash(self::SPECIAL_PASSWORD, PASSWORD_DEFAULT));

        $user = $this->users->loginUser('registered@example.com', self::SPECIAL_PASSWORD);

        $this->assertNotNull($user);
    }

    public function testLegacyEncodedHashStillWorksAndIsMigratedToTheRawPassword(): void
    {
        $legacyHash = password_hash(InputSanitizer::sanitize(self::SPECIAL_PASSWORD), PASSWORD_DEFAULT);
        $this->insertUser('legacy', $legacyHash);

        $user = $this->users->loginUser('legacy@example.com', self::SPECIAL_PASSWORD);

        $this->assertNotNull($user);
        $stored = $this->storedHash('legacy');
        $this->assertNotSame($legacyHash, $stored);
        $this->assertTrue(password_verify(self::SPECIAL_PASSWORD, $stored));
        $this->assertSame($stored, $user['password']);
    }

    public function testMigratedLoginKeepsTheSessionFingerprintValid(): void
    {
        $this->insertUser('legacy', password_hash(InputSanitizer::sanitize(self::SPECIAL_PASSWORD), PASSWORD_DEFAULT));

        $user = $this->users->loginUser('legacy@example.com', self::SPECIAL_PASSWORD);
        $session = ['auth_fingerprint' => SessionPolicy::fingerprint($user['password'])];

        $this->assertTrue(SessionPolicy::matchesUser($session, $this->users->getSessionState('legacy')));
    }

    public function testLegacyHashOfATrimmedPasswordStillWorks(): void
    {
        $this->insertUser('trimmed', password_hash(InputSanitizer::sanitize('  Abc&1234!  '), PASSWORD_DEFAULT));

        $this->assertNotNull($this->users->loginUser('trimmed@example.com', '  Abc&1234!  '));
    }

    public function testWrongPasswordIsRejectedAndTheHashIsLeftUntouched(): void
    {
        $hash = password_hash(InputSanitizer::sanitize(self::SPECIAL_PASSWORD), PASSWORD_DEFAULT);
        $this->insertUser('legacy', $hash);

        $this->assertNull($this->users->loginUser('legacy@example.com', 'Wrong&Password1!'));
        $this->assertSame($hash, $this->storedHash('legacy'));
    }

    public function testUnknownEmailIsRejected(): void
    {
        $this->assertNull($this->users->loginUser('nobody@example.com', self::SPECIAL_PASSWORD));
    }

    public function testNewPasswordIsStoredRawAndCanBeUsedToLogIn(): void
    {
        $this->insertUser('changer', password_hash('OldSecret123!', PASSWORD_DEFAULT));

        $this->assertTrue($this->users->updateUserPassword('changer', 'OldSecret123!', self::SPECIAL_PASSWORD));

        $stored = $this->storedHash('changer');
        $this->assertTrue(password_verify(self::SPECIAL_PASSWORD, $stored));
        $this->assertFalse(password_verify(InputSanitizer::sanitize(self::SPECIAL_PASSWORD), $stored));
        $this->assertNotNull($this->users->loginUser('changer@example.com', self::SPECIAL_PASSWORD));
        $this->assertNull($this->users->loginUser('changer@example.com', 'OldSecret123!'));
    }

    public function testCurrentPasswordMayStillBeInLegacyForm(): void
    {
        $this->insertUser('legacy', password_hash(InputSanitizer::sanitize(self::SPECIAL_PASSWORD), PASSWORD_DEFAULT));

        $this->assertTrue($this->users->updateUserPassword('legacy', self::SPECIAL_PASSWORD, 'NewSecret456!'));
        $this->assertNotNull($this->users->loginUser('legacy@example.com', 'NewSecret456!'));
    }

    public function testVerifyPasswordAcceptsTheRawAndTheLegacyEncodedForms(): void
    {
        $this->insertUser('raw', password_hash(self::SPECIAL_PASSWORD, PASSWORD_DEFAULT));
        $this->insertUser('legacy', password_hash(InputSanitizer::sanitize(self::SPECIAL_PASSWORD), PASSWORD_DEFAULT));

        $this->assertTrue($this->users->verifyPassword('raw', self::SPECIAL_PASSWORD));
        $this->assertTrue($this->users->verifyPassword('legacy', self::SPECIAL_PASSWORD));
        $this->assertFalse($this->users->verifyPassword('raw', 'Wrong&Password1!'));
        $this->assertFalse($this->users->verifyPassword('legacy', ''));
        $this->assertFalse($this->users->verifyPassword('unknown', self::SPECIAL_PASSWORD));
    }

    public function testWrongCurrentPasswordDoesNotChangeAnything(): void
    {
        $hash = password_hash('OldSecret123!', PASSWORD_DEFAULT);
        $this->insertUser('changer', $hash);

        $this->assertFalse($this->users->updateUserPassword('changer', 'Nope&123!', 'NewSecret456!'));
        $this->assertSame($hash, $this->storedHash('changer'));
    }
}
