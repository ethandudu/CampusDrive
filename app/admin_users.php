<?php
use CampusDrive\Infrastructure\Database\PromotionRepository;
use CampusDrive\Infrastructure\Database\UserRepository;

require_once 'utils/db.php';
require_once 'utils/session.php';
require_once 'utils/i18n.php';
require_once 'utils/mail.php';
require_once 'utils/emailTemplates/password_reset.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: login.php');
    exit;
}
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$users = new UserRepository();
$promotions = new PromotionRepository();
$error = '';
$search = isset($_GET['q']) && is_string($_GET['q']) ? trim($_GET['q']) : '';
$promotionFilter = isset($_GET['promotion']) && is_string($_GET['promotion']) ? trim($_GET['promotion']) : '';
$filterParams = [];
if ($search !== '') {
    $filterParams['q'] = $search;
}
if ($promotionFilter !== '') {
    $filterParams['promotion'] = $promotionFilter;
}
$searchQuery = $filterParams ? '?' . http_build_query($filterParams) : '';
$successKeys = ['admin_updated', 'admin_user_deleted', 'admin_reset_sent'];
$success = isset($_GET['success']) && is_string($_GET['success']) && in_array($_GET['success'], $successKeys, true)
    ? t($_GET['success']) : '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || !is_string($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        http_response_code(400);
        die(t('security_error'));
    }
    $action = $_POST['action'] ?? '';
    $userId = $_POST['user_id'] ?? '';
    try {
        if (!is_string($action) || !is_string($userId) || $userId === '') {
            throw new InvalidArgumentException('admin_record_missing');
        }
        if ($action === 'email') {
            if (!isset($_POST['email']) || !is_string($_POST['email'])) {
                throw new InvalidArgumentException('invalid_admin_email');
            }
            $users->updateEmail($userId, $_POST['email']);
            $result = 'admin_updated';
        } elseif ($action === 'promotion') {
            $promotionId = $_POST['promotion_id'] ?? '';
            if (!is_string($promotionId)) {
                throw new InvalidArgumentException('admin_record_missing');
            }
            $users->updatePromotionForAdmin($userId, $promotionId === '' ? null : $promotionId, ($_POST['confirm_move'] ?? '') === '1');
            $result = 'admin_updated';
        } elseif ($action === 'delete') {
            if (!$users->deleteUserForAdmin($userId, (string) $_SESSION['user_id'])) {
                throw new InvalidArgumentException('admin_record_missing');
            }
            $result = 'admin_user_deleted';
        } elseif ($action === 'reset') {
            $token = bin2hex(random_bytes(32));
            $accountEmail = $users->createPasswordResetTokenForUser($userId, hash('sha256', $token), time() + 3600);
            if ($accountEmail === null) {
                throw new InvalidArgumentException('admin_record_missing');
            }
            $link = rtrim(APP_BASE_URL, '/') . '/reset_password.php?token=' . urlencode($token);
            $template = passwordResetEmailTemplate($link);
            if (!(new Mailer())->sendMail(html_entity_decode($accountEmail, ENT_QUOTES, 'UTF-8'), $template['subject'], $template['body'])) {
                throw new RuntimeException('admin_mail_failed');
            }
            $result = 'admin_reset_sent';
        } else {
            throw new InvalidArgumentException('generic_error');
        }
        header('Location: admin_users.php?' . http_build_query(['success' => $result] + $filterParams));
        exit;
    } catch (InvalidArgumentException $e) {
        $allowed = ['admin_record_missing', 'invalid_admin_email', 'admin_email_taken', 'admin_cannot_delete_self', 'admin_confirm_ambassador_move', 'generic_error'];
        $error = t(in_array($e->getMessage(), $allowed, true) ? $e->getMessage() : 'generic_error');
    } catch (Throwable $e) {
        error_log('Admin user management: ' . $e->getMessage());
        $error = t($e->getMessage() === 'admin_mail_failed' ? 'admin_mail_failed' : 'generic_error');
    }
}

