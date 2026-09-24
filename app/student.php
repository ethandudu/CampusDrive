<?php
require_once 'utils/db.php';
require_once 'utils/session.php';
require_once 'utils/i18n.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

if (!$_SESSION['promotion_id']) {
    header('Location: settings.php');
    exit;
}

$user_id = $_SESSION['user_id'];
$promo_id = $_SESSION['promotion_id'];
$message = ''; $error = '';

if (Database::getPromotionStatus($promo_id) == 'pending') {
    header('Location: settings.php?error=promotion_inactive');
    exit;
}

if (isset($_GET['folderId'])) {
    $folder_id = ($_GET['folderId']) && $_GET['folderId'] !== 'null' && $_GET['folderId'] !== ''
        ? (int) $_GET['folderId']
        : null;
    $folder_details = Database::getPromotionFolderFiles($folder_id, (int) $promo_id);

    header('Content-Type: application/json');
    echo json_encode($folder_details);
    exit;
}

// Traitement de l'upload
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['file_upload'])) {
    $file = $_FILES['file_upload'];
    $folder_id = isset($_POST['folder_id']) && $_POST['folder_id'] !== '' && $_POST['folder_id'] !== 'null'
        ? (int) $_POST['folder_id']
        : null;
    $display_name = isset($_POST['file_name']) && trim($_POST['file_name']) !== ''
        ? trim($_POST['file_name'])
        : $file['name'];

    if ($file['error'] === UPLOAD_ERR_OK) {
        $allowed_types = [
            'application/pdf' => 'pdf',
            'image/jpeg'      => 'jpg',
            'image/png'       => 'png',
            'image/gif'       => 'gif',
            'video/mp4'       => 'mp4',
            'video/webm'      => 'webm'
        ];

        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime_type = finfo_file($finfo, $file['tmp_name']);

        if (array_key_exists($mime_type, $allowed_types)) {
            $ext = $allowed_types[$mime_type];
            $stored_name = uniqid('file_', true) . '.' . $ext;
            $upload_dir = __DIR__ . '/uploads/';

            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }

            $destination = $upload_dir . $stored_name;

            if (move_uploaded_file($file['tmp_name'], $destination)) {
                Database::createFileRecord($user_id, $promo_id, $display_name, $stored_name, $mime_type, $folder_id);
                $message = t('file_uploaded');
            } else {
                $error = t('file_save_error');
            }
        } else {
            $error = t('file_type_error');
        }
    } else {
        $error = t('file_upload_error');
    }
}

// Récupération des fichiers en attente de l'utilisateur connecté
$my_pending_files = Database::getUserPendingFiles($user_id);
?>
<!DOCTYPE html>
<html lang="<?= locale() ?>">
<head>
    <meta charset="UTF-8">
    <title><?= t('student_area') ?> - CampusDrive</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
<nav class="navbar navbar-dark bg-primary mb-4 shadow">
    <div class="container">
        <span class="navbar-brand mb-0 h1">CampusDrive - <?= t('student_area') ?></span>
        <div>
            <a href="student.php" class="btn btn-light btn-sm me-2"><?= t('home') ?></a>
            <?php if ($_SESSION['role'] === 'delegate'): ?>
                <a href="delegate.php" class="btn btn-outline-light btn-sm me-2"><?= t('delegate_area') ?></a>
            <?php endif; ?>
            <a href="settings.php" class="btn btn-outline-light btn-sm me-2"><?= t('settings') ?></a>
            <a href="logout.php" class="btn btn-outline-danger btn-sm"><?= t('logout') ?></a>
        </div>
    </div>
</nav>

<div class="container">
    <?php if ($message): ?><div class="alert alert-success py-2"><?= $message ?></div><?php endif; ?>
    <?php if ($error): ?><div class="alert alert-danger py-2"><?= $error ?></div><?php endif; ?>
    <div class="row">
        <div class="col-md-4">
            <?php if (!empty($my_pending_files)): ?>
                <div class="card shadow-sm mb-4">
                    <div class="card-header bg-warning text-dark fw-bold"><?= t('pending_uploads') ?></div>
                    <ul class="list-group list-group-flush">
                        <?php foreach ($my_pending_files as $p_file): ?>
                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                <small class="text-truncate" style="max-width: 180px;"><?= htmlspecialchars($p_file['original_name']) ?></small>
                                <span class="badge bg-secondary"><?= t('pending') ?></span>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>
        </div>

        <!-- Fichiers partagés dans la promotion -->
        <div class="col-md-8">
            <div class="card shadow-sm">
                <div class="card-header bg-white fw-bold d-flex justify-content-between align-items-center" id="folderHeader">
                    <span id="folderHeaderLabel"><?= t('folders') ?></span>
                    <button type="button" class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#uploadFileModal"><?= t('share_document') ?></button>
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
                        <tbody></tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
<div class="modal fade" id="uploadFileModal" tabindex="-1" aria-labelledby="uploadFileModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" enctype="multipart/form-data">
                <div class="modal-header">
                    <h5 class="modal-title" id="uploadFileModalLabel"><?= t('share_document') ?></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="<?= t('cancel') ?>"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label"><?= t('select_file') ?></label>
                        <input type="file" class="form-control" name="file_upload" id="uploadFileInput" required>
                        <div class="form-text"><?= t('formats') ?></div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label"><?= t('file_name') ?></label>
                        <input type="text" class="form-control" name="file_name" id="uploadFileNameInput" required>
                    </div>
                    <input type="hidden" name="folder_id" id="uploadFolderIdInput" value="">
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><?= t('cancel') ?></button>
                    <button type="submit" class="btn btn-primary"><?= t('submit_for_approval') ?></button>
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
        'view' => t('view'),
        'emptyFolder' => t('empty_folder'),
        'root' => t('root'),
        'error' => t('generic_error'),
    ]) ?>;

    function openFolder(folderId) {
        sessionStorage.setItem('student_current_folder', folderId === null || folderId === undefined ? 'null' : folderId);
        document.querySelector('#uploadFolderIdInput').value = folderId === null || folderId === undefined ? '' : folderId;

        fetch(`student.php?folderId=${folderId}`)
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
                                <td></td>
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
                                <td><a href="view.php?id=${file.id}" target="_blank" class="btn btn-sm btn-outline-info">${labels.view}</a></td>
                            </tr>
                        `);
                    });
                }

                if (!hasContent) {
                    rows.push(`<tr><td colspan="3" class="text-center text-muted py-3">${labels.emptyFolder}</td></tr>`);
                }

                document.querySelector('#folderTable tbody').innerHTML = rows.join('');
                document.querySelector('#folderHeaderLabel').textContent =
                    currentFolder ? currentFolder.name : labels.root;
            })
            .catch(error => console.error(labels.error, error));
    }

    document.querySelector('#uploadFileInput').addEventListener('change', function () {
        const nameInput = document.querySelector('#uploadFileNameInput');
        if (this.files.length && !nameInput.value) {
            nameInput.value = this.files[0].name;
        }
    });

    document.addEventListener('DOMContentLoaded', function () {
        const savedFolder = sessionStorage.getItem('student_current_folder');
        openFolder(savedFolder && savedFolder !== 'null' ? Number(savedFolder) : null);
    });
</script>
</body>
</html>
