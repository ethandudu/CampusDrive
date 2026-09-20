<?php
require_once 'utils/db.php';
require_once 'utils/session.php';
require_once 'utils/i18n.php';
$message = '';
$error = '';

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

if (isset($_GET['registered'])){
    $message = t('registration_success');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        die(t('security_error'));
    }

    $user = Database::loginUser($_POST['email'], $_POST['password']);

    if ($user !== null) {
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['role'] = $user['role'];
        $_SESSION['promotion_id'] = $user['promotion_id'];
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
</head>
<body class="bg-light d-flex align-items-center vh-100">
<div class="container">
    <div class="row justify-content-center">
        <div class="col-md-5">
            <div class="card shadow-sm">
                <div class="card-header bg-primary text-white text-center">
                    <h4 class="mb-0">CampusDrive</h4>
                </div>
                <div class="card-body p-4">
                    <?php if ($message): ?>
                        <div class="alert alert-success"><?= htmlspecialchars($message) ?></div>
                    <?php endif; ?>
                    <?php if ($error): ?>
                        <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
                    <?php endif; ?>
                    <form method="POST" action="login.php">
                        <div class="mb-3">
                            <label for="email" class="form-label"><?= t('email') ?></label>
                            <input type="email" class="form-control" id="email" name="email" required>
                        </div>
                        <div class="mb-4">
                            <label for="password" class="form-label"><?= t('password') ?></label>
                            <input type="password" class="form-control" id="password" name="password" required>
                        </div>
                        <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                        <button type="submit" class="btn btn-primary w-100"><?= t('sign_in') ?></button>
                    </form>
                    <div class="mt-3 text-center">
                        <a href="register.php" class="text-decoration-none"><?= t('create_account') ?></a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
</body>
</html>
