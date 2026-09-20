<?php
require_once 'utils/db.php';
require_once 'utils/session.php';
require_once 'utils/i18n.php';

if (!isset($_SESSION['user_id'])) {
    die(t('unauthorized_access'));
}

$file_id = (int) ($_GET['id'] ?? 0);

$file = Database::getFile($file_id);

if (!$file) {
    die(t('file_not_found'));
}

// Vérification des droits : l'utilisateur doit être dans la même promotion que le fichier
// (ou être un administrateur)
if ($_SESSION['role'] !== 'admin' && $_SESSION['promotion_id'] !== $file['promotion_id']) {
    die(t('file_access_denied'));
}

// Un étudiant ne peut voir le fichier que s'il est approuvé ou s'il en est l'auteur
if ($file['status'] !== 'approved' && $_SESSION['user_id'] !== $file['user_id'] && $_SESSION['role'] !== 'delegue') {
    die(t('file_pending_approval'));
}

$full_path = __DIR__ . '/uploads/' . $file['file_path'];

if (!file_exists($full_path)) {
    die(t('file_missing'));
}

// Envoi des en-têtes HTTP pour affichage direct dans le navigateur
header('Content-Type: ' . $file['file_type']);
header('Content-Disposition: inline; filename="' . basename($file['original_name']) . '"');
header('Content-Length: ' . filesize($full_path));

readfile($full_path);
exit;
