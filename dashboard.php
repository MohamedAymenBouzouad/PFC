<?php
session_start();
if (empty($_SESSION['user_id'])) { header('Location: login.php'); exit; }
require_once 'db.php';
$pdo = getDB();

$nom_user  = $_SESSION['nom']  ?? 'Utilisateur';
$role_user = $_SESSION['role'] ?? 'user';
$initiales = strtoupper(substr($nom_user,0,1).(strpos($nom_user,' ')!==false?substr($nom_user,strpos($nom_user,' ')+1,1):''));

// Stats
$nb_attente    = $pdo->query("SELECT COUNT(*) FROM factures WHERE statut='en_attente'")->fetchColumn();
$nb_traitement = $pdo->query("SELECT COUNT(*) FROM factures WHERE statut='en_traitement'")->fetchColumn();
$nb_rejetees   = $pdo->query("SELECT COUNT(*) FROM factures WHERE statut='rejetee'")->fetchColumn();
$nb_validees   = $pdo->query("SELECT COUNT(*) FROM factures WHERE statut='validee'")->fetchColumn();
$montant_total = $pdo->query("SELECT SUM(montant) FROM factures WHERE statut IN ('en_attente','en_traitement')")->fetchColumn();
$nb_bordereaux_non_recus = $pdo->query("SELECT COUNT(*) FROM bordereaux WHERE statut='envoye'")->fetchColumn();

// Notifications non lues pour l'user connecté
$notifs_stmt = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE user_id=? AND lu=0");
$notifs_stmt->execute([$_SESSION['user_id']]);
$nb_notifs = (int)$notifs_stmt->fetchColumn();

// 5 derniers bordereaux
$derniers = $pdo->query(
    "SELECT b.numero_bordereau, b.region, b.statut, b.date_envoi,
            COUNT(f.id) as nb_factures, u.prenom, u.nom as nom_agent
     FROM bordereaux b
     LEFT JOIN factures f ON f.bordereau_id = b.id
     LEFT JOIN utilisateurs u ON u.id = b.created_by
     GROUP BY b.id ORDER BY b.created_at DESC LIMIT 5"
)->fetchAll();

$role_labels = ['admin'=>'Administrateur','gestionnaire'=>'Gestionnaire','secretaire'=>'Secrétaire','user'=>'User Région'];

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
  <title>Tableau de Bord – Suivi Factures DP</title>
  <link rel="stylesheet" href="css/bootstrap.min.css">
  <style>
    @import url('https://fonts.googleapis.com/css2?family=Rajdhani:wght@400;600;700&family=Nunito:wght@300;400;600;700&display=swap');
    :root{--sidebar-bg:#111318;--main-bg:#0d0f14;--card-bg:#1a1d26;--card-border:#242733;--accent-orange:#e05c00;--accent-green:#00c875;--accent-blue:#00bfff;--accent-red:#ff4d4d;--text-muted:rgba(255,255,255,0.4);--sidebar-w:240px;}
    *{box-sizing:border-box;}body{font-family:'Nunito',sans-serif;background:var(--main-bg);color:#e8eaf0;margin:0;display:flex;min-height:100vh;}
    .sidebar{width:var(--sidebar-w);background:var(--sidebar-bg);display:flex;flex-direction:column;position:fixed;top:0;left:0;bottom:0;border-right:1px solid var(--card-border);z-index:100;}
    .sidebar-brand{padding:24px 24px 18px;border-bottom:1px solid var(--card-border);}
    .sidebar-brand h2{font-family:'Rajdhani',sans-serif;font-size:1.2rem;font-weight:700;color:#fff;letter-spacing:2px;margin:0;line-height:1.3;}
    .sidebar-brand span{display:block;font-size:0.68rem;letter-spacing:3px;color:var(--accent-orange);text-transform:uppercase;margin-top:2px;}
    .sidebar-user{padding:14px 24px;border-bottom:1px solid var(--card-border);display:flex;align-items:center;gap:10px;}
    .user-avatar{width:36px;height:36px;background:linear-gradient(135deg,var(--accent-orange),#ff8c00);border-radius:50%;display:flex;align-items:center;justify-content:center;font-family:'Rajdhani',sans-serif;font-weight:700;font-size:0.85rem;color:#fff;flex-shrink:0;}
    .user-info strong{font-size:0.82rem;color:#fff;display:block;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;}
    .user-role-badge{font-size:0.65rem;color:var(--accent-blue);text-transform:uppercase;letter-spacing:1px;}
    .sidebar-nav{flex:1;padding:16px 0;overflow-y:auto;}
    .nav-section-label{padding:6px 24px 4px;font-size:0.62rem;letter-spacing:2px;text-transform:uppercase;color:rgba(255,255,255,0.2);margin-top:8px;}
    .nav-item{display:flex;align-items:center;gap:12px;padding:11px 24px;color:rgba(255,255,255,0.5);text-decoration:none;font-size:0.87rem;font-weight:600;border-left:3px solid transparent;transition:all 0.2s;}
    .nav-item:hover{color:#fff;background:rgba(255,255,255,0.04);}
    .nav-item.active{color:#fff;background:rgba(224,92,0,0.1);border-left-color:var(--accent-orange);}
    .nav-item svg{width:18px;height:18px;flex-shrink:0;}
    .nav-badge{margin-left:auto;background:var(--accent-orange);color:#fff;font-size:0.65rem;font-weight:700;padding:2px 7px;border-radius:20px;}
    .nav-badge-red{margin-left:auto;background:var(--accent-red);color:#fff;font-size:0.65rem;font-weight:700;padding:2px 7px;border-radius:20px;}
    .sidebar-footer{padding:16px 24px;border-top:1px solid var(--card-border);}
    .btn-logout{display:flex;align-items:center;gap:8px;color:var(--accent-red);font-size:0.85rem;font-weight:600;text-decoration:none;}
    .btn-logout:hover{opacity:0.7;color:var(--accent-red);}
    .main-content{margin-left:var(--sidebar-w);flex:1;display:flex;flex-direction:column;}
    .topbar{background:var(--sidebar-bg);border-bottom:1px solid var(--card-border);padding:16px 32px;display:flex;align-items:center;justify-content:space-between;position:sticky;top:0;z-index:50;}
    .topbar h1{font-family:'Rajdhani',sans-serif;font-size:1.5rem;font-weight:700;color:#fff;letter-spacing:1px;margin:0;}
    .topbar-date{font-size:0.78rem;color:var(--text-muted);}
    .content-area{padding:28px 32px;flex:1;}
    .stats-row{display:grid;grid-template-columns:repeat(4,1fr);gap:16px;margin-bottom:28px;}
    .stat-card{background:var(--card-bg);border:1px solid var(--card-border);border-radius:14px;padding:22px 20px 18px;position:relative;overflow:hidden;transition:transform 0.2s,box-shadow 0.2s;}
    .stat-card:hover{transform:translateY(-3px);box-shadow:0 12px 32px rgba(0,0,0,0.4);}
    .stat-card::before{content:'';position:absolute;top:0;left:0;right:0;height:3px;}
    .stat-card.orange::before{background:var(--accent-orange);}
    .stat-card.green::before{background:var(--accent-green);}
    .stat-card.blue::before{background:var(--accent-blue);}
    .stat-card.red::before{background:var(--accent-red);}
    .stat-label{font-size:0.78rem;font-weight:700;text-transform:uppercase;letter-spacing:0.5px;margin-bottom:10px;}
    .stat-card.orange .stat-label{color:var(--accent-orange);}
    .stat-card.green  .stat-label{color:var(--accent-green);}
    .stat-card.blue   .stat-label{color:var(--accent-blue);}
    .stat-card.red    .stat-label{color:var(--accent-red);}
    .stat-value{font-family:'Rajdhani',sans-serif;font-size:2.2rem;font-weight:700;color:#fff;line-height:1;margin-bottom:6px;}
    .stat-sub{font-size:0.72rem;color:var(--text-muted);}
    .section-card{background:var(--card-bg);border:1px solid var(--card-border);border-radius:14px;overflow:hidden;margin-bottom:24px;}
    .section-header{padding:18px 24px;border-bottom:1px solid var(--card-border);display:flex;align-items:center;justify-content:space-between;}
    .section-title{font-family:'Rajdhani',sans-serif;font-size:1.1rem;font-weight:700;letter-spacing:1px;color:#fff;margin:0;}
    table.t{width:100%;border-collapse:collapse;}
    .t th{padding:12px 20px;font-size:0.7rem;font-weight:700;letter-spacing:2px;text-transform:uppercase;color:var(--text-muted);text-align:left;border-bottom:1px solid var(--card-border);}
    .t td{padding:13px 20px;font-size:0.85rem;border-bottom:1px solid rgba(255,255,255,0.04);color:rgba(255,255,255,0.75);}
    .t tr:last-child td{border-bottom:none;}
    .t tr:hover td{background:rgba(255,255,255,0.025);}
    .badge-s{display:inline-block;padding:3px 12px;border-radius:20px;font-size:0.72rem;font-weight:700;}
    .badge-pending{background:rgba(224,92,0,0.15);color:var(--accent-orange);}
    .badge-approved{background:rgba(0,200,117,0.15);color:var(--accent-green);}
    .badge-process{background:rgba(0,191,255,0.15);color:var(--accent-blue);}
    .empty-state{text-align:center;padding:48px;color:var(--text-muted);font-size:0.9rem;}
    .quick-actions{display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:14px;margin-bottom:28px;}
    .quick-btn{background:var(--card-bg);border:1px solid var(--card-border);border-radius:12px;padding:18px 20px;text-decoration:none;display:flex;align-items:center;gap:14px;transition:all 0.2s;color:#fff;}
    .quick-btn:hover{transform:translateY(-2px);box-shadow:0 8px 24px rgba(0,0,0,0.3);color:#fff;}
    .quick-btn-icon{width:42px;height:42px;border-radius:10px;display:flex;align-items:center;justify-content:center;flex-shrink:0;}
    .quick-btn-icon svg{width:22px;height:22px;fill:#fff;}
    .quick-btn-text strong{display:block;font-size:0.9rem;font-weight:700;}
    .quick-btn-text span{font-size:0.75rem;color:var(--text-muted);}
  </style>
</head>
<body>
<aside class="sidebar">
  <div class="sidebar-brand"><h2>SUIVI FACTURES</h2><span>Direction des Projets</span></div>
  <div class="sidebar-user">
    <div class="user-avatar"><?= $initiales ?></div>
    <div class="user-info">
      <strong><?= htmlspecialchars($nom_user) ?></strong>
      <div class="user-role-badge"><?= $role_labels[$role_user] ?? $role_user ?></div>
    </div>
  </div>
  <nav class="sidebar-nav">
    <a class="nav-item active" href="dashboard.php"><svg viewBox="0 0 24 24" fill="currentColor"><path d="M3 13h8V3H3v10zm0 8h8v-6H3v6zm10 0h8V11h-8v10zm0-18v6h8V3h-8z"/></svg>Accueil</a>

    <?php if($role_user === 'user'): ?>
    <div class="nav-section-label">Agent Région</div>
    <a class="nav-item" href="mes_bordereaux.php"><svg viewBox="0 0 24 24" fill="currentColor"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8l-6-6zm-1 1.5L18.5 9H13V3.5zM6 20V4h5v7h7v9H6z"/></svg>Mes Bordereaux
      <?php if($nb_notifs>0): ?><span class="nav-badge-red"><?= $nb_notifs ?></span><?php endif; ?>
    </a>
    <?php endif; ?>

    <?php if($role_user === 'secretaire'): ?>
    <div class="nav-section-label">Secrétaire</div>
    <a class="nav-item" href="reception_bordereaux.php"><svg viewBox="0 0 24 24" fill="currentColor"><path d="M20 6h-2.18c.07-.44.18-.88.18-1.36C18 2.51 15.49 0 12.36 0c-1.73 0-3.25.78-4.29 2H6C3.79 2 2 3.79 2 6v14c0 2.21 1.79 4 4 4h14c2.21 0 4-1.79 4-4V10c0-2.21-1.79-4-4-4z"/></svg>Réception Bordereaux
      <?php if($nb_bordereaux_non_recus>0): ?><span class="nav-badge"><?= $nb_bordereaux_non_recus ?></span><?php endif; ?>
    </a>
    <?php endif; ?>

    <?php if($role_user === 'gestionnaire'): ?>
    <div class="nav-section-label">Gestionnaire</div>
    <a class="nav-item" href="traitement_factures.php"><svg viewBox="0 0 24 24" fill="currentColor"><path d="M9 16.2L4.8 12l-1.4 1.4L9 19 21 7l-1.4-1.4L9 16.2z"/></svg>Traitement Factures
      <?php if($nb_traitement>0): ?><span class="nav-badge"><?= $nb_traitement ?></span><?php endif; ?>
    </a>
    <?php endif; ?>

    <?php if($role_user === 'admin'): ?>
    <div class="nav-section-label">Administration</div>
    <a class="nav-item" href="reception_bordereaux.php"><svg viewBox="0 0 24 24" fill="currentColor"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8l-6-6z"/></svg>Tous les Bordereaux</a>
    <a class="nav-item" href="traitement_factures.php"><svg viewBox="0 0 24 24" fill="currentColor"><path d="M9 16.2L4.8 12l-1.4 1.4L9 19 21 7l-1.4-1.4L9 16.2z"/></svg>Traitement</a>
    <a class="nav-item" href="administration.php"><svg viewBox="0 0 24 24" fill="currentColor"><path d="M12 12c2.7 0 4-1.8 4-4s-1.3-4-4-4-4 1.8-4 4 1.3 4 4 4zm0 2c-4 0-6 2-6 3v1h12v-1c0-1-2-3-6-3z"/></svg>Utilisateurs</a>
    <?php endif; ?>
  </nav>
  <div class="sidebar-footer"><a href="logout.php" class="btn-logout"><svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor"><path d="M17 7l-1.41 1.41L18.17 11H8v2h10.17l-2.58 2.58L17 17l5-5zM4 5h8V3H4c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h8v-2H4V5z"/></svg>Déconnexion</a></div>
</aside>

<div class="main-content">
  <div class="topbar">
    <h1>Tableau de Bord</h1>
    <span class="topbar-date" id="currentDate"></span>
  </div>
  <div class="content-area">

    <!-- ACCÈS RAPIDES selon rôle -->
    <div class="quick-actions">
      <?php if($role_user === 'user'): ?>
        <a href="mes_bordereaux.php" class="quick-btn"><div class="quick-btn-icon" style="background:var(--accent-orange);"><svg viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8l-6-6z"/></svg></div><div class="quick-btn-text"><strong>Nouveau Bordereau</strong><span>Créer et envoyer</span></div></a>
        <a href="mes_bordereaux.php" class="quick-btn"><div class="quick-btn-icon" style="background:var(--accent-blue);"><svg viewBox="0 0 24 24"><path d="M3 13h8V3H3v10zm0 8h8v-6H3v6zm10 0h8V11h-8v10zm0-18v6h8V3h-8z"/></svg></div><div class="quick-btn-text"><strong>Mes Bordereaux</strong><span>Voir le suivi</span></div></a>
      <?php elseif($role_user === 'secretaire'): ?>
        <a href="reception_bordereaux.php" class="quick-btn"><div class="quick-btn-icon" style="background:var(--accent-green);"><svg viewBox="0 0 24 24"><path d="M9 16.2L4.8 12l-1.4 1.4L9 19 21 7l-1.4-1.4L9 16.2z"/></svg></div><div class="quick-btn-text"><strong>Confirmer Réceptions</strong><span><?= $nb_bordereaux_non_recus ?> en attente</span></div></a>
      <?php elseif($role_user === 'gestionnaire'): ?>
        <a href="traitement_factures.php" class="quick-btn"><div class="quick-btn-icon" style="background:var(--accent-blue);"><svg viewBox="0 0 24 24"><path d="M9 16.2L4.8 12l-1.4 1.4L9 19 21 7l-1.4-1.4L9 16.2z"/></svg></div><div class="quick-btn-text"><strong>Traiter les Factures</strong><span><?= $nb_traitement ?> en cours</span></div></a>
      <?php elseif($role_user === 'admin'): ?>
        <a href="reception_bordereaux.php" class="quick-btn"><div class="quick-btn-icon" style="background:var(--accent-orange);"><svg viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/></svg></div><div class="quick-btn-text"><strong>Bordereaux</strong><span>Voir tout</span></div></a>
        <a href="traitement_factures.php" class="quick-btn"><div class="quick-btn-icon" style="background:var(--accent-blue);"><svg viewBox="0 0 24 24"><path d="M9 16.2L4.8 12l-1.4 1.4L9 19 21 7l-1.4-1.4L9 16.2z"/></svg></div><div class="quick-btn-text"><strong>Traitement</strong><span>Gérer factures</span></div></a>
      <?php endif; ?>
    </div>

    <!-- STATS -->
    <div class="stats-row">
      <div class="stat-card orange"><div class="stat-label">En Attente</div><div class="stat-value"><?= (int)$nb_attente ?></div><div class="stat-sub">Factures à traiter</div></div>
      <div class="stat-card blue"><div class="stat-label">En Traitement</div><div class="stat-value"><?= (int)$nb_traitement ?></div><div class="stat-sub">En cours</div></div>
      <div class="stat-card green"><div class="stat-label">Validées</div><div class="stat-value"><?= (int)$nb_validees ?></div><div class="stat-sub">Approuvées</div></div>
      <div class="stat-card red"><div class="stat-label">Rejetées</div><div class="stat-value"><?= (int)$nb_rejetees ?></div><div class="stat-sub">À corriger</div></div>
    </div>

    <!-- DERNIERS BORDEREAUX -->
    <div class="section-card">
      <div class="section-header"><h3 class="section-title">Derniers Bordereaux</h3></div>
      <table class="t">
        <thead><tr><th>N° Bordereau</th><th>Agent / Région</th><th>Date Envoi</th><th>Nb Factures</th><th>Statut</th></tr></thead>
        <tbody>
          <?php if(empty($derniers)): ?>
            <tr><td colspan="5" class="empty-state">Aucun bordereau enregistré.</td></tr>
          <?php else: ?>
            <?php foreach($derniers as $b):
              $sc = $statut_colors[$b['statut']] ?? ['label'=>$b['statut'],'class'=>'badge-pending'];
            ?>
            <tr>
              <td><strong><?= htmlspecialchars($b['numero_bordereau']) ?></strong></td>
              <td><?= htmlspecialchars($b['prenom'].' '.$b['nom_agent']) ?> <span style="font-size:0.75rem;color:var(--text-muted);">(<?= htmlspecialchars($b['region']) ?>)</span></td>
              <td><?= date('d/m/Y', strtotime($b['date_envoi'])) ?></td>
              <td><?= (int)$b['nb_factures'] ?></td>
              <td><span class="badge-s <?= $sc['class'] ?>"><?= $sc['label'] ?></span></td>
            </tr>
            <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>

  </div>
</div>
<script src="js/bootstrap.bundle.min.js"></script>
<script>
  const d = new Date();
  document.getElementById('currentDate').textContent = d.toLocaleDateString('fr-DZ',{weekday:'long',year:'numeric',month:'long',day:'numeric'});
</script>
</body>
</html>