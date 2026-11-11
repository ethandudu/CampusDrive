<?php
require_once 'utils/session.php';
require_once 'utils/i18n.php';

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST'
    && ($_POST['action'] ?? '') === 'change_locale') {
    if (!isset($_POST['csrf_token'])
        || !is_string($_POST['csrf_token'])
        || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        die(t('security_error'));
    }

    $newLocale = $_POST['locale'] ?? null;
    if (is_string($newLocale) && array_key_exists($newLocale, SUPPORTED_LOCALES)) {
        $_SESSION['locale'] = $newLocale;
    }

    header('Location: index.php');
    exit;
}

$copy = locale() === 'en'
    ? [
        'description' => 'CampusDrive, a free and simple document-sharing space for students.',
        'title' => 'CampusDrive - Student file sharing, made simple',
        'my_space' => 'My space',
        'sign_in' => 'Sign in',
        'create_account' => 'Create an account',
        'eyebrow' => 'File sharing designed for students',
        'heading' => 'All your class documents, in one place.',
        'intro' => 'CampusDrive makes it easy to share courses, resources, and files with students in an organized space for your class.',
        'get_started' => 'Get started for free',
        'discover' => 'Discover the features',
        'free' => '100% free: no credit card, no subscription.',
        'my_class' => 'My class',
        'active' => 'Active',
        'course_notes' => 'Course notes - S1.pdf',
        'exam_schedule' => 'Exam schedule.png',
        'past_exams' => 'Past exams 2025.pdf',
        'file_size_mb' => '2 MB',
        'file_size_kb' => '840 KB',
        'file_size_large_mb' => '4 MB',
        'used_space' => 'Storage used',
        'organized_space' => 'An organized space for your whole class',
        'why' => 'Why CampusDrive?',
        'features_heading' => 'Simple, collaborative, and accessible',
        'feature_one_title' => 'A space for every class',
        'feature_one' => 'Find all your class documents in an easy-to-browse folder structure.',
        'feature_two_title' => 'Controlled sharing',
        'feature_two' => 'Uploaded files are scanned by ClamAV antivirus, then reviewed by the class sponsor before they are visible to the group.',
        'feature_three_title' => 'Free by design',
        'feature_three' => 'CampusDrive is free: no intrusive ads, hidden fees, or subscriptions.',
        'cta_heading' => 'Ready to share better?',
        'cta_text' => 'Create your space and invite your class in just a few minutes.',
        'contact' => 'Contact',
        'privacy' => 'Privacy policy (GDPR)',
        'terms' => 'Terms of service',
    ]
    : [
        'description' => 'CampusDrive, l’espace de partage de documents entre étudiants, gratuit et simple.',
        'title' => 'CampusDrive - Le partage étudiant, simplement',
        'my_space' => 'Mon espace',
        'sign_in' => 'Se connecter',
        'create_account' => 'Créer un compte',
        'eyebrow' => 'L’espace de partage pensé pour les étudiants',
        'heading' => 'Tous vos documents de promotion, au même endroit.',
        'intro' => 'CampusDrive facilite le partage de cours, de ressources et de fichiers entre étudiants, dans un espace organisé par promotion.',
        'get_started' => 'Commencer gratuitement',
        'discover' => 'Découvrir les fonctionnalités',
        'free' => '100 % gratuit : aucune carte bancaire, aucun abonnement.',
        'my_class' => 'Ma promotion',
        'active' => 'Active',
        'course_notes' => 'Notes de cours - S1.pdf',
        'exam_schedule' => 'Planning examens.png',
        'past_exams' => 'Annales 2025.pdf',
        'file_size_mb' => '2 Mo',
        'file_size_kb' => '840 Ko',
        'file_size_large_mb' => '4 Mo',
        'used_space' => 'Espace utilisé',
        'organized_space' => 'Un espace clair pour toute la promotion',
        'why' => 'Pourquoi CampusDrive ?',
        'features_heading' => 'Simple, collaboratif et accessible',
        'feature_one_title' => 'Un espace par promotion',
        'feature_one' => 'Retrouvez tous les documents de votre promotion dans une arborescence facile à parcourir.',
        'feature_two_title' => 'Partage contrôlé',
        'feature_two' => 'Les fichiers envoyés sont analysés par l’antivirus ClamAV, puis vérifiés par le parrain avant d’être visibles par le groupe.',
        'feature_three_title' => 'Gratuit par nature',
        'feature_three' => 'CampusDrive est gratuit : pas de publicité intrusive, pas de frais cachés, pas d’abonnement.',
        'cta_heading' => 'Prêt à mieux partager ?',
        'cta_text' => 'Créez votre espace et invitez votre promotion en quelques minutes.',
        'contact' => 'Contact',
        'privacy' => 'Politique de confidentialité (RGPD)',
        'terms' => 'CGU',
    ];
