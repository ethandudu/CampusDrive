<?php
use CampusDrive\Infrastructure\Database\SettingsRepository;

require_once 'utils/db.php';
require_once 'utils/session.php';
require_once 'utils/i18n.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: login.php');
    exit;
}
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$repository = new SettingsRepository();
$error = '';
$success = isset($_GET['success']) && $_GET['success'] === '1' ? t('global_settings_saved') : '';
$announcement = $repository->getAnnouncementDetails();
$text = html_entity_decode($announcement['text'], ENT_QUOTES, 'UTF-8');
$enabled = $announcement['enabled'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || !is_string($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        http_response_code(400);
        die(t('security_error'));
    }

    $submittedText = $_POST['announcement_text'] ?? null;
    $submittedEnabled = $_POST['announcement_enabled'] ?? null;
    if (!is_string($submittedText) || !is_string($submittedEnabled) || !in_array($submittedEnabled, ['0', '1'], true)) {
        $error = t('generic_error');
    } else {
        $text = $submittedText;
        $enabled = $submittedEnabled === '1';
        try {
            $repository->updateAnnouncement($text, $enabled);
            header('Location: admin_settings.php?success=1');
            exit;
        } catch (InvalidArgumentException $e) {
            $error = t($e->getMessage() === 'announcement_too_long' ? 'announcement_too_long' : 'generic_error');
        } catch (Throwable $e) {
            error_log('Admin site settings: ' . $e->getMessage());
            $error = t('generic_error');
        }
    }
}
?>
<!DOCTYPE html>
<html lang="<?= htmlspecialchars(locale(), ENT_QUOTES, 'UTF-8') ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= t('global_settings') ?> - CampusDrive</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="icon" type="image/png" href="assets/favicon.ico">
</head>
<body class="bg-light">
<?php require 'utils/admin_header.php'; ?>
<main class="container">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h3><?= t('global_settings') ?></h3>
        <a class="btn btn-outline-secondary" href="admin.php"><?= t('back') ?></a>
    </div>
    <?php if ($success): ?><div class="alert alert-success"><?= htmlspecialchars($success, ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>
    <?php if ($error): ?><div class="alert alert-danger"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>
    <section class="card shadow-sm">
        <div class="card-header fw-bold"><?= t('global_announcement') ?></div>
        <div class="card-body">
            <p class="text-muted"><?= t('global_announcement_help') ?></p>
            <form method="POST" action="admin_settings.php">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8') ?>">
                <div class="mb-3">
                    <label class="form-label" for="announcement-text"><?= t('announcement_text') ?></label>
                    <textarea class="form-control" id="announcement-text" name="announcement_text" rows="4" maxlength="10000"><?= htmlspecialchars($text, ENT_QUOTES, 'UTF-8') ?></textarea>
                </div>
                <div class="form-check mb-3">
                    <input type="hidden" name="announcement_enabled" value="0">
                    <input class="form-check-input" type="checkbox" id="announcement-enabled" name="announcement_enabled" value="1" <?= $enabled ? 'checked' : '' ?>>
                    <label class="form-check-label" for="announcement-enabled"><?= t('announcement_enabled') ?></label>
                </div>
                <button type="submit" class="btn btn-secondary"><?= t('save') ?></button>
            </form>
        </div>
    </section>
</main>
</body>
</html>
