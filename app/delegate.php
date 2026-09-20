<?php
require_once 'utils/db.php';
require_once 'utils/session.php';
require_once 'utils/i18n.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'delegate') {
    header('Location: login.php');
    exit;
}

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

if (Database::getPromotionStatus($_SESSION['promotion_id']) == 'pending') {
    header('Location: settings.php?error=promotion_inactive');
    exit;
}

$success = '';

if (isset($_GET['folderId'])) {
    $folderId = isset($_GET['folderId']) && $_GET['folderId'] !== 'null' && $_GET['folderId'] !== ''
        ? (int) $_GET['folderId']
        : null;
    $folderDetails = Database::getPromotionFolderFiles($folderId, (int) $_SESSION['promotion_id']);

    header('Content-Type: application/json');
    echo json_encode($folderDetails);
    exit;
}

// Handle invitation generation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['invite_email'])) {
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        die(t('security_error'));
    }
    $token = bin2hex(random_bytes(32));

    Database::createInvitation($_POST['invite_email'], $_SESSION['promotion_id'], $token);
}

// Create new folder
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'create_folder') {
    $folder_name = trim($_POST['folder_name']);
    $parent_id = isset($_POST['parent_id']) && $_POST['parent_id'] !== '' && $_POST['parent_id'] !== 'null'
        ? (int) $_POST['parent_id']
        : null;
    if (!empty($folder_name)) {
        Database::createFolder($_SESSION['promotion_id'], $parent_id, $folder_name);
    }
}

// Delete folder
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_folder') {
    $folder_id = (int) $_POST['element_id'];
    Database::deletePromotionFolder($folder_id);
}

// Delete file
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_file') {
    $file_id = (int) $_POST['element_id'];
    Database::rejectPromotionFile($file_id);
}

$invitations = Database::getPromotionInvitations($_SESSION['promotion_id']);

// Validation or rejection of files
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['file_action'])) {
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        die(t('security_error'));
    }
    $file_id = (int) $_POST['file_id'];
    if ($_POST['file_action'] === 'approve') {
        Database::approvePromotionFile($file_id);
    } elseif ($_POST['file_action'] === 'reject') {
        Database::rejectPromotionFile($file_id);
    }
}

$pending_files = Database::getPromotionPendingFiles($_SESSION['promotion_id']);
$promo = Database::getPromotionDetails($_SESSION['promotion_id']);

?>
<!DOCTYPE html>
<html lang="<?= locale() ?>">
<head>
    <meta charset="UTF-8">
    <title><?= t('delegate_area') ?> - CampusDrive</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
<nav class="navbar navbar-dark bg-success mb-4 shadow">
    <div class="container">
        <span class="navbar-brand mb-0 h1">CampusDrive - <?= t('delegate_area') ?> (<?= htmlspecialchars($promo['name']) ?>)</span>
        <div>
            <a href="student.php" class="btn btn-outline-light btn-sm me-2"><?= t('home') ?></a>
            <a href="delegate.php" class="btn btn-light btn-sm me-2"><?= t('delegate_area') ?></a>
            <a href="settings.php" class="btn btn-outline-light btn-sm me-2"><?= t('settings') ?></a>
            <a href="logout.php" class="btn btn-outline-danger btn-sm"><?= t('logout') ?></a>
        </div>
    </div>
</nav>

