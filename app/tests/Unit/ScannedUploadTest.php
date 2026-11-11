<?php

namespace Tests\Unit;

use CampusDrive\Infrastructure\Security\UploadScanner;
use CampusDrive\Infrastructure\Storage\ScannedUpload;
use CampusDrive\Infrastructure\Storage\UploadStorage;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class ScannedUploadTest extends TestCase
{
    private string $directory;
    private string $file;

    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'upload_' . bin2hex(random_bytes(6));
        $this->file = tempnam(sys_get_temp_dir(), 'upload_');
        file_put_contents($this->file, 'contents');
    }

    protected function tearDown(): void
    {
        if (is_dir($this->directory)) {
            foreach (glob($this->directory . DIRECTORY_SEPARATOR . '*') as $file) {
                unlink($file);
            }
            rmdir($this->directory);
        }
        if (is_file($this->file)) {
            unlink($this->file);
        }
    }

    public function testCleanFileIsStoredAndRecordIsCreated(): void
    {
        $scanner = $this->createMock(UploadScanner::class);
        $scanner->expects(self::once())->method('isClean')->with($this->file)->willReturn(true);
        $upload = new ScannedUpload($scanner, new UploadStorage($this->directory), rename(...));
        $record = null;
        $result = $upload->save($this->file, 'pdf', function (string $name) use (&$record): bool {
            $record = $name;
            return true;
        });
        self::assertSame('uploaded', $result);
        self::assertFileExists($this->directory . DIRECTORY_SEPARATOR . $record);
    }

    public function testFlaggedAndFailedScansDoNotMoveOrCreateRecord(): void
    {
        foreach ([false, new RuntimeException('scanner unavailable')] as $outcome) {
            $scanner = $this->createMock(UploadScanner::class);
            $method = $scanner->expects(self::once())->method('isClean');
            $outcome instanceof RuntimeException ? $method->willThrowException($outcome) : $method->willReturn(false);
            $never = static function (): never { self::fail('Rejected upload reached storage or database.'); };
            $upload = new ScannedUpload($scanner, new UploadStorage($this->directory), $never);
            self::assertSame($outcome === false ? 'flagged' : 'scan_error', $upload->save($this->file, 'pdf', $never));
            self::assertDirectoryDoesNotExist($this->directory);
            self::assertFileExists($this->file);
        }
    }

    public function testFailedRecordRemovesStoredFile(): void
    {
        $scanner = $this->createMock(UploadScanner::class);
        $scanner->method('isClean')->willReturn(true);
        $upload = new ScannedUpload($scanner, new UploadStorage($this->directory), rename(...));
        self::assertSame('save_error', $upload->save($this->file, 'pdf', static fn (): bool => false));
        self::assertSame([], glob($this->directory . DIRECTORY_SEPARATOR . '*'));
    }

    public function testExceptionCreatingRecordStillRemovesStoredFile(): void
    {
        $scanner = $this->createMock(UploadScanner::class);
        $scanner->method('isClean')->willReturn(true);
        $upload = new ScannedUpload($scanner, new UploadStorage($this->directory), rename(...));
        try {
            $upload->save($this->file, 'pdf', static function (): never {
                throw new RuntimeException('database error');
            });
            self::fail('Database exception should be propagated.');
        } catch (RuntimeException $exception) {
            self::assertSame('database error', $exception->getMessage());
            self::assertSame([], glob($this->directory . DIRECTORY_SEPARATOR . '*'));
        }
    }
}
