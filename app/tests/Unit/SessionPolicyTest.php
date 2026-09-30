<?php

namespace Tests\Unit;

use CampusDrive\Infrastructure\Session\SessionPolicy;
use PHPUnit\Framework\TestCase;

final class SessionPolicyTest extends TestCase
{
    private const NOW = 1_800_000_000;

    public function testFingerprintChangesWithThePasswordHash(): void
    {
        $this->assertSame(SessionPolicy::fingerprint('hash-1'), SessionPolicy::fingerprint('hash-1'));
        $this->assertNotSame(SessionPolicy::fingerprint('hash-1'), SessionPolicy::fingerprint('hash-2'));
    }

    public function testSessionMatchesItsUserUntilThePasswordChanges(): void
    {
        $session = ['auth_fingerprint' => SessionPolicy::fingerprint('hash-1')];

        $this->assertTrue(SessionPolicy::matchesUser($session, ['password' => 'hash-1']));
        $this->assertFalse(SessionPolicy::matchesUser($session, ['password' => 'hash-2']));
    }

    public function testSessionDoesNotMatchADeletedUserOrALegacySession(): void
    {
        $this->assertFalse(SessionPolicy::matchesUser(['auth_fingerprint' => SessionPolicy::fingerprint('hash-1')], null));
        $this->assertFalse(SessionPolicy::matchesUser([], ['password' => 'hash-1']));
    }

    public function testFreshSessionIsNotExpired(): void
    {
        $session = ['auth_time' => self::NOW - 60, 'last_activity' => self::NOW - 10];

        $this->assertFalse(SessionPolicy::isExpired($session, self::NOW));
    }

    public function testSessionExpiresAfterTheIdleTimeout(): void
    {
        $session = ['auth_time' => self::NOW - 7200, 'last_activity' => self::NOW - SessionPolicy::IDLE_TIMEOUT - 1];

        $this->assertTrue(SessionPolicy::isExpired($session, self::NOW));
    }

    public function testSessionExpiresAfterTheAbsoluteTimeoutEvenWhenActive(): void
    {
        $session = ['auth_time' => self::NOW - SessionPolicy::ABSOLUTE_TIMEOUT - 1, 'last_activity' => self::NOW];

        $this->assertTrue(SessionPolicy::isExpired($session, self::NOW));
    }

    public function testSessionWithoutTimestampsIsExpired(): void
    {
        $this->assertTrue(SessionPolicy::isExpired(['user_id' => 'abc'], self::NOW));
    }

    /**
     * @return array<string, array{0: array<string, string>, 1: ?bool, 2: bool}>
     */
    public static function secureCookieProvider(): array
    {
        return [
            'plain http' => [[], null, false],
            'https' => [['HTTPS' => 'on'], null, true],
            'https off' => [['HTTPS' => 'off'], null, false],
            'behind a TLS proxy' => [['HTTP_X_FORWARDED_PROTO' => 'https'], null, true],
            'proxy chain' => [['HTTP_X_FORWARDED_PROTO' => 'https, http'], null, true],
            'proxy over http' => [['HTTP_X_FORWARDED_PROTO' => 'http'], null, false],
            'forced on' => [[], true, true],
            'forced off' => [['HTTPS' => 'on'], false, false],
        ];
    }

    /**
     * @dataProvider secureCookieProvider
     * @param array<string, string> $server
     */
    public function testSecureCookieDetection(array $server, ?bool $configured, bool $expected): void
    {
        $this->assertSame($expected, SessionPolicy::shouldUseSecureCookie($server, $configured));
    }

    public function testSessionBootstrapHardensTheCookie(): void
    {
        $contents = (string) file_get_contents(dirname(__DIR__, 2) . '/utils/session.php');

        $this->assertStringContainsString("'httponly' => true", $contents);
        $this->assertStringContainsString("'samesite' => 'Lax'", $contents);
        $this->assertStringContainsString("'session.use_strict_mode', '1'", $contents);
        $this->assertStringContainsString('session_regenerate_id(true)', $contents);
    }

    public function testLoginAndPasswordChangeRotateTheSession(): void
    {
        $root = dirname(__DIR__, 2);

        $this->assertStringContainsString('startAuthenticatedSession($user)', (string) file_get_contents($root . '/login.php'));
        $this->assertStringContainsString('startAuthenticatedSession(', (string) file_get_contents($root . '/settings.php'));
    }

    public function testSessionsAreDestroyedCompletely(): void
    {
        $root = dirname(__DIR__, 2);

        foreach (['logout.php', 'settings.php'] as $file) {
            $contents = (string) file_get_contents($root . '/' . $file);
            $this->assertStringContainsString('destroySession()', $contents, $file);
            $this->assertStringNotContainsString('session_destroy()', $contents, $file);
        }
    }

    public function testPasswordChangeNeverEchoesSensitiveData(): void
    {
        $contents = (string) file_get_contents(dirname(__DIR__, 2) . '/src/Infrastructure/Database/UserRepository.php');

        $this->assertDoesNotMatchRegularExpression('/\b(echo|print_r|var_dump)\b/', $contents);
    }
}