$allUsers = $users->getAllUsers();
$allPromotions = $promotions->getAllPromotions();
$selectedPromotion = null;
if ($promotionFilter !== '') {
    foreach ($allPromotions as $promotion) {
        if ($promotion['id'] === $promotionFilter) {
            $selectedPromotion = $promotion;
            break;
        }
    }
    if ($selectedPromotion === null) {
        $error = t('admin_record_missing');
        $allUsers = [];
    } else {
        $allUsers = array_filter($allUsers, static fn(array $user): bool => $user['promotion_id'] === $promotionFilter);
    }
}
if ($search !== '') {
    $allUsers = array_filter($allUsers, static function (array $user) use ($search): bool {
        $fields = [
            html_entity_decode($user['email'], ENT_QUOTES, 'UTF-8'),
            t($user['role']),
            html_entity_decode($user['promo_name'] ?? t('admin_unassigned'), ENT_QUOTES, 'UTF-8'),
        ];
        foreach ($fields as $field) {
            if (mb_stripos($field, $search, 0, 'UTF-8') !== false) {
                return true;
            }
        }
        return false;
    });
}
function adminUserEscape(string $value): string { return htmlspecialchars($value, ENT_QUOTES, 'UTF-8'); }
?>
<!DOCTYPE html>
<html lang="<?= adminUserEscape(locale()) ?>">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= t('manage_users') ?> - CampusDrive</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="icon" type="image/png" href="assets/favicon.ico">
</head>
<body class="bg-light">
<?php require 'utils/admin_header.php'; ?>
<main class="container">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h3><?= t('manage_users') ?></h3>
        <a class="btn btn-outline-secondary" href="<?= $promotionFilter !== '' ? 'admin_promotions.php' : 'admin.php' ?>"><?= t('back') ?></a>
    </div>
    <?php if ($success): ?><div class="alert alert-success"><?= adminUserEscape($success) ?></div><?php endif; ?>
    <?php if ($error): ?><div class="alert alert-danger"><?= adminUserEscape($error) ?></div><?php endif; ?>
    <?php if ($selectedPromotion): ?>
        <p><?= t('admin_promotion_users') ?>: <strong><?= adminUserEscape(html_entity_decode($selectedPromotion['name'], ENT_QUOTES, 'UTF-8')) ?></strong>
            <a href="admin_users.php"><?= t('admin_show_all_users') ?></a>
        </p>
    <?php endif; ?>
    <form method="GET" action="admin_users.php" class="mb-3">
        <?php if ($promotionFilter !== ''): ?><input type="hidden" name="promotion" value="<?= adminUserEscape($promotionFilter) ?>"><?php endif; ?>
        <label for="user-search" class="form-label"><?= t('admin_search_users') ?></label>
        <div class="input-group">
            <input id="user-search" type="search" name="q" class="form-control" value="<?= adminUserEscape($search) ?>">
            <button type="submit" class="btn btn-secondary"><?= t('search') ?></button>
            <?php if ($search !== ''): ?><a href="admin_users.php<?= $promotionFilter !== '' ? '?promotion=' . adminUserEscape(urlencode($promotionFilter)) : '' ?>" class="btn btn-outline-secondary"><?= t('admin_clear_search') ?></a><?php endif; ?>
        </div>
    </form>
    <div class="card shadow-sm">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light"><tr>
                    <th scope="col"><?= t('email') ?></th>
                    <th scope="col"><?= t('current_role') ?></th>
                    <th scope="col"><?= t('promotion_name') ?></th>
                    <th scope="col"><?= t('action') ?></th>
                </tr></thead>
                <tbody>
                <?php if (!$allUsers): ?>
                    <tr><td colspan="4" class="text-center text-muted p-4"><?= t('no_results') ?></td></tr>
                <?php endif; ?>
                <?php foreach ($allUsers as $user): ?>
                    <?php $id = adminUserEscape((string) $user['id']); $email = html_entity_decode($user['email'], ENT_QUOTES, 'UTF-8'); ?>
                    <tr>
                        <td><?= adminUserEscape($email) ?></td>
                        <td><?= adminUserEscape(t($user['role'])) ?></td>
                        <td><?= adminUserEscape(html_entity_decode($user['promo_name'] ?? t('admin_unassigned'), ENT_QUOTES, 'UTF-8')) ?></td>
                        <td>
                            <div class="d-flex flex-wrap gap-2">
                                <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-toggle="modal" data-bs-target="#email-modal-<?= $id ?>"><?= t('admin_change_email') ?></button>
                                <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-toggle="modal" data-bs-target="#promotion-modal-<?= $id ?>"><?= t('admin_change_promotion') ?></button>
                                <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-toggle="modal" data-bs-target="#mail-modal-<?= $id ?>"><?= t('admin_send_email') ?></button>
                                <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-toggle="modal" data-bs-target="#reset-modal-<?= $id ?>"><?= t('admin_reset') ?></button>
                                <?php if ($user['id'] !== $_SESSION['user_id']): ?>
                                    <button type="button" class="btn btn-outline-danger btn-sm" data-bs-toggle="modal" data-bs-target="#delete-modal-<?= $id ?>"><?= t('delete_account') ?></button>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</main>
