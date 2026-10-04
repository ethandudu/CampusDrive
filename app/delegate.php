<?php
use CampusDrive\Infrastructure\Database\FileRepository;
use CampusDrive\Infrastructure\Database\InvitationRepository;
use CampusDrive\Infrastructure\Database\PromotionRepository;
use CampusDrive\Infrastructure\Database\UserRepository;

require_once 'utils/db.php';

$userRepository = new UserRepository();
$promotionRepository = new PromotionRepository();
$fileRepository = new FileRepository();
$invitationRepository = new InvitationRepository();
require_once 'utils/session.php';
require_once 'utils/i18n.php';
require_once 'utils/mail.php';
require_once 'utils/emailTemplates/invitation.php';
require_once 'utils/emailTemplates/file_approved.php';
require_once 'utils/emailTemplates/file_rejected.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'delegate') {
    header('Location: login.php');
    exit;
}

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

if ($promotionRepository->getPromotionStatus($_SESSION['promotion_id']) == 'pending') {
    header('Location: settings.php?error=promotion_inactive');
    exit;
}

$success = '';
$error = '';

// Only known message keys are translated: the translations may contain trusted HTML,
// so arbitrary user input must never reach t().
$allowedErrorKeys = ['invalid_email_domain', 'email_already_invited'];
$allowedSuccessKeys = ['invitation_deleted'];

if (isset($_GET['error']) && is_string($_GET['error']) && in_array($_GET['error'], $allowedErrorKeys, true)) {
    $error = t($_GET['error']);
}
if (isset($_GET['success']) && is_string($_GET['success']) && in_array($_GET['success'], $allowedSuccessKeys, true)) {
    $success = t($_GET['success']);
}

if (isset($_GET['folderId'])) {
    $folderId = ($_GET['folderId']) && $_GET['folderId'] !== 'null' && $_GET['folderId'] !== ''
        ? $_GET['folderId']
        : null;
    $folderDetails = $fileRepository->getPromotionFolderFiles($folderId, $_SESSION['promotion_id']);

    header('Content-Type: application/json');
    echo json_encode($folderDetails);
    exit;
}

// Every state-changing request must carry a valid CSRF token.
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || !is_string($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        die(t('security_error'));
    }
}

// Handle announcement update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['announcement_text'])) {
    $announcement_text = trim($_POST['announcement_text']);
    $announcement_enabled = isset($_POST['announcement_enabled']) && $_POST['announcement_enabled'] === '1';
    $stmt = $promotionRepository->updateAnnouncement($_SESSION['promotion_id'], $announcement_text, $announcement_enabled);
}

// Handle invitation deletion
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete' && isset($_POST['invitation_id'])) {
    $invitation_id = htmlspecialchars($_POST['invitation_id']);
    $invitationRepository->deleteInvitation($invitation_id, $_SESSION['promotion_id']);
    header('Location: delegate.php?success=invitation_deleted');
    exit;
}

// Handle invitation generation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['invite_email'])) {
    $_POST['invite_email'] = strtolower(trim($_POST['invite_email']));
    //check if the email is in the allowed domains
    $email_domain = substr(strrchr($_POST['invite_email'], "@"), 1);
    if (!in_array($email_domain, UNIVERSITY_EMAIL_DOMAINS)) {
        header('Location: delegate.php?error=invalid_email_domain');
        exit;
    }

    if ($invitationRepository->checkIfEmailIsAlreadyInvited($_POST['invite_email'], $_SESSION['promotion_id'])) {
        header('Location: delegate.php?error=email_already_invited');
        exit;
    }
    $token = bin2hex(random_bytes(32));

    $invitationRepository->createInvitation($_POST['invite_email'], $_SESSION['promotion_id'], $token);
    $promotionForInvite = $promotionRepository->getPromotionDetails($_SESSION['promotion_id']);
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $baseUrl = $scheme . '://' . $_SERVER['HTTP_HOST'] . dirname($_SERVER['SCRIPT_NAME']);
    $registerLink = $baseUrl . '/register.php?token=' . urlencode($token);
    $invitationEmail = invitationEmailTemplate($promotionForInvite['name'] ?? '', $registerLink);
    new Mailer()->sendMail($_POST['invite_email'], $invitationEmail['subject'], $invitationEmail['body']);
    header('Location: delegate.php?success=invitation_sent');
}

// Create new folder
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'create_folder') {
    $folder_name = trim($_POST['folder_name']);
    $parent_id = isset($_POST['parent_id']) && $_POST['parent_id'] !== '' && $_POST['parent_id'] !== 'null'
        ? $_POST['parent_id']
        : null;
    if (!empty($folder_name)) {
        $fileRepository->createFolder($_SESSION['promotion_id'], $parent_id, $folder_name);
    }
}

