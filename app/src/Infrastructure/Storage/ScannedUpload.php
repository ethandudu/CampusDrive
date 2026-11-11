<?php

namespace CampusDrive\Infrastructure\Storage;

use CampusDrive\Infrastructure\Security\UploadScanner;
use Closure;
use RuntimeException;

final class ScannedUpload
{
    public function __construct(
        private readonly UploadScanner $scanner,
        private readonly UploadStorage $storage,
        private readonly ?Closure $move = null,
    ) {
    }

    /**
     * The record callback receives the stored name; it returns whether insertion succeeded.
     * @param Closure(string): bool $createRecord
     * @return 'uploaded'|'flagged'|'scan_error'|'save_error'
     */
    public function save(string $temporaryPath, string $extension, Closure $createRecord): string
    {
        try {
            if (!$this->scanner->isClean($temporaryPath)) {
                error_log('Upload rejected by antivirus scan.');
                return 'flagged';
            }
        } catch (RuntimeException $exception) {
            error_log('Upload antivirus scan failed: ' . $exception->getMessage());
            return 'scan_error';
        }

        $storedName = $this->storage->generateName($extension);
        $move = $this->move ?? move_uploaded_file(...);
        if (!$this->storage->prepareDirectory() || !$move($temporaryPath, $this->storage->pathFor($storedName))) {
            return 'save_error';
        }

        $recordCreated = false;
        try {
            $recordCreated = $createRecord($storedName);
            return $recordCreated ? 'uploaded' : 'save_error';
        } finally {
            if (!$recordCreated) {
                $this->storage->delete($storedName);
            }
        }
    }
}