<?php foreach ($allUsers as $user): ?>
    <?php $id = adminUserEscape((string) $user['id']); $email = html_entity_decode($user['email'], ENT_QUOTES, 'UTF-8'); ?>
    <div class="modal fade" id="email-modal-<?= $id ?>" tabindex="-1" aria-labelledby="email-title-<?= $id ?>" aria-hidden="true">
        <div class="modal-dialog">
            <form method="POST" action="admin_users.php<?= adminUserEscape($searchQuery) ?>" class="modal-content">
                <input type="hidden" name="csrf_token" value="<?= adminUserEscape($_SESSION['csrf_token']) ?>">
                <input type="hidden" name="action" value="email"><input type="hidden" name="user_id" value="<?= $id ?>">
                <div class="modal-header">
                    <h2 class="modal-title fs-5" id="email-title-<?= $id ?>"><?= t('admin_change_email') ?></h2>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="<?= t('close') ?>"></button>
                </div>
                <div class="modal-body">
                    <label class="form-label" for="email-<?= $id ?>"><?= t('email') ?></label>
                    <input class="form-control" type="email" id="email-<?= $id ?>" name="email" maxlength="255" value="<?= adminUserEscape($email) ?>" required>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal"><?= t('cancel') ?></button>
                    <button type="submit" class="btn btn-secondary"><?= t('save') ?></button>
                </div>
            </form>
        </div>
    </div>
    <div class="modal fade" id="promotion-modal-<?= $id ?>" tabindex="-1" aria-labelledby="promotion-title-<?= $id ?>" aria-hidden="true">
        <div class="modal-dialog">
            <form method="POST" action="admin_users.php<?= adminUserEscape($searchQuery) ?>" class="modal-content">
                <input type="hidden" name="csrf_token" value="<?= adminUserEscape($_SESSION['csrf_token']) ?>">
                <input type="hidden" name="action" value="promotion"><input type="hidden" name="user_id" value="<?= $id ?>">
                <div class="modal-header">
                    <h2 class="modal-title fs-5" id="promotion-title-<?= $id ?>"><?= t('admin_change_promotion') ?></h2>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="<?= t('close') ?>"></button>
                </div>
                <div class="modal-body">
                    <p><?= adminUserEscape($email) ?></p>
                    <label class="form-label" for="promotion-<?= $id ?>"><?= t('promotion_name') ?></label>
                    <select class="form-select mb-2" id="promotion-<?= $id ?>" name="promotion_id">
                        <option value=""><?= t('admin_unassigned') ?></option>
                        <?php foreach ($allPromotions as $promotion): ?>
                            <option value="<?= adminUserEscape((string) $promotion['id']) ?>" <?= $user['promotion_id'] === $promotion['id'] ? 'selected' : '' ?>><?= adminUserEscape(html_entity_decode($promotion['name'], ENT_QUOTES, 'UTF-8')) ?> (<?= adminUserEscape(t($promotion['status'])) ?>)</option>
                        <?php endforeach; ?>
                    </select>
                    <?php if ($user['role'] === 'ambassador'): ?>
                        <label class="d-block"><input type="checkbox" name="confirm_move" value="1"> <?= t('admin_ambassador_move_help') ?></label>
                    <?php endif; ?>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal"><?= t('cancel') ?></button>
                    <button type="submit" class="btn btn-secondary"><?= t('save') ?></button>
                </div>
            </form>
        </div>
    </div>
    <div class="modal fade" id="mail-modal-<?= $id ?>" tabindex="-1" aria-labelledby="mail-title-<?= $id ?>" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h2 class="modal-title fs-5" id="mail-title-<?= $id ?>"><?= t('admin_send_email') ?></h2>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="<?= t('close') ?>"></button>
                </div>
                <div class="modal-body"><?= t('admin_mail_confirm') ?> <strong><?= adminUserEscape($email) ?></strong>?</div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal"><?= t('cancel') ?></button>
                    <a class="btn btn-secondary" href="mailto:<?= adminUserEscape(rawurlencode($email)) ?>"><?= t('admin_send_email') ?></a>
                </div>
            </div>
        </div>
    </div>
    <div class="modal fade" id="reset-modal-<?= $id ?>" tabindex="-1" aria-labelledby="reset-title-<?= $id ?>" aria-hidden="true">
        <div class="modal-dialog">
            <form method="POST" action="admin_users.php<?= adminUserEscape($searchQuery) ?>" class="modal-content">
                <input type="hidden" name="csrf_token" value="<?= adminUserEscape($_SESSION['csrf_token']) ?>">
                <input type="hidden" name="action" value="reset"><input type="hidden" name="user_id" value="<?= $id ?>">
                <div class="modal-header">
                    <h2 class="modal-title fs-5" id="reset-title-<?= $id ?>"><?= t('admin_reset') ?></h2>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="<?= t('close') ?>"></button>
                </div>
                <div class="modal-body"><?= t('admin_reset_confirm') ?> <strong><?= adminUserEscape($email) ?></strong>?</div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal"><?= t('cancel') ?></button>
                    <button type="submit" class="btn btn-secondary"><?= t('admin_reset') ?></button>
                </div>
            </form>
        </div>
    </div>
    <?php if ($user['id'] !== $_SESSION['user_id']): ?>
        <div class="modal fade" id="delete-modal-<?= $id ?>" tabindex="-1" aria-labelledby="delete-title-<?= $id ?>" aria-hidden="true">
            <div class="modal-dialog">
                <form method="POST" action="admin_users.php<?= adminUserEscape($searchQuery) ?>" class="modal-content">
                    <input type="hidden" name="csrf_token" value="<?= adminUserEscape($_SESSION['csrf_token']) ?>">
                    <input type="hidden" name="action" value="delete"><input type="hidden" name="user_id" value="<?= $id ?>">
                    <div class="modal-header">
                        <h2 class="modal-title fs-5" id="delete-title-<?= $id ?>"><?= t('delete_account') ?></h2>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="<?= t('close') ?>"></button>
                    </div>
                    <div class="modal-body">
                        <p><strong><?= adminUserEscape($email) ?></strong></p>
                        <p class="mb-0"><?= t('admin_delete_user_warning') ?></p>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal"><?= t('cancel') ?></button>
                        <button type="submit" class="btn btn-danger"><?= t('delete_account') ?></button>
                    </div>
                </form>
            </div>
        </div>
    <?php endif; ?>
<?php endforeach; ?>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
