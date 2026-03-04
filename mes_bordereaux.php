<?php
session_start();
if (empty($_SESSION['user_id']) || $_SESSION['role'] !== 'user') {
    header('Location: login.php'); exit;
}
require_once 'db.php';
$pdo = getDB();

$nom_user  = $_SESSION['nom'] ?? '';
$initiales = strtoupper(substr($nom_user,0,1).(strpos($nom_user,' ')!==false?substr($nom_user,strpos($nom_user,' ')+1,1):''));

// Mes bordereaux
$mes_bordereaux = $pdo->prepare(
    "SELECT b.*, COUNT(f.id) as nb_factures,
            SUM(f.montant) as total_montant
     FROM bordereaux b
     LEFT JOIN factures f ON f.bordereau_id = b.id
     WHERE b.created_by = ?
     GROUP BY b.id ORDER BY b.created_at DESC"
);
$mes_bordereaux->execute([$_SESSION['user_id']]);
$bordereaux = $mes_bordereaux->fetchAll();

// Notifications non lues
$notifs = $pdo->prepare("SELECT * FROM notifications WHERE user_id=? AND lu=0 ORDER BY created_at DESC");
$notifs->execute([$_SESSION['user_id']]);
$notifications = $notifs->fetchAll();

$fournisseurs = $pdo->query("SELECT id, nom FROM fournisseurs ORDER BY nom")->fetchAll();

