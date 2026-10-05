<?php
use CampusDrive\Infrastructure\Database\UserRepository;

require_once 'utils/db.php';

$userRepository = new UserRepository();
require_once 'utils/session.php';
require_once 'utils/i18n.php';
$compose = json_decode(file_get_contents(__DIR__ . '/composer.json'), true);
$appVersion = is_array($compose) ? ($compose['version'] ?? '') : '';
$message = '';
$error = '';

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

if (isset($_GET['registered']) && $_GET['registered'] === '1') {
    $message = t('registration_success');
}

if (isset($_GET['registered']) && $_GET['registered'] === '2') {
    $message = t('registration_waiting_confirmation');
}

if (isset($_GET['locked'])) {
    $error = t('too_many_attempts');
}

if (isset($_GET['error'])) {
    $error = t('account_not_activated');
}

if (isset($_GET['activated'])) {
    if ($_GET['activated'] === '1') {
        $message = t('account_activated');
    } else {
        $error = t('activation_failed');
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        die(t('security_error'));
    }

    if($_POST['action'] === 'change_locale' && isset($_POST['locale'])) {
        $newLocale = $_POST['locale'];
        if (array_key_exists($newLocale, SUPPORTED_LOCALES)) {
            $_SESSION['locale'] = $newLocale;
        }
        header('Location: login.php');
        exit;
    }

    $user = $userRepository->loginUser($_POST['email'], $_POST['password']);

    if ($user !== null) {
        if (isset($user['error'])) {
            header('Location: login.php?error=' . urlencode($user['error']));
            exit;
        }

        $user = $user[0];
        startAuthenticatedSession($user);
        $_SESSION['locale'] = in_array($user['language'] ?? null, array_keys(SUPPORTED_LOCALES), true)
            ? $user['language']
            : DEFAULT_LOCALE;

        if ($user['role'] === 'admin') {
            header('Location: admin.php');
        } else {
            header('Location: student.php');
        }
        exit;
    } else {
        $error = t('invalid_credentials');
    }
}
?>
<!DOCTYPE html>
<html lang="<?= locale() ?>">
<head>
    <meta charset="UTF-8">
    <title><?= t('login') ?> - CampusDrive</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="icon" type="image/png" href="assets/favicon.ico">
</head>
<body class="bg-light d-flex align-items-center vh-100">
<div class="container">
    <div class="row justify-content-center">
        <div class="col-md-5">
            <div class="card shadow-sm">
                <div class="card-header bg-primary text-white text-center">
                    <img src="assets/img/campusdrivewhite.webp" alt="Logo" class="img-fluid" style="max-height: 150px;">
                    <hr>
                    <h5><?= t('login') ?></h5>
                </div>
                <div class="card-body p-4">
                    <?php if ($message): ?>
                        <div class="alert alert-success"><?= htmlspecialchars($message) ?></div>
                    <?php endif; ?>
                    <?php if ($error): ?>
                        <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
                    <?php endif; ?>
                    <form method="post" action="login.php">
                        <div class="mb-3">
                            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                            <input type="hidden" name="action" value="change_locale">
                            <label for="locale" class="form-label"><?= t('language') ?></label>
                            <select class="form-select" id="locale" name="locale" onchange="this.form.submit()">
                                <?php foreach (SUPPORTED_LOCALES as $code => $name): ?>
                                    <option value="<?= htmlspecialchars($code) ?>" <?= locale() === $code ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($name) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </form>
                    <form method="POST" action="login.php">
                        <div class="mb-3">
                            <label for="email" class="form-label"><?= t('email') ?></label>
                            <input type="email" class="form-control" id="email" name="email" required>
                        </div>
                        <div class="mb-4">
                            <label for="password" class="form-label"><?= t('password') ?></label>
                            <input type="password" class="form-control" id="password" name="password" required>
                        </div>
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8') ?>">
                        <input type="hidden" name="action" value="login">
                        <button type="submit" class="btn btn-primary w-100"><?= t('sign_in') ?></button>
                        <button type="submit" class="btn btn-outline-secondary w-100 mt-2"
                                formaction="forgot_password.php" formmethod="post" formnovalidate>
                            <?= t('request_password_reset') ?>
                        </button>
                    </form>
                    <div class="mt-3 text-center">
                        <a href="register.php" class="text-decoration-none"><?= t('create_account') ?></a>
                    </div>
                    <div class="mt-3 text-center text-muted">
                        <?php if ($appVersion !== ''): ?>
                            Version <?= htmlspecialchars((string) $appVersion, ENT_QUOTES, 'UTF-8') ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
</body>
</html>
