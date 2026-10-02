<?php

namespace Tests\Unit;

use CampusDrive\Infrastructure\Storage\UploadStorage;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;

final class UploadStorageTest extends TestCase
{
    private string $root;
    private string $private;
    private string $legacy;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'campusdrive_' . bin2hex(random_bytes(6));
        $this->private = $this->root . DIRECTORY_SEPARATOR . 'private';
        $this->legacy = $this->root . DIRECTORY_SEPARATOR . 'legacy';
        mkdir($this->legacy, 0777, true);
    }

    protected function tearDown(): void
    {
        foreach ([$this->private, $this->legacy] as $directory) {
            foreach (glob($directory . '/*') ?: [] as $file) {
                unlink($file);
            }
            if (is_dir($directory)) {
                rmdir($directory);
            }
        }
        rmdir($this->root);
    }

    public function testDefaultDirectoryIsOutsideTheWebRoot(): void
    {
        $webRoot = realpath(dirname(__DIR__, 2));
        $directory = (new UploadStorage())->directory();

        $this->assertNotFalse($webRoot);
        $this->assertFalse(str_starts_with($directory, $webRoot . DIRECTORY_SEPARATOR));
    }

    #[RunInSeparateProcess]
    public function testUploadDirConstantOverridesTheDefaultDirectory(): void
    {
        define('UPLOAD_DIR', $this->private . DIRECTORY_SEPARATOR);

        $this->assertSame($this->private, (new UploadStorage())->directory());
    }

    public function testGeneratedNamesAreRandomAndKeepTheExtension(): void
    {
        $storage = new UploadStorage($this->private, $this->legacy);

        $first = $storage->generateName('pdf');
        $second = $storage->generateName('pdf');

        $this->assertMatchesRegularExpression('/^file_[0-9a-f]{32}\.pdf$/', $first);
        $this->assertNotSame($first, $second);
    }

    public function testPrepareDirectoryCreatesAPrivateDirectory(): void
    {
        $storage = new UploadStorage($this->private, $this->legacy);

        $this->assertTrue($storage->prepareDirectory());
        $this->assertDirectoryExists($this->private);
        if (DIRECTORY_SEPARATOR === '/') {
            $this->assertSame('0750', substr(sprintf('%o', fileperms($this->private)), -4));
        }
    }

    public function testPathForStripsDirectoryComponents(): void
    {
        $storage = new UploadStorage($this->private, $this->legacy);

        $this->assertSame(
            $this->private . DIRECTORY_SEPARATOR . 'passwd',
            $storage->pathFor('../../etc/passwd')
        );
    }

    public function testFindLooksInThePrivateDirectoryThenInTheLegacyOne(): void
    {
        $storage = new UploadStorage($this->private, $this->legacy);
        $storage->prepareDirectory();
        file_put_contents($this->private . '/new.pdf', 'new');
        file_put_contents($this->legacy . '/old.pdf', 'old');

        file_put_contents($this->root . '/secret.txt', 'secret');

        $this->assertSame($this->private . DIRECTORY_SEPARATOR . 'new.pdf', $storage->find('new.pdf'));
        $this->assertSame($this->legacy . DIRECTORY_SEPARATOR . 'old.pdf', $storage->find('old.pdf'));
        $this->assertNull($storage->find('missing.pdf'));
        $this->assertNull($storage->find('../secret.txt'));
        unlink($this->root . '/secret.txt');
    }

    public function testDeleteRemovesTheFileFromBothDirectories(): void
    {
        $storage = new UploadStorage($this->private, $this->legacy);
        $storage->prepareDirectory();
        file_put_contents($this->private . '/a.pdf', 'a');
        file_put_contents($this->legacy . '/b.pdf', 'b');

        $storage->delete('a.pdf');
        $storage->delete('b.pdf');

        $this->assertNull($storage->find('a.pdf'));
        $this->assertNull($storage->find('b.pdf'));
    }

    public function testPagesUseTheUploadStorageInsteadOfTheWebRootDirectory(): void
    {
        $root = dirname(__DIR__, 2);

        foreach (['student.php', 'view.php', 'src/Infrastructure/Database/FileRepository.php'] as $file) {
            $contents = (string) file_get_contents($root . '/' . $file);

            $this->assertStringContainsString('UploadStorage', $contents, $file);
            $this->assertDoesNotMatchRegularExpression('/[\'"]\/?uploads\/?[\'"]/', $contents, $file);
            $this->assertStringNotContainsString("__DIR__ . '/uploads", $contents, $file);
        }
    }
}