$statut_colors = [
    'envoye'        => ['label'=>'Envoyé',        'class'=>'badge-pending'],
    'recu'          => ['label'=>'Reçu',           'class'=>'badge-process'],
    'en_traitement' => ['label'=>'En Traitement',  'class'=>'badge-process'],
    'cloture'       => ['label'=>'Clôturé',        'class'=>'badge-approved'],
];
?>
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Mes Bordereaux – Agent Région</title>
  <link rel="stylesheet" href="css/bootstrap.min.css">
  <style>
    @import url('https://fonts.googleapis.com/css2?family=Rajdhani:wght@400;600;700&family=Nunito:wght@300;400;600;700&display=swap');
    :root { --sidebar-bg:#111318; --main-bg:#0d0f14; --card-bg:#1a1d26; --card-border:#242733; --accent-orange:#e05c00; --accent-green:#00c875; --accent-blue:#00bfff; --accent-red:#ff4d4d; --text-muted:rgba(255,255,255,0.4); --sidebar-w:240px; }
    *{box-sizing:border-box;} body{font-family:'Nunito',sans-serif;background:var(--main-bg);color:#e8eaf0;margin:0;display:flex;min-height:100vh;}
    .sidebar{width:var(--sidebar-w);background:var(--sidebar-bg);display:flex;flex-direction:column;position:fixed;top:0;left:0;bottom:0;border-right:1px solid var(--card-border);z-index:100;}
    .sidebar-brand{padding:28px 24px 20px;border-bottom:1px solid var(--card-border);}
    .sidebar-brand h2{font-family:'Rajdhani',sans-serif;font-size:1.2rem;font-weight:700;color:#fff;letter-spacing:2px;margin:0;}
    .sidebar-brand span{display:block;font-size:0.68rem;letter-spacing:3px;color:var(--accent-orange);text-transform:uppercase;}
    .sidebar-user{padding:14px 24px;border-bottom:1px solid var(--card-border);display:flex;align-items:center;gap:10px;}
    .user-avatar{width:36px;height:36px;background:linear-gradient(135deg,var(--accent-orange),#ff8c00);border-radius:50%;display:flex;align-items:center;justify-content:center;font-family:'Rajdhani',sans-serif;font-weight:700;font-size:0.85rem;color:#fff;flex-shrink:0;}
    .user-info strong{font-size:0.82rem;color:#fff;display:block;}
    .user-role-badge{font-size:0.65rem;color:var(--accent-blue);text-transform:uppercase;letter-spacing:1px;}
    .sidebar-nav{flex:1;padding:16px 0;}
    .nav-item{display:flex;align-items:center;gap:12px;padding:11px 24px;color:rgba(255,255,255,0.5);text-decoration:none;font-size:0.87rem;font-weight:600;border-left:3px solid transparent;transition:all 0.2s;}
    .nav-item:hover{color:#fff;background:rgba(255,255,255,0.04);}
    .nav-item.active{color:#fff;background:rgba(224,92,0,0.1);border-left-color:var(--accent-orange);}
    .nav-item svg{width:18px;height:18px;flex-shrink:0;}
    .notif-dot{width:8px;height:8px;background:var(--accent-red);border-radius:50%;margin-left:auto;}
    .sidebar-footer{padding:16px 24px;border-top:1px solid var(--card-border);}
    .btn-logout{display:flex;align-items:center;gap:8px;color:var(--accent-red);font-size:0.85rem;font-weight:600;text-decoration:none;}
    .main-content{margin-left:var(--sidebar-w);flex:1;display:flex;flex-direction:column;}
    .topbar{background:var(--sidebar-bg);border-bottom:1px solid var(--card-border);padding:16px 32px;display:flex;align-items:center;justify-content:space-between;position:sticky;top:0;z-index:50;}
    .topbar h1{font-family:'Rajdhani',sans-serif;font-size:1.5rem;font-weight:700;color:#fff;letter-spacing:1px;margin:0;}
    .content-area{padding:28px 32px;flex:1;}
    .section-card{background:var(--card-bg);border:1px solid var(--card-border);border-radius:14px;overflow:hidden;margin-bottom:24px;}
    .section-header{padding:18px 24px;border-bottom:1px solid var(--card-border);display:flex;align-items:center;justify-content:space-between;}
    .section-title{font-family:'Rajdhani',sans-serif;font-size:1.1rem;font-weight:700;letter-spacing:1px;color:#fff;margin:0;}
    .btn-add{background:var(--accent-orange);border:none;color:#fff;font-size:0.82rem;font-weight:700;padding:8px 18px;border-radius:8px;cursor:pointer;text-decoration:none;display:inline-block;transition:all 0.2s;}
    .btn-add:hover{opacity:0.85;color:#fff;}
    table.t{width:100%;border-collapse:collapse;}
    .t th{padding:12px 20px;font-size:0.72rem;font-weight:700;letter-spacing:2px;text-transform:uppercase;color:var(--text-muted);text-align:left;border-bottom:1px solid var(--card-border);}
    .t td{padding:13px 20px;font-size:0.88rem;border-bottom:1px solid rgba(255,255,255,0.04);color:rgba(255,255,255,0.75);}
    .t tr:last-child td{border-bottom:none;}
    .t tr:hover td{background:rgba(255,255,255,0.025);}
    .badge-s{display:inline-block;padding:3px 12px;border-radius:20px;font-size:0.72rem;font-weight:700;}
    .badge-pending{background:rgba(224,92,0,0.15);color:var(--accent-orange);}
    .badge-approved{background:rgba(0,200,117,0.15);color:var(--accent-green);}
    .badge-rejected{background:rgba(255,77,77,0.15);color:var(--accent-red);}
    .badge-process{background:rgba(0,191,255,0.15);color:var(--accent-blue);}
    .empty-state{text-align:center;padding:48px;color:var(--text-muted);font-size:0.9rem;}
    .btn-sm-action{padding:4px 12px;border-radius:6px;font-size:0.75rem;font-weight:700;border:none;cursor:pointer;text-decoration:none;display:inline-block;}
    .btn-view{background:rgba(0,191,255,0.15);color:var(--accent-blue);}
    .btn-view:hover{background:rgba(0,191,255,0.25);color:var(--accent-blue);}

    /* Notifications */
    .notif-panel{background:var(--card-bg);border:1px solid var(--card-border);border-radius:14px;padding:0;margin-bottom:24px;overflow:hidden;}
    .notif-item{padding:14px 20px;border-bottom:1px solid rgba(255,255,255,0.05);display:flex;align-items:flex-start;gap:12px;}
    .notif-item:last-child{border-bottom:none;}
    .notif-icon{width:36px;height:36px;border-radius:50%;display:flex;align-items:center;justify-content:center;flex-shrink:0;}
    .notif-refus{background:rgba(255,77,77,0.15);}
    .notif-refus svg{fill:var(--accent-red);}
    .notif-validation{background:rgba(0,200,117,0.15);}
    .notif-validation svg{fill:var(--accent-green);}
    .notif-msg{font-size:0.85rem;color:rgba(255,255,255,0.8);flex:1;}
    .notif-date{font-size:0.72rem;color:var(--text-muted);margin-top:3px;}
    .btn-mark-read{background:rgba(255,255,255,0.06);border:1px solid var(--card-border);color:rgba(255,255,255,0.4);font-size:0.72rem;padding:3px 10px;border-radius:6px;cursor:pointer;text-decoration:none;}

    /* MODAL */
    .modal-content{background:#1a1d26;border:1px solid var(--card-border);border-radius:16px;color:#e8eaf0;}
    .modal-header{border-bottom:1px solid var(--card-border);padding:20px 24px;}
    .modal-title{font-family:'Rajdhani',sans-serif;font-size:1.2rem;font-weight:700;letter-spacing:1px;color:#fff;}
    .modal-footer{border-top:1px solid var(--card-border);}
    .btn-close{filter:invert(1);}
    .modal .form-label{color:rgba(255,255,255,0.6);font-size:0.8rem;font-weight:600;text-transform:uppercase;letter-spacing:1px;}
    .modal .form-control,.modal .form-select{background:rgba(255,255,255,0.06);border:1px solid var(--card-border);color:#fff;border-radius:8px;}
    .modal .form-control:focus,.modal .form-select:focus{background:rgba(255,255,255,0.1);border-color:var(--accent-orange);box-shadow:0 0 0 3px rgba(224,92,0,0.2);color:#fff;}
    .modal .form-select option{background:#1a1d26;}
    .modal .form-control::placeholder{color:rgba(255,255,255,0.25);}
    .btn-save{background:linear-gradient(135deg,var(--accent-orange),#ff6a00);border:none;color:#fff;font-family:'Rajdhani',sans-serif;font-weight:700;letter-spacing:1px;padding:10px 28px;border-radius:8px;}
    .btn-cancel{background:rgba(255,255,255,0.06);border:1px solid var(--card-border);color:rgba(255,255,255,0.6);border-radius:8px;padding:10px 20px;}
    .facture-row{background:rgba(255,255,255,0.03);border:1px solid var(--card-border);border-radius:10px;padding:16px;margin-bottom:12px;position:relative;}
    .btn-remove-facture{position:absolute;top:10px;right:10px;background:rgba(255,77,77,0.15);border:none;color:var(--accent-red);width:26px;height:26px;border-radius:50%;cursor:pointer;font-size:1rem;line-height:1;}
    .btn-add-facture{background:rgba(0,191,255,0.1);border:1px dashed rgba(0,191,255,0.3);color:var(--accent-blue);border-radius:8px;padding:8px 16px;width:100%;font-size:0.85rem;cursor:pointer;transition:all 0.2s;margin-top:4px;}
    .btn-add-facture:hover{background:rgba(0,191,255,0.2);}
    .alert-success-custom{background:rgba(0,200,117,0.15);border:1px solid rgba(0,200,117,0.3);color:var(--accent-green);border-radius:10px;padding:12px 16px;margin-bottom:20px;}
    .alert-danger-custom{background:rgba(255,77,77,0.15);border:1px solid rgba(255,77,77,0.3);color:var(--accent-red);border-radius:10px;padding:12px 16px;margin-bottom:20px;}
  </style>
</head>
<body>
<aside class="sidebar">
  <div class="sidebar-brand"><h2>SUIVI FACTURES</h2><span>Agent Région</span></div>
  <div class="sidebar-user">
    <div class="user-avatar"><?= $initiales ?></div>
    <div class="user-info">
      <strong><?= htmlspecialchars($nom_user) ?></strong>
      <div class="user-role-badge">User Région</div>
    </div>
  </div>
  <nav class="sidebar-nav">
    <a class="nav-item" href="dashboard.php"><svg viewBox="0 0 24 24" fill="currentColor"><path d="M3 13h8V3H3v10zm0 8h8v-6H3v6zm10 0h8V11h-8v10zm0-18v6h8V3h-8z"/></svg>Accueil</a>
    <a class="nav-item active" href="mes_bordereaux.php">
      <svg viewBox="0 0 24 24" fill="currentColor"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8l-6-6zm-1 1.5L18.5 9H13V3.5zM6 20V4h5v7h7v9H6z"/></svg>
      Mes Bordereaux
    </a>
    <a class="nav-item" href="mes_notifications.php">
      <svg viewBox="0 0 24 24" fill="currentColor"><path d="M12 22c1.1 0 2-.9 2-2h-4c0 1.1.9 2 2 2zm6-6v-5c0-3.07-1.64-5.64-4.5-6.32V4c0-.83-.67-1.5-1.5-1.5s-1.5.67-1.5 1.5v.68C7.63 5.36 6 7.92 6 11v5l-2 2v1h16v-1l-2-2z"/></svg>
      Notifications
      <?php if(count($notifications)>0): ?><span class="notif-dot"></span><?php endif; ?>
    </a>
  </nav>
  <div class="sidebar-footer"><a href="logout.php" class="btn-logout"><svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor"><path d="M17 7l-1.41 1.41L18.17 11H8v2h10.17l-2.58 2.58L17 17l5-5zM4 5h8V3H4c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h8v-2H4V5z"/></svg>Déconnexion</a></div>
</aside>

<div class="main-content">
  <div class="topbar">
    <h1>Mes Bordereaux</h1>
    <button class="btn-add" data-bs-toggle="modal" data-bs-target="#modalBordereau">+ Nouveau Bordereau</button>
  </div>
  <div class="content-area">

    <?php if(isset($_GET['success'])): ?>
      <div class="alert-success-custom">✅ Bordereau créé et envoyé avec succès !</div>
    <?php endif; ?>
    <?php if(isset($_GET['error'])): ?>
      <div class="alert-danger-custom">❌ Erreur : <?= htmlspecialchars($_GET['error']) ?></div>
    <?php endif; ?>

    <!-- NOTIFICATIONS NON LUES -->
    <?php if(count($notifications) > 0): ?>
    <div class="section-card" style="border-color:rgba(255,77,77,0.3);">
      <div class="section-header">
        <h3 class="section-title" style="color:var(--accent-red);">🔔 Notifications (<?= count($notifications) ?>)</h3>
        <a href="marquer_lu.php?all=1&redirect=mes_bordereaux.php" class="btn-mark-read">Tout marquer comme lu</a>
      </div>
      <?php foreach($notifications as $n): ?>
        <div class="notif-item">
          <div class="notif-icon notif-<?= $n['type'] ?>">
            <?php if($n['type']==='refus'): ?>
              <svg width="18" height="18" viewBox="0 0 24 24"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1 15h-2v-2h2v2zm0-4h-2V7h2v6z"/></svg>
            <?php else: ?>
              <svg width="18" height="18" viewBox="0 0 24 24"><path d="M9 16.2L4.8 12l-1.4 1.4L9 19 21 7l-1.4-1.4L9 16.2z"/></svg>
            <?php endif; ?>
          </div>
          <div style="flex:1;">
            <div class="notif-msg"><?= htmlspecialchars($n['message']) ?></div>
            <div class="notif-date"><?= date('d/m/Y H:i', strtotime($n['created_at'])) ?></div>
          </div>
          <a href="marquer_lu.php?id=<?= $n['id'] ?>&redirect=mes_bordereaux.php" class="btn-mark-read">✓ Lu</a>
        </div>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <!-- LISTE BORDEREAUX -->
    <div class="section-card">
      <div class="section-header"><h3 class="section-title">Mes Bordereaux envoyés</h3></div>
      <table class="t">
        <thead><tr>
          <th>N° Bordereau</th><th>Région</th><th>Date Envoi</th><th>Nb Factures</th><th>Total</th><th>Statut</th><th>Action</th>
        </tr></thead>
        <tbody>
          <?php if(empty($bordereaux)): ?>
            <tr><td colspan="7" class="empty-state">Aucun bordereau. Créez votre premier bordereau !</td></tr>
          <?php else: ?>
            <?php foreach($bordereaux as $b):
              $sc = $statut_colors[$b['statut']] ?? ['label'=>$b['statut'],'class'=>'badge-pending'];
            ?>
            <tr>
              <td><strong><?= htmlspecialchars($b['numero_bordereau']) ?></strong></td>
              <td><?= htmlspecialchars($b['region']) ?></td>
              <td><?= date('d/m/Y', strtotime($b['date_envoi'])) ?></td>
              <td><?= (int)$b['nb_factures'] ?> facture(s)</td>
              <td><?= number_format((float)$b['total_montant'], 2, ',', ' ') ?> DZD</td>
              <td><span class="badge-s <?= $sc['class'] ?>"><?= $sc['label'] ?></span></td>
              <td><a href="detail_bordereau.php?id=<?= $b['id'] ?>" class="btn-sm-action btn-view">Voir détail</a></td>
            </tr>
            <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>

  </div>
</div>

<!-- MODAL NOUVEAU BORDEREAU -->
<div class="modal fade" id="modalBordereau" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
    <div class="modal-content">
      <form action="creer_bordereau.php" method="POST" id="formBordereau">
        <div class="modal-header">
          <h5 class="modal-title">Créer un nouveau Bordereau</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body p-4">

          <div class="row g-3 mb-4">
            <div class="col-md-6">
              <label class="form-label">N° Bordereau *</label>
              <input type="text" class="form-control" name="numero_bordereau" placeholder="Ex: BRD-2026-001" required>
            </div>
            <div class="col-md-6">
              <label class="form-label">Région *</label>
              <input type="text" class="form-control" name="region" value="<?= htmlspecialchars($_SESSION['region'] ?? '') ?>" placeholder="Ex: Région Est" required>
            </div>
            <div class="col-md-6">
              <label class="form-label">Date d'envoi *</label>
              <input type="date" class="form-control" name="date_envoi" value="<?= date('Y-m-d') ?>" required>
            </div>
          </div>

          <hr style="border-color:var(--card-border);margin:20px 0;">
          <h6 style="font-family:'Rajdhani',sans-serif;font-size:1rem;font-weight:700;color:#fff;letter-spacing:1px;margin-bottom:16px;">FACTURES DU BORDEREAU</h6>

          <div id="factures-container">
            <!-- Facture 1 par défaut -->
            <div class="facture-row" id="facture-1">
              <button type="button" class="btn-remove-facture" onclick="removeFacture(1)" style="display:none;">×</button>
              <div class="row g-2">
                <div class="col-md-3">
                  <label class="form-label">N° Facture *</label>
                  <input type="text" class="form-control" name="factures[0][numero]" placeholder="FAC-001" required>
                </div>
                <div class="col-md-3">
                  <label class="form-label">Fournisseur *</label>
                  <select class="form-select" name="factures[0][fournisseur_id]" required>
                    <option value="" disabled selected>-- Choisir --</option>
                    <?php foreach($fournisseurs as $f): ?>
                      <option value="<?= $f['id'] ?>"><?= htmlspecialchars($f['nom']) ?></option>
                    <?php endforeach; ?>
                  </select>
                </div>
                <div class="col-md-2">
                  <label class="form-label">N° Contrat</label>
                  <input type="text" class="form-control" name="factures[0][numero_contrat]" placeholder="CNT-001">
                </div>
                <div class="col-md-2">
                  <label class="form-label">Montant *</label>
                  <input type="number" class="form-control" name="factures[0][montant]" placeholder="0.00" min="0" step="0.01" required>
                </div>
                <div class="col-md-2">
                  <label class="form-label">Devise</label>
                  <select class="form-select" name="factures[0][devise]">
                    <option value="DZD">DZD (Dinar)</option>
                    <option value="EUR">EUR (Euro)</option>
                    <option value="USD">USD (Dollar)</option>
                  </select>
                </div>
                <div class="col-md-3">
                  <label class="form-label">Date émission *</label>
                  <input type="date" class="form-control" name="factures[0][date_emission]" value="<?= date('Y-m-d') ?>" required>
                </div>
                <div class="col-md-3">
                  <label class="form-label">Date échéance</label>
                  <input type="date" class="form-control" name="factures[0][date_echeance]">
                </div>
                <div class="col-md-6">
                  <label class="form-label">Description</label>
                  <input type="text" class="form-control" name="factures[0][description]" placeholder="Description de la facture">
                </div>
              </div>
            </div>
          </div>

          <button type="button" class="btn-add-facture" onclick="ajouterFacture()">+ Ajouter une autre facture</button>

        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-cancel" data-bs-dismiss="modal">Annuler</button>
          <button type="submit" class="btn btn-save">Envoyer le Bordereau</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script src="js/bootstrap.bundle.min.js"></script>
<script>
let compteur = 1;
const fournisseursOptions = `<?php foreach($fournisseurs as $f): ?><option value="<?= $f['id'] ?>"><?= htmlspecialchars($f['nom']) ?></option><?php endforeach; ?>`;

function ajouterFacture() {
  const idx = compteur;
  const div = document.createElement('div');
  div.className = 'facture-row';
  div.id = 'facture-' + idx;
  div.innerHTML = `
    <button type="button" class="btn-remove-facture" onclick="removeFacture(${idx})">×</button>
    <div class="row g-2">
      <div class="col-md-3"><label class="form-label">N° Facture *</label><input type="text" class="form-control" name="factures[${idx}][numero]" placeholder="FAC-00${idx+1}" required></div>
      <div class="col-md-3"><label class="form-label">Fournisseur *</label><select class="form-select" name="factures[${idx}][fournisseur_id]" required><option value="" disabled selected>-- Choisir --</option>${fournisseursOptions}</select></div>
      <div class="col-md-2"><label class="form-label">N° Contrat</label><input type="text" class="form-control" name="factures[${idx}][numero_contrat]" placeholder="CNT-001"></div>
      <div class="col-md-2"><label class="form-label">Montant *</label><input type="number" class="form-control" name="factures[${idx}][montant]" placeholder="0.00" min="0" step="0.01" required></div>
      <div class="col-md-2"><label class="form-label">Devise</label><select class="form-select" name="factures[${idx}][devise]"><option value="DZD">DZD (Dinar)</option><option value="EUR">EUR (Euro)</option><option value="USD">USD (Dollar)</option></select></div>
      <div class="col-md-3"><label class="form-label">Date émission *</label><input type="date" class="form-control" name="factures[${idx}][date_emission]" value="<?= date('Y-m-d') ?>" required></div>
      <div class="col-md-3"><label class="form-label">Date échéance</label><input type="date" class="form-control" name="factures[${idx}][date_echeance]"></div>
      <div class="col-md-6"><label class="form-label">Description</label><input type="text" class="form-control" name="factures[${idx}][description]" placeholder="Description"></div>
    </div>`;
  document.getElementById('factures-container').appendChild(div);
  compteur++;
  // Afficher bouton supprimer sur la première
  document.querySelector('#facture-1 .btn-remove-facture').style.display = 'block';
}

function removeFacture(idx) {
  const el = document.getElementById('facture-' + idx);
  if(el) el.remove();
  const rows = document.querySelectorAll('.facture-row');
  if(rows.length === 1) rows[0].querySelector('.btn-remove-facture').style.display = 'none';
}
</script>
</body>
</html>
