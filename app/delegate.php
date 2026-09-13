<?php
require 'db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'delegue') {
    header('Location: login.php');
    exit;
}

// Vérification du statut de la promotion
$stmt = $pdo->prepare("SELECT * FROM promotions WHERE id = ?");
$stmt->execute([$_SESSION['promotion_id']]);
$promo = $stmt->fetch();

if ($promo['status'] === 'pending') {
    die("<div style='padding:2rem;font-family:sans-serif;'>Votre espace <b>" . htmlspecialchars($promo['name']) . "</b> est en attente d'approbation. <br><br><a href='logout.php'>Se déconnecter</a></div>");
}

$success = '';
// Génération de l'invitation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['invite_email'])) {
    $invite_email = trim($_POST['invite_email']);
    $token = bin2hex(random_bytes(32));

    $stmt = $pdo->prepare("INSERT INTO invitations (email, promotion_id, token) VALUES (?, ?, ?)");
    $stmt->execute([$invite_email, $promo['id'], $token]);

    $invite_link = "http://localhost:8080/inscription_etudiant.php?token=" . $token;
    $success = "Invitation générée pour $invite_email.<br><b>Lien à transmettre :</b> <a href='$invite_link' target='_blank'>$invite_link</a>";
}

// Récupération de l'historique des invitations
$stmt = $pdo->prepare("SELECT * FROM invitations WHERE promotion_id = ? ORDER BY created_at DESC");
$stmt->execute([$promo['id']]);
$invitations = $stmt->fetchAll();

// Validation ou refus d'un fichier
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['file_action'])) {
    $file_id = (int) $_POST['file_id'];
    if ($_POST['file_action'] === 'approve') {
        $stmt = $pdo->prepare("UPDATE files SET status = 'approved' WHERE id = ? AND promotion_id = ?");
        $stmt->execute([$file_id, $promo['id']]);
    } elseif ($_POST['file_action'] === 'reject') {
        // Supprime le fichier en BDD et du disque
        $stmt = $pdo->prepare("SELECT file_path FROM files WHERE id = ? AND promotion_id = ?");
        $stmt->execute([$file_id, $promo['id']]);
        $f = $stmt->fetch();
        if ($f) {
            @unlink(__DIR__ . '/uploads/' . $f['file_path']);
            $stmt = $pdo->prepare("DELETE FROM files WHERE id = ?");
            $stmt->execute([$file_id]);
        }
    }
}

// Récupération des fichiers en attente d'approbation
$stmt = $pdo->prepare("SELECT f.*, u.email as uploader_email FROM files f JOIN users u ON f.user_id = u.id WHERE f.promotion_id = ? AND f.status = 'pending' ORDER BY f.created_at ASC");
$stmt->execute([$promo['id']]);
$pending_files = $stmt->fetchAll();

?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Espace Délégué - CampusDrive</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
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
                        <button type="submit" class="btn btn-success w-100">Générer l'invitation</button>
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
