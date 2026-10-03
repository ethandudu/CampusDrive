<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

use CampusDrive\Infrastructure\Database\InvitationRepository;
use CampusDrive\Infrastructure\Database\UserRepository;
use CampusDrive\Infrastructure\Security\PasswordPolicy;

require_once 'utils/db.php';

$userRepository = new UserRepository();
$invitationRepository = new InvitationRepository();
require_once 'utils/session.php';
require_once 'utils/i18n.php';
require_once 'dCaptcha/captcha.php';
require_once 'utils/mail.php';
require_once 'utils/config.php';
require_once 'utils/emailTemplates/welcome.php';
require_once 'utils/emailTemplates/activate_account.php';

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
$invitation = null;
$invited_email = '';
$promo_id = null;
$role = 'delegate';

if ($token) {
    $invitation = $invitationRepository->getInvitationByToken($token);

    if ($invitation) {
        $invited_email = $invitation['email'];
        $promo_id = $invitation['promotion_id'];
        $message = t('invitation_recognized');
    } else {
        $error = t('invalid_invitation');
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        die(t('security_error'));
    }

    if($_POST['action'] === 'change_locale' && isset($_POST['locale'])) {
        $newLocale = $_POST['locale'];
        if (array_key_exists($newLocale, SUPPORTED_LOCALES)) {
            $_SESSION['locale'] = $newLocale;
        }
        header('Location: register.php' . ($token ? '?token=' . urlencode($token) : ''));
        exit;
    }

    // Password complexity validation
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    if ($password !== $confirm_password) {
        $error = t('password_mismatch');
    } elseif (($passwordViolation = PasswordPolicy::violation($password)) !== null) {
        $error = t($passwordViolation);
    }

    $email = htmlspecialchars(trim($_POST['email']));
    $password = password_hash($_POST['password'], PASSWORD_BCRYPT);
    $captcha_input = htmlspecialchars(trim($_POST['captcha'] ?? ''));

    // Captcha validation
    if (empty($captcha_input) || strtolower($captcha_input) !== strtolower($_SESSION['captcha'] ?? '')) {
        $error = t('captcha_invalid');
    }

    if ($token && $invitation && strtolower($email) !== strtolower($invited_email)) {
        $error = t('invitation_email_mismatch');
    }

    if (!$error) {
        try {
            if ($token && is_array($invitation)) {
                $role = $invitation['role'] ?? 'student';
            }
            $user = $userRepository->createUser($email, $password, $promo_id, $role);
            if ($user[0] == 1) {
                if ($role === 'student') {
                    $invitationRepository->markInvitationAsUsed($token);
                    $welcomeEmail = welcomeEmailTemplate($email);
                    (new Mailer)->sendMail($email, $welcomeEmail['subject'], $welcomeEmail['body']);
                    header("Location: login.php?registered=1");
                } else {
                    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
                    $baseUrl = $scheme . '://' . $_SERVER['HTTP_HOST'] . dirname($_SERVER['SCRIPT_NAME']);
                    $activationLink = $baseUrl . '/activate.php?token=' . urlencode($user[1]);
                    $activationEmail = activateAccountEmailTemplate($email, $activationLink);
                    (new Mailer)->sendMail($email, $activationEmail['subject'], $activationEmail['body']);
                    header("Location: login.php?registered=2");
                }
                exit;
            }
        } catch (\InvalidArgumentException $e) {
            $error = $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="<?= locale() ?>">
<head>
    <meta charset="UTF-8">
    <title><?= t('register') ?> - CampusDrive</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="icon" type="image/png" href="assets/favicon.ico">
</head>
<body class="bg-light d-flex align-items-start min-vh-100 py-4">
<div class="container">
    <div class="row justify-content-center">
        <div class="col-md-5">
            <div class="card shadow-sm">
                <div class="card-header bg-primary text-white text-center">
                    <img src="assets/img/campusdrivewhite.webp" alt="Logo" class="img-fluid" style="max-height: 150px;">
                    <hr>
                    <h5><?= t('create_account') ?></h5>
                </div>
                <div class="card-body">
                    <?php if ($message): ?>
                        <div class="alert alert-info py-2"><?= htmlspecialchars($message) ?></div>
                    <?php endif; ?>
                    <?php if ($error): ?>
                        <div class="alert alert-danger py-2"><?= htmlspecialchars($error) ?></div>
                    <?php endif; ?>
                    <form method="post" action="register.php">
                        <div class="mb-3">
                            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                            <input type="hidden" name="action" value="change_locale">
                            <label for="locale" class="form-label"><?= t('language') ?></label>
                            <select class="form-select" id="locale" name="locale" onchange="this.form.submit()">
                                <?php foreach (SUPPORTED_LOCALES as $code => $name): ?>
                                    <option value="<?= htmlspecialchars($code) ?>" <?= locale() === $code ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($name) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </form>
                    <form method="POST">
                        <div class="mb-3">
                            <?php if (!$token): ?>
                            <div class="alert alert-primary" role="alert">
                                <?= t('register_message') ?>
                            </div>
                            <?php endif; ?>
                        </div>
                        <div class="mb-3">
                            <label class="form-label"><?= t('email') ?></label>
                            <input type="email" class="form-control" name="email" value="<?= htmlspecialchars($invited_email) ?>" <?= $token ? 'readonly' : 'required' ?>>
                        </div>
                        <div class="mb-4">
                            <label class="form-label"><?= t('password') ?></label>
                            <input type="password" class="form-control" name="password" required>
                            <ul>
                                <li id="length" class="text-danger"><?= t('password_length') ?></li>
                                <li id="uppercase" class="text-danger"><?= t('password_uppercase') ?></li>
                                <li id="lowercase" class="text-danger"><?= t('password_lowercase') ?></li>
                                <li id="number" class="text-danger"><?= t('password_number') ?></li>
                                <li id="special" class="text-danger"><?= t('password_special') ?></li>
                            </ul>
                        </div>
                        <div class="mb-4">
                            <label class="form-label"><?= t('confirm_password') ?></label>
                            <input type="password" class="form-control" name="confirm_password" required>
                        </div>
                        <div class="mb-4">
                            <label class="form-label"><?= t('verification_code') ?></label>
                            <div class="d-flex align-items-center mb-2">
                                <!-- Affichage de l'image générée par captcha.php -->
                                <img src="register.php?ShowCaptcha" alt="Captcha" id="captcha-img" class="border rounded me-2">

                                <!-- Bouton pour recharger l'image sans rafraîchir la page -->
                                <button type="button" class="btn btn-sm btn-outline-secondary" onclick="document.getElementById('captcha-img').src = 'register.php?ShowCaptcha&' + Date.now();">
                                    <?= t('refresh') ?>
                                </button>
                            </div>
                            <input type="text" class="form-control" name="captcha" required placeholder="<?= t('captcha_placeholder') ?>">
                        </div>
                        <div class="mb-4">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="terms" required>
                                <label class="form-check-label" for="terms">
                                    <?= t('accept_terms') ?>
                                </label>
                            </div>
                        </div>
                        <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                        <input type="hidden" name="action" value="register">
                        <button type="submit" class="btn btn-primary w-100" disabled><?= t('sign_up') ?></button>
                    </form>
                    <div class="mt-3 text-center">
                        <a href="login.php" class="text-decoration-none"><?= t('already_have_account') ?></a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<script>
<!--    check userpass complexity -->
    document.addEventListener('DOMContentLoaded', function() {
        const passwordInput = document.querySelector('input[name="password"]');
        const submitButton = document.querySelector('button[type="submit"]');

        passwordInput.addEventListener('input', function() {
            const password = this.value;
            let isValid = true;

            // Check password complexity
            if (password.length < 8 || password.length > 64) {
                isValid = false;
                document.getElementById('length').classList.remove('text-success');
                document.getElementById('length').classList.add('text-danger');
            } else {
                document.getElementById('length').classList.remove('text-danger');
                document.getElementById('length').classList.add('text-success');
            }
            if (!/[A-Z]/.test(password)) {
                isValid = false;
                document.getElementById('uppercase').classList.remove('text-success');
                document.getElementById('uppercase').classList.add('text-danger');
            } else {
                document.getElementById('uppercase').classList.remove('text-danger');
                document.getElementById('uppercase').classList.add('text-success');
            }
            if (!/[a-z]/.test(password)) {
                isValid = false;
                document.getElementById('lowercase').classList.remove('text-success');
                document.getElementById('lowercase').classList.add('text-danger');
            } else {
                document.getElementById('lowercase').classList.remove('text-danger');
                document.getElementById('lowercase').classList.add('text-success');
            }
            if (!/[0-9]/.test(password)) {
                isValid = false;
                document.getElementById('number').classList.remove('text-success');
                document.getElementById('number').classList.add('text-danger');
            } else {
                document.getElementById('number').classList.remove('text-danger');
                document.getElementById('number').classList.add('text-success');
            }
            if (!/[!@#$%^&*()+-]/.test(password)) {
                isValid = false;
                document.getElementById('special').classList.remove('text-success');
                document.getElementById('special').classList.add('text-danger');
            } else {
                document.getElementById('special').classList.remove('text-danger');
                document.getElementById('special').classList.add('text-success');
            }
            submitButton.disabled = !isValid;
        });
    });
</script>
</body>
</html>
