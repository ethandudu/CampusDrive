<?php
require_once 'utils/db.php';
require_once 'utils/session.php';
require_once 'utils/i18n.php';
require_once 'utils/mail.php';
require_once 'utils/emailTemplates/promotion_activated.php';

// Check permissions: only admin can access this page
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: login.php');
    exit;
}

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// approve promotion request
if (isset($_POST['approve_promo_id'])) {
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        die(t('security_error'));
    }
    $promo_id = $_POST['approve_promo_id'];
    $promo_to_activate = Database::getPromotionDetails($promo_id);

    Database::updatePromotionStatus($promo_id, 'active');

    if ($promo_to_activate && !empty($promo_to_activate['created_by'])) {
        $requester = Database::getUserDetails((string) $promo_to_activate['created_by']);
        if ($requester && !empty($requester['email'])) {
            $activationEmail = promotionActivatedEmailTemplate($promo_to_activate['name']);
            (new Mailer())->sendMail($requester['email'], $activationEmail['subject'], $activationEmail['body']);
        }
    }

    $success = t('promotion_activated');
}

$promotions = Database::getPendingPromotions();
?>
<!DOCTYPE html>
<html lang="<?= locale() ?>">
<head>
    <meta charset="UTF-8">
    <title><?= t('administration') ?> - CampusDrive</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
<nav class="navbar navbar-dark bg-dark mb-4 shadow">
    <div class="container">
        <span class="navbar-brand mb-0 h1">CampusDrive - <?= t('administration') ?></span>
        <div>
            <a href="settings.php" class="btn btn-outline-light btn-sm me-2"><?= t('settings') ?></a>
            <a href="logout.php" class="btn btn-outline-light btn-sm"><?= t('logout') ?></a>
        </div>
    </div>
</nav>

<div class="container">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h3><?= t('pending_promotions') ?></h3>
    </div>

    <?php if (isset($success)): ?>
        <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
    <?php endif; ?>

    <div class="row">
<!--        statistics cards-->
        <div class="col-md-4 mb-3">
            <div class="card shadow-sm">
                <div class="card-body">
                    <h5 class="card-title"><?= t('total_users') ?></h5>
                    <p class="card-text fs-4"><?= Database::getTotalUsers() ?></p>
                </div>
            </div>
        </div>
        <div class="col-md-4 mb-3">
            <div class="card shadow-sm">
                <div class="card-body">
                    <h5 class="card-title"><?= t('total_promotions') ?></h5>
                    <p class="card-text fs-4"><?= Database::getTotalPromotions() ?></p>
                </div>
            </div>
        </div>
        <div class="col-md-4 mb-3">
            <div class="card shadow-sm">
                <div class="card-body">
                    <h5 class="card-title"><?= t('total_files') ?></h5>
                    <p class="card-text fs-4"><?= Database::getTotalFiles() ?></p>
                </div>
            </div>
        </div>
    </div>
    <hr>
    <div class="row">
        <div class="card shadow-sm">
            <div class="card-body p-0">
                <?php if (empty($promotions)): ?>
                    <div class="p-4 text-center text-muted"><?= t('no_pending_promotions') ?></div>
                <?php else: ?>
                    <table class="table table-hover mb-0">
                        <thead class="table-light">
                        <tr>
                            <th>#</th>
                            <th><?= t('promotion_name') ?></th>
                            <th><?= t('promotion_request_date') ?></th>
                            <th class="text-end"><?= t('action') ?></th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($promotions as $promo): ?>
                            <tr>
                                <td class="align-middle"><?= $promo['id'] ?></td>
                                <td class="align-middle fw-bold"><?= htmlspecialchars($promo['name']) ?></td>
                                <td class="align-middle"><?= date('d/m/Y H:i', strtotime($promo['created_at'])) ?></td>
                                <td class="text-end">
                                    <form method="POST" class="d-inline">
                                        <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                                        <input type="hidden" name="approve_promo_id" value="<?= $promo['id'] ?>">
                                        <button type="submit" class="btn btn-success btn-sm"><?= t('approve') ?></button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
</body>
</html>
