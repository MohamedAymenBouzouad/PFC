<?php
session_start();
if (empty($_SESSION['user_id'])) { header('Location: login.php'); exit; }
require_once 'db.php';
$pdo = getDB();
$redirect = $_GET['redirect'] ?? 'dashboard.php';

if (isset($_GET['all'])) {
    $pdo->prepare("UPDATE notifications SET lu=1 WHERE user_id=?")->execute([$_SESSION['user_id']]);
} elseif (isset($_GET['id'])) {
    $pdo->prepare("UPDATE notifications SET lu=1 WHERE id=? AND user_id=?")->execute([(int)$_GET['id'], $_SESSION['user_id']]);
}
header('Location: ' . $redirect);
exit;
