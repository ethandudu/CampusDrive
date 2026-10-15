<?php
use CampusDrive\Infrastructure\Database\FileRepository;
use CampusDrive\Infrastructure\Storage\UploadStorage;

require_once 'utils/db.php';

$fileRepository = new FileRepository();
require_once 'utils/session.php';
require_once 'utils/i18n.php';

if (!isset($_SESSION['user_id'])) {
    die(t('unauthorized_access'));
}

$file_id = (string) ($_GET['id'] ?? 0);

$file = $fileRepository->getFile($file_id);

if (!$file) {
    die(t('file_not_found'));
}

if ($_SESSION['role'] !== 'admin' && $_SESSION['promotion_id'] !== $file['promotion_id']) {
    die(t('file_access_denied'));
}

if ($file['status'] !== 'approved' && $_SESSION['user_id'] !== $file['user_id'] && $_SESSION['role'] !== 'ambassador') {
    die(t('file_pending_approval'));
}

$full_path = (new UploadStorage())->find($file['file_path']);

if ($full_path === null) {
    die(t('file_missing'));
}

header('Content-Type: ' . $file['file_type']);
header('X-Content-Type-Options: nosniff');
header('Content-Disposition: inline; filename="' . basename($file['original_name']) . '"');
header('Content-Length: ' . filesize($full_path));

readfile($full_path);
exit;
