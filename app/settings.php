<?php
require_once 'utils/db.php';
require_once 'utils/session.php';
require_once 'utils/i18n.php';

if (!isset($_SESSION['user_id'])) { header('Location: login.php'); exit; }
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$success = ''; $error = '';
if (isset($_GET['error'])) {
    if ($_GET['error'] === 'promotion_inactive') {
        $error = t('promotion_inactive');
    } else {
        $error = t('generic_error');
    }
} elseif (isset($_GET['success'])) {
    if ($_GET['success'] === 'promo_created') {
        $success = t('promotion_request_success');
    } elseif ($_GET['success'] === 'language_updated') {
        $success = t('language_updated');
    }
}

$user = Database::getUserDetails($_SESSION['user_id']);

// Handle language update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'update_language') {
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        die(t('security_error'));
    }

    $language = $_POST['language'] ?? '';
    if (!array_key_exists($language, SUPPORTED_LOCALES)) {
        $error = t('invalid_language');
    } else {
        Database::updateUserLanguage((int) $user['id'], $language);
        $_SESSION['locale'] = $language;
        header('Location: settings.php?success=language_updated');
        exit;
    }
}

// Handle account deletion
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete_account') {
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        die(t('security_error'));
    }
    if (Database::deleteUser((string) $user['id'])) {
        session_destroy();
        header('Location: index.php');
        exit;
    } else {
        $error = t('account_deletion_error');
    }
}

// Handle password change
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'change_password') {
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        die(t('security_error'));
    }
    $current_password = $_POST['current_password'] ?? '';
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    if ($new_password !== $confirm_password) {
        $error = t('password_mismatch');
    } else {
        // Update the user's password
        if(Database::updateUserPassword((int) $user['id'], $current_password, $new_password)) {
            $success = t('password_change_success');
        } else {
            $error = t('password_change_error');
        }
    }
}

// Handle promotion request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'request_promo') {
    if ($user['promotion_id']) {
        $error = t('promotion_already_assigned');
    } else {
        $promo_name = trim($_POST['promo_name']);
        try {
            $new_promo_id = Database::createPromotion($promo_name);
            Database::attachUserToPromotion($user['id'], $new_promo_id);

            $_SESSION['promotion_id'] = $new_promo_id;
            $success = t('promotion_request_success');
            header("Location: settings.php?success=promo_created");
            exit;
        } catch (\Exception $e) {
            $error = t('promotion_request_error');
        }
    }
}
?>
<!DOCTYPE html>
<html lang="<?= locale() ?>">
<head>
    <meta charset="UTF-8">
    <title><?= t('settings') ?> - CampusDrive</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
<nav class="navbar navbar-dark bg-secondary mb-4 shadow">
    <div class="container">
        <span class="navbar-brand mb-0 h1">CampusDrive - <?= t('settings') ?></span>
        <div>
            <a href="student.php" class="btn btn-outline-light btn-sm me-2"><?= t('home') ?></a>
            <?php if ($user['role'] === 'delegate'): ?>
                <a href="delegate.php" class="btn btn-outline-light btn-sm me-2"><?= t('delegate_area') ?></a>
            <?php endif; ?>
            <a href="settings.php" class="btn btn-light btn-sm me-2"><?= t('settings') ?></a>
            <a href="logout.php" class="btn btn-outline-danger btn-sm"><?= t('logout') ?></a>
        </div>
    </div>
</nav>

