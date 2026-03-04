<?php
session_start();
if (empty($_SESSION['user_id']) || $_SESSION['role'] !== 'gestionnaire') {
    header('Location: login.php'); exit;
}
require_once 'db.php';
$pdo = getDB();

$nom_user  = $_SESSION['nom'] ?? '';
$initiales = strtoupper(substr($nom_user,0,1).(strpos($nom_user,' ')!==false?substr($nom_user,strpos($nom_user,' ')+1,1):''));

// Bordereaux reçus à traiter
$bordereaux = $pdo->query(
    "SELECT b.*, u.prenom, u.nom as nom_agent,
            COUNT(f.id) as nb_total,
            SUM(CASE WHEN f.statut='validee' THEN 1 ELSE 0 END) as nb_validees,
            SUM(CASE WHEN f.statut='rejetee' THEN 1 ELSE 0 END) as nb_rejetees,
            SUM(CASE WHEN f.statut='en_traitement' THEN 1 ELSE 0 END) as nb_en_cours
     FROM bordereaux b
     LEFT JOIN utilisateurs u ON u.id = b.created_by
     LEFT JOIN factures f ON f.bordereau_id = b.id
     WHERE b.statut IN ('recu','en_traitement')
     GROUP BY b.id ORDER BY b.date_reception DESC"
)->fetchAll();

