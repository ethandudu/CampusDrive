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
     * Every `$_SERVER['REQUEST_METHOD'] === 'POST'` handler must be covered by a
     * server-side CSRF check: either its own hash_equals() right after the
     * condition, or an earlier handler that only tests the request method and
     * validates the token for everything that follows.
     *
     * @dataProvider postFormFileProvider
     */
    public function testEveryPostHandlerIsCoveredByACsrfCheck(string $file): void
    {
        $contents = file_get_contents($file);
        $this->assertNotFalse($contents, "Unable to read {$file}");

        $marker = "\$_SERVER['REQUEST_METHOD'] === 'POST'";
        $offset = 0;
        $centralCheckSeen = false;

        while (($position = strpos($contents, $marker, $offset)) !== false) {
            $offset = $position + strlen($marker);
            $next = strpos($contents, $marker, $offset);
            $length = $next === false ? 400 : min(400, $next - $position);
            $window = substr($contents, $position, $length);

            $hasCheck = preg_match('/hash_equals\s*\(\s*\$_SESSION\s*\[\s*[\'"]csrf_token[\'"]\s*\]/', $window) === 1;
            $onlyTestsMethod = preg_match('/^' . preg_quote($marker, '/') . '\s*\)\s*\{/', $window) === 1;

            if ($hasCheck && $onlyTestsMethod) {
                $centralCheckSeen = true;
            }

            $this->assertTrue(
                $centralCheckSeen || $hasCheck,
                sprintf(
                    'A POST handler in %s (offset %d) is not protected by a csrf_token check.',
                    basename($file),
                    $position
                )
            );
        }

        $this->addToAssertionCount(1);
    }

    public function testLogoutRequiresAPostRequestWithAValidToken(): void
    {
        $logout = file_get_contents(dirname(__DIR__, 2) . '/logout.php');
        $this->assertNotFalse($logout);

        $this->assertStringContainsString("\$_SERVER['REQUEST_METHOD'] === 'POST'", $logout);
        $this->assertMatchesRegularExpression('/hash_equals\s*\(\s*\$_SESSION\s*\[\s*[\'"]csrf_token[\'"]\s*\]/', $logout);

        foreach (glob(dirname(__DIR__, 2) . '/*.php') ?: [] as $file) {
            $this->assertStringNotContainsString(
                'href="logout.php"',
                (string) file_get_contents($file),
                basename($file) . ' must not link to logout.php with a GET request.'
            );
        }
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
