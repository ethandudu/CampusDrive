<?php
require_once 'utils/session.php';
require_once 'utils/i18n.php';

require_once 'src/Infrastructure/Database/UserRepository.php';
use CampusDrive\Infrastructure\Database\UserRepository;

$userRepository = new UserRepository();

if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['token'])) {
    $token = htmlspecialchars($_GET['token'], ENT_QUOTES, 'UTF-8');
    if ($userRepository->activateUser($token)) {
        header("Location: login.php?activated=1");
    } else {
        header("Location: login.php?activated=0");
    }
    exit;

}
exit;
