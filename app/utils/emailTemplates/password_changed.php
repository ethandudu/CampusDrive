<?php
require_once __DIR__ . '/layout.php';

/**
 * Email sent to a user right after their password has been changed, so an
 * unauthorized change is noticed quickly.
 *
 * @return array{subject: string, body: string}
 */
function passwordChangedEmailTemplate(): array
{
    $subject = 'Votre mot de passe a été modifié / Your password has been changed';

    $bodyFr = <<<HTML
<h2 style="margin-top:0;color:#dc3545;">Mot de passe modifié</h2>
<p>Bonjour,</p>
<p>Le mot de passe de votre compte CampusDrive vient d'être modifié. Vos autres sessions ont été déconnectées.</p>
<p>Si vous n'êtes pas à l'origine de ce changement, contactez-nous immédiatement via la page Contact de CampusDrive.</p>
HTML;

    $bodyEn = <<<HTML
<h2 style="margin-top:0;color:#dc3545;">Password changed</h2>
<p>Hello,</p>
<p>The password of your CampusDrive account has just been changed. Your other sessions have been signed out.</p>
<p>If you did not make this change, please contact us immediately through the CampusDrive Contact page.</p>
HTML;

    return [
        'subject' => $subject,
        'body' => renderEmailLayout($bodyFr, $bodyEn),
    ];
}
