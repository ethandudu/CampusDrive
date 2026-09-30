<?php

namespace Tests\Unit;

use CampusDrive\Infrastructure\Security\PasswordPolicy;
use PHPUnit\Framework\TestCase;

final class PasswordPolicyTest extends TestCase
{
    /**
     * @return array<string, array{0: string, 1: ?string}>
     */
    public static function passwordProvider(): array
    {
        return [
            'valid' => ['Secret123!', null],
            'valid with html characters' => ['Pass&word<1>"x\'!', null],
            'empty' => ['', 'password_length'],
            'too short' => ['Ab1!xyz', 'password_length'],
            'minimum length' => ['Abcdef1!', null],
            'maximum length' => [str_repeat('a', 60) . 'A1!b', null],
            'too long' => [str_repeat('a', 61) . 'A1!b', 'password_length'],
            'no uppercase' => ['secret123!', 'password_uppercase'],
            'no lowercase' => ['SECRET123!', 'password_lowercase'],
            'no digit' => ['Secret!!!!', 'password_number'],
            'no special character' => ['Secret1234', 'password_special'],
        ];
    }

    /**
     * @dataProvider passwordProvider
     */
    public function testViolation(string $password, ?string $expected): void
    {
        $this->assertSame($expected, PasswordPolicy::violation($password));
    }

    public function testEveryViolationIsATranslationKey(): void
    {
        require_once __DIR__ . '/../../utils/i18n.php';

        foreach (['password_length', 'password_uppercase', 'password_lowercase', 'password_number', 'password_special'] as $key) {
            foreach (translations() as $locale => $keys) {
                $this->assertArrayHasKey($key, $keys, "Missing {$key} in {$locale}");
            }
        }
    }

    public function testRegistrationAndPasswordChangeUseTheServerSidePolicy(): void
    {
        $root = dirname(__DIR__, 2);

        foreach (['register.php', 'settings.php'] as $file) {
            $this->assertStringContainsString(
                'PasswordPolicy::violation(',
                (string) file_get_contents($root . '/' . $file),
                $file
            );
        }
    }
}
