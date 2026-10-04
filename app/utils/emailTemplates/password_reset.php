<?php
require_once __DIR__ . '/layout.php';

/**
 * @return array{subject: string, body: string}
 */
function passwordResetEmailTemplate(string $resetLink): array
{
    $resetLink = htmlspecialchars($resetLink, ENT_QUOTES, 'UTF-8');
    $subject = 'Réinitialisation de votre mot de passe / Reset your password';

    $bodyFr = <<<HTML
<h2 style="margin-top:0;color:#0d6efd;">Réinitialisation du mot de passe</h2>
<p>Vous avez demandé à réinitialiser votre mot de passe CampusDrive.</p>
<p><a href="{$resetLink}">Choisir un nouveau mot de passe</a></p>
<p>Ce lien expirera dans une heure. Si vous n'avez pas fait cette demande, ignorez cet e-mail.</p>
HTML;

    $bodyEn = <<<HTML
<h2 style="margin-top:0;color:#0d6efd;">Password reset</h2>
<p>A password reset was requested for your CampusDrive account.</p>
<p><a href="{$resetLink}">Choose a new password</a></p>
<p>This link will expire in one hour. If you did not make this request, you can ignore this email.</p>
HTML;

    return [
        'subject' => $subject,
        'body' => renderEmailLayout($bodyFr, $bodyEn),
    ];
}
