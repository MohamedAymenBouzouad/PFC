<?php
// ============================================================
//  login.php  - Traitement du formulaire de connexion
// ============================================================
session_start();
require_once 'db.php';

// Si deja connecte → rediriger vers le dashboard
if (!empty($_SESSION['user_id'])) {
    header('Location: dashboard.php');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $role     = trim($_POST['role'] ?? '');

    if (empty($username) || empty($password) || empty($role)) {
        $error = 'Veuillez remplir tous les champs.';
    } else {
        try {
            $pdo  = getDB();
            $stmt = $pdo->prepare(
                'SELECT id, nom, prenom, username, password_hash, role, actif
                 FROM utilisateurs
                 WHERE username = :username
                 LIMIT 1'
            );
            $stmt->execute([':username' => $username]);
            $user = $stmt->fetch();

            if (
                $user &&
                $user['actif'] == 1 &&
                password_verify($password, $user['password_hash']) &&
                $user['role'] === $role
            ) {
                session_regenerate_id(true);

                $_SESSION['user_id']  = $user['id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['nom']      = $user['prenom'] . ' ' . $user['nom'];
                $_SESSION['role']     = $user['role'];

                header('Location: dashboard.php');
                exit;

            } else {
                $error = 'Identifiants incorrects ou rôle non autorisé.';
            }

        } catch (PDOException $e) {
            $error = 'Erreur serveur : ' . $e->getMessage();
        }
    }
}

include 'login_view.php';