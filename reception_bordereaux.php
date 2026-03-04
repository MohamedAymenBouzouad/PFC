<?php
session_start();
if (empty($_SESSION['user_id']) || $_SESSION['role'] !== 'secretaire') {
    header('Location: login.php'); exit;
}
require_once 'db.php';
$pdo = getDB();

$nom_user  = $_SESSION['nom'] ?? '';
$initiales = strtoupper(substr($nom_user,0,1).(strpos($nom_user,' ')!==false?substr($nom_user,strpos($nom_user,' ')+1,1):''));

// Tous les bordereaux
$bordereaux = $pdo->query(
    "SELECT b.*, u.prenom, u.nom as nom_agent, u.region as region_agent,
            COUNT(f.id) as nb_factures, SUM(f.montant) as total
     FROM bordereaux b
     LEFT JOIN utilisateurs u ON u.id = b.created_by
     LEFT JOIN factures f ON f.bordereau_id = b.id
     GROUP BY b.id ORDER BY b.created_at DESC"
)->fetchAll();

$notifs_count = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE user_id=? AND lu=0");
$notifs_count->execute([$_SESSION['user_id']]);
$nb_notifs = $notifs_count->fetchColumn();

$statut_colors = [
    'envoye'        => ['label'=>'Envoyé (non reçu)', 'class'=>'badge-pending'],
    'recu'          => ['label'=>'Reçu',              'class'=>'badge-process'],
    'en_traitement' => ['label'=>'En Traitement',     'class'=>'badge-process'],
    'cloture'       => ['label'=>'Clôturé',           'class'=>'badge-approved'],
];
?>
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Réception Bordereaux – Secrétaire</title>
  <link rel="stylesheet" href="css/bootstrap.min.css">
  <style>
    @import url('https://fonts.googleapis.com/css2?family=Rajdhani:wght@400;600;700&family=Nunito:wght@300;400;600;700&display=swap');
    :root{--sidebar-bg:#111318;--main-bg:#0d0f14;--card-bg:#1a1d26;--card-border:#242733;--accent-orange:#e05c00;--accent-green:#00c875;--accent-blue:#00bfff;--accent-red:#ff4d4d;--text-muted:rgba(255,255,255,0.4);--sidebar-w:240px;}
    *{box-sizing:border-box;}body{font-family:'Nunito',sans-serif;background:var(--main-bg);color:#e8eaf0;margin:0;display:flex;min-height:100vh;}
    .sidebar{width:var(--sidebar-w);background:var(--sidebar-bg);display:flex;flex-direction:column;position:fixed;top:0;left:0;bottom:0;border-right:1px solid var(--card-border);z-index:100;}
    .sidebar-brand{padding:28px 24px 20px;border-bottom:1px solid var(--card-border);}
    .sidebar-brand h2{font-family:'Rajdhani',sans-serif;font-size:1.2rem;font-weight:700;color:#fff;letter-spacing:2px;margin:0;}
    .sidebar-brand span{display:block;font-size:0.68rem;letter-spacing:3px;color:var(--accent-orange);text-transform:uppercase;}
    .sidebar-user{padding:14px 24px;border-bottom:1px solid var(--card-border);display:flex;align-items:center;gap:10px;}
    .user-avatar{width:36px;height:36px;background:linear-gradient(135deg,#00c875,#00a860);border-radius:50%;display:flex;align-items:center;justify-content:center;font-family:'Rajdhani',sans-serif;font-weight:700;font-size:0.85rem;color:#fff;flex-shrink:0;}
    .user-info strong{font-size:0.82rem;color:#fff;display:block;}
    .user-role-badge{font-size:0.65rem;color:var(--accent-green);text-transform:uppercase;letter-spacing:1px;}
    .sidebar-nav{flex:1;padding:16px 0;}
    .nav-item{display:flex;align-items:center;gap:12px;padding:11px 24px;color:rgba(255,255,255,0.5);text-decoration:none;font-size:0.87rem;font-weight:600;border-left:3px solid transparent;transition:all 0.2s;}
    .nav-item:hover{color:#fff;background:rgba(255,255,255,0.04);}
    .nav-item.active{color:#fff;background:rgba(0,200,117,0.1);border-left-color:var(--accent-green);}
    .nav-item svg{width:18px;height:18px;flex-shrink:0;}
    .sidebar-footer{padding:16px 24px;border-top:1px solid var(--card-border);}
    .btn-logout{display:flex;align-items:center;gap:8px;color:var(--accent-red);font-size:0.85rem;font-weight:600;text-decoration:none;}
    .main-content{margin-left:var(--sidebar-w);flex:1;display:flex;flex-direction:column;}
    .topbar{background:var(--sidebar-bg);border-bottom:1px solid var(--card-border);padding:16px 32px;display:flex;align-items:center;justify-content:space-between;position:sticky;top:0;z-index:50;}
    .topbar h1{font-family:'Rajdhani',sans-serif;font-size:1.5rem;font-weight:700;color:#fff;letter-spacing:1px;margin:0;}
    .content-area{padding:28px 32px;flex:1;}
    .section-card{background:var(--card-bg);border:1px solid var(--card-border);border-radius:14px;overflow:hidden;margin-bottom:24px;}
    .section-header{padding:18px 24px;border-bottom:1px solid var(--card-border);display:flex;align-items:center;justify-content:space-between;}
    .section-title{font-family:'Rajdhani',sans-serif;font-size:1.1rem;font-weight:700;letter-spacing:1px;color:#fff;margin:0;}
    table.t{width:100%;border-collapse:collapse;}
    .t th{padding:12px 20px;font-size:0.72rem;font-weight:700;letter-spacing:2px;text-transform:uppercase;color:var(--text-muted);text-align:left;border-bottom:1px solid var(--card-border);}
    .t td{padding:13px 20px;font-size:0.88rem;border-bottom:1px solid rgba(255,255,255,0.04);color:rgba(255,255,255,0.75);}
    .t tr:last-child td{border-bottom:none;}
    .t tr:hover td{background:rgba(255,255,255,0.025);}
    .badge-s{display:inline-block;padding:3px 12px;border-radius:20px;font-size:0.72rem;font-weight:700;}
    .badge-pending{background:rgba(224,92,0,0.15);color:var(--accent-orange);}
    .badge-approved{background:rgba(0,200,117,0.15);color:var(--accent-green);}
    .badge-process{background:rgba(0,191,255,0.15);color:var(--accent-blue);}
    .btn-sm{padding:5px 14px;border-radius:6px;font-size:0.75rem;font-weight:700;border:none;cursor:pointer;text-decoration:none;display:inline-block;transition:all 0.2s;}
    .btn-confirmer{background:rgba(0,200,117,0.2);color:var(--accent-green);}
    .btn-confirmer:hover{background:rgba(0,200,117,0.35);color:var(--accent-green);}
    .btn-view{background:rgba(0,191,255,0.15);color:var(--accent-blue);}
    .btn-view:hover{background:rgba(0,191,255,0.25);color:var(--accent-blue);}
    .empty-state{text-align:center;padding:48px;color:var(--text-muted);font-size:0.9rem;}
    .alert-success-custom{background:rgba(0,200,117,0.15);border:1px solid rgba(0,200,117,0.3);color:var(--accent-green);border-radius:10px;padding:12px 16px;margin-bottom:20px;}
    .highlight-row td{background:rgba(224,92,0,0.05) !important;}
  </style>
</head>
<body>
<aside class="sidebar">
  <div class="sidebar-brand"><h2>SUIVI FACTURES</h2><span>Secrétaire</span></div>
  <div class="sidebar-user">
    <div class="user-avatar"><?= $initiales ?></div>
    <div class="user-info"><strong><?= htmlspecialchars($nom_user) ?></strong><div class="user-role-badge">Secrétaire</div></div>
  </div>
  <nav class="sidebar-nav">
    <a class="nav-item" href="dashboard.php"><svg viewBox="0 0 24 24" fill="currentColor"><path d="M3 13h8V3H3v10zm0 8h8v-6H3v6zm10 0h8V11h-8v10zm0-18v6h8V3h-8z"/></svg>Accueil</a>
    <a class="nav-item active" href="reception_bordereaux.php"><svg viewBox="0 0 24 24" fill="currentColor"><path d="M20 6h-2.18c.07-.44.18-.88.18-1.36C18 2.51 15.49 0 12.36 0c-1.73 0-3.25.78-4.29 2H6C3.79 2 2 3.79 2 6v14c0 2.21 1.79 4 4 4h14c2.21 0 4-1.79 4-4V10c0-2.21-1.79-4-4-4zm-3 14H7c-.55 0-1-.45-1-1s.45-1 1-1h10c.55 0 1 .45 1 1s-.45 1-1 1zm0-4H7c-.55 0-1-.45-1-1s.45-1 1-1h10c.55 0 1 .45 1 1s-.45 1-1 1zm-5-8c-.55 0-1-.45-1-1V3.5L15.5 8H12z"/></svg>Réception Bordereaux</a>
  </nav>
  <div class="sidebar-footer"><a href="logout.php" class="btn-logout"><svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor"><path d="M17 7l-1.41 1.41L18.17 11H8v2h10.17l-2.58 2.58L17 17l5-5zM4 5h8V3H4c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h8v-2H4V5z"/></svg>Déconnexion</a></div>
</aside>

<div class="main-content">
  <div class="topbar"><h1>Réception des Bordereaux</h1></div>
  <div class="content-area">

    <?php if(isset($_GET['success'])): ?>
      <div class="alert-success-custom">✅ Réception confirmée ! Le gestionnaire a été notifié.</div>
    <?php endif; ?>

    <div class="section-card">
      <div class="section-header">
        <h3 class="section-title">Tous les Bordereaux</h3>
        <span style="font-size:0.8rem;color:var(--text-muted);">
          <?= count(array_filter($bordereaux, fn($b) => $b['statut']==='envoye')) ?> en attente de réception
        </span>
      </div>
      <table class="t">
        <thead><tr>
          <th>N° Bordereau</th><th>Agent / Région</th><th>Date Envoi</th><th>Factures</th><th>Total</th><th>Statut</th><th>Actions</th>
        </tr></thead>
        <tbody>
          <?php if(empty($bordereaux)): ?>
            <tr><td colspan="7" class="empty-state">Aucun bordereau reçu pour le moment.</td></tr>
          <?php else: ?>
            <?php foreach($bordereaux as $b):
              $sc = $statut_colors[$b['statut']] ?? ['label'=>$b['statut'],'class'=>'badge-pending'];
              $is_new = ($b['statut'] === 'envoye');
            ?>
            <tr class="<?= $is_new ? 'highlight-row' : '' ?>">
              <td><strong><?= htmlspecialchars($b['numero_bordereau']) ?></strong></td>
              <td>
                <?= htmlspecialchars($b['prenom'].' '.$b['nom_agent']) ?><br>
                <span style="font-size:0.75rem;color:var(--text-muted);"><?= htmlspecialchars($b['region_agent'] ?? $b['region']) ?></span>
              </td>
              <td><?= date('d/m/Y', strtotime($b['date_envoi'])) ?></td>
              <td><?= (int)$b['nb_factures'] ?> facture(s)</td>
              <td><?= number_format((float)$b['total'], 2, ',', ' ') ?> DZD</td>
              <td><span class="badge-s <?= $sc['class'] ?>"><?= $sc['label'] ?></span></td>
              <td style="display:flex;gap:6px;flex-wrap:wrap;">
                <a href="detail_bordereau.php?id=<?= $b['id'] ?>" class="btn-sm btn-view">Voir</a>
                <?php if($b['statut'] === 'envoye'): ?>
                  <a href="confirmer_reception.php?id=<?= $b['id'] ?>" class="btn-sm btn-confirmer"
                     onclick="return confirm('Confirmer la réception physique du bordereau <?= htmlspecialchars($b['numero_bordereau']) ?> ?')">
                    ✓ Confirmer réception
                  </a>
                <?php endif; ?>
              </td>
            </tr>
            <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>

  </div>
</div>
<script src="js/bootstrap.bundle.min.js"></script>
</body>
</html>
