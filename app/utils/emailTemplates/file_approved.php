<?php
require_once __DIR__ . '/layout.php';

/**
 * Email sent to a student when their shared file is approved by a delegate.
 *
 * @param string $fileName Original name of the approved file.
 * @return array{subject: string, body: string}
 */
function fileApprovedEmailTemplate(string $fileName): array
{
    $subject = 'Votre fichier a été validé / Your file has been approved';
    $safeName = htmlspecialchars($fileName, ENT_QUOTES, 'UTF-8');

    $bodyFr = <<<HTML
<h2 style="margin-top:0;color:#198754;">Fichier validé</h2>
<p>Bonjour,</p>
<p>Bonne nouvelle : votre fichier <strong>{$safeName}</strong> a été validé par le délégué de votre promotion.</p>
<p>Il est désormais visible par l'ensemble de la promotion.</p>
HTML;

    $bodyEn = <<<HTML
<h2 style="margin-top:0;color:#198754;">File approved</h2>
<p>Hello,</p>
<p>Good news: your file <strong>{$safeName}</strong> has been approved by your promotion's delegate.</p>
<p>It is now visible to the whole promotion.</p>
HTML;

    return [
        'subject' => $subject,
        'body' => renderEmailLayout($bodyFr, $bodyEn),
    ];
}