$notifs_count = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE user_id=? AND lu=0");
$notifs_count->execute([$_SESSION['user_id']]);
$nb_notifs = $notifs_count->fetchColumn();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Traitement Factures – Gestionnaire</title>
  <link rel="stylesheet" href="css/bootstrap.min.css">
  <style>
    @import url('https://fonts.googleapis.com/css2?family=Rajdhani:wght@400;600;700&family=Nunito:wght@300;400;600;700&display=swap');
    :root{--sidebar-bg:#111318;--main-bg:#0d0f14;--card-bg:#1a1d26;--card-border:#242733;--accent-orange:#e05c00;--accent-green:#00c875;--accent-blue:#00bfff;--accent-red:#ff4d4d;--text-muted:rgba(255,255,255,0.4);--sidebar-w:240px;}
    *{box-sizing:border-box;}body{font-family:'Nunito',sans-serif;background:var(--main-bg);color:#e8eaf0;margin:0;display:flex;min-height:100vh;}
    .sidebar{width:var(--sidebar-w);background:var(--sidebar-bg);display:flex;flex-direction:column;position:fixed;top:0;left:0;bottom:0;border-right:1px solid var(--card-border);z-index:100;}
    .sidebar-brand{padding:28px 24px 20px;border-bottom:1px solid var(--card-border);}
    .sidebar-brand h2{font-family:'Rajdhani',sans-serif;font-size:1.2rem;font-weight:700;color:#fff;letter-spacing:2px;margin:0;}
    .sidebar-brand span{display:block;font-size:0.68rem;letter-spacing:3px;color:var(--accent-blue);text-transform:uppercase;}
    .sidebar-user{padding:14px 24px;border-bottom:1px solid var(--card-border);display:flex;align-items:center;gap:10px;}
    .user-avatar{width:36px;height:36px;background:linear-gradient(135deg,var(--accent-blue),#007acc);border-radius:50%;display:flex;align-items:center;justify-content:center;font-family:'Rajdhani',sans-serif;font-weight:700;font-size:0.85rem;color:#fff;flex-shrink:0;}
    .user-info strong{font-size:0.82rem;color:#fff;display:block;}
    .user-role-badge{font-size:0.65rem;color:var(--accent-blue);text-transform:uppercase;letter-spacing:1px;}
    .sidebar-nav{flex:1;padding:16px 0;}
    .nav-item{display:flex;align-items:center;gap:12px;padding:11px 24px;color:rgba(255,255,255,0.5);text-decoration:none;font-size:0.87rem;font-weight:600;border-left:3px solid transparent;transition:all 0.2s;}
    .nav-item:hover{color:#fff;background:rgba(255,255,255,0.04);}
    .nav-item.active{color:#fff;background:rgba(0,191,255,0.1);border-left-color:var(--accent-blue);}
    .nav-item svg{width:18px;height:18px;flex-shrink:0;}
    .sidebar-footer{padding:16px 24px;border-top:1px solid var(--card-border);}
    .btn-logout{display:flex;align-items:center;gap:8px;color:var(--accent-red);font-size:0.85rem;font-weight:600;text-decoration:none;}
    .main-content{margin-left:var(--sidebar-w);flex:1;display:flex;flex-direction:column;}
    .topbar{background:var(--sidebar-bg);border-bottom:1px solid var(--card-border);padding:16px 32px;display:flex;align-items:center;justify-content:space-between;position:sticky;top:0;z-index:50;}
    .topbar h1{font-family:'Rajdhani',sans-serif;font-size:1.5rem;font-weight:700;color:#fff;letter-spacing:1px;margin:0;}
    .content-area{padding:28px 32px;flex:1;}

    .bordereau-card{background:var(--card-bg);border:1px solid var(--card-border);border-radius:14px;margin-bottom:24px;overflow:hidden;}
    .bordereau-header{padding:18px 24px;border-bottom:1px solid var(--card-border);display:flex;align-items:center;justify-content:space-between;background:rgba(255,255,255,0.02);}
    .bordereau-title{font-family:'Rajdhani',sans-serif;font-size:1.05rem;font-weight:700;color:#fff;letter-spacing:1px;}
    .bordereau-meta{font-size:0.78rem;color:var(--text-muted);margin-top:3px;}
    .progress-bar-custom{height:6px;background:rgba(255,255,255,0.08);border-radius:3px;overflow:hidden;margin-top:8px;}
    .progress-fill{height:100%;border-radius:3px;background:linear-gradient(90deg,var(--accent-green),#00a860);}

    table.t{width:100%;border-collapse:collapse;}
    .t th{padding:11px 20px;font-size:0.7rem;font-weight:700;letter-spacing:2px;text-transform:uppercase;color:var(--text-muted);text-align:left;border-bottom:1px solid var(--card-border);}
    .t td{padding:12px 20px;font-size:0.85rem;border-bottom:1px solid rgba(255,255,255,0.04);color:rgba(255,255,255,0.75);vertical-align:middle;}
    .t tr:last-child td{border-bottom:none;}
    .badge-s{display:inline-block;padding:3px 12px;border-radius:20px;font-size:0.72rem;font-weight:700;}
    .badge-pending{background:rgba(224,92,0,0.15);color:var(--accent-orange);}
    .badge-approved{background:rgba(0,200,117,0.15);color:var(--accent-green);}
    .badge-rejected{background:rgba(255,77,77,0.15);color:var(--accent-red);}
    .badge-process{background:rgba(0,191,255,0.15);color:var(--accent-blue);}

    .action-btns{display:flex;gap:6px;flex-wrap:wrap;}
    .btn-valider{background:rgba(0,200,117,0.2);color:var(--accent-green);border:1px solid rgba(0,200,117,0.3);padding:5px 14px;border-radius:6px;font-size:0.75rem;font-weight:700;cursor:pointer;transition:all 0.2s;}
    .btn-valider:hover{background:rgba(0,200,117,0.35);}
    .btn-refuser{background:rgba(255,77,77,0.15);color:var(--accent-red);border:1px solid rgba(255,77,77,0.25);padding:5px 14px;border-radius:6px;font-size:0.75rem;font-weight:700;cursor:pointer;transition:all 0.2s;}
    .btn-refuser:hover{background:rgba(255,77,77,0.3);}
    .btn-attente{background:rgba(224,92,0,0.15);color:var(--accent-orange);border:1px solid rgba(224,92,0,0.25);padding:5px 14px;border-radius:6px;font-size:0.75rem;font-weight:700;cursor:pointer;transition:all 0.2s;}
    .btn-attente:hover{background:rgba(224,92,0,0.3);}
    .motif-refus{font-size:0.75rem;color:var(--accent-red);font-style:italic;margin-top:4px;}

    /* MODAL REFUS */
    .modal-content{background:#1a1d26;border:1px solid var(--card-border);border-radius:16px;color:#e8eaf0;}
    .modal-header{border-bottom:1px solid var(--card-border);padding:20px 24px;}
    .modal-title{font-family:'Rajdhani',sans-serif;font-size:1.2rem;font-weight:700;color:#fff;}
    .modal-footer{border-top:1px solid var(--card-border);}
    .btn-close{filter:invert(1);}
    .modal .form-label{color:rgba(255,255,255,0.6);font-size:0.8rem;font-weight:600;text-transform:uppercase;letter-spacing:1px;}
    .modal .form-control{background:rgba(255,255,255,0.06);border:1px solid var(--card-border);color:#fff;border-radius:8px;}
    .modal .form-control:focus{background:rgba(255,255,255,0.1);border-color:var(--accent-red);box-shadow:0 0 0 3px rgba(255,77,77,0.2);color:#fff;}
    .modal .form-control::placeholder{color:rgba(255,255,255,0.25);}
    .btn-confirm-refus{background:linear-gradient(135deg,var(--accent-red),#cc0000);border:none;color:#fff;font-family:'Rajdhani',sans-serif;font-weight:700;letter-spacing:1px;padding:10px 28px;border-radius:8px;}
    .btn-cancel{background:rgba(255,255,255,0.06);border:1px solid var(--card-border);color:rgba(255,255,255,0.6);border-radius:8px;padding:10px 20px;}
    .alert-success-custom{background:rgba(0,200,117,0.15);border:1px solid rgba(0,200,117,0.3);color:var(--accent-green);border-radius:10px;padding:12px 16px;margin-bottom:20px;}
    .empty-state{text-align:center;padding:48px;color:var(--text-muted);font-size:0.9rem;}
  </style>
</head>
<body>
<aside class="sidebar">
  <div class="sidebar-brand"><h2>SUIVI FACTURES</h2><span>Gestionnaire</span></div>
  <div class="sidebar-user">
    <div class="user-avatar"><?= $initiales ?></div>
    <div class="user-info"><strong><?= htmlspecialchars($nom_user) ?></strong><div class="user-role-badge">Gestionnaire</div></div>
  </div>
  <nav class="sidebar-nav">
    <a class="nav-item" href="dashboard.php"><svg viewBox="0 0 24 24" fill="currentColor"><path d="M3 13h8V3H3v10zm0 8h8v-6H3v6zm10 0h8V11h-8v10zm0-18v6h8V3h-8z"/></svg>Accueil</a>
    <a class="nav-item active" href="traitement_factures.php"><svg viewBox="0 0 24 24" fill="currentColor"><path d="M9 16.2L4.8 12l-1.4 1.4L9 19 21 7l-1.4-1.4L9 16.2z"/></svg>Traitement Factures</a>
  </nav>
  <div class="sidebar-footer"><a href="logout.php" class="btn-logout"><svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor"><path d="M17 7l-1.41 1.41L18.17 11H8v2h10.17l-2.58 2.58L17 17l5-5zM4 5h8V3H4c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h8v-2H4V5z"/></svg>Déconnexion</a></div>
</aside>

<div class="main-content">
  <div class="topbar"><h1>Traitement des Factures</h1></div>
  <div class="content-area">

    <?php if(isset($_GET['success'])): ?>
      <div class="alert-success-custom">✅ Décision enregistrée et agent notifié !</div>
    <?php endif; ?>

    <?php if(empty($bordereaux)): ?>
      <div class="bordereau-card"><p class="empty-state">Aucun bordereau en attente de traitement.</p></div>
    <?php else: ?>
      <?php foreach($bordereaux as $b):
        $nb_total   = (int)$b['nb_total'];
        $nb_traites = (int)$b['nb_validees'] + (int)$b['nb_rejetees'];
        $pct        = $nb_total > 0 ? round($nb_traites / $nb_total * 100) : 0;

        // Récupérer les factures de ce bordereau
        $stmt = $pdo->prepare(
            "SELECT f.*, fo.nom as fournisseur_nom FROM factures f
             LEFT JOIN fournisseurs fo ON fo.id = f.fournisseur_id
             WHERE f.bordereau_id = ? ORDER BY f.id"
        );
        $stmt->execute([$b['id']]);
        $factures = $stmt->fetchAll();
      ?>
      <div class="bordereau-card">
        <div class="bordereau-header">
          <div>
            <div class="bordereau-title">📋 <?= htmlspecialchars($b['numero_bordereau']) ?></div>
            <div class="bordereau-meta">
              Agent : <?= htmlspecialchars($b['prenom'].' '.$b['nom_agent']) ?> |
              Région : <?= htmlspecialchars($b['region']) ?> |
              Reçu le : <?= date('d/m/Y', strtotime($b['date_reception'])) ?>
            </div>
            <div class="progress-bar-custom" style="width:200px;">
              <div class="progress-fill" style="width:<?= $pct ?>%"></div>
            </div>
            <div style="font-size:0.72rem;color:var(--text-muted);margin-top:2px;"><?= $nb_traites ?>/<?= $nb_total ?> factures traitées (<?= $pct ?>%)</div>
          </div>
          <div style="text-align:right;font-size:0.8rem;color:var(--text-muted);">
            <span style="color:var(--accent-green);">✓ <?= $b['nb_validees'] ?> validées</span> &nbsp;
            <span style="color:var(--accent-red);">✗ <?= $b['nb_rejetees'] ?> rejetées</span> &nbsp;
            <span style="color:var(--accent-blue);">⏳ <?= $b['nb_en_cours'] ?> en cours</span>
          </div>
        </div>

        <table class="t">
          <thead><tr>
            <th>N° Facture</th><th>Fournisseur</th><th>N° Contrat</th><th>Montant</th><th>Devise</th><th>Statut</th><th>Décision</th>
          </tr></thead>
          <tbody>
            <?php foreach($factures as $f):
              $statut = $f['statut'];
            ?>
            <tr>
              <td><strong><?= htmlspecialchars($f['numero_facture']) ?></strong></td>
              <td><?= htmlspecialchars($f['fournisseur_nom'] ?? '-') ?></td>
              <td><?= htmlspecialchars($f['numero_contrat'] ?? '-') ?></td>
              <td><?= number_format((float)$f['montant'], 2, ',', ' ') ?></td>
              <td><?= $f['devise'] ?></td>
              <td>
                <?php if($statut === 'validee'): ?>
                  <span class="badge-s badge-approved">✓ Validée</span>
                <?php elseif($statut === 'rejetee'): ?>
                  <span class="badge-s badge-rejected">✗ Rejetée</span>
                  <?php if($f['motif_refus']): ?>
                    <div class="motif-refus">Motif : <?= htmlspecialchars($f['motif_refus']) ?></div>
                  <?php endif; ?>
                <?php elseif($statut === 'en_attente'): ?>
                  <span class="badge-s badge-pending">⏸ En Attente</span>
                <?php else: ?>
                  <span class="badge-s badge-process">⏳ En Traitement</span>
                <?php endif; ?>
              </td>
              <td>
                <?php if($statut === 'en_traitement' || $statut === 'en_attente'): ?>
                  <div class="action-btns">
                    <form method="POST" action="decider_facture.php" style="display:inline;">
                      <input type="hidden" name="facture_id" value="<?= $f['id'] ?>">
                      <input type="hidden" name="decision" value="validee">
                      <input type="hidden" name="redirect" value="traitement_factures.php">
                      <button type="submit" class="btn-valider" onclick="return confirm('Valider cette facture ?')">✓ Valider</button>
                    </form>
                    <button type="button" class="btn-refuser"
                      onclick="ouvrirRefus(<?= $f['id'] ?>, '<?= htmlspecialchars($f['numero_facture']) ?>')">
                      ✗ Refuser
                    </button>
                    <form method="POST" action="decider_facture.php" style="display:inline;">
                      <input type="hidden" name="facture_id" value="<?= $f['id'] ?>">
                      <input type="hidden" name="decision" value="en_attente">
                      <input type="hidden" name="redirect" value="traitement_factures.php">
                      <button type="submit" class="btn-attente">⏸ En Attente</button>
                    </form>
                  </div>
                <?php else: ?>
                  <span style="font-size:0.75rem;color:var(--text-muted);">— Traité —</span>
                <?php endif; ?>
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <?php endforeach; ?>
    <?php endif; ?>

  </div>
</div>

<!-- MODAL REFUS -->
<div class="modal fade" id="modalRefus" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <form method="POST" action="decider_facture.php">
        <input type="hidden" name="decision" value="rejetee">
        <input type="hidden" name="redirect" value="traitement_factures.php">
        <input type="hidden" name="facture_id" id="refus_facture_id">
        <div class="modal-header">
          <h5 class="modal-title">✗ Motif de Refus</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body p-4">
          <p style="color:var(--text-muted);font-size:0.85rem;">Facture : <strong id="refus_numero" style="color:#fff;"></strong></p>
          <div class="mb-3">
            <label class="form-label">Motif du refus *</label>
            <textarea class="form-control" name="motif_refus" rows="4"
              placeholder="Ex: Document incomplet, montant incorrect, contrat inexistant..." required></textarea>
          </div>
          <p style="font-size:0.78rem;color:var(--accent-red);">⚠️ L'agent de la région sera automatiquement notifié du refus.</p>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-cancel" data-bs-dismiss="modal">Annuler</button>
          <button type="submit" class="btn btn-confirm-refus">Confirmer le Refus</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script src="js/bootstrap.bundle.min.js"></script>
<script>
function ouvrirRefus(id, numero) {
  document.getElementById('refus_facture_id').value = id;
  document.getElementById('refus_numero').textContent = numero;
  new bootstrap.Modal(document.getElementById('modalRefus')).show();
}
</script>
</body>
</html>
