<?php

use CampusDrive\Infrastructure\Database\UserRepository;

require_once 'utils/db.php';
require_once 'utils/session.php';
require_once 'utils/i18n.php';
require_once 'utils/mail.php';
require_once 'utils/emailTemplates/password_reset.php';

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$message = isset($_GET['sent']) ? t('password_reset_email_sent') : '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (
        !isset($_POST['csrf_token'])
        || !is_string($_POST['csrf_token'])
        || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])
    ) {
        http_response_code(400);
        die(t('security_error'));
    }

    $email = $_POST['email'] ?? '';
    if (is_string($email) && filter_var($email, FILTER_VALIDATE_EMAIL) !== false) {
        $token = bin2hex(random_bytes(32));
        $accountEmail = (new UserRepository())->createPasswordResetToken(
            $email,
            hash('sha256', $token),
            time() + 3600
        );

        if ($accountEmail !== null) {
            $resetLink = rtrim(APP_BASE_URL, '/') . '/reset_password.php?token=' . urlencode($token);
            $emailTemplate = passwordResetEmailTemplate($resetLink);
            (new Mailer())->sendMail($accountEmail, $emailTemplate['subject'], $emailTemplate['body']);
        }
    }

    header('Location: forgot_password.php?sent=1');
    exit;
}
?>
<!DOCTYPE html>
<html lang="<?= locale() ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= t('forgot_password') ?> - CampusDrive</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="icon" type="image/png" href="assets/favicon.ico">
</head>
<body class="bg-light d-flex align-items-center vh-100">
<div class="container">
    <div class="row justify-content-center">
        <div class="col-md-5">
            <div class="card shadow-sm">
                <div class="card-header bg-primary text-white text-center">
                    <img src="assets/img/campusdrivewhite.webp" alt="CampusDrive" class="img-fluid" style="max-height: 150px;">
                    <hr>
                    <h1 class="h5"><?= t('forgot_password') ?></h1>
                </div>
                <div class="card-body p-4">
                    <?php if ($message): ?>
                        <div class="alert alert-info"><?= htmlspecialchars($message, ENT_QUOTES, 'UTF-8') ?></div>
                    <?php endif; ?>
                    <form method="post" action="forgot_password.php">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8') ?>">
                        <div class="mb-3">
                            <label for="email" class="form-label"><?= t('email') ?></label>
                            <input type="email" class="form-control" id="email" name="email" autocomplete="email" required>
                        </div>
                        <button type="submit" class="btn btn-primary w-100"><?= t('request_password_reset') ?></button>
                    </form>
                    <div class="mt-3 text-center">
                        <a href="login.php" class="text-decoration-none"><?= t('back_to_login') ?></a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
</body>
</html>
