<?php
// ============================================================
//  ajouter_facture.php - Traitement ajout facture
// ============================================================
session_start();
if (empty($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

require_once 'db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $numero        = trim($_POST['numero_facture'] ?? '');
    $fournisseur   = (int)($_POST['fournisseur_id'] ?? 0);
    $montant       = (float)($_POST['montant'] ?? 0);
    $date_emission = $_POST['date_emission'] ?? date('Y-m-d');
    $date_echeance = !empty($_POST['date_echeance']) ? $_POST['date_echeance'] : null;
    $region        = trim($_POST['region'] ?? '');
    $description   = trim($_POST['description'] ?? '');
    $created_by    = $_SESSION['user_id'];

    if (empty($numero) || $fournisseur == 0 || $montant <= 0 || empty($date_emission)) {
        header('Location: dashboard.php?error=champs_manquants');
        exit;
    }

    try {
        $pdo = getDB();
        $stmt = $pdo->prepare(
            "INSERT INTO factures
             (numero_facture, fournisseur_id, montant, date_emission, date_echeance, region, description, statut, created_by)
             VALUES
             (:numero, :fournisseur_id, :montant, :date_emission, :date_echeance, :region, :description, 'en_attente', :created_by)"
        );
        $stmt->execute([
            ':numero'        => $numero,
            ':fournisseur_id'=> $fournisseur,
            ':montant'       => $montant,
            ':date_emission' => $date_emission,
            ':date_echeance' => $date_echeance,
            ':region'        => $region,
            ':description'   => $description,
            ':created_by'    => $created_by,
        ]);

        header('Location: dashboard.php?success=1');
        exit;

    } catch (PDOException $e) {
        // Numéro de facture déjà existant
        if ($e->getCode() == 23000) {
            header('Location: dashboard.php?error=numero_existant');
        } else {
            header('Location: dashboard.php?error=erreur_serveur');
        }
        exit;
    }
}

header('Location: dashboard.php');
exit;