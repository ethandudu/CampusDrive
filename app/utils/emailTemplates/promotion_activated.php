<?php
require_once __DIR__ . '/layout.php';

/**
 * Email sent to a student when their promotion workspace request is
 * approved by an administrator, making them the promotion's delegate.
 *
 * @param string $promotionName Name of the newly activated promotion.
 * @return array{subject: string, body: string}
 */
function promotionActivatedEmailTemplate(string $promotionName): array
{
    $subject = 'Votre espace de promotion est actif / Your promotion workspace is now active';
    $safePromotion = htmlspecialchars($promotionName, ENT_QUOTES, 'UTF-8');

    $bodyFr = <<<HTML
<h2 style="margin-top:0;color:#198754;">Espace de promotion activé</h2>
<p>Bonjour,</p>
<p>Votre demande d'ouverture de l'espace <strong>{$safePromotion}</strong> a été validée par l'administration.</p>
<p>Vous êtes désormais le délégué de cette promotion et pouvez inviter vos camarades et gérer les documents partagés.</p>
HTML;

    $bodyEn = <<<HTML
<h2 style="margin-top:0;color:#198754;">Promotion workspace activated</h2>
<p>Hello,</p>
<p>Your request to open the <strong>{$safePromotion}</strong> workspace has been approved by the administration.</p>
<p>You are now this promotion's delegate and can invite your classmates and manage shared documents.</p>
HTML;

    return [
        'subject' => $subject,
        'body' => renderEmailLayout($bodyFr, $bodyEn),
    ];
}
