<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../utils/i18n.php';

final class I18nTest extends TestCase
{
    protected function setUp(): void
    {
        $_SESSION = [];
    }

    public function testDefaultLocaleIsFrenchWhenSessionIsEmpty(): void
    {
        $this->assertSame('fr', locale());
    }

    public function testLocaleFallsBackToDefaultForUnsupportedValue(): void
    {
        $_SESSION['locale'] = 'de';
        $this->assertSame('fr', locale());
    }

    public function testLocaleReturnsSupportedSessionValue(): void
    {
        $_SESSION['locale'] = 'en';
        $this->assertSame('en', locale());
    }

    public function testTranslationExistsForBothSupportedLocales(): void
    {
        $_SESSION['locale'] = 'fr';
        $this->assertSame('Connexion', t('login'));

        $_SESSION['locale'] = 'en';
        $this->assertSame('Sign in', t('login'));
    }

    public function testTranslationFallsBackToKeyWhenMissing(): void
    {
        $this->assertSame('this_key_does_not_exist', t('this_key_does_not_exist'));
    }

    public function testTranslationAppliesReplacements(): void
    {
        $_SESSION['locale'] = 'en';
        $this->assertSame(
            'Create a folder in "Documents"',
            t('create_folder_in', ['name' => 'Documents'])
        );
    }
}
