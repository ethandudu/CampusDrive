<?php
require_once __DIR__ . '/layout.php';

/**
 * Email sent to the CampusDrive administration when a visitor submits
 * the contact form.
 *
 * @param string $name    Sender's name.
 * @param string $email   Sender's email address.
 * @param string $subject Subject entered by the sender.
 * @param string $message Message content entered by the sender.
 * @return array{subject: string, body: string}
 */
function contactNotificationEmailTemplate(string $name, string $email, string $subject, string $message): array
{
    $emailSubject = '[CampusDrive] ' . $subject;
    $safeName = htmlspecialchars($name, ENT_QUOTES, 'UTF-8');
    $safeEmail = htmlspecialchars($email, ENT_QUOTES, 'UTF-8');
    $safeSubject = htmlspecialchars($subject, ENT_QUOTES, 'UTF-8');
    $safeMessage = nl2br(htmlspecialchars($message, ENT_QUOTES, 'UTF-8'));

    $bodyFr = <<<HTML
<h2 style="margin-top:0;color:#0d6efd;">Nouveau message de contact</h2>
<p><strong>Nom :</strong> {$safeName}</p>
<p><strong>E-mail :</strong> {$safeEmail}</p>
<p><strong>Objet :</strong> {$safeSubject}</p>
<p><strong>Message :</strong><br>{$safeMessage}</p>
HTML;

    $bodyEn = <<<HTML
<h2 style="margin-top:0;color:#0d6efd;">New contact message</h2>
<p><strong>Name:</strong> {$safeName}</p>
<p><strong>Email:</strong> {$safeEmail}</p>
<p><strong>Subject:</strong> {$safeSubject}</p>
<p><strong>Message:</strong><br>{$safeMessage}</p>
HTML;

    return [
        'subject' => $emailSubject,
        'body' => renderEmailLayout($bodyFr, $bodyEn),
    ];
}
