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

// Traitement de l'upload
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['file_upload'])) {
    $file = $_FILES['file_upload'];

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
                Database::createFileRecord($user_id, $promo_id, $file['name'], $stored_name, $mime_type);
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

// Récupération des fichiers approuvés de la promotion
$approved_files = Database::getPromotionFiles($promo_id);

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
    <div class="row">
        <!-- Formulaire d'upload -->
        <div class="col-md-4">
            <div class="card shadow-sm mb-4">
                <div class="card-header bg-white fw-bold"><?= t('share_document') ?></div>
                <div class="card-body">
                    <?php if ($message): ?><div class="alert alert-success py-2"><?= $message ?></div><?php endif; ?>
                    <?php if ($error): ?><div class="alert alert-danger py-2"><?= $error ?></div><?php endif; ?>

                    <form method="POST" enctype="multipart/form-data">
                        <div class="mb-3">
                            <label class="form-label"><?= t('select_file') ?></label>
                            <input type="file" class="form-control" name="file_upload" required>
                            <div class="form-text"><?= t('formats') ?></div>
                        </div>
                        <button type="submit" class="btn btn-primary w-100"><?= t('submit_for_approval') ?></button>
                    </form>
                </div>
            </div>

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
                <div class="card-header bg-white fw-bold"><?= t('documents') ?></div>
                <div class="card-body p-0">
                    <?php if (empty($approved_files)): ?>
                        <p class="text-center text-muted p-4 mb-0"><?= t('no_documents') ?></p>
                    <?php else: ?>
                        <table class="table table-hover mb-0">
                            <thead class="table-light">
                            <tr>
                                <th><?= t('file_name') ?></th>
                                <th><?= t('shared_by') ?></th>
                                <th><?= t('date') ?></th>
                                <th class="text-end"><?= t('action') ?></th>
                            </tr>
                            </thead>
                            <tbody>
                            <?php foreach ($approved_files as $file): ?>
                                <tr>
                                    <td class="align-middle fw-bold"><?= htmlspecialchars($file['original_name']) ?></td>
                                    <td class="align-middle"><small><?= htmlspecialchars($file['uploader_email']) ?></small></td>
                                    <td class="align-middle"><small><?= date('d/m/Y', strtotime($file['created_at'])) ?></small></td>
                                    <td class="text-end">
                                        <a href="view.php?id=<?= $file['id'] ?>" target="_blank" class="btn btn-sm btn-outline-primary"><?= t('view') ?></a>
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
</div>
</body>
</html>