// Delete folder
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_folder') {
    $folder_id = $_POST['element_id'];
    $fileRepository->deletePromotionFolder($folder_id);
}

// Delete file
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_file') {
    $file_id = (string) $_POST['element_id'];
    $fileRepository->rejectPromotionFile($file_id);
}

// Validation or rejection of files
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['file_action'])) {
    $file_id = (string) $_POST['file_id'];
    $file = $fileRepository->getFile($file_id);
    $uploader = $file ? $userRepository->getUserDetails((string) $file['user_id']) : null;

    if ($_POST['file_action'] === 'approve') {
        $fileRepository->approvePromotionFile($file_id);
        if ($file && $uploader && !empty($uploader['email'])) {
            $fileApprovedEmail = fileApprovedEmailTemplate($file['original_name']);
            (new Mailer())->sendMail($uploader['email'], $fileApprovedEmail['subject'], $fileApprovedEmail['body']);
        }
    } elseif ($_POST['file_action'] === 'reject') {
        $fileRepository->rejectPromotionFile($file_id);
        if ($file && $uploader && !empty($uploader['email'])) {
            $fileRejectedEmail = fileRejectedEmailTemplate($file['original_name']);
            (new Mailer())->sendMail($uploader['email'], $fileRejectedEmail['subject'], $fileRejectedEmail['body']);
        }
    }
}

$pending_files = $fileRepository->getPromotionPendingFiles($_SESSION['promotion_id']);
$promo = $promotionRepository->getPromotionDetails($_SESSION['promotion_id']);
$invitations = $invitationRepository->getPromotionInvitations($_SESSION['promotion_id']);

?>
<!DOCTYPE html>
<html lang="<?= locale() ?>">
<head>
    <meta charset="UTF-8">
    <title><?= t('delegate_area') ?> - CampusDrive</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="icon" type="image/png" href="assets/favicon.ico">
