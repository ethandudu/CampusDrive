<?php
/**
 * Shared HTML layout for all CampusDrive emails.
 * Every email is bilingual: the French version is shown first,
 * followed by a separator and the English version.
 */

if (!function_exists('renderEmailLayout')) {
    /**
     * Wrap the French and English content blocks in a common HTML shell.
     *
     * @param string $bodyFr HTML content of the French section.
     * @param string $bodyEn HTML content of the English section.
     * @return string Full HTML email body.
     */
    function renderEmailLayout(string $bodyFr, string $bodyEn): string
    {
        return <<<HTML
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>CampusDrive</title>
</head>
<body style="margin:0;padding:0;background-color:#f4f6f8;font-family:Arial, Helvetica, sans-serif;color:#212529;">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background-color:#f4f6f8;padding:24px 0;">
<tr>
<td align="center">
<table role="presentation" width="600" cellpadding="0" cellspacing="0" style="background-color:#ffffff;border-radius:8px;overflow:hidden;box-shadow:0 1px 3px rgba(0,0,0,0.1);">
<tr>
<td style="background-color:#0d6efd;padding:20px 32px;">
<span style="color:#ffffff;font-size:20px;font-weight:bold;">CampusDrive</span>
</td>
</tr>
<tr>
<td style="padding:32px;">
{$bodyFr}
<hr style="border:none;border-top:1px solid #e0e0e0;margin:28px 0;">
{$bodyEn}
</td>
</tr>
<tr>
<td style="background-color:#f8f9fa;padding:16px 32px;text-align:center;">
<span style="font-size:12px;color:#6c757d;">CampusDrive &middot; Ceci est un message automatique, merci de ne pas y répondre. / This is an automated message, please do not reply.</span>
</td>
</tr>
</table>
</td>
</tr>
</table>
</body>
</html>
HTML;
    }
}