?>
<!DOCTYPE html>
<html lang="<?= htmlspecialchars(locale(), ENT_QUOTES, 'UTF-8') ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="<?= htmlspecialchars($copy['description'], ENT_QUOTES, 'UTF-8') ?>">
    <title><?= htmlspecialchars($copy['title'], ENT_QUOTES, 'UTF-8') ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="assets/css/site.css" rel="stylesheet">
    <link rel="icon" type="image/png" href="assets/favicon.ico">
</head>
<body>
<nav class="navbar navbar-expand-lg navbar-light bg-white border-bottom">
    <div class="container">
        <a class="navbar-brand fw-bold text-primary" href="index.php">CampusDrive</a>
        <div class="d-flex gap-2">
            <?php if (isset($_SESSION['user_id'])): ?>
                <a class="btn btn-primary btn-sm" href="student.php"><?= htmlspecialchars($copy['my_space'], ENT_QUOTES, 'UTF-8') ?></a>
            <?php else: ?>
                <a class="btn btn-outline-primary btn-sm" href="login.php"><?= htmlspecialchars($copy['sign_in'], ENT_QUOTES, 'UTF-8') ?></a>
                <a class="btn btn-primary btn-sm" href="register.php"><?= htmlspecialchars($copy['create_account'], ENT_QUOTES, 'UTF-8') ?></a>
            <?php endif; ?>
            <form method="post" action="index.php">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8') ?>">
                <input type="hidden" name="action" value="change_locale">
                <label class="visually-hidden" for="locale"><?= htmlspecialchars(locale() === 'en' ? 'Language' : 'Langue', ENT_QUOTES, 'UTF-8') ?></label>
                <select class="form-select form-select-sm" id="locale" name="locale" aria-label="<?= htmlspecialchars(locale() === 'en' ? 'Language' : 'Langue', ENT_QUOTES, 'UTF-8') ?>" onchange="this.form.submit()">
                    <?php foreach (SUPPORTED_LOCALES as $code => $name): ?>
                        <option value="<?= htmlspecialchars($code, ENT_QUOTES, 'UTF-8') ?>" <?= locale() === $code ? 'selected' : '' ?>>
                            <?= htmlspecialchars($name, ENT_QUOTES, 'UTF-8') ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </form>
        </div>
    </div>
