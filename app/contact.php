<?php
require_once 'utils/session.php';
require_once 'utils/i18n.php';
require_once 'utils/mail.php';

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$message = '';
$error = '';
$name = trim($_POST['name'] ?? '');
$email = trim($_POST['email'] ?? '');
$subject = trim($_POST['subject'] ?? '');
$content = trim($_POST['message'] ?? '');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        $error = t('security_error');
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL) || $name === '' || $subject === '' || $content === '') {
        $error = 'Veuillez renseigner tous les champs avec une adresse e-mail valide.';
    } elseif (empty($_POST['privacy_consent'])) {
        $error = 'Votre consentement au traitement de votre message est nécessaire.';
    } else {
        $body = '<p><strong>Nom :</strong> ' . htmlspecialchars($name, ENT_QUOTES, 'UTF-8') . '</p>'
            . '<p><strong>E-mail :</strong> ' . htmlspecialchars($email, ENT_QUOTES, 'UTF-8') . '</p>'
            . '<p><strong>Message :</strong><br>' . nl2br(htmlspecialchars($content, ENT_QUOTES, 'UTF-8')) . '</p>';
        if ((new Mailer())->sendMail(MAIL_FROM, '[CampusDrive] ' . $subject, $body, $email)) {
            $message = 'Votre message a bien été envoyé. Nous vous répondrons dans les meilleurs délais.';
            $name = $email = $subject = $content = '';
        } else {
            $error = 'Le message n’a pas pu être envoyé. Vous pouvez nous écrire directement à ' . MAIL_FROM . '.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Contact - CampusDrive</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="assets/css/site.css" rel="stylesheet">
</head>
<body class="bg-light">
<nav class="navbar bg-white border-bottom"><div class="container"><a class="navbar-brand fw-bold text-primary" href="index.php">CampusDrive</a><a href="index.php">Retour à l’accueil</a></div></nav>
<main class="container py-5"><div class="row justify-content-center"><div class="col-lg-8">
    <h1 class="fw-bold">Contactez-nous</h1><p class="text-secondary">Une question, une suggestion ou un problème ? Écrivez-nous.</p>
    <?php if ($message): ?><div class="alert alert-success"><?= htmlspecialchars($message) ?></div><?php endif; ?>
    <?php if ($error): ?><div class="alert alert-danger"><?= htmlspecialchars($error) ?></div><?php endif; ?>
    <div class="card border-0 shadow-sm"><div class="card-body p-4"><form method="POST">
        <div class="row g-3">
            <div class="col-md-6"><label for="name" class="form-label">Nom</label><input id="name" name="name" class="form-control" value="<?= htmlspecialchars($name) ?>" required></div>
            <div class="col-md-6"><label for="email" class="form-label">Adresse e-mail</label><input id="email" type="email" name="email" class="form-control" value="<?= htmlspecialchars($email) ?>" required></div>
            <div class="col-12"><label for="subject" class="form-label">Objet</label><input id="subject" name="subject" class="form-control" value="<?= htmlspecialchars($subject) ?>" required></div>
            <div class="col-12"><label for="message" class="form-label">Message</label><textarea id="message" name="message" rows="6" class="form-control" required><?= htmlspecialchars($content) ?></textarea></div>
            <div class="col-12"><div class="form-check"><input id="privacy_consent" type="checkbox" name="privacy_consent" class="form-check-input" required><label for="privacy_consent" class="form-check-label small">J’accepte que CampusDrive utilise les informations de ce formulaire pour répondre à ma demande. <a href="privacy.php">En savoir plus</a>.</label></div></div>
        </div>
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
        <button class="btn btn-primary mt-4" type="submit">Envoyer le message</button>
    </form></div></div>
</div></div></main>
<footer class="container pb-4 small"><a href="privacy.php">Confidentialité</a> · <a href="cgu.php">CGU</a></footer>
</body></html>
