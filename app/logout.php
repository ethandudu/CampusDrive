<?php
use CampusDrive\Infrastructure\Database\LogRepository;

require_once 'utils/db.php';
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

    if (isset($_SESSION['user_id'])) {
        (new LogRepository())->logLogout((string) $_SESSION['user_id']);
    }
    destroySession();
}

header("Location: login.php");
exit;
