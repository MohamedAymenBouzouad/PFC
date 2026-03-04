<?php
session_start();
if (empty($_SESSION['user_id']) || $_SESSION['role'] !== 'user') {
    header('Location: login.php'); exit;
}
require_once 'db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: mes_bordereaux.php'); exit;
}

$numero_bordereau = trim($_POST['numero_bordereau'] ?? '');
$region           = trim($_POST['region'] ?? '');
$date_envoi       = $_POST['date_envoi'] ?? date('Y-m-d');
$factures         = $_POST['factures'] ?? [];
$created_by       = $_SESSION['user_id'];

if (empty($numero_bordereau) || empty($region) || empty($factures)) {
    header('Location: mes_bordereaux.php?error=Champs+manquants'); exit;
}

try {
    $pdo = getDB();
    $pdo->beginTransaction();

    // Créer le bordereau
    $stmt = $pdo->prepare(
        "INSERT INTO bordereaux (numero_bordereau, region, created_by, statut, date_envoi)
         VALUES (?, ?, ?, 'envoye', ?)"
    );
    $stmt->execute([$numero_bordereau, $region, $created_by, $date_envoi]);
    $bordereau_id = $pdo->lastInsertId();

    // Créer chaque facture
    $stmt_f = $pdo->prepare(
        "INSERT INTO factures
         (bordereau_id, numero_facture, fournisseur_id, numero_contrat, montant, devise,
          date_emission, date_echeance, description, statut, created_by)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'en_attente', ?)"
    );

    foreach ($factures as $f) {
        $num     = trim($f['numero'] ?? '');
        $fourn   = (int)($f['fournisseur_id'] ?? 0);
        $montant = (float)($f['montant'] ?? 0);
        $devise  = $f['devise'] ?? 'DZD';
        $date_e  = $f['date_emission'] ?? date('Y-m-d');
        $date_ec = !empty($f['date_echeance']) ? $f['date_echeance'] : null;
        $contrat = trim($f['numero_contrat'] ?? '');
        $desc    = trim($f['description'] ?? '');

        if (empty($num) || $fourn == 0 || $montant <= 0) continue;

        $stmt_f->execute([
            $bordereau_id, $num, $fourn, $contrat ?: null,
            $montant, $devise, $date_e, $date_ec, $desc ?: null, $created_by
        ]);
    }

    // Notifier la secrétaire
    $secretaires = $pdo->query("SELECT id FROM utilisateurs WHERE role='secretaire' AND actif=1")->fetchAll();
    $stmt_n = $pdo->prepare(
        "INSERT INTO notifications (user_id, bordereau_id, type, message)
         VALUES (?, ?, 'info', ?)"
    );
    $msg = "Nouveau bordereau {$numero_bordereau} reçu de la région {$region}. Veuillez confirmer la réception.";
    foreach ($secretaires as $s) {
        $stmt_n->execute([$s['id'], $bordereau_id, $msg]);
    }

    $pdo->commit();
    header('Location: mes_bordereaux.php?success=1');
    exit;

} catch (PDOException $e) {
    $pdo->rollBack();
    if ($e->getCode() == 23000) {
        header('Location: mes_bordereaux.php?error=Numero+de+bordereau+ou+facture+deja+existant');
    } else {
        header('Location: mes_bordereaux.php?error=' . urlencode($e->getMessage()));
    }
    exit;
}
