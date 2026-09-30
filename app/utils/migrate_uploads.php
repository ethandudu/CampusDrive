<?php

use CampusDrive\Infrastructure\Storage\UploadStorage;

/**
 * One-off CLI script: moves files previously stored in app/uploads (inside the
 * web root) to the private upload directory.
 *
 * Usage (Docker): docker compose exec -u www-data php php utils/migrate_uploads.php
 * Run it as www-data so the moved files stay readable by Apache.
 */
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/db.php';

$storage = new UploadStorage();
$result = $storage->migrateLegacyFiles();

echo sprintf("Source : %s\nDestination : %s\n", $storage->legacyDirectory(), $storage->directory());
echo sprintf("%d fichier(s) déplacé(s).\n", $result['moved']);

if ($result['failed'] !== []) {
    fwrite(STDERR, "Échec pour : " . implode(', ', $result['failed']) . "\n");
    exit(1);
}
