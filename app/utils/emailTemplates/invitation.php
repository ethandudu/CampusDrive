<?php
require_once __DIR__ . '/layout.php';

/**
 * Email sent to a student invited by a promotion delegate.
 *
 * @param string $promotionName Name of the promotion the student is invited to.
 * @param string $registerLink  Full URL to the registration page, including the invitation token.
 * @return array{subject: string, body: string}
 */
function invitationEmailTemplate(string $promotionName, string $registerLink): array
{
    $subject = 'Vous êtes invité(e) à rejoindre CampusDrive / You have been invited to join CampusDrive';
    $safeLink = htmlspecialchars($registerLink, ENT_QUOTES, 'UTF-8');
    $safePromotion = htmlspecialchars($promotionName, ENT_QUOTES, 'UTF-8');

    $bodyFr = <<<HTML
<h2 style="margin-top:0;color:#0d6efd;">Vous êtes invité(e) sur CampusDrive</h2>
<p>Bonjour,</p>
<p>Le délégué de la promotion <strong>{$safePromotion}</strong> vous invite à rejoindre son espace CampusDrive.</p>
<p>Cliquez sur le lien ci-dessous pour créer votre compte :</p>
<p style="text-align:center;margin:24px 0;">
<a href="{$safeLink}" style="background-color:#0d6efd;color:#ffffff;text-decoration:none;padding:12px 24px;border-radius:6px;display:inline-block;">Créer mon compte</a>
</p>
<p>Si le bouton ne fonctionne pas, copiez-collez ce lien dans votre navigateur : <br>{$safeLink}</p>
HTML;

    $bodyEn = <<<HTML
<h2 style="margin-top:0;color:#0d6efd;">You've been invited to CampusDrive</h2>
<p>Hello,</p>
<p>The delegate of the <strong>{$safePromotion}</strong> promotion has invited you to join their CampusDrive workspace.</p>
<p>Click the link below to create your account:</p>
<p style="text-align:center;margin:24px 0;">
<a href="{$safeLink}" style="background-color:#0d6efd;color:#ffffff;text-decoration:none;padding:12px 24px;border-radius:6px;display:inline-block;">Create my account</a>
</p>
<p>If the button does not work, copy and paste this link into your browser: <br>{$safeLink}</p>
HTML;

    return [
        'subject' => $subject,
        'body' => renderEmailLayout($bodyFr, $bodyEn),
    ];
}
