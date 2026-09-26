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

    public function testNoMissingTranslationKeysAcrossSupportedLocales(): void
    {
        $translations = translations();

        $this->assertSame(
            array_keys(SUPPORTED_LOCALES),
            array_keys($translations),
            'The translations map must define an entry for every supported locale.'
        );

        $referenceKeys = array_keys($translations[DEFAULT_LOCALE]);
        $this->assertNotEmpty($referenceKeys, 'The default locale must define at least one translation key.');

        foreach ($translations as $locale => $keys) {
            $localeKeys = array_keys($keys);

            $missing = array_diff($referenceKeys, $localeKeys);
            $this->assertSame(
                [],
                $missing,
                sprintf('Locale "%s" is missing translation keys: %s', $locale, implode(', ', $missing))
            );

            $extra = array_diff($localeKeys, $referenceKeys);
            $this->assertSame(
                [],
                $extra,
                sprintf('Locale "%s" has extra translation keys not present in "%s": %s', $locale, DEFAULT_LOCALE, implode(', ', $extra))
            );
        }
    }

    public function testNoEmptyTranslationValues(): void
    {
        foreach (translations() as $locale => $keys) {
            foreach ($keys as $key => $value) {
                $this->assertNotSame(
                    '',
                    trim($value),
                    sprintf('Locale "%s" has an empty translation for key "%s".', $locale, $key)
                );
            }
        }
    }
}
