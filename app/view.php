<?php
require_once 'utils/db.php';
require_once 'utils/session.php';

if (!isset($_SESSION['user_id'])) {
    die("Accès non autorisé.");
}

$file_id = (int) ($_GET['id'] ?? 0);

$file = Database::getFile($file_id);

if (!$file) {
    die("Fichier introuvable.");
}

// Vérification des droits : l'utilisateur doit être dans la même promotion que le fichier
// (ou être un administrateur)
if ($_SESSION['role'] !== 'admin' && $_SESSION['promotion_id'] !== $file['promotion_id']) {
    die("Vous n'avez pas accès à ce fichier.");
}

// Un étudiant ne peut voir le fichier que s'il est approuvé ou s'il en est l'auteur
if ($file['status'] !== 'approved' && $_SESSION['user_id'] !== $file['user_id'] && $_SESSION['role'] !== 'delegue') {
    die("Ce fichier est en attente d'approbation.");
}

$full_path = __DIR__ . '/uploads/' . $file['file_path'];

if (!file_exists($full_path)) {
    die("Le fichier physique n'existe plus sur le serveur.");
}

// Envoi des en-têtes HTTP pour affichage direct dans le navigateur
header('Content-Type: ' . $file['file_type']);
header('Content-Disposition: inline; filename="' . basename($file['original_name']) . '"');
header('Content-Length: ' . filesize($full_path));

readfile($full_path);
exit;
