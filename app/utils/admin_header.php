<nav class="navbar navbar-dark bg-dark mb-4 shadow">
    <div class="container">
        <span class="navbar-brand mb-0 h1"><?= t('administration') ?></span>
        <img src="assets/img/campusdrivewhite.webp" alt="Logo" class="img-fluid" style="max-height: 50px;">
        <div>
            <a href="settings.php" class="btn btn-outline-light btn-sm me-2"><?= t('settings') ?></a>
            <form method="POST" action="logout.php" class="d-inline">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8') ?>">
                <button type="submit" class="btn btn-outline-light btn-sm"><?= t('logout') ?></button>
            </form>
        </div>
    </div>
</nav>
