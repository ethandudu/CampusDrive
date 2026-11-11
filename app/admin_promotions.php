<?php
use CampusDrive\Infrastructure\Database\PromotionRepository;
use CampusDrive\Infrastructure\Database\UserRepository;

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
$promotions = new PromotionRepository();
$users = new UserRepository();
$error = '';
$search = isset($_GET['q']) && is_string($_GET['q']) ? trim($_GET['q']) : '';
$searchQuery = $search !== '' ? '?q=' . urlencode($search) : '';
$successKeys = ['admin_updated', 'admin_promotion_deleted'];
$success = isset($_GET['success']) && is_string($_GET['success']) && in_array($_GET['success'], $successKeys, true)
    ? t($_GET['success']) : '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || !is_string($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        http_response_code(400);
        die(t('security_error'));
    }
    $action = $_POST['action'] ?? '';
    $promotionId = $_POST['promotion_id'] ?? '';
    try {
        if (!is_string($action) || !is_string($promotionId) || $promotionId === '') {
            throw new InvalidArgumentException('admin_record_missing');
        }
        if ($action === 'rename') {
            if (!isset($_POST['name']) || !is_string($_POST['name'])) {
                throw new InvalidArgumentException('admin_invalid_promotion_name');
            }
            $promotions->renameForAdmin($promotionId, $_POST['name']);
            $result = 'admin_updated';
        } elseif ($action === 'ambassador') {
            $old = $_POST['old_user_id'] ?? '';
            $new = $_POST['new_user_id'] ?? '';
            if (!is_string($old) || !is_string($new) || $new === '') {
                throw new InvalidArgumentException('admin_invalid_ambassador');
            }
            $promotions->replaceAmbassador($promotionId, $old === '' ? null : $old, $new);
            $result = 'admin_updated';
        } elseif ($action === 'delete') {
            $promotions->deleteForAdmin($promotionId);
            $result = 'admin_promotion_deleted';
        } else {
            throw new InvalidArgumentException('generic_error');
        }
        header('Location: admin_promotions.php?success=' . urlencode($result) . ($search !== '' ? '&q=' . urlencode($search) : ''));
        exit;
    } catch (InvalidArgumentException $e) {
        $allowed = ['admin_record_missing', 'admin_invalid_promotion_name', 'admin_invalid_ambassador', 'generic_error'];
        $error = t(in_array($e->getMessage(), $allowed, true) ? $e->getMessage() : 'generic_error');
    } catch (Throwable $e) {
        error_log('Admin promotion management: ' . $e->getMessage());
        $error = t($action === 'delete' ? 'admin_delete_promotion_error' : 'generic_error');
    }
}
$allPromotions = $promotions->getAllPromotions();
$allUsers = $users->getAllUsers();
$usersByPromotion = [];
foreach ($allUsers as $user) {
    if ($user['promotion_id'] !== null) {
        $usersByPromotion[$user['promotion_id']][] = $user;
    }
}
if ($search !== '') {
    $allPromotions = array_filter($allPromotions, static function (array $promotion) use ($search, $usersByPromotion): bool {
        $fields = [html_entity_decode($promotion['name'], ENT_QUOTES, 'UTF-8'), t($promotion['status'])];
        foreach ($usersByPromotion[$promotion['id']] ?? [] as $member) {
            if ($member['role'] === 'ambassador') {
                $fields[] = html_entity_decode($member['email'], ENT_QUOTES, 'UTF-8');
            }
        }
        foreach ($fields as $field) {
            if (mb_stripos($field, $search, 0, 'UTF-8') !== false) {
                return true;
            }
        }
        return false;
    });
}
function adminPromotionEscape(string $value): string { return htmlspecialchars($value, ENT_QUOTES, 'UTF-8'); }
?>
<!DOCTYPE html>
<html lang="<?= adminPromotionEscape(locale()) ?>">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= t('manage_promotions') ?> - CampusDrive</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="icon" type="image/png" href="assets/favicon.ico">
</head>
<body class="bg-light">
<?php require 'utils/admin_header.php'; ?>
<main class="container">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h3><?= t('manage_promotions') ?></h3>
        <a class="btn btn-outline-secondary" href="admin.php"><?= t('back') ?></a>
    </div>
    <?php if ($success): ?><div class="alert alert-success"><?= adminPromotionEscape($success) ?></div><?php endif; ?>
    <?php if ($error): ?><div class="alert alert-danger"><?= adminPromotionEscape($error) ?></div><?php endif; ?>
    <form method="GET" action="admin_promotions.php" class="mb-3">
        <label for="promotion-search" class="form-label"><?= t('admin_search_promotions') ?></label>
        <div class="input-group">
            <input id="promotion-search" type="search" name="q" class="form-control" value="<?= adminPromotionEscape($search) ?>">
            <button type="submit" class="btn btn-secondary"><?= t('search') ?></button>
            <?php if ($search !== ''): ?><a href="admin_promotions.php" class="btn btn-outline-secondary"><?= t('admin_clear_search') ?></a><?php endif; ?>
        </div>
    </form>
    <div class="card shadow-sm"><div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light"><tr>
                <th scope="col"><?= t('promotion_name') ?></th>
                <th scope="col"><?= t('status') ?></th>
                <th scope="col"><?= t('admin_current_ambassador') ?></th>
                <th scope="col"><?= t('action') ?></th>
            </tr></thead>
            <tbody>
            <?php if (!$allPromotions): ?><tr><td colspan="4" class="text-center text-muted p-4"><?= t('no_results') ?></td></tr><?php endif; ?>
            <?php foreach ($allPromotions as $promotion): ?>
                <?php
                $id = adminPromotionEscape((string) $promotion['id']);
                $name = html_entity_decode($promotion['name'], ENT_QUOTES, 'UTF-8');
                $members = $usersByPromotion[$promotion['id']] ?? [];
                $ambassadors = array_filter($members, static fn(array $u): bool => $u['role'] === 'ambassador');
                ?>
                <tr>
                    <td><?= adminPromotionEscape($name) ?></td>
                    <td><?= adminPromotionEscape(t($promotion['status'])) ?></td>
                    <td><?= $ambassadors ? adminPromotionEscape(implode(', ', array_map(static fn(array $u): string => html_entity_decode($u['email'], ENT_QUOTES, 'UTF-8'), $ambassadors))) : t('admin_no_ambassadors') ?></td>
                    <td><div class="d-flex flex-wrap gap-2">
                        <a class="btn btn-outline-secondary btn-sm" href="admin_users.php?promotion=<?= adminPromotionEscape(urlencode((string) $promotion['id'])) ?>"><?= t('admin_view_users') ?></a>
                        <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-toggle="modal" data-bs-target="#rename-modal-<?= $id ?>"><?= t('admin_rename_promotion') ?></button>
                        <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-toggle="modal" data-bs-target="#ambassador-modal-<?= $id ?>"><?= t('admin_replace_ambassador') ?></button>
                        <button type="button" class="btn btn-outline-danger btn-sm" data-bs-toggle="modal" data-bs-target="#delete-modal-<?= $id ?>"><?= t('admin_delete_promotion') ?></button>
                    </div></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div></div>
