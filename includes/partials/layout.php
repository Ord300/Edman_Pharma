<?php
// Layout helpers
function layout_header($title='Dashboard', $extraHead=''){
  $user = $_SESSION['user'] ?? null;
  $alerts = [];
  if(isset($GLOBALS['pdo'])) $alerts = getAlerts($GLOBALS['pdo']);
  $alertCount = count($alerts);
  $current = basename($_SERVER['PHP_SELF']);
  $role = $user['role'] ?? '';
  // Permissions centralisées via config/config.php — chaque rôle voit uniquement ce qui le concerne
  $can = function($page) use ($role){
    return canAccessPage($page, $role);
  };
  // Bloque l'accès si l'utilisateur tente d'accéder à une page non autorisée pour son rôle (sécurité + UX)
  if(!canAccessPage($current, $role)){
    http_response_code(403);
    $label = roleLabel($role);
    die("<div style='font-family:Inter, sans-serif; max-width:640px; margin:60px auto; padding:32px; border:1px solid #e2e8f0; border-radius:16px; text-align:center'><h1 style='color:#0a2a5e'>403 — Accès refusé</h1><p>Votre rôle <strong style='text-transform:capitalize'>".e($label)." (".e($role).")</strong> ne permet pas d'accéder à <strong>".e($current)."</strong>.</p><p style='color:#64748b; font-size:13px'>Menu filtré : vous ne voyez que les modules autorisés pour votre rôle.</p><a href='dashboard.php' style='display:inline-block; margin-top:12px; background:#0a2a5e; color:#fff; padding:10px 18px; border-radius:999px; text-decoration:none; font-weight:700'>Retour au dashboard</a></div>");
  }
?>
<!doctype html>
<html lang="fr">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title><?= e($title) ?> — Boyambi Pharmacy</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link href="assets/css/style.css" rel="stylesheet">
<?= $extraHead ?>
</head>
<body>
<?php if($user){ ?>
<aside class="app-sidebar">
  <div class="sidebar-brand">
    <div class="logo-icon"><i class="fa-solid fa-pills"></i></div>
    <div>
      <h1>BOYAMBI<br>PHARMACY</h1>
      <small>HÔPITAL • KISANGANI — RDC</small>
    </div>
    <span class="ms-auto badge bg-white text-primary d-none d-xl-inline" style="border-radius:999px; font-size:9px; padding:5px 8px; letter-spacing:.08em">v1.0 PRO</span>
  </div>
  <nav class="sidebar-nav">
    <div class="nav-section">Principal</div>
    <a href="dashboard.php" class="<?= $current=='dashboard.php'?'active':'' ?>"><i class="fa-solid fa-table-columns"></i> Dashboard</a>
    <?php if($can('medicines.php')): ?><a href="medicines.php" class="<?= $current=='medicines.php'?'active':'' ?>"><i class="fa-solid fa-capsules"></i> Médicaments</a><?php endif; ?>
    <?php if($can('stocks.php')): ?><a href="stocks.php" class="<?= $current=='stocks.php'?'active':'' ?>"><i class="fa-solid fa-boxes-stacked"></i> Stocks</a><?php endif; ?>
    <?php if($can('movements.php')): ?><a href="movements.php" class="<?= $current=='movements.php'?'active':'' ?>"><i class="fa-solid fa-arrow-right-arrow-left"></i> Mouvements</a><?php endif; ?>
    <?php if($can('sales.php')): ?><a href="sales.php" class="<?= $current=='sales.php'?'active':'' ?>"><i class="fa-solid fa-cash-register"></i> Ventes</a><?php endif; ?>
    <?php if($can('invoices.php')): ?><a href="invoices.php" class="<?= $current=='invoices.php'?'active':'' ?>"><i class="fa-solid fa-file-invoice"></i> Factures</a><?php endif; ?>
    <?php if($can('commande.php')): ?><a href="commande.php" class="<?= $current=='commande.php'?'active':'' ?>"><i class="fa-solid fa-bag-shopping"></i> Commande</a><?php endif; ?>
    <?php if($can('customers.php')): ?><a href="customers.php" class="<?= $current=='customers.php'?'active':'' ?>"><i class="fa-solid fa-users"></i> Clients</a><?php endif; ?>
    <?php if($can('suppliers.php')): ?><a href="suppliers.php" class="<?= $current=='suppliers.php'?'active':'' ?>"><i class="fa-solid fa-truck-field"></i> Fournisseurs</a><?php endif; ?>
    <?php if($can('users.php') || $can('reports.php') || $can('logs.php')): ?><div class="nav-section">Administration</div><?php endif; ?>
    <?php if($can('users.php')): ?><a href="users.php" class="<?= $current=='users.php'?'active':'' ?>"><i class="fa-solid fa-user-gear"></i> Utilisateurs</a><?php endif; ?>
    <?php if($can('reports.php')): ?><a href="reports.php" class="<?= $current=='reports.php'?'active':'' ?>"><i class="fa-solid fa-chart-line"></i> Rapports</a><?php endif; ?>
    <?php if($can('logs.php')): ?><a href="logs.php" class="<?= $current=='logs.php'?'active':'' ?>"><i class="fa-solid fa-clock-rotate-left"></i> Traçabilité</a><?php endif; ?>
    <?php if($can('settings.php')): ?><a href="settings.php" class="<?= $current=='settings.php'?'active':'' ?>"><i class="fa-solid fa-gear"></i> Paramètres</a><?php endif; ?>
  </nav>
  <div class="sidebar-footer">
    <div class="user-mini">
      <div class="avatar"><?= strtoupper(substr($user['name'],0,1)) ?></div>
      <div>
        <div class="name"><?= e($user['name']) ?></div>
        <div class="role text-capitalize"><?= e(roleLabel($user['role'])) ?> <span style="opacity:.6; font-size:11px; text-transform:none">(<?= e($user['role']) ?>)</span></div>
      </div>
    </div>
  </div>
</aside>
<div class="app-main">
  <div class="app-topbar">
    <button class="btn btn-icon mobile-toggle" data-toggle-sidebar><i class="fa-solid fa-bars"></i></button>
    <div class="d-none d-md-flex align-items-center gap-2 small text-muted" style="font-weight:500">
      <span class="badge bg-light border text-dark" style="border-radius:999px; font-size:11px"><i class="fa-solid fa-table-columns text-primary me-1"></i> <?= e($title) ?></span>
      <span class="d-none d-lg-inline" style="opacity:.4">/</span>
      <span class="d-none d-lg-inline" style="font-size:12px"><?= date('d F Y') ?></span>
    </div>
    <div class="search-wrap d-none d-md-block ms-3">
      <i class="fa-solid fa-magnifying-glass"></i>
      <input type="search" class="form-control" placeholder="Rechercher médicament, facture, client..." id="globalSearch" data-table-search="#mainTable">
    </div>
    <div class="top-actions">
      <?php if($can('sales.php')): ?><a href="sales.php" class="btn btn-primary d-none d-lg-inline-flex" style="border-radius:999px; padding:8px 16px; font-size:13px; font-weight:700">Vente rapide</a><?php endif; ?>
      <?php if($can('commande.php')): ?><a href="commande.php" class="btn btn-outline-primary d-none d-lg-inline-flex" style="border-radius:999px; padding:8px 16px; font-size:13px; font-weight:700"><i class="fa-solid fa-bag-shopping me-1"></i> Commander</a><?php endif; ?>
      <div class="dropdown">
        <button class="btn btn-icon" data-bs-toggle="dropdown" title="Notifications"><i class="fa-solid fa-bell"></i><?php if($alertCount): ?><span class="badge-dot"><?= $alertCount ?></span><?php endif; ?></button>
        <div class="dropdown-menu dropdown-menu-end p-0 shadow-lg" style="width:380px; border-radius:16px; overflow:hidden; border:1px solid var(--gray-200)">
          <div class="p-3" style="background:linear-gradient(135deg,#0a2a5e 0%, #0b5ed7 100%); color:#fff">
            <div class="d-flex justify-content-between align-items-center"><strong><i class="fa-solid fa-bell me-2"></i> Centre d'alertes</strong><span class="badge bg-white text-primary" style="border-radius:999px"><?= $alertCount ?> actives</span></div>
            <div class="small" style="opacity:.85">Surveillance stock & péremptions</div>
          </div>
          <div class="p-2" style="max-height:340px; overflow:auto; background:#f8fafc">
          <?php if(!$alerts): ?><div class="text-center small text-muted p-4"><i class="fa-solid fa-circle-check text-success mb-2" style="font-size:20px"></i><div>Aucune alerte — tout est en ordre.</div></div>
          <?php else: foreach($alerts as $a): ?>
            <a href="<?= e($a['link']) ?>" class="dropdown-item rounded-3 mb-2 bg-white border" style="white-space:normal; padding:10px 12px; border-left:3px solid <?= $a['type']=='danger'?'#ef4444':'#f59e0b' ?> !important">
              <div class="d-flex gap-2"><i class="fa-solid <?= e($a['icon']) ?> mt-1 <?= $a['type']=='danger'?'text-danger':'text-warning' ?>"></i><span class="small" style="line-height:1.4"><?= $a['msg'] ?></span></div>
            </a>
          <?php endforeach; endif; ?>
          </div>
          <div class="p-2 bg-white border-top text-center"><a href="stocks.php" class="btn btn-sm btn-light border w-100" style="border-radius:999px; font-size:12px">Voir tous les stocks <i class="fa-solid fa-arrow-right ms-1"></i></a></div>
        </div>
      </div>
      <div class="dropdown">
        <div class="d-flex align-items-center gap-2 ps-2" style="border-left:1px solid var(--gray-200); cursor:pointer" data-bs-toggle="dropdown">
          <div class="text-end d-none d-sm-block">
            <div style="font-size:11px; color:var(--gray-500)" class="text-capitalize"><i class="fa-solid fa-circle text-success me-1" style="font-size:7px"></i><?= e(roleLabel($user['role'])) ?></div>
           <div style="font-size:10px; color:var(--gray-400)" class="d-none d-sm-block"><?= e($user['role']) ?></div>
          </div>
          <div class="avatar" style="width:38px;height:38px; border-radius:50%; background:var(--blue-dark); color:#fff; display:grid; place-items:center; font-weight:800; box-shadow:0 2px 10px rgba(10,42,94,.18)"><?= strtoupper(substr($user['name'],0,1)) ?></div>
          <i class="fa-solid fa-chevron-down text-muted d-none d-sm-block" style="font-size:11px"></i>
        </div>
        <div class="dropdown-menu dropdown-menu-end shadow" style="border-radius:14px; min-width:220px">
          <div class="px-3 py-2 border-bottom"><div class="fw-bold small"><?= e($user['name']) ?></div><div class="small text-muted"><?= e($user['email'] ?? '') ?></div></div>
          <a href="settings.php" class="dropdown-item small"><i class="fa-solid fa-gear me-2 text-muted"></i> Paramètres</a>
          <a href="logs.php" class="dropdown-item small"><i class="fa-solid fa-clock-rotate-left me-2 text-muted"></i> Traçabilité</a>
          <div class="dropdown-divider"></div>
          <a href="logout.php" class="dropdown-item small text-danger"><i class="fa-solid fa-right-from-bracket me-2"></i> Déconnexion</a>
        </div>
      </div>
    </div>
  </div>
  <div class="content-wrap">
<?php
  // flash
  $ok = flash('success'); $err = flash('error');
  if($ok) echo '<div class="alert alert-success alert-auto d-flex align-items-center gap-2" style="border-radius:12px"><i class="fa-solid fa-circle-check"></i>'.e($ok).'</div>';
  if($err) echo '<div class="alert alert-danger alert-auto d-flex align-items-center gap-2" style="border-radius:12px"><i class="fa-solid fa-triangle-exclamation"></i>'.e($err).'</div>';
  } // end if($user) header
}

function layout_footer(){
  $user = $_SESSION['user'] ?? null;
  if($user){
    echo '</div></div>';
  }
?>
<div class="toast-container"></div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="assets/js/app.js"></script>
</body>
</html>
<?php } ?>
