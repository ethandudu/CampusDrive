<?php
require_once __DIR__ . '/layout.php';

/**
 * Email sent right after a student successfully creates their account.
 *
 * @param string $email The email address of the newly registered user.
 * @return array{subject: string, body: string}
 */
function welcomeEmailTemplate(string $email): array
{
    $subject = 'Bienvenue sur CampusDrive ! / Welcome to CampusDrive!';

    $bodyFr = <<<HTML
<h2 style="margin-top:0;color:#0d6efd;">Bienvenue sur CampusDrive !</h2>
<p>Bonjour,</p>
<p>Votre compte <strong>{$email}</strong> a été créé avec succès.</p>
<p>Vous pouvez dès à présent vous connecter pour consulter les documents de votre promotion et partager les vôtres.</p>
<p>À bientôt sur CampusDrive !</p>
HTML;

    $bodyEn = <<<HTML
<h2 style="margin-top:0;color:#0d6efd;">Welcome to CampusDrive!</h2>
<p>Hello,</p>
<p>Your account <strong>{$email}</strong> has been created successfully.</p>
<p>You can now sign in to view your promotion's documents and share your own.</p>
<p>See you soon on CampusDrive!</p>
HTML;

    return [
        'subject' => $subject,
        'body' => renderEmailLayout($bodyFr, $bodyEn),
    ];
}
