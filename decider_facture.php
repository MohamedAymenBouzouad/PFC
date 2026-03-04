<?php
// decider_facture.php - Gestionnaire valide / refuse / met en attente une facture
session_start();
if (empty($_SESSION['user_id']) || $_SESSION['role'] !== 'gestionnaire') {
    header('Location: login.php'); exit;
}
require_once 'db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: traitement_factures.php'); exit;
}

$facture_id  = (int)($_POST['facture_id'] ?? 0);
$decision    = $_POST['decision'] ?? '';
$motif_refus = trim($_POST['motif_refus'] ?? '');
$redirect    = $_POST['redirect'] ?? 'traitement_factures.php';

$decisions_valides = ['validee', 'rejetee', 'en_attente'];
if (!$facture_id || !in_array($decision, $decisions_valides)) {
    header('Location: ' . $redirect); exit;
}

if ($decision === 'rejetee' && empty($motif_refus)) {
    header('Location: ' . $redirect . '?error=motif_requis'); exit;
}

$pdo = getDB();
$pdo->beginTransaction();

try {
    // Récupérer la facture avec info bordereau et agent
    $stmt = $pdo->prepare(
        "SELECT f.*, b.numero_bordereau, b.created_by as agent_id, u.prenom, u.nom
         FROM factures f
         JOIN bordereaux b ON b.id = f.bordereau_id
         JOIN utilisateurs u ON u.id = b.created_by
         WHERE f.id = ?"
    );
    $stmt->execute([$facture_id]);
    $facture = $stmt->fetch();

    if (!$facture) {
        $pdo->rollBack();
        header('Location: ' . $redirect . '?error=facture_introuvable'); exit;
    }

    $ancien_statut = $facture['statut'];

    // Mettre à jour la facture
    $stmt_update = $pdo->prepare(
        "UPDATE factures
         SET statut=?, motif_refus=?, traite_par=?, date_traitement=NOW(), updated_at=NOW()
         WHERE id=?"
    );
    $stmt_update->execute([
        $decision,
        $decision === 'rejetee' ? $motif_refus : null,
        $_SESSION['user_id'],
        $facture_id
    ]);

    // Historique
    $stmt_h = $pdo->prepare(
        "INSERT INTO historique_factures (facture_id, user_id, ancien_statut, nouveau_statut, commentaire)
         VALUES (?, ?, ?, ?, ?)"
    );
    $stmt_h->execute([
        $facture_id,
        $_SESSION['user_id'],
        $ancien_statut,
        $decision,
        $decision === 'rejetee' ? $motif_refus : null
    ]);

    // ── Notification vers l'agent région ──
    if ($decision === 'rejetee') {
        $message = "❌ Votre facture {$facture['numero_facture']} (bordereau {$facture['numero_bordereau']}) a été REFUSÉE.\nMotif : {$motif_refus}";
        $type    = 'refus';
    } elseif ($decision === 'validee') {
        $message = "✅ Votre facture {$facture['numero_facture']} (bordereau {$facture['numero_bordereau']}) a été VALIDÉE.";
        $type    = 'validation';
    } else {
        $message = "⏸ Votre facture {$facture['numero_facture']} a été mise EN ATTENTE (bordereau {$facture['numero_bordereau']} non encore reçu d'une autre région).";
        $type    = 'info';
    }

    $stmt_n = $pdo->prepare(
        "INSERT INTO notifications (user_id, facture_id, bordereau_id, type, message)
         VALUES (?, ?, ?, ?, ?)"
    );
    $stmt_n->execute([
        $facture['agent_id'],
        $facture_id,
        null,
        $type,
        $message
    ]);

    // Vérifier si toutes les factures du bordereau sont traitées → clôturer
    $non_traites = $pdo->prepare(
        "SELECT COUNT(*) FROM factures WHERE bordereau_id=? AND statut IN ('en_traitement','en_attente')"
    );
    $non_traites->execute([$facture['bordereau_id'] ?? 0]);
    if ((int)$non_traites->fetchColumn() === 0) {
        $pdo->prepare("UPDATE bordereaux SET statut='cloture' WHERE id=?")
            ->execute([$facture['bordereau_id']]);
    }

    $pdo->commit();
    header('Location: ' . $redirect . '?success=1');

} catch (PDOException $e) {
    $pdo->rollBack();
    header('Location: ' . $redirect . '?error=' . urlencode($e->getMessage()));
}
exit;
