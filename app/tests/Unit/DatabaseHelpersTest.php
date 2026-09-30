<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use CampusDrive\Infrastructure\Database\InputSanitizer;

require_once __DIR__ . '/../../utils/db.php';

/**
 * Unit tests for input helpers that do not require a database connection.
 */
final class DatabaseHelpersTest extends TestCase
{
    public function testSanitizeInputTrimsAndEscapesHtml(): void
    {
        $result = InputSanitizer::sanitize(' <b>hi</b> ');
        $this->assertSame('&lt;b&gt;hi&lt;/b&gt;', $result);
    }

    public function testSanitizeInputReturnsNullForEmptyValues(): void
    {
        $this->assertNull(InputSanitizer::sanitize(null));
        $this->assertNull(InputSanitizer::sanitize(''));
    }

    public function testValidateEmailAcceptsValidAddress(): void
    {
        $this->assertTrue(InputSanitizer::isValidEmail('student@example.com'));
    }

    public function testValidateEmailRejectsInvalidAddress(): void
    {
        $this->assertFalse(InputSanitizer::isValidEmail('not-an-email'));
    }
}