<div class="container">
    <div class="row justify-content-center">
        <div class="col-md-8">
            <?php if($error): ?>
                <div class="alert alert-danger"><?= $error ?></div>
            <?php endif; ?>
            <?php if($success): ?>
                <div class="alert alert-success"><?= $success ?></div>
            <?php endif; ?>
            <div class="card shadow-sm mb-4">
                <div class="card-header bg-white fw-bold"><?= t('account') ?> (<?= htmlspecialchars($user['email']) ?>)</div>
                <div class="card-body">
                    <p><?= t('current_role') ?> : <span class="badge bg-primary text-uppercase"><?= t($user['role']) ?></span></p>

                    <?php if ($user['promotion_id']): ?>
                        <p><?= t('promotion_area') ?> : <b><?= htmlspecialchars($user['promo_name']) ?></b></p>
                        <p><?= t('area_status') ?> :
                            <?php if ($user['promo_status'] === 'active'): ?>
                                <span class="badge bg-success"><?= t('active') ?></span>
                            <?php else: ?>
                                <span class="badge bg-warning text-dark"><?= t('pending_approval') ?></span>
                            <?php endif; ?>
                        </p>
                    <?php else: ?>
                        <p class="text-muted"><?= t('no_promotion') ?></p>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Create promotion -->
            <?php if (!$user['promotion_id']): ?>
                <div class="card shadow-sm border-primary mb-4">
                    <div class="card-header bg-primary text-white fw-bold">
                        <?= t('request_promotion') ?>
                    </div>
                    <div class="card-body">
                        <?php if ($error): ?><div class="alert alert-danger"><?= $error ?></div><?php endif; ?>
                        <p><?= t('request_promotion_help') ?></p>
                        <form method="POST">
                            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                            <input type="hidden" name="action" value="request_promo">
                            <div class="mb-3">
                                <label class="form-label"><?= t('promotion_name') ?></label>
                                <input type="text" class="form-control" name="promo_name" placeholder="<?= t('promotion_placeholder') ?>" required>
                            </div>
                            <button type="submit" class="btn btn-primary"><?= t('submit_request') ?></button>
                        </form>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
    <div class="row justify-content-center">
        <div class="col-md-4">
            <div class="row">
                <div class="card shadow-sm mb-4">
                    <div class="card-header bg-white fw-bold"><?= t('settings') ?></div>
                    <div class="card-body">
                        <form method="POST">
                            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                            <input type="hidden" name="action" value="update_language">
                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                            <div class="mb-3">
                                <label for="language" class="form-label"><?= t('language') ?></label>
                                <select id="language" name="language" class="form-select">
                                    <?php foreach (SUPPORTED_LOCALES as $code => $label): ?>
                                        <option value="<?= $code ?>" <?= locale() === $code ? 'selected' : '' ?>><?= $label ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <div class="form-text"><?= t('language_help') ?></div>
                            </div>
                            <button type="submit" class="btn btn-primary"><?= t('save') ?></button>
                        </form>
                    </div>
                </div>
            </div>
            <div class="row">
                <div class="card shadow-sm mb-4">
                    <div class="card-header bg-white fw-bold"><?= t('account_deletion') ?></div>
                    <div class="card-body">
                        <p><?= t('account_deletion_warning') ?></p>
                        <form method="POST" action="" onsubmit="return confirm('<?= t('confirm_account_deletion') ?>');">
                            <input type="hidden" name="action" value="delete_account">
                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                            <button type="submit" class="btn btn-danger"><?= t('delete_account') ?></button>
                        </form>
                    </div>
                </div>
            </div>

        </div>
        <div class="col-md-4">
            <div class="card shadow-sm mb-4">
                <div class="card-header bg-white fw-bold"><?= t('change_password') ?></div>
                <div class="card-body">
                    <form method="POST" action="">
                        <input type="hidden" name="action" value="change_password">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                        <div class="mb-3">
                            <label class="form-label"><?= t('current_password') ?></label>
                            <input type="password" class="form-control" name="current_password" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label"><?= t('new_password') ?></label>
                            <input type="password" class="form-control" name="new_password" required>
                            <ul>
                                <li id="length" class="text-danger"><?= t('password_length') ?></li>
                                <li id="uppercase" class="text-danger"><?= t('password_uppercase') ?></li>
                                <li id="lowercase" class="text-danger"><?= t('password_lowercase') ?></li>
                                <li id="number" class="text-danger"><?= t('password_number') ?></li>
                                <li id="special" class="text-danger"><?= t('password_special') ?></li>
                            </ul>
                        </div>
                        <div class="mb-3">
                            <label class="form-label"><?= t('confirm_password') ?></label>
                            <input type="password" class="form-control" name="confirm_password" required>
                        </div>
                        <button type="submit" class="btn btn-primary"><?= t('change_password') ?></button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
<script>
    <!--    check userpass complexity -->
    document.addEventListener('DOMContentLoaded', function() {
        const passwordInput = document.querySelector('input[name="new_password"]');
        const submitButton = document.querySelector('button[type="submit"]');

        passwordInput.addEventListener('input', function() {
            const password = this.value;
            let isValid = true;

            // Check password complexity
            if (password.length < 8 || password.length > 64) {
                isValid = false;
                document.getElementById('length').classList.remove('text-success');
                document.getElementById('length').classList.add('text-danger');
            } else {
                document.getElementById('length').classList.remove('text-danger');
                document.getElementById('length').classList.add('text-success');
            }
            if (!/[A-Z]/.test(password)) {
                isValid = false;
                document.getElementById('uppercase').classList.remove('text-success');
                document.getElementById('uppercase').classList.add('text-danger');
            } else {
                document.getElementById('uppercase').classList.remove('text-danger');
                document.getElementById('uppercase').classList.add('text-success');
            }
            if (!/[a-z]/.test(password)) {
                isValid = false;
                document.getElementById('lowercase').classList.remove('text-success');
                document.getElementById('lowercase').classList.add('text-danger');
            } else {
                document.getElementById('lowercase').classList.remove('text-danger');
                document.getElementById('lowercase').classList.add('text-success');
            }
            if (!/[0-9]/.test(password)) {
                isValid = false;
                document.getElementById('number').classList.remove('text-success');
                document.getElementById('number').classList.add('text-danger');
            } else {
                document.getElementById('number').classList.remove('text-danger');
                document.getElementById('number').classList.add('text-success');
            }
            if (!/[!@#$%^&*()-+]/.test(password)) {
                isValid = false;
                document.getElementById('special').classList.remove('text-success');
                document.getElementById('special').classList.add('text-danger');
            } else {
                document.getElementById('special').classList.remove('text-danger');
                document.getElementById('special').classList.add('text-success');
            }
            submitButton.disabled = !isValid;
        });
    });
</script>
</body>
</html>
