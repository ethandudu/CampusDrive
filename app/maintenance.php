<?php
// Standalone on purpose: it must keep working when the database or config is unavailable.
require_once __DIR__ . '/utils/session.php';
require_once __DIR__ . '/utils/i18n.php';

http_response_code(503);
header('Retry-After: 3600');
header('Cache-Control: no-store');
?>
<!DOCTYPE html>
<html lang="<?= locale() ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title><?= t('maintenance_title') ?> - CampusDrive</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="icon" type="image/png" href="/assets/favicon.ico">
</head>
<body class="bg-light d-flex align-items-center vh-100">
<div class="container">
    <div class="row justify-content-center">
        <div class="col-md-6 col-lg-5">
            <div class="card shadow-sm text-center">
                <div class="card-header bg-primary text-white">
                    <img src="/assets/img/campusdrivewhite.webp" alt="CampusDrive" class="img-fluid" style="max-height: 150px;">
                </div>
                <div class="card-body p-4 p-lg-5">
                    <h1 class="h4 fw-bold"><?= t('maintenance_title') ?></h1>
                    <p class="text-secondary mt-3"><?= t('maintenance_message') ?></p>
                    <p class="text-secondary"><?= t('maintenance_retry') ?></p>
                    <button type="button" class="btn btn-primary px-4 mt-2" onclick="window.location.assign(window.location.href)">
                        <?= t('refresh') ?>
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>
</body>
</html>
