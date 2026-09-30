<?php

namespace CampusDrive\Infrastructure\Storage;

/**
 * Stores uploaded files outside the web root so they can only be reached
 * through view.php, which enforces the access rules.
 */
final class UploadStorage
{
    private string $directory;
    private string $legacyDirectory;

    public function __construct(?string $directory = null, ?string $legacyDirectory = null)
    {
        $this->directory = rtrim($directory ?? self::defaultDirectory(), '/\\');
        // Files uploaded before the move used to live in app/uploads (inside the web root).
        $this->legacyDirectory = rtrim($legacyDirectory ?? dirname(__DIR__, 3) . DIRECTORY_SEPARATOR . 'uploads', '/\\');
    }

    public function directory(): string
    {
        return $this->directory;
    }

    public function legacyDirectory(): string
    {
        return $this->legacyDirectory;
    }

    public function generateName(string $extension): string
    {
        return 'file_' . bin2hex(random_bytes(16)) . '.' . $extension;
    }

    public function prepareDirectory(): bool
    {
        if (!is_dir($this->directory) && !mkdir($this->directory, 0750, true) && !is_dir($this->directory)) {
            return false;
        }

        return is_writable($this->directory);
    }

    public function pathFor(string $storedName): string
    {
        return $this->directory . DIRECTORY_SEPARATOR . basename($storedName);
    }

    public function find(string $storedName): ?string
    {
        $name = basename($storedName);
        if ($name === '') {
            return null;
        }

        foreach ([$this->directory, $this->legacyDirectory] as $directory) {
            $path = $directory . DIRECTORY_SEPARATOR . $name;
            if (is_file($path)) {
                return $path;
            }
        }

        return null;
    }

    public function delete(string $storedName): void
    {
        $name = basename($storedName);
        if ($name === '') {
            return;
        }

        foreach ([$this->directory, $this->legacyDirectory] as $directory) {
            $path = $directory . DIRECTORY_SEPARATOR . $name;
            if (is_file($path)) {
                unlink($path);
            }
        }
    }

    /**
     * Moves the files left in the legacy (web-accessible) directory to the private one.
     *
     * @return array{moved: int, failed: string[]}
     */
    public function migrateLegacyFiles(): array
    {
        $result = ['moved' => 0, 'failed' => []];

        if (!is_dir($this->legacyDirectory) || !$this->prepareDirectory()) {
            return $result;
        }

        foreach (scandir($this->legacyDirectory) ?: [] as $name) {
            $source = $this->legacyDirectory . DIRECTORY_SEPARATOR . $name;
            if ($name[0] === '.' || !is_file($source)) {
                continue;
            }

            $destination = $this->pathFor($name);
            // rename() fails across filesystems (e.g. bind mount to a volume), so fall back to copy.
            if (!file_exists($destination) && (@rename($source, $destination) || (copy($source, $destination) && unlink($source)))) {
                $result['moved']++;
            } else {
                $result['failed'][] = $name;
            }
        }

        return $result;
    }

    private static function defaultDirectory(): string
    {
        $configured = defined('UPLOAD_DIR') ? (string) constant('UPLOAD_DIR') : '';
        if ($configured !== '') {
            return $configured;
        }

        // Sibling of the web root (app/): /var/www/uploads in the Docker image.
        return dirname(__DIR__, 4) . DIRECTORY_SEPARATOR . 'uploads';
    }
}
