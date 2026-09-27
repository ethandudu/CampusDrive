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

    /**
     * Scans every PHP source file (excluding vendor/tests) for calls to t('some_key')
     * with a literal string argument, and asserts each referenced key actually exists
     * in the default locale's translation map, so a typo/rename never silently falls
     * back to displaying the raw key to users.
     */
    public function testEveryLiteralTranslationCallUsesAnExistingKey(): void
    {
        $appDir = __DIR__ . '/../..';
        $referenceKeys = array_keys(translations()[DEFAULT_LOCALE]);

        $usedKeys = [];
        foreach ($this->phpSourceFiles($appDir) as $file) {
            $content = file_get_contents($file);
            if (preg_match_all('/\bt\(\s*([\'"])([a-zA-Z0-9_]+)\1/', $content, $matches)) {
                foreach ($matches[2] as $key) {
                    $usedKeys[$key][] = $file;
                }
            }
        }

        $this->assertNotEmpty($usedKeys, 'Expected to find at least one call to t() with a literal key in the app source.');

        $missing = [];
        foreach ($usedKeys as $key => $files) {
            if (!in_array($key, $referenceKeys, true)) {
                $missing[] = sprintf('"%s" (used in %s)', $key, implode(', ', array_unique($files)));
            }
        }

        $this->assertSame(
            [],
            $missing,
            "The following t() calls reference translation keys that do not exist:\n" . implode("\n", $missing)
        );
    }

    /**
     * @return iterable<string>
     */
    private function phpSourceFiles(string $dir): iterable
    {
        $excluded = ['vendor', 'tests', 'uploads', '.phpunit.cache'];

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveCallbackFilterIterator(
                new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS),
                function (\SplFileInfo $file) use ($excluded) {
                    if ($file->isDir()) {
                        return !in_array($file->getFilename(), $excluded, true);
                    }
                    return true;
                }
            )
        );

        foreach ($iterator as $file) {
            /** @var \SplFileInfo $file */
            if ($file->isFile() && $file->getExtension() === 'php') {
                yield $file->getPathname();
            }
        }
    }
}
