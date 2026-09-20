<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once 'utils/db.php';
require_once 'utils/session.php';
require_once 'dCaptcha/captcha.php';
require_once 'utils/mail.php';
require_once 'utils/config.php';

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$captcha = new Captcha();

if (isset($_GET['ShowCaptcha'])) {
    header('Content-Type: image/jpeg');
    $captcha->generateCaptcha();
    exit;
}

$message = ''; $error = '';
$token = htmlspecialchars(trim($_GET['token'] ?? ''));
$invited_email = '';
$promo_id = null;

if ($token) {
    $invitation = Database::getInvitationByToken($token);

    if ($invitation) {
        $invited_email = $invitation['email'];
        $promo_id = $invitation['promotion_id'];
        $message = "Invitation reconnue. Veuillez choisir un mot de passe pour finaliser votre inscription.";
    } else {
        $error = "Le lien d'invitation est invalide ou a déjà été utilisé.";
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        die("Erreur de sécurité : Jeton CSRF invalide.");
    }

    $email = htmlspecialchars(trim($_POST['email']));
    $password = password_hash($_POST['password'], PASSWORD_BCRYPT);
    $captcha_input = htmlspecialchars(trim($_POST['captcha'] ?? ''));

    // Captcha validation
    if (empty($captcha_input) || strtolower($captcha_input) !== strtolower($_SESSION['captcha'] ?? '')) {
        $error = "Le code de vérification est incorrect.";
    }

    if ($token && $invitation && strtolower($email) !== strtolower($invited_email)) {
        $error = "L'adresse email doit correspondre à celle de l'invitation.";
    }

    if (!$error) {
        try {
            if (Database::createUser($email, $password, $promo_id)) {
                Database::markInvitationAsUsed($token);
                (new Mailer)->sendMail($email, "Bienvenue sur CampusDrive", "Votre compte a été créé avec succès !");
                header("Location: login.php?registered=1");
                exit;
            }
        } catch (\InvalidArgumentException $e) {
            $error = $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Inscription - CampusDrive</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light d-flex align-items-center vh-100">
<div class="container">
    <div class="row justify-content-center">
        <div class="col-md-5">
            <div class="card shadow-sm">
                <div class="card-header bg-primary text-white text-center">
                    <h5 class="mb-0">Créer un compte étudiant</h5>
                </div>
                <div class="card-body">
                    <?php if ($message): ?>
                        <div class="alert alert-info py-2"><?= htmlspecialchars($message) ?></div>
                    <?php endif; ?>
                    <?php if ($error): ?>
                        <div class="alert alert-danger py-2"><?= htmlspecialchars($error) ?></div>
                    <?php endif; ?>

                    <form method="POST">
                        <div class="mb-3">
                            <label class="form-label">Adresse Email</label>
                            <input type="email" class="form-control" name="email" value="<?= htmlspecialchars($invited_email) ?>" <?= $token ? 'readonly' : 'required' ?>>
                        </div>
                        <div class="mb-4">
                            <label class="form-label">Mot de passe</label>
                            <input type="password" class="form-control" name="password" required>
                        </div>
                        <div class="mb-4">
                            <label class="form-label">Code de vérification</label>
                            <div class="d-flex align-items-center mb-2">
                                <!-- Affichage de l'image générée par captcha.php -->
                                <img src="register.php?ShowCaptcha" alt="Captcha" id="captcha-img" class="border rounded me-2">

                                <!-- Bouton pour recharger l'image sans rafraîchir la page -->
                                <button type="button" class="btn btn-sm btn-outline-secondary" onclick="document.getElementById('captcha-img').src = 'register.php?ShowCaptcha&' + Date.now();">
                                    Actualiser
                                </button>
                            </div>
                            <input type="text" class="form-control" name="captcha" required placeholder="Saisissez le code de l'image">
                        </div>
                        <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                        <button type="submit" class="btn btn-primary w-100">S'inscrire</button>
                    </form>
                    <div class="mt-3 text-center">
                        <a href="login.php" class="text-decoration-none">Déjà un compte ? Se connecter</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
</body>
</html>
