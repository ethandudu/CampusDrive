<?php
require_once 'utils/db.php';
require_once 'utils/session.php';
require_once 'utils/i18n.php';

if (!isset($_SESSION['user_id'])) { header('Location: login.php'); exit; }

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
            <div class="card shadow-sm mb-4">
                <div class="card-header bg-white fw-bold"><?= t('settings') ?></div>
                <div class="card-body">
                    <form method="POST">
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
        <div class="col-md-4">
            <div class="card shadow-sm mb-4">
                <div class="card-header bg-white fw-bold"><?= t('change_password') ?></div>
                <div class="card-body">
                    <form method="POST" action="change_password.php">
                        <div class="mb-3">
                            <label class="form-label"><?= t('new_password') ?></label>
                            <input type="password" class="form-control" name="new_password" required>
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
</body>
</html>
