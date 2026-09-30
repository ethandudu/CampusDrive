<?php
require_once 'utils/session.php';
require_once 'utils/i18n.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (
        !isset($_SESSION['csrf_token'], $_POST['csrf_token'])
        || !is_string($_POST['csrf_token'])
        || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])
    ) {
        die(t('security_error'));
    }

    destroySession();
}

header("Location: login.php");
exit;