</nav>
<main>
    <section class="hero-section">
        <div class="container py-5"><div class="row align-items-center g-5">
            <div class="col-lg-7">
                <span class="eyebrow"><?= htmlspecialchars($copy['eyebrow'], ENT_QUOTES, 'UTF-8') ?></span>
                <h1 class="display-4 fw-bold mt-3"><?= htmlspecialchars($copy['heading'], ENT_QUOTES, 'UTF-8') ?></h1>
                <p class="lead text-secondary mt-3"><?= htmlspecialchars($copy['intro'], ENT_QUOTES, 'UTF-8') ?></p>
                <div class="d-flex flex-wrap gap-3 mt-4"><a href="register.php" class="btn btn-primary btn-lg px-4"><?= htmlspecialchars($copy['get_started'], ENT_QUOTES, 'UTF-8') ?></a><a href="#fonctionnalites" class="btn btn-outline-secondary btn-lg px-4"><?= htmlspecialchars($copy['discover'], ENT_QUOTES, 'UTF-8') ?></a></div>
                <p class="small text-secondary mt-3 mb-0"><strong><?= htmlspecialchars($copy['free'], ENT_QUOTES, 'UTF-8') ?></strong></p>
            </div>
            <div class="col-lg-5"><div class="hero-card shadow-sm">
                <div class="d-flex justify-content-between align-items-center mb-4"><span class="fw-bold"><?= htmlspecialchars($copy['my_class'], ENT_QUOTES, 'UTF-8') ?></span><span class="badge rounded-pill text-bg-success"><?= htmlspecialchars($copy['active'], ENT_QUOTES, 'UTF-8') ?></span></div>
                <div class="document-row"><span class="document-icon">PDF</span><span><?= htmlspecialchars($copy['course_notes'], ENT_QUOTES, 'UTF-8') ?></span><small><?= htmlspecialchars($copy['file_size_mb'], ENT_QUOTES, 'UTF-8') ?></small></div>
                <div class="document-row"><span class="document-icon">IMG</span><span><?= htmlspecialchars($copy['exam_schedule'], ENT_QUOTES, 'UTF-8') ?></span><small><?= htmlspecialchars($copy['file_size_kb'], ENT_QUOTES, 'UTF-8') ?></small></div>
                <div class="document-row"><span class="document-icon">PDF</span><span><?= htmlspecialchars($copy['past_exams'], ENT_QUOTES, 'UTF-8') ?></span><small><?= htmlspecialchars($copy['file_size_large_mb'], ENT_QUOTES, 'UTF-8') ?></small></div>
                <div class="progress mt-4" role="progressbar" aria-label="<?= htmlspecialchars($copy['used_space'], ENT_QUOTES, 'UTF-8') ?>" aria-valuenow="35" aria-valuemin="0" aria-valuemax="100"><div class="progress-bar" style="width: 35%"></div></div>
                <small class="text-secondary d-block mt-2"><?= htmlspecialchars($copy['organized_space'], ENT_QUOTES, 'UTF-8') ?></small>
            </div></div>
        </div></div>
    </section>
    <section id="fonctionnalites" class="py-5"><div class="container">
        <div class="text-center mb-5"><span class="eyebrow"><?= htmlspecialchars($copy['why'], ENT_QUOTES, 'UTF-8') ?></span><h2 class="fw-bold mt-2"><?= htmlspecialchars($copy['features_heading'], ENT_QUOTES, 'UTF-8') ?></h2></div>
        <div class="row g-4">
            <div class="col-md-4"><div class="feature-card h-100"><div class="feature-icon">01</div><h3 class="h5"><?= htmlspecialchars($copy['feature_one_title'], ENT_QUOTES, 'UTF-8') ?></h3><p class="text-secondary mb-0"><?= htmlspecialchars($copy['feature_one'], ENT_QUOTES, 'UTF-8') ?></p></div></div>
            <div class="col-md-4"><div class="feature-card h-100"><div class="feature-icon">02</div><h3 class="h5"><?= htmlspecialchars($copy['feature_two_title'], ENT_QUOTES, 'UTF-8') ?></h3><p class="text-secondary mb-0"><?= htmlspecialchars($copy['feature_two'], ENT_QUOTES, 'UTF-8') ?></p></div></div>
            <div class="col-md-4"><div class="feature-card h-100"><div class="feature-icon">03</div><h3 class="h5"><?= htmlspecialchars($copy['feature_three_title'], ENT_QUOTES, 'UTF-8') ?></h3><p class="text-secondary mb-0"><?= htmlspecialchars($copy['feature_three'], ENT_QUOTES, 'UTF-8') ?></p></div></div>
        </div>
    </div></section>
    <section class="cta-section py-5"><div class="container text-center"><h2 class="fw-bold"><?= htmlspecialchars($copy['cta_heading'], ENT_QUOTES, 'UTF-8') ?></h2><p class="text-secondary"><?= htmlspecialchars($copy['cta_text'], ENT_QUOTES, 'UTF-8') ?></p><a href="register.php" class="btn btn-primary px-4"><?= htmlspecialchars($copy['create_account'], ENT_QUOTES, 'UTF-8') ?></a></div></section>
</main>
<footer class="border-top bg-white py-4"><div class="container d-flex flex-wrap justify-content-between gap-3 small"><span class="text-secondary">&copy; <?= date('Y') ?> CampusDrive</span><div class="d-flex gap-3"><a href="contact.php"><?= htmlspecialchars($copy['contact'], ENT_QUOTES, 'UTF-8') ?></a><a href="privacy.php"><?= htmlspecialchars($copy['privacy'], ENT_QUOTES, 'UTF-8') ?></a><a href="cgu.php"><?= htmlspecialchars($copy['terms'], ENT_QUOTES, 'UTF-8') ?></a></div></div></footer>
</body>
</html>
