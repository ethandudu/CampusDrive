<?php
use CampusDrive\Infrastructure\Database\LogRepository;

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

const ADMIN_LOGS_PER_PAGE = 50;

function adminLogEscape(string $value): string { return htmlspecialchars($value, ENT_QUOTES, 'UTF-8'); }

$logs = new LogRepository();
// GDPR accountability: record every consultation of the logs, before reading them.
$logs->logLogsAccess((string) $_SESSION['user_id']);
$filters = [
    'search' => isset($_GET['q']) && is_string($_GET['q']) ? mb_substr(trim($_GET['q']), 0, 255) : '',
    'action' => isset($_GET['action']) && is_string($_GET['action']) ? trim($_GET['action']) : '',
    'status' => isset($_GET['status']) && is_string($_GET['status']) ? trim($_GET['status']) : '',
];
$actions = $logs->getActions();
if ($filters['action'] !== '' && !in_array($filters['action'], $actions, true)) {
    $filters['action'] = '';
}
if (!in_array($filters['status'], [LogRepository::STATUS_SUCCESS, LogRepository::STATUS_FAILURE], true)) {
    $filters['status'] = '';
}

$total = $logs->countLogs($filters);
$pages = max(1, (int) ceil($total / ADMIN_LOGS_PER_PAGE));
$page = isset($_GET['page']) && is_string($_GET['page']) && ctype_digit($_GET['page']) ? min($pages, max(1, (int) $_GET['page'])) : 1;
$entries = $logs->getLogs(($page - 1) * ADMIN_LOGS_PER_PAGE, ADMIN_LOGS_PER_PAGE, $filters);

$pageUrl = static function (int $targetPage) use ($filters): string {
    $params = array_filter($filters, static fn(string $v): bool => $v !== '');
    $params = array_combine(array_map(static fn($k) => $k === 'search' ? 'q' : $k, array_keys($params)), $params);
    if ($targetPage > 1) {
        $params['page'] = $targetPage;
    }
    return 'admin_logs.php' . ($params ? '?' . http_build_query($params) : '');
};
$hasFilters = $filters['search'] !== '' || $filters['action'] !== '' || $filters['status'] !== '';
?>
<!DOCTYPE html>
<html lang="<?= adminLogEscape(locale()) ?>">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= t('admin_logs') ?> - CampusDrive</title>
    <link href="assets/vendor/bootstrap/css/bootstrap.min.css" rel="stylesheet">
    <link rel="icon" type="image/png" href="assets/favicon.ico">
</head>
<body class="bg-light">
<?php require 'utils/admin_header.php'; ?>
<main class="container">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h3><?= t('admin_logs') ?> <small class="text-muted fs-6"><?= adminLogEscape(t('admin_logs_total', ['count' => $total])) ?></small></h3>
        <a class="btn btn-outline-secondary" href="admin.php"><?= t('back') ?></a>
    </div>
    <form method="GET" action="admin_logs.php" class="row g-2 mb-3">
        <div class="col-12 col-md-5">
            <label for="log-search" class="visually-hidden"><?= t('admin_logs_search') ?></label>
            <input id="log-search" type="search" name="q" class="form-control" placeholder="<?= adminLogEscape(t('admin_logs_search')) ?>" value="<?= adminLogEscape($filters['search']) ?>">
        </div>
        <div class="col-6 col-md-3">
            <label for="log-action" class="visually-hidden"><?= t('action') ?></label>
            <select id="log-action" name="action" class="form-select" onchange="this.form.submit()">
                <option value=""><?= t('admin_logs_all_actions') ?></option>
                <?php foreach ($actions as $action): ?>
                    <option value="<?= adminLogEscape($action) ?>"<?= $filters['action'] === $action ? ' selected' : '' ?>><?= adminLogEscape($action) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-6 col-md-2">
            <label for="log-status" class="visually-hidden"><?= t('status') ?></label>
            <select id="log-status" name="status" class="form-select" onchange="this.form.submit()">
                <option value=""><?= t('admin_logs_all_statuses') ?></option>
                <option value="success"<?= $filters['status'] === 'success' ? ' selected' : '' ?>><?= t('admin_logs_success') ?></option>
                <option value="failure"<?= $filters['status'] === 'failure' ? ' selected' : '' ?>><?= t('admin_logs_failure') ?></option>
            </select>
        </div>
        <div class="col-12 col-md-2 d-flex gap-2">
            <button type="submit" class="btn btn-secondary flex-fill"><?= t('search') ?></button>
            <?php if ($hasFilters): ?><a href="admin_logs.php" class="btn btn-outline-secondary" title="<?= adminLogEscape(t('admin_logs_reset')) ?>">✕</a><?php endif; ?>
        </div>
    </form>
    <div class="card shadow-sm">
        <div class="table-responsive">
            <table class="table table-hover table-sm align-middle mb-0">
                <thead class="table-light"><tr>
                    <th scope="col"><?= t('admin_logs_date') ?></th>
                    <th scope="col"><?= t('email') ?></th>
                    <th scope="col"><?= t('action') ?></th>
                    <th scope="col"><?= t('status') ?></th>
                    <th scope="col"><?= t('admin_logs_ip') ?></th>
                </tr></thead>
                <tbody>
                <?php foreach ($entries as $entry): ?>
                    <tr>
                        <td class="text-nowrap"><?= adminLogEscape((string) $entry['created_at']) ?></td>
                        <td>
                            <?php if ($entry['user_email'] !== null && $entry['user_email'] !== ''): ?>
                                <?= adminLogEscape(html_entity_decode($entry['user_email'], ENT_QUOTES, 'UTF-8')) ?>
                            <?php else: ?><span class="text-muted">—</span><?php endif; ?>
                        </td>
                        <td><code><?= adminLogEscape((string) $entry['action']) ?></code></td>
                        <td>
                            <?php if ($entry['status'] === LogRepository::STATUS_SUCCESS): ?>
                                <span class="badge text-bg-success"><?= t('admin_logs_success') ?></span>
                            <?php else: ?>
                                <span class="badge text-bg-danger"><?= t('admin_logs_failure') ?></span>
                            <?php endif; ?>
                        </td>
                        <td class="text-nowrap"><?= adminLogEscape((string) $entry['ip_address']) ?></td>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$entries): ?>
                    <tr><td colspan="5" class="text-center text-muted py-4"><?= t('admin_logs_none') ?></td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php if ($pages > 1): ?>
        <nav class="d-flex justify-content-between align-items-center mt-3" aria-label="pagination">
            <a class="btn btn-outline-secondary btn-sm<?= $page <= 1 ? ' disabled' : '' ?>" href="<?= adminLogEscape($pageUrl($page - 1)) ?>"<?= $page <= 1 ? ' aria-disabled="true"' : '' ?>>← <?= t('admin_logs_previous') ?></a>
            <span class="text-muted small"><?= adminLogEscape(t('admin_logs_page', ['page' => $page, 'pages' => $pages])) ?></span>
            <a class="btn btn-outline-secondary btn-sm<?= $page >= $pages ? ' disabled' : '' ?>" href="<?= adminLogEscape($pageUrl($page + 1)) ?>"<?= $page >= $pages ? ' aria-disabled="true"' : '' ?>><?= t('admin_logs_next') ?> →</a>
        </nav>
    <?php endif; ?>
</main>
</body>
</html>
