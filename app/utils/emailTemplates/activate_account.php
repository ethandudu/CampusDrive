<?php
require_once __DIR__ . '/layout.php';

/**
 * Email sent right after a student successfully creates their account.
 *
 * @param string $email The email address of the newly registered user.
 * @param string $activationLink The link to activate the user's account.
 * @return array{subject: string, body: string}
 */
function activateAccountEmailTemplate(string $email, string $activationLink): array
{
    $subject = 'Activez votre compte ! / Activate your account !';
    $email = htmlspecialchars($email, ENT_QUOTES, 'UTF-8');

    $bodyFr = <<<HTML
<h2 style="margin-top:0;color:#0d6efd;">Bienvenue sur CampusDrive !</h2>
<p>Bonjour,</p>
<p>Votre compte <strong>{$email}</strong> a été créé avec succès.</p>
<p>Merci d'activer votre compte en cliquant sur le lien suivant : <a href="{$activationLink}">Activer mon compte</a>.</p>
<p>Si vous n'avez pas demandé l'activation de votre compte, vous pouvez ignorer cet email.</p>
<p>À bientôt sur CampusDrive !</p>
HTML;

    $bodyEn = <<<HTML
<h2 style="margin-top:0;color:#0d6efd;">Welcome to CampusDrive!</h2>
<p>Hello,</p>
<p>Your account <strong>{$email}</strong> has been created successfully.</p>
<p>Please click the link below to activate your account: <a href="{$activationLink}">Activate my account</a>.</p>
<p>If you did not request to activate your account, you can ignore this email.</p>
<p>See you soon on CampusDrive!</p>
HTML;

    return [
        'subject' => $subject,
        'body' => renderEmailLayout($bodyFr, $bodyEn),
    ];
}
