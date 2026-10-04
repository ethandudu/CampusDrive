<?php

use CampusDrive\Infrastructure\Database\UserRepository;
use CampusDrive\Infrastructure\Security\PasswordPolicy;

require_once 'utils/db.php';
require_once 'utils/session.php';
require_once 'utils/i18n.php';

header('Cache-Control: no-store');
header('Referrer-Policy: no-referrer');

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$token = isset($_POST['token']) ? $_POST['token'] : ($_GET['token'] ?? '');
$tokenIsValid = is_string($token) && preg_match('/\A[a-f0-9]{64}\z/', $token) === 1;
$error = '';
$success = isset($_GET['success']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (
        !isset($_POST['csrf_token'])
        || !is_string($_POST['csrf_token'])
        || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])
    ) {
        http_response_code(400);
        die(t('security_error'));
    }

    $password = $_POST['password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';

    if (!$tokenIsValid) {
        $error = t('password_reset_invalid');
    } elseif (!is_string($password) || !is_string($confirmPassword)) {
        $error = t('generic_error');
    } elseif ($password !== $confirmPassword) {
        $error = t('password_mismatch');
    } elseif (($passwordViolation = PasswordPolicy::violation($password)) !== null) {
        $error = t($passwordViolation);
    } elseif (!(new UserRepository())->resetPasswordWithToken(hash('sha256', $token), $password)) {
        $error = t('password_reset_invalid');
    } else {
        header('Location: reset_password.php?success=1');
        exit;
    }
}

if (!$tokenIsValid && !$success) {
    $error = t('password_reset_invalid');
}
?>
<!DOCTYPE html>
<html lang="<?= locale() ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= t('reset_password') ?> - CampusDrive</title>
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
                    <h1 class="h5"><?= t('reset_password') ?></h1>
                </div>
                <div class="card-body p-4">
                    <?php if ($error): ?>
                        <div class="alert alert-danger"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div>
                        <?php if (!$tokenIsValid): ?>
                            <div class="text-center mb-3">
                                <a href="forgot_password.php"><?= t('forgot_password') ?></a>
                            </div>
                        <?php endif; ?>
                    <?php endif; ?>
                    <?php if ($success): ?>
                        <div class="alert alert-success"><?= htmlspecialchars(t('password_reset_success'), ENT_QUOTES, 'UTF-8') ?></div>
                    <?php elseif ($tokenIsValid): ?>
                        <form method="post" action="reset_password.php">
                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8') ?>">
                            <input type="hidden" name="token" value="<?= htmlspecialchars($token, ENT_QUOTES, 'UTF-8') ?>">
                            <div class="mb-3">
                                <label for="password" class="form-label"><?= t('new_password') ?></label>
                                <input type="password" class="form-control" id="password" name="password" autocomplete="new-password" required>
                                <div class="form-text"><?= t('password_length') ?>; <?= t('password_uppercase') ?>; <?= t('password_lowercase') ?>; <?= t('password_number') ?>; <?= t('password_special') ?></div>
                            </div>
                            <div class="mb-3">
                                <label for="confirm_password" class="form-label"><?= t('confirm_password') ?></label>
                                <input type="password" class="form-control" id="confirm_password" name="confirm_password" autocomplete="new-password" required>
                            </div>
                            <button type="submit" class="btn btn-primary w-100"><?= t('reset_password') ?></button>
                        </form>
                    <?php endif; ?>
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
