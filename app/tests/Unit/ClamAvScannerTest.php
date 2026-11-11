<?php

namespace Tests\Unit;

use CampusDrive\Infrastructure\Security\ClamAvScanner;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class ClamAvScannerTest extends TestCase
{
    private string $file;

    protected function setUp(): void
    {
        $this->file = tempnam(sys_get_temp_dir(), 'scan_');
        file_put_contents($this->file, 'test contents');
    }

    protected function tearDown(): void
    {
        unlink($this->file);
    }

    public function testCleanAndInfectedResponsesAndStreamFraming(): void
    {
        foreach (["stream: OK\0" => true, "stream: Eicar-Test-Signature FOUND\0" => false] as $response => $expected) {
            [$client, $server] = stream_socket_pair(STREAM_PF_UNIX, STREAM_SOCK_STREAM, STREAM_IPPROTO_IP);
            fwrite($server, $response);
            $scanner = new ClamAvScanner(connect: static fn () => $client);
            self::assertSame($expected, $scanner->isClean($this->file));
            self::assertSame("zINSTREAM\0" . pack('N', 13) . 'test contents' . pack('N', 0), stream_get_contents($server));
            fclose($server);
        }
    }

    public static function invalidResponses(): array
    {
        return [
            'daemon error' => ["stream: size limit exceeded ERROR\0"],
            'invalid reply' => ["stream: OK extra\0"],
            'missing terminator' => ['stream: OK'],
            'invalid detection' => ["other: test FOUND\0"],
            'oversized reply' => [str_repeat('x', 4096) . "\0"],
        ];
    }

    #[DataProvider('invalidResponses')]
    public function testRejectsInvalidOrIncompleteResponses(string $response): void
    {
        [$client, $server] = stream_socket_pair(STREAM_PF_UNIX, STREAM_SOCK_STREAM, STREAM_IPPROTO_IP);
        fwrite($server, $response);
        stream_socket_shutdown($server, STREAM_SHUT_WR);
        try {
            $this->expectException(RuntimeException::class);
            (new ClamAvScanner(connect: static fn () => $client))->isClean($this->file);
        } finally {
            fclose($server);
        }
    }

    public function testConnectionFailureFailsClosed(): void
    {
        $this->expectException(RuntimeException::class);
        (new ClamAvScanner(connect: static fn () => false))->isClean($this->file);
    }

    public function testBrokenConnectionFailsClosed(): void
    {
        [$client, $server] = stream_socket_pair(STREAM_PF_UNIX, STREAM_SOCK_STREAM, STREAM_IPPROTO_IP);
        fclose($server);
        $this->expectException(RuntimeException::class);
        (new ClamAvScanner(connect: static fn () => $client))->isClean($this->file);
    }

    public function testPartialWritesAreRetried(): void
    {
        stream_wrapper_register('clamtest', PartialWriteStream::class);
        PartialWriteStream::$written = '';
        try {
            $scanner = new ClamAvScanner(connect: static fn () => fopen('clamtest://scanner', 'r+'));
            self::assertTrue($scanner->isClean($this->file));
            self::assertSame("zINSTREAM\0" . pack('N', 13) . 'test contents' . pack('N', 0), PartialWriteStream::$written);
        } finally {
            stream_wrapper_unregister('clamtest');
        }
    }

    public function testUnreadableFileFailsClosed(): void
    {
        $this->expectException(RuntimeException::class);
        (new ClamAvScanner())->isClean($this->file . '.missing');
    }

    public function testTimeoutFailsClosed(): void
    {
        [$client, $server] = stream_socket_pair(STREAM_PF_UNIX, STREAM_SOCK_STREAM, STREAM_IPPROTO_IP);
        try {
            $this->expectException(RuntimeException::class);
            (new ClamAvScanner(timeout: 0.1, connect: static fn () => $client))->isClean($this->file);
        } finally {
            fclose($server);
        }

    }

    public function testTooLargeFileFailsBeforeConnecting(): void
    {
        $file = fopen($this->file, 'wb');
        ftruncate($file, 10485761);
        fclose($file);
        $this->expectException(RuntimeException::class);
        (new ClamAvScanner(connect: static function () {
            self::fail('Oversized file must not be sent.');
        }))->isClean($this->file);
    }
}

final class PartialWriteStream
{
    public $context;
    public static string $written = '';
    private string $response = "stream: OK\0";

    public function stream_open(string $path, string $mode, int $options, ?string &$openedPath): bool
    {
        return true;
    }

    public function stream_write(string $data): int
    {
        $length = min(3, strlen($data));
        self::$written .= substr($data, 0, $length);
        return $length;
    }

    public function stream_read(int $count): string
    {
        $part = substr($this->response, 0, $count);
        $this->response = substr($this->response, strlen($part));
        return $part;
    }

    public function stream_eof(): bool
    {
        return $this->response === '';
    }

    public function stream_set_option(int $option, int $arg1, int $arg2): bool
    {
        return true;
    }
}