</main>
<?php foreach ($allPromotions as $promotion): ?>
    <?php
    $id = adminPromotionEscape((string) $promotion['id']);
    $name = html_entity_decode($promotion['name'], ENT_QUOTES, 'UTF-8');
    $members = $usersByPromotion[$promotion['id']] ?? [];
    $ambassadors = array_filter($members, static fn(array $u): bool => $u['role'] === 'ambassador');
    $eligible = array_filter($members, static fn(array $u): bool => $u['role'] === 'student' && $u['activation_token'] === null);
    ?>
    <div class="modal fade" id="rename-modal-<?= $id ?>" tabindex="-1" aria-labelledby="rename-title-<?= $id ?>" aria-hidden="true">
        <div class="modal-dialog"><form method="POST" action="admin_promotions.php<?= adminPromotionEscape($searchQuery) ?>" class="modal-content">
            <input type="hidden" name="csrf_token" value="<?= adminPromotionEscape($_SESSION['csrf_token']) ?>">
            <input type="hidden" name="action" value="rename"><input type="hidden" name="promotion_id" value="<?= $id ?>">
            <div class="modal-header"><h2 class="modal-title fs-5" id="rename-title-<?= $id ?>"><?= t('admin_rename_promotion') ?></h2>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="<?= t('close') ?>"></button></div>
            <div class="modal-body"><label class="form-label" for="name-<?= $id ?>"><?= t('promotion_name') ?></label>
                <input class="form-control" id="name-<?= $id ?>" name="name" maxlength="255" value="<?= adminPromotionEscape($name) ?>" required></div>
            <div class="modal-footer"><button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal"><?= t('cancel') ?></button>
                <button type="submit" class="btn btn-secondary"><?= t('save') ?></button></div>
        </form></div>
    </div>
    <div class="modal fade" id="ambassador-modal-<?= $id ?>" tabindex="-1" aria-labelledby="ambassador-title-<?= $id ?>" aria-hidden="true">
        <div class="modal-dialog"><form method="POST" action="admin_promotions.php<?= adminPromotionEscape($searchQuery) ?>" class="modal-content">
            <input type="hidden" name="csrf_token" value="<?= adminPromotionEscape($_SESSION['csrf_token']) ?>">
            <input type="hidden" name="action" value="ambassador"><input type="hidden" name="promotion_id" value="<?= $id ?>">
            <div class="modal-header"><h2 class="modal-title fs-5" id="ambassador-title-<?= $id ?>"><?= t('admin_replace_ambassador') ?></h2>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="<?= t('close') ?>"></button></div>
            <div class="modal-body">
                <p><strong><?= adminPromotionEscape($name) ?></strong></p>
                <?php if ($eligible): ?>
                    <label class="form-label" for="old-<?= $id ?>"><?= t('admin_current_ambassador') ?></label>
                    <select class="form-select mb-3" id="old-<?= $id ?>" name="old_user_id">
                        <?php foreach ($ambassadors as $ambassador): ?><option value="<?= adminPromotionEscape((string) $ambassador['id']) ?>"><?= adminPromotionEscape(html_entity_decode($ambassador['email'], ENT_QUOTES, 'UTF-8')) ?></option><?php endforeach; ?>
                        <option value=""><?= t('admin_add_ambassador') ?></option>
                    </select>
                    <label class="form-label" for="new-<?= $id ?>"><?= t('admin_new_ambassador') ?></label>
                    <select class="form-select" id="new-<?= $id ?>" name="new_user_id" required>
                        <?php foreach ($eligible as $student): ?><option value="<?= adminPromotionEscape((string) $student['id']) ?>"><?= adminPromotionEscape(html_entity_decode($student['email'], ENT_QUOTES, 'UTF-8')) ?></option><?php endforeach; ?>
                    </select>
                <?php else: ?><p class="text-muted mb-0"><?= t('admin_no_eligible_students') ?></p><?php endif; ?>
            </div>
            <div class="modal-footer"><button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal"><?= t('cancel') ?></button>
                <?php if ($eligible): ?><button type="submit" class="btn btn-secondary"><?= t('admin_replace_ambassador') ?></button><?php endif; ?></div>
        </form></div>
    </div>
    <div class="modal fade" id="delete-modal-<?= $id ?>" tabindex="-1" aria-labelledby="delete-title-<?= $id ?>" aria-hidden="true">
        <div class="modal-dialog"><form method="POST" action="admin_promotions.php<?= adminPromotionEscape($searchQuery) ?>" class="modal-content">
            <input type="hidden" name="csrf_token" value="<?= adminPromotionEscape($_SESSION['csrf_token']) ?>">
            <input type="hidden" name="action" value="delete"><input type="hidden" name="promotion_id" value="<?= $id ?>">
            <div class="modal-header"><h2 class="modal-title fs-5" id="delete-title-<?= $id ?>"><?= t('admin_delete_promotion') ?></h2>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="<?= t('close') ?>"></button></div>
            <div class="modal-body"><p><strong><?= adminPromotionEscape($name) ?></strong></p><p class="mb-0"><?= t('admin_delete_promotion_warning') ?></p></div>
            <div class="modal-footer"><button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal"><?= t('cancel') ?></button>
                <button type="submit" class="btn btn-danger"><?= t('admin_delete_promotion') ?></button></div>
        </form></div>
    </div>
<?php endforeach; ?>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
