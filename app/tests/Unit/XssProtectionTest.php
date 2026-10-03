<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../utils/emailTemplates/welcome.php';

final class XssProtectionTest extends TestCase
{
    public function testWelcomeEmailEscapesTheEmailAddress(): void
    {
        $email = '"<img src=x onerror=alert(1)>"@example.com';

        $body = welcomeEmailTemplate($email)['body'];

        $this->assertStringNotContainsString('<img src=x', $body);
        $this->assertStringContainsString('&lt;img src=x onerror=alert(1)&gt;', $body);
    }

    public function testEmailLayoutIncludesTheEmbeddedCampusDriveLogoAtTheTop(): void
    {
        $body = welcomeEmailTemplate('student@example.com')['body'];

        $this->assertStringContainsString('<img src="cid:campusdrive-logo" alt="CampusDrive"', $body);
    }

    public function testDelegatePageOnlyTranslatesWhitelistedQueryParameters(): void
    {
        $contents = file_get_contents(__DIR__ . '/../../delegate.php');
        $this->assertNotFalse($contents);

        $this->assertStringContainsString('in_array($_GET[\'error\'], $allowedErrorKeys, true)', $contents);
        $this->assertStringContainsString('in_array($_GET[\'success\'], $allowedSuccessKeys, true)', $contents);
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function folderBrowserPageProvider(): array
    {
        return [
            'student.php' => [__DIR__ . '/../../student.php'],
            'delegate.php' => [__DIR__ . '/../../delegate.php'],
        ];
    }

    /**
     * @dataProvider folderBrowserPageProvider
     */
    public function testFileNamesAreEscapedBeforeBeingInjectedIntoInnerHtml(string $file): void
    {
        $contents = file_get_contents($file);
        $this->assertNotFalse($contents);

        $this->assertStringNotContainsString('${file.original_name}', $contents);
        $this->assertStringContainsString('${escapeHtml(file.original_name)}', $contents);
        $this->assertDoesNotMatchRegularExpression('/onclick="(openFolder|deleteFolder|deleteFile)\(\'\$\{/', $contents);
    }
}
