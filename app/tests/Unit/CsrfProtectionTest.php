<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * Static analysis test ensuring every POST form in the application is
 * protected against CSRF: each <form method="POST"> must emit a hidden
 * csrf_token field, and the page must validate it server-side before
 * processing the submission.
 */
final class CsrfProtectionTest extends TestCase
{
    /**
     * @return array<string, array{0: string}>
     */
    public static function postFormFileProvider(): array
    {
        $root = dirname(__DIR__, 2);
        $files = glob($root . '/*.php') ?: [];

        $cases = [];
        foreach ($files as $file) {
            $cases[basename($file)] = [$file];
        }

        return $cases;
    }

    /**
     * @dataProvider postFormFileProvider
     */
    public function testEveryPostFormHasCsrfTokenField(string $file): void
    {
        $contents = file_get_contents($file);
        $this->assertNotFalse($contents, "Unable to read {$file}");

        $formCount = preg_match_all('/<form\b[^>]*>/i', $contents, $formMatches);

        if ($formCount === 0) {
            $this->assertTrue(true, 'No <form> tags in ' . basename($file));
            return;
        }

        foreach ($formMatches[0] as $index => $formTag) {
            $isPost = preg_match('/method\s*=\s*["\']?post["\']?/i', $formTag) === 1;

            if (!$isPost) {
                // GET forms (e.g. search boxes) don't need CSRF protection.
                continue;
            }

            $formBody = $this->extractFormBody($contents, $formTag);

            $this->assertMatchesRegularExpression(
                '/name\s*=\s*["\']csrf_token["\']/i',
                $formBody,
                sprintf(
                    'Form #%d (method POST) in %s is missing a hidden csrf_token field.',
                    $index + 1,
                    basename($file)
                )
            );
        }
    }

    /**
     * @dataProvider postFormFileProvider
     */
    public function testFilesWithPostFormsValidateCsrfTokenServerSide(string $file): void
    {
        $contents = file_get_contents($file);
        $this->assertNotFalse($contents, "Unable to read {$file}");

        $hasPostForm = preg_match_all('/<form\b[^>]*>/i', $contents, $formMatches) > 0
            && array_reduce(
                $formMatches[0],
                fn (bool $carry, string $tag) => $carry || preg_match('/method\s*=\s*["\']?post["\']?/i', $tag) === 1,
                false
            );

        if (!$hasPostForm) {
            $this->assertTrue(true, 'No POST forms in ' . basename($file));
            return;
        }

        $validatesCsrf = preg_match('/\$_SESSION\s*\[\s*[\'"]csrf_token[\'"]\s*\]/', $contents) === 1
            && preg_match('/hash_equals\s*\(/', $contents) === 1;

        $this->assertTrue(
            $validatesCsrf,
            sprintf(
                '%s contains a POST form but does not appear to validate the csrf_token server-side '
                . '(expected a check against $_SESSION[\'csrf_token\'] using hash_equals()).',
                basename($file)
            )
        );
    }

    /**
     * Extracts the markup of a single form (from its opening tag to the
     * matching closing </form>), used to scope the csrf_token search to the
     * specific form rather than the whole file.
     */
    private function extractFormBody(string $contents, string $formTag): string
    {
        $start = strpos($contents, $formTag);
        if ($start === false) {
            return '';
        }

        $end = strpos($contents, '</form>', $start);
        if ($end === false) {
            return substr($contents, $start);
        }

        return substr($contents, $start, $end - $start);
    }
}
