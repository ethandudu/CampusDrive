<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use ReflectionMethod;
use Database;

require_once __DIR__ . '/../../utils/db.php';

/**
 * Unit tests for the pure/static helper logic on Database that does not
 * require an actual database connection.
 */
final class DatabaseHelpersTest extends TestCase
{
    private function callPrivateStatic(string $method, array $args = []): mixed
    {
        $reflection = new ReflectionMethod(Database::class, $method);

        return $reflection->invokeArgs(null, $args);
    }

    public function testSanitizeInputTrimsAndEscapesHtml(): void
    {
        $result = $this->callPrivateStatic('sanitizeInput', [' <b>hi</b> ']);
        $this->assertSame('&lt;b&gt;hi&lt;/b&gt;', $result);
    }

    public function testSanitizeInputReturnsNullForEmptyValues(): void
    {
        $this->assertNull($this->callPrivateStatic('sanitizeInput', [null]));
        $this->assertNull($this->callPrivateStatic('sanitizeInput', ['']));
    }

    public function testValidateEmailAcceptsValidAddress(): void
    {
        $this->assertTrue($this->callPrivateStatic('validateEmail', ['student@example.com']));
    }

    public function testValidateEmailRejectsInvalidAddress(): void
    {
        $this->assertFalse($this->callPrivateStatic('validateEmail', ['not-an-email']));
    }
}