<div class="container">
    <div class="row">
        <div class="col-md-4">
            <div class="card shadow-sm mb-4">
                <div class="card-header bg-white fw-bold"><?= t('invite_student') ?></div>
                <div class="card-body">
                    <?php if ($success): ?>
                        <div class="alert alert-info py-2"><?= $success ?></div>
                    <?php endif; ?>
                    <form method="POST">
                        <div class="mb-3">
                            <label class="form-label"><?= t('student_email') ?></label>
                            <input type="email" class="form-control" name="invite_email" required>
                        </div>
                        <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                        <button type="submit" class="btn btn-success w-100"><?= t('generate_invitation') ?></button>
                    </form>
                </div>
            </div>
        </div>
        <div class="col-md-8">
            <div class="card shadow-sm">
                <div class="card-header bg-white fw-bold"><?= t('sent_invitations') ?></div>
                <table class="table table-hover mb-0">
                    <thead class="table-light">
                    <tr>
                        <th>Email</th>
                        <th><?= t('status') ?></th>
                        <th><?= t('date') ?></th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($invitations as $inv): ?>
                        <tr>
                            <td><?= htmlspecialchars($inv['email']) ?></td>
                            <td>
                                <?php if ($inv['is_used']): ?>
                                    <span class="badge bg-secondary"><?= t('registered') ?></span>
                                <?php else: ?>
                                    <span class="badge bg-warning text-dark"><?= t('pending') ?></span>
                                <?php endif; ?>
                            </td>
                            <td><?= date('d/m/Y H:i', strtotime($inv['created_at'])) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if(empty($invitations)): ?>
                        <tr><td colspan="3" class="text-center text-muted"><?= t('no_invitations') ?></td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
            <div class="card shadow-sm mt-4">
                <div class="card-header bg-white fw-bold"><?= t('files_pending') ?></div>
                <div class="card-body p-0">
                    <?php if (empty($pending_files)): ?>
                        <p class="text-muted p-3 mb-0"><?= t('no_files_pending') ?></p>
                    <?php else: ?>
                        <table class="table table-hover mb-0">
                            <thead class="table-light">
                            <tr>
                                <th><?= t('file') ?></th>
                                <th><?= t('author') ?></th>
                                <th><?= t('action') ?></th>
                            </tr>
                            </thead>
                            <tbody>
                            <?php foreach ($pending_files as $pf): ?>
                                <tr>
                                    <td class="align-middle">
                                        <b><?= htmlspecialchars($pf['original_name']) ?></b>
                                    </td>
                                    <td class="align-middle"><?= htmlspecialchars($pf['uploader_email']) ?></td>
                                    <td class="align-middle">
                                        <a href="view.php?id=<?= $pf['id'] ?>" target="_blank" class="btn btn-sm btn-outline-info me-2"><?= t('preview') ?></a>
                                        <form method="POST" class="d-inline">
                                            <input type="hidden" name="file_id" value="<?= $pf['id'] ?>">
                                            <button type="submit" name="file_action" value="approve" class="btn btn-sm btn-success"><?= t('approve') ?></button>
                                            <button type="submit" name="file_action" value="reject" class="btn btn-sm btn-danger"><?= t('reject') ?></button>
                                            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>
            </div>
            <div class="card shadow-sm mt-4">
                <div class="card-header bg-white fw-bold" id="folderHeader">
                    <div class="d-flex justify-content-between align-items-center">
                        <span><?= t('folders') ?></span>
                        <button class="btn btn-sm btn-success" data-bs-toggle="modal" data-bs-target="#createFolderModal"><?= t('create_folder') ?></button>
                    </div>
                </div>
                <div class="card-body p-0">
                    <table class="table table-hover mb-0" id="folderTable">
                        <thead class="table-light">
                        <tr>
                            <th><?= t('name') ?></th>
                            <th><?= t('created_at') ?></th>
                            <th><?= t('action') ?></th>
                        </tr>
                        </thead>
                        <tbody>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
<div class="modal fade" id="createFolderModal" tabindex="-1" aria-labelledby="createFolderModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form id="createFolderForm" method="POST">
                <div class="modal-header">
                    <h5 class="modal-title" id="createFolderModalLabel"><?= t('new_folder') ?></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="<?= t('cancel') ?>"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="folderNameInput" class="form-label"><?= t('folder_name') ?></label>
                        <input type="text" class="form-control" id="folderNameInput" name="folder_name" required>
                    </div>
                    <input type="hidden" name="action" value="create_folder">
                    <input type="hidden" name="parent_id" value="">
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><?= t('cancel') ?></button>
                    <button type="submit" class="btn btn-success"><?= t('create_folder') ?></button>
                </div>
            </form>
        </div>
    </div>
