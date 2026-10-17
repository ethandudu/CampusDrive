<?php
use CampusDrive\Infrastructure\Database\FileRepository;
use CampusDrive\Infrastructure\Database\PromotionRepository;
use CampusDrive\Infrastructure\Database\UserRepository;

require_once 'utils/db.php';
require_once 'utils/session.php';
require_once 'utils/i18n.php';

$userRepository = new UserRepository();
$promotionRepository = new PromotionRepository();
$fileRepository = new FileRepository();
require_once 'utils/mail.php';
require_once 'utils/emailTemplates/promotion_activated.php';
require_once 'utils/emailTemplates/promotion_rejected.php';

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
    $promo_to_activate = $promotionRepository->getPromotionDetails($promo_id);

    $promotionRepository->updatePromotionStatus($promo_id, 'active');

    if ($promo_to_activate && !empty($promo_to_activate['created_by'])) {
        $requester = $userRepository->getUserDetails((string) $promo_to_activate['created_by']);
        if ($requester && !empty($requester['email'])) {
            $activationEmail = promotionActivatedEmailTemplate($promo_to_activate['name']);
            (new Mailer())->sendMail($requester['email'], $activationEmail['subject'], $activationEmail['body']);
        }
    }

    $success = t('promotion_activated');
}

// reject promotion request
if (isset($_POST['reject_promo_id'])) {
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        die(t('security_error'));
    }
    $promo_id = $_POST['reject_promo_id'];
    $reason = mb_substr(trim((string) ($_POST['reject_message'] ?? '')), 0, 1000);
    $promo_to_reject = $promotionRepository->getPromotionDetails($promo_id);

    if ($promo_to_reject && $promo_to_reject['status'] === 'pending'
        && $promotionRepository->deletePendingPromotion($promo_id)) {
        if (!empty($promo_to_reject['created_by'])) {
            $requester = $userRepository->getUserDetails((string) $promo_to_reject['created_by']);
            if ($requester && !empty($requester['email'])) {
                $rejectionEmail = promotionRejectedEmailTemplate($promo_to_reject['name'], $reason);
                (new Mailer())->sendMail($requester['email'], $rejectionEmail['subject'], $rejectionEmail['body']);
            }
        }
        $success = t('promotion_rejected');
    }
}

$promotions = $promotionRepository->getPendingPromotions();
?>
<!DOCTYPE html>
<html lang="<?= locale() ?>">
<head>
    <meta charset="UTF-8">
    <title><?= t('administration') ?> - CampusDrive</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="icon" type="image/png" href="assets/favicon.ico">
</head>
<body class="bg-light">
<nav class="navbar navbar-dark bg-dark mb-4 shadow">
    <div class="container">
        <span class="navbar-brand mb-0 h1"><?= t('administration') ?></span>
        <img src="assets/img/campusdrivewhite.webp" alt="Logo" class="img-fluid" style="max-height: 50px;">
        <div>
            <a href="settings.php" class="btn btn-outline-light btn-sm me-2"><?= t('settings') ?></a>
            <form method="POST" action="logout.php" class="d-inline">
                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                <button type="submit" class="btn btn-outline-light btn-sm"><?= t('logout') ?></button>
            </form>
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
                    <p class="card-text fs-4"><?= $userRepository->getTotalUsers() ?></p>
                </div>
            </div>
        </div>
        <div class="col-md-4 mb-3">
            <div class="card shadow-sm">
                <div class="card-body">
                    <h5 class="card-title"><?= t('total_promotions') ?></h5>
                    <p class="card-text fs-4"><?= $promotionRepository->getTotalPromotions() ?></p>
                </div>
            </div>
        </div>
        <div class="col-md-4 mb-3">
            <div class="card shadow-sm">
                <div class="card-body">
                    <h5 class="card-title"><?= t('total_files') ?></h5>
                    <p class="card-text fs-4"><?= $fileRepository->getTotalFiles() ?></p>
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
                                    <button type="button" class="btn btn-danger btn-sm" data-bs-toggle="modal" data-bs-target="#rejectModal<?= htmlspecialchars($promo['id']) ?>"><?= t('reject') ?></button>
                                    <div class="modal fade text-start" id="rejectModal<?= htmlspecialchars($promo['id']) ?>" tabindex="-1" aria-hidden="true">
                                        <div class="modal-dialog">
                                            <form method="POST" class="modal-content">
                                                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                                                <input type="hidden" name="reject_promo_id" value="<?= htmlspecialchars($promo['id']) ?>">
                                                <div class="modal-header">
                                                    <h5 class="modal-title"><?= t('reject_promotion') ?> : <?= htmlspecialchars($promo['name']) ?></h5>
                                                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                </div>
                                                <div class="modal-body">
                                                    <label class="form-label" for="reject_message<?= htmlspecialchars($promo['id']) ?>"><?= t('rejection_message') ?></label>
                                                    <textarea class="form-control" id="reject_message<?= htmlspecialchars($promo['id']) ?>" name="reject_message" rows="3" maxlength="1000"></textarea>
                                                </div>
                                                <div class="modal-footer">
                                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><?= t('cancel') ?></button>
                                                    <button type="submit" class="btn btn-danger"><?= t('reject') ?></button>
                                                </div>
                                            </form>
                                        </div>
                                    </div>
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
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
