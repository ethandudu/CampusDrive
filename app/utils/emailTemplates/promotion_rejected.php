<?php
require_once __DIR__ . '/layout.php';

/**
 * Email sent to a student when their promotion workspace request is
 * rejected by an administrator.
 *
 * @param string $promotionName Name of the rejected promotion.
 * @param string $reason Optional message from the administrator.
 * @return array{subject: string, body: string}
 */
function promotionRejectedEmailTemplate(string $promotionName, string $reason = ''): array
{
    $subject = 'Votre demande d\'espace de promotion a été refusée / Your promotion workspace request was rejected';
    $safePromotion = htmlspecialchars($promotionName, ENT_QUOTES, 'UTF-8');
    $safeReason = nl2br(htmlspecialchars(trim($reason), ENT_QUOTES, 'UTF-8'));

    $reasonFr = $safeReason !== '' ? "<p><strong>Message de l'administration :</strong><br>{$safeReason}</p>" : '';
    $reasonEn = $safeReason !== '' ? "<p><strong>Message from the administration:</strong><br>{$safeReason}</p>" : '';

    $bodyFr = <<<HTML
<h2 style="margin-top:0;color:#dc3545;">Demande refusée</h2>
<p>Bonjour,</p>
<p>Votre demande d'ouverture de l'espace <strong>{$safePromotion}</strong> a été refusée par l'administration.</p>
{$reasonFr}
HTML;

    $bodyEn = <<<HTML
<h2 style="margin-top:0;color:#dc3545;">Request rejected</h2>
<p>Hello,</p>
<p>Your request to open the <strong>{$safePromotion}</strong> workspace has been rejected by the administration.</p>
{$reasonEn}
HTML;

    return [
        'subject' => $subject,
        'body' => renderEmailLayout($bodyFr, $bodyEn),
    ];
}
