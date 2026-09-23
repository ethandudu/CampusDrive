<?php
require_once 'utils/session.php';
require_once 'utils/i18n.php';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="CampusDrive, l'espace de partage de documents entre étudiants, gratuit et simple.">
    <title>CampusDrive - Le partage étudiant, simplement</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="assets/css/site.css" rel="stylesheet">
</head>
<body>
<nav class="navbar navbar-expand-lg navbar-light bg-white border-bottom">
    <div class="container">
        <a class="navbar-brand fw-bold text-primary" href="index.php">CampusDrive</a>
        <div class="d-flex gap-2">
            <?php if (isset($_SESSION['user_id'])): ?>
                <a class="btn btn-primary btn-sm" href="student.php">Mon espace</a>
            <?php else: ?>
                <a class="btn btn-outline-primary btn-sm" href="login.php">Se connecter</a>
                <a class="btn btn-primary btn-sm" href="register.php">Créer un compte</a>
            <?php endif; ?>
        </div>
    </div>
</nav>
<main>
    <section class="hero-section">
        <div class="container py-5"><div class="row align-items-center g-5">
            <div class="col-lg-7">
                <span class="eyebrow">L'espace de partage pensé pour les étudiants</span>
                <h1 class="display-4 fw-bold mt-3">Tous vos documents de promotion, au même endroit.</h1>
                <p class="lead text-secondary mt-3">CampusDrive facilite le partage de cours, de ressources et de fichiers entre étudiants, dans un espace organisé par promotion.</p>
                <div class="d-flex flex-wrap gap-3 mt-4"><a href="register.php" class="btn btn-primary btn-lg px-4">Commencer gratuitement</a><a href="#fonctionnalites" class="btn btn-outline-secondary btn-lg px-4">Découvrir les fonctionnalités</a></div>
                <p class="small text-secondary mt-3 mb-0"><strong>100 % gratuit</strong> : aucune carte bancaire, aucun abonnement.</p>
            </div>
            <div class="col-lg-5"><div class="hero-card shadow-sm">
                <div class="d-flex justify-content-between align-items-center mb-4"><span class="fw-bold">Ma promotion</span><span class="badge rounded-pill text-bg-success">Active</span></div>
                <div class="document-row"><span class="document-icon">PDF</span><span>Notes de cours - S1.pdf</span><small>2 Mo</small></div>
                <div class="document-row"><span class="document-icon">IMG</span><span>Planning examens.png</span><small>840 Ko</small></div>
                <div class="document-row"><span class="document-icon">PDF</span><span>Annales 2025.pdf</span><small>4 Mo</small></div>
                <div class="progress mt-4" role="progressbar" aria-label="Espace utilisé" aria-valuenow="35" aria-valuemin="0" aria-valuemax="100"><div class="progress-bar" style="width: 35%"></div></div>
                <small class="text-secondary d-block mt-2">Un espace clair pour toute la promotion</small>
            </div></div>
        </div></div>
    </section>
    <section id="fonctionnalites" class="py-5"><div class="container">
        <div class="text-center mb-5"><span class="eyebrow">Pourquoi CampusDrive ?</span><h2 class="fw-bold mt-2">Simple, collaboratif et accessible</h2></div>
        <div class="row g-4">
            <div class="col-md-4"><div class="feature-card h-100"><div class="feature-icon">01</div><h3 class="h5">Un espace par promotion</h3><p class="text-secondary mb-0">Retrouvez tous les documents de votre promotion dans une arborescence facile à parcourir.</p></div></div>
            <div class="col-md-4"><div class="feature-card h-100"><div class="feature-icon">02</div><h3 class="h5">Partage contrôlé</h3><p class="text-secondary mb-0">Les fichiers envoyés sont vérifiés par le délégué avant d'être visibles par le groupe.</p></div></div>
            <div class="col-md-4"><div class="feature-card h-100"><div class="feature-icon">03</div><h3 class="h5">Gratuit par nature</h3><p class="text-secondary mb-0">CampusDrive est gratuit : pas de publicité intrusive, pas de frais cachés, pas d'abonnement.</p></div></div>
        </div>
    </div></section>
    <section class="cta-section py-5"><div class="container text-center"><h2 class="fw-bold">Prêt à mieux partager ?</h2><p class="text-secondary">Créez votre espace et invitez votre promotion en quelques minutes.</p><a href="register.php" class="btn btn-primary px-4">Créer mon compte</a></div></section>
</main>
<footer class="border-top bg-white py-4"><div class="container d-flex flex-wrap justify-content-between gap-3 small"><span class="text-secondary">&copy; <?= date('Y') ?> CampusDrive</span><div class="d-flex gap-3"><a href="contact.php">Contact</a><a href="privacy.php">Politique de confidentialité (RGPD)</a><a href="cgu.php">CGU</a></div></div></footer>
</body>
</html>