</div>
<div class="modal fade" id="deleteModal" tabindex="-1" aria-labelledby="deleteModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form id="deleteForm" method="POST">
                <div class="modal-header">
                    <h5 class="modal-title" id="deleteModalLabel"><?= t('delete_item') ?></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="<?= t('cancel') ?>"></button>
                </div>
                <div class="modal-body">
                    <p><?= t('delete_confirmation') ?></p>
                    <input type="hidden" name="action" value="delete_folder">
                    <input type="hidden" name="element_id" value="">
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><?= t('cancel') ?></button>
                    <button type="submit" class="btn btn-danger"><?= t('delete') ?></button>
                </div>
            </form>
        </div>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js"></script>
<script>
    const locale = <?= json_encode(locale()) ?>;
    const labels = <?= json_encode([
        'back' => t('back'),
        'delete' => t('delete'),
        'view' => t('view'),
        'emptyFolder' => t('empty_folder'),
        'createFolderIn' => t('create_folder_in'),
        'root' => t('root'),
        'error' => t('generic_error'),
    ]) ?>;

    function openFolder(folderId) {
        fetch(`delegate.php?folderId=${folderId}`)
            .then(response => response.json())
            .then(data => {
                const rows = [];
                let hasContent = false;
                const currentFolder = data.folder;

                if (currentFolder) {
                    const previousFolderId = currentFolder.parent_id !== null ? currentFolder.parent_id : 'null';
                    rows.push(`
                        <tr>
                            <td>
                                <button class="btn btn-sm btn-link p-0 text-decoration-none" onclick="openFolder(${previousFolderId})">
                                    📁.. / ${labels.back}
                                </button>
                            </td>
                            <td></td>
                            <td></td>
                        </tr>
                    `);
                }

                if (data.folders && data.folders.length) {
                    hasContent = true;
                    data.folders.forEach(folder => {
                        rows.push(`
                            <tr>
                                <td>
                                    <button class="btn btn-sm btn-link p-0 text-decoration-none fw-bold" onclick="openFolder(${folder.id})">
                                        📁 ${folder.name}
                                    </button>
                                </td>
                                <td>${folder.created_at ? new Date(folder.created_at).toLocaleString(locale) : ''}</td>
                                <td>
                                    <button class="btn btn-sm btn-outline-danger" onclick="deleteFolder(${folder.id})">${labels.delete}</button>
                                </td>
                            </tr>
                        `);
                    });
                }

                if (data.files && data.files.length) {
                    hasContent = true;
                    data.files.forEach(file => {
                        rows.push(`
                            <tr>
                                <td>📄 ${file.original_name}</td>
                                <td>${file.created_at ? new Date(file.created_at).toLocaleString(locale) : ''}</td>
                                <td><a href="view.php?id=${file.id}" target="_blank" class="btn btn-sm btn-outline-info">${labels.view}</a><button class="btn btn-sm btn-outline-danger" onclick="deleteFile(${file.id})">${labels.delete}</button></td>
                            </tr>
                        `);
                    });
                }

                if (!hasContent) {
                    rows.push(`<tr><td colspan="3" class="text-center text-muted py-3">${labels.emptyFolder}</td></tr>`);
                }

                document.querySelector('#folderTable tbody').innerHTML = rows.join('');
                let button = document.querySelector('#folderHeader button');
                button.textContent = labels.createFolderIn.replace('%name%', currentFolder ? currentFolder.name : labels.root);
                document.querySelector('#createFolderForm input[name="parent_id"]').value = folderId ?? 'null';
            })
            .catch(error => console.error(labels.error, error));
    }

    function deleteFolder(folderId) {
        document.querySelector('#deleteForm input[name="element_id"]').value = folderId;
        new bootstrap.Modal(document.getElementById('deleteModal')).show();
    }

    function deleteFile(fileId) {
        document.querySelector('#deleteForm input[name="element_id"]').value = fileId;
        new bootstrap.Modal(document.getElementById('deleteModal')).show();
    }

    document.addEventListener('DOMContentLoaded', function () {
        openFolder(null);
    });
</script>
</body>
</html>
