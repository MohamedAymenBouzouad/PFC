<?php
// confirmer_reception.php - Secretaire confirme reception bordereau
session_start();
if (empty($_SESSION['user_id']) || $_SESSION['role'] !== 'secretaire') {
    header('Location: login.php'); exit;
}
require_once 'db.php';

$id = (int)($_GET['id'] ?? 0);
if (!$id) { header('Location: reception_bordereaux.php'); exit; }

$pdo = getDB();
$pdo->beginTransaction();
try {
    // Mettre à jour le bordereau
    $stmt = $pdo->prepare(
        "UPDATE bordereaux SET statut='recu', date_reception=CURDATE(), recu_par=? WHERE id=? AND statut='envoye'"
    );
    $stmt->execute([$_SESSION['user_id'], $id]);

    // Récupérer info bordereau
    $b = $pdo->prepare("SELECT b.numero_bordereau, b.region, b.created_by FROM bordereaux b WHERE b.id=?");
    $b->execute([$id]);
    $bordereau = $b->fetch();

    if ($bordereau) {
        // Notifier les gestionnaires
        $gestionnaires = $pdo->query("SELECT id FROM utilisateurs WHERE role='gestionnaire' AND actif=1")->fetchAll();
        $stmt_n = $pdo->prepare("INSERT INTO notifications (user_id, bordereau_id, type, message) VALUES (?,?,'info',?)");
        $msg = "Le bordereau {$bordereau['numero_bordereau']} de la région {$bordereau['region']} a été reçu. Vous pouvez commencer le traitement.";
        foreach ($gestionnaires as $g) {
            $stmt_n->execute([$g['id'], $id, $msg]);
        }

        // Mettre les factures en traitement
        $pdo->prepare("UPDATE factures SET statut='en_traitement' WHERE bordereau_id=?")->execute([$id]);
    }

    $pdo->commit();
    header('Location: reception_bordereaux.php?success=1');
} catch (PDOException $e) {
    $pdo->rollBack();
    header('Location: reception_bordereaux.php?error=' . urlencode($e->getMessage()));
}
exit;
