<?php
require_once 'utils/db.php';
require_once 'utils/session.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'delegate') {
    header('Location: login.php');
    exit;
}

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Vérification du statut de la promotion
if (Database::getPromotionStatus($_SESSION['promotion_id']) == 'pending') {
    header('Location: settings.php?error=promotion_inactive');
    exit;
}

$promo = Database::getPromotionDetails($_SESSION['promotion_id']);

$success = '';

// Génération de l'invitation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['invite_email'])) {
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        die("Erreur de sécurité : Jeton CSRF invalide.");
    }
    $token = bin2hex(random_bytes(32));

    Database::createInvitation($_SESSION['promotion_id'], $_POST['invite_email'], $token);
}

// Création d'un dossier
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'create_folder') {
    $folder_name = trim($_POST['folder_name']);
    if (!empty($folder_name)) {
        Database::createFolder($_SESSION['promotion_id'], $folder_name);
    }
}

$invitations = Database::getPromotionInvitations($_SESSION['promotion_id']);

// Validation ou refus d'un fichier
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['file_action'])) {
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        die("Erreur de sécurité : Jeton CSRF invalide.");
    }
    $file_id = (int) $_POST['file_id'];
    if ($_POST['file_action'] === 'approve') {
        Database::approvePromotionFile($file_id);
    } elseif ($_POST['file_action'] === 'reject') {
        // Supprime le fichier en BDD et du disque
        Database::rejectPromotionFile($file_id);
    }
}

$pending_files = Database::getPromotionPendingFiles($_SESSION['promotion_id']);

?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Espace Délégué - CampusDrive</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
<nav class="navbar navbar-dark bg-success mb-4 shadow">
    <div class="container">
        <span class="navbar-brand mb-0 h1">CampusDrive - Délégué (<?= htmlspecialchars($promo['name']) ?>)</span>
        <a href="logout.php" class="btn btn-outline-light btn-sm">Déconnexion</a>
    </div>
</nav>

<div class="container">
    <div class="row">
        <div class="col-md-4">
            <div class="card shadow-sm mb-4">
                <div class="card-header bg-white fw-bold">Inviter un étudiant</div>
                <div class="card-body">
                    <?php if ($success): ?>
                        <div class="alert alert-info py-2"><?= $success ?></div>
                    <?php endif; ?>
                    <form method="POST">
                        <div class="mb-3">
                            <label class="form-label">Email de l'étudiant</label>
                            <input type="email" class="form-control" name="invite_email" required>
                        </div>
                        <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                        <button type="submit" class="btn btn-success w-100">Générer l'invitation</button>
                    </form>
                </div>
            </div>
            <div class="card shadow-sm mb-4">
                <div class="card-header bg-white fw-bold">Créer un dossier</div>
                <div class="card-body">
                    <form method="POST">
                        <input type="hidden" name="action" value="create_folder">
                        <div class="mb-3">
                            <label class="form-label">Nom du dossier</label>
                            <input type="text" class="form-control" name="folder_name" placeholder="Ex: Cours S1, TD, Annales..." required>
                        </div>
                        <button type="submit" class="btn btn-success w-100">Créer le dossier</button>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-md-8">
            <div class="card shadow-sm">
                <div class="card-header bg-white fw-bold">Invitations envoyées</div>
                <table class="table table-hover mb-0">
                    <thead class="table-light">
                    <tr>
                        <th>Email</th>
                        <th>Statut</th>
                        <th>Date</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($invitations as $inv): ?>
                        <tr>
                            <td><?= htmlspecialchars($inv['email']) ?></td>
                            <td>
                                <?php if ($inv['is_used']): ?>
                                    <span class="badge bg-secondary">Inscrit</span>
                                <?php else: ?>
                                    <span class="badge bg-warning text-dark">En attente</span>
                                <?php endif; ?>
                            </td>
                            <td><?= date('d/m/Y H:i', strtotime($inv['created_at'])) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if(empty($invitations)): ?>
                        <tr><td colspan="3" class="text-center text-muted">Aucune invitation envoyée.</td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
                <div class="card shadow-sm mt-4">
                    <div class="card-header bg-white fw-bold">Fichiers en attente de validation</div>
                    <div class="card-body p-0">
                        <?php if (empty($pending_files)): ?>
                            <p class="text-muted p-3 mb-0">Aucun fichier en attente.</p>
                        <?php else: ?>
                            <table class="table table-hover mb-0">
                                <thead class="table-light">
                                <tr>
                                    <th>Fichier</th>
                                    <th>Auteur</th>
                                    <th>Action</th>
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
                                            <a href="view.php?id=<?= $pf['id'] ?>" target="_blank" class="btn btn-sm btn-outline-info me-2">Aperçu</a>
                                            <form method="POST" class="d-inline">
                                                <input type="hidden" name="file_id" value="<?= $pf['id'] ?>">
                                                <button type="submit" name="file_action" value="approve" class="btn btn-sm btn-success">Valider</button>
                                                <button type="submit" name="file_action" value="reject" class="btn btn-sm btn-danger">Refuser</button>
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
    </div>
</div>
</body>
</html>