</head>
<body class="bg-light">
<nav class="navbar navbar-dark bg-success mb-4 shadow">
    <div class="container">
        <span class="navbar-brand mb-0 h1"><?= t('delegate_area') ?> (<?= htmlspecialchars($promo['name']) ?>)</span>
        <img src="assets/img/campusdrivewhite.webp" alt="Logo" class="img-fluid" style="max-height: 50px;">
        <div>
            <a href="student.php" class="btn btn-outline-light btn-sm me-2"><?= t('home') ?></a>
            <a href="delegate.php" class="btn btn-light btn-sm me-2"><?= t('delegate_area') ?></a>
            <a href="settings.php" class="btn btn-outline-light btn-sm me-2"><?= t('settings') ?></a>
            <form method="POST" action="logout.php" class="d-inline">
                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                <button type="submit" class="btn btn-outline-danger btn-sm"><?= t('logout') ?></button>
            </form>
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
                    <?php if ($error): ?>
                        <div class="alert alert-danger py-2"><?= $error ?></div>
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
            <div class="accordion mb-4" id="invitationsAccordion">
                <div class="accordion-item">
                    <h2 class="accordion-header">
                        <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseInvitations" aria-expanded="false" aria-controls="collapseInvitations">
                            <?= t('sent_invitations') ?> (<?= count($invitations) ?>)
                        </button>
                    </h2>
                    <div id="collapseInvitations" class="accordion-collapse collapse">
                        <div class="accordion-body p-0">
                            <table class="table table-hover mb-0">
                                <thead class="table-light">
                                <tr>
                                    <th>Email</th>
                                    <th><?= t('status') ?></th>
                                    <th><?= t('date') ?></th>
                                    <th><?= t('action') ?></th>
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
                                        <td>
                                            <form method="POST" class="d-inline">
                                                <input type="hidden" name="invitation_id" value="<?= $inv['id'] ?>">
                                                <?php if (!$inv['is_used']): ?>
                                                    <button type="submit" name="action" value="delete" class="btn btn-sm btn-danger" onclick="return confirm('<?= t('delete_confirmation') ?>');"><?= t('delete') ?></button>
                                                <?php endif; ?>
                                                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                                <?php if (empty($invitations)): ?>
                                    <tr><td colspan="4" class="text-center text-muted"><?= t('no_invitations') ?></td></tr>
                                <?php endif; ?>
                                </tbody>
                            </table>
                            <small class="text-muted d-block p-2"><?= t('remove_student_info') ?></small>
                        </div>
                    </div>
                </div>
            </div>
            <div class="accordion mt-4" id="pendingFilesAccordion">
                <div class="accordion-item">
                    <h2 class="accordion-header">
                        <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapsePendingFiles" aria-expanded="false" aria-controls="collapsePendingFiles">
                            <?= t('files_pending') ?> (<?= count($pending_files) ?>)
                        </button>
                    </h2>
                    <div id="collapsePendingFiles" class="accordion-collapse collapse">
                        <div class="accordion-body p-0">
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
                </div>
            </div>
            <div class="accordion mt-4" id="announcementAccordion">
                <div class="accordion-item">
                    <h2 class="accordion-header">
                        <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseAnnouncement" aria-expanded="false" aria-controls="collapseAnnouncement">
                            <?= t('announcement') ?>
                        </button>
                    </h2>
                    <div id="collapseAnnouncement" class="accordion-collapse collapse">
                        <div class="accordion-body p-0">
                            <?php
                            $announcement = $promotionRepository->getAnnouncementDetails($_SESSION['promotion_id']);
                            ?>

                            <form method="post">
                                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                                <div class="mb-3">
                                    <label for="announcementText" class="form-label"><?= t('announcement_text') ?></label>
                                    <textarea class="form-control" id="announcementText" name="announcement_text" rows="3"><?= htmlspecialchars($announcement['announcement_text'] ?? '') ?></textarea>
                                </div>
                                <div class="form-check mb-3">
                                    <input type="hidden" name="announcement_enabled" value="0">
                                    <input class="form-check-input" type="checkbox" id="announcementEnabled" name="announcement_enabled" value="1" <?= !empty($announcement['announcement_enabled']) ? 'checked' : '' ?>>
                                    <label class="form-check-label" for="announcementEnabled"><?= t('announcement_enabled') ?></label>
                                </div>
                                <button type="submit" class="btn btn-success"><?= t('save') ?></button>
                            </form>
                        </div>
                    </div>
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
                    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
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
                    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                    <h5 class="modal-title" id="deleteModalLabel"><?= t('delete_item') ?></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="<?= t('cancel') ?>"></button>
                </div>
                <div class="modal-body">
                    <p><?= t('delete_confirmation') ?></p>
                    <input type="hidden" id="deleteAction" name="action" value="delete_folder">
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

    const escapeHtml = value => String(value ?? '').replace(/[&<>"']/g, char => ({
        '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;',
    }[char]));

    function openFolder(folderId) {
        fetch(`delegate.php?folderId=${encodeURIComponent(folderId)}`)
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
                                <button class="btn btn-sm btn-link p-0 text-decoration-none" data-action="open-folder" data-id="${escapeHtml(previousFolderId)}">
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
                    // Folder names are stored already HTML-encoded (see FileRepository::createFolder).
                    data.folders.forEach(folder => {
                        rows.push(`
                            <tr>
                                <td>
                                    <button class="btn btn-sm btn-link p-0 text-decoration-none fw-bold" data-action="open-folder" data-id="${escapeHtml(folder.id)}">
                                        📁 ${folder.name}
                                    </button>
                                </td>
                                <td>${folder.created_at ? new Date(folder.created_at).toLocaleString(locale) : ''}</td>
                                <td>
                                    <button class="btn btn-sm btn-outline-danger" data-action="delete-folder" data-id="${escapeHtml(folder.id)}">${labels.delete}</button>
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
                                <td>📄 ${escapeHtml(file.original_name)}</td>
                                <td>${file.created_at ? new Date(file.created_at).toLocaleString(locale) : ''}</td>
                                <td><a href="view.php?id=${escapeHtml(encodeURIComponent(file.id))}" target="_blank" class="btn btn-sm btn-outline-info">${labels.view}</a><button class="btn btn-sm btn-outline-danger" data-action="delete-file" data-id="${escapeHtml(file.id)}">${labels.delete}</button></td>
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
        document.querySelector('#deleteForm input[name="action"]').value = 'delete_folder';
        new bootstrap.Modal(document.getElementById('deleteModal')).show();
    }

    function deleteFile(fileId) {
        document.querySelector('#deleteForm input[name="element_id"]').value = fileId;
        document.querySelector('#deleteForm input[name="action"]').value = 'delete_file';
        new bootstrap.Modal(document.getElementById('deleteModal')).show();
    }

    document.querySelector('#folderTable tbody').addEventListener('click', event => {
        const button = event.target.closest('[data-action]');
        if (!button) {
            return;
        }
        const handlers = {'open-folder': openFolder, 'delete-folder': deleteFolder, 'delete-file': deleteFile};
        handlers[button.dataset.action]?.(button.dataset.id);
    });

    document.addEventListener('DOMContentLoaded', function () {
        openFolder(null);
    });
</script>
</body>
</html>
