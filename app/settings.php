<?php
require_once 'utils/db.php';
require_once 'utils/session.php';

if (!isset($_SESSION['user_id'])) { header('Location: login.php'); exit; }

$success = ''; $error = '';
if (isset($_GET['error'])) {
    if ($_GET['error'] === 'promotion_inactive') {
        $error = "Votre espace de promotion n'est pas encore validé par l'administration.";
    } else {
        $error = "Une erreur est survenue.";
    }
} elseif (isset($_GET['success'])) {
    if ($_GET['success'] === 'promo_created') {
        $success = "Demande envoyée ! Dès validation par l'admin, vous deviendrez le délégué de cet espace.";
    }
}

$user = Database::getUserDetails($_SESSION['user_id']);

// Traitement de la demande de promotion
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'request_promo') {
    if ($user['promotion_id']) {
        $error = "Vous êtes déjà rattaché à une promotion.";
    } else {
        $promo_name = trim($_POST['promo_name']);
        try {
            $new_promo_id = Database::createPromotion($promo_name);
            Database::attachUserToPromotion($user['id'], $new_promo_id);

            $_SESSION['promotion_id'] = $new_promo_id;
            $success = "Demande envoyée ! Dès validation par l'admin, vous deviendrez le délégué de cet espace.";
            header("Location: settings.php?success=promo_created");
            exit;
        } catch (\Exception $e) {
            $error = "Erreur lors de la demande.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Paramètres - CampusDrive</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
<nav class="navbar navbar-dark bg-secondary mb-4 shadow">
    <div class="container">
        <span class="navbar-brand mb-0 h1">CampusDrive - Paramètres</span>
        <div>
            <a href="student.php" class="btn btn-outline-light btn-sm me-2">Accueil</a>
            <?php if ($user['role'] === 'delegate'): ?>
                <a href="delegate.php" class="btn btn-outline-light btn-sm me-2">Espace délégué</a>
            <?php endif; ?>
            <a href="settings.php" class="btn btn-light btn-sm me-2">Paramètres</a>
            <a href="logout.php" class="btn btn-outline-danger btn-sm">Déconnexion</a>
        </div>
    </div>
</nav>

<div class="container">
    <div class="row justify-content-center">
        <div class="col-md-8">
            <?php if($error): ?>
                <div class="alert alert-danger"><?= $error ?></div>
            <?php endif; ?>
            <?php if($success): ?>
                <div class="alert alert-success"><?= $success ?></div>
            <?php endif; ?>
            <div class="card shadow-sm mb-4">
                <div class="card-header bg-white fw-bold">Mon Compte (<?= htmlspecialchars($user['email']) ?>)</div>
                <div class="card-body">
                    <p>Rôle actuel : <span class="badge bg-primary text-uppercase"><?= $user['role'] ?></span></p>

                    <?php if ($user['promotion_id']): ?>
                        <p>Espace de promotion : <b><?= htmlspecialchars($user['promo_name']) ?></b></p>
                        <p>Statut de l'espace :
                            <?php if ($user['promo_status'] === 'active'): ?>
                                <span class="badge bg-success">Actif</span>
                            <?php else: ?>
                                <span class="badge bg-warning text-dark">En attente de validation</span>
                            <?php endif; ?>
                        </p>
                    <?php else: ?>
                        <p class="text-muted">Vous n'êtes rattaché à aucune promotion actuellement.</p>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Demande de création de promotion -->
            <?php if (!$user['promotion_id']): ?>
                <div class="card shadow-sm border-primary">
                    <div class="card-header bg-primary text-white fw-bold">
                        Demander l'ouverture d'un espace (Devenir Délégué)
                    </div>
                    <div class="card-body">
                        <?php if ($success): ?>
                            <div class="alert alert-success"><?= $success ?></div>
                        <?php else: ?>
                            <?php if ($error): ?><div class="alert alert-danger"><?= $error ?></div><?php endif; ?>
                            <p>En créant un espace, vous en deviendrez le délégué une fois celui-ci validé par l'administration.</p>
                            <form method="POST">
                                <input type="hidden" name="action" value="request_promo">
                                <div class="mb-3">
                                    <label class="form-label">Nom de la promotion</label>
                                    <input type="text" class="form-control" name="promo_name" placeholder="Ex: Master 1 Info" required>
                                </div>
                                <button type="submit" class="btn btn-primary">Soumettre la demande</button>
                            </form>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>

        </div>
    </div>
</div>
</body>
</html>
