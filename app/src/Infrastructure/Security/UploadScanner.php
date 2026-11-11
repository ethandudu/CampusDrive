<?php

namespace CampusDrive\Infrastructure\Security;

interface UploadScanner
{
    /** Returns true only for a clean file. Throws if a verdict cannot be obtained. */
    public function isClean(string $path): bool;
}
