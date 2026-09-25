<?php
require_once __DIR__ . '/layout.php';

/**
 * Email sent to a student when their shared file is rejected by a delegate.
 *
 * @param string $fileName Original name of the rejected file.
 * @return array{subject: string, body: string}
 */
function fileRejectedEmailTemplate(string $fileName): array
{
    $subject = 'Votre fichier a été refusé / Your file has been rejected';
    $safeName = htmlspecialchars($fileName, ENT_QUOTES, 'UTF-8');

    $bodyFr = <<<HTML
<h2 style="margin-top:0;color:#dc3545;">Fichier refusé</h2>
<p>Bonjour,</p>
<p>Votre fichier <strong>{$safeName}</strong> a été refusé par le délégué de votre promotion et a été supprimé.</p>
<p>N'hésitez pas à le contacter si vous avez des questions à ce sujet.</p>
HTML;

    $bodyEn = <<<HTML
<h2 style="margin-top:0;color:#dc3545;">File rejected</h2>
<p>Hello,</p>
<p>Your file <strong>{$safeName}</strong> was rejected by your promotion's delegate and has been removed.</p>
<p>Feel free to contact them if you have any questions.</p>
HTML;

    return [
        'subject' => $subject,
        'body' => renderEmailLayout($bodyFr, $bodyEn),
    ];
}
