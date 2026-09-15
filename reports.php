<?php
require_once __DIR__.'/config/database.php';
requireLogin();
requireRole(['admin','pharmacien']);
require_once __DIR__.'/includes/partials/layout.php';

$range=$_GET['range'] ?? 'month';
$from=$_GET['from'] ?? ''; $to=$_GET['to'] ?? '';
$whereSale=" WHERE 1=1 "; $paramsSale=[];
if($range==='today'){ $whereSale.=" AND DATE(s.created_at)=CURDATE() "; }
elseif($range==='week'){ $whereSale.=" AND s.created_at >= DATE_SUB(CURDATE(), INTERVAL 7 DAY) "; }
elseif($range==='month'){ $whereSale.=" AND MONTH(s.created_at)=MONTH(CURDATE()) AND YEAR(s.created_at)=YEAR(CURDATE()) "; }
elseif($range==='year'){ $whereSale.=" AND YEAR(s.created_at)=YEAR(CURDATE()) "; }
elseif($range==='custom' && $from && $to){ $whereSale.=" AND DATE(s.created_at) BETWEEN ? AND ? "; $paramsSale=[$from,$to]; }

$whereSimple = str_replace('s.', '', $whereSale);
$stmt=$pdo->prepare("SELECT COUNT(*) FROM sales $whereSimple"); $stmt->execute($paramsSale); $nbSales=(int)$stmt->fetchColumn();
$stmt=$pdo->prepare("SELECT COALESCE(SUM(total),0) FROM sales $whereSimple"); $stmt->execute($paramsSale); $ca=(float)$stmt->fetchColumn();
$stockTotal=(int)$pdo->query("SELECT COUNT(*) FROM medicines")->fetchColumn();
$expired=(int)$pdo->query("SELECT COUNT(*) FROM medicines WHERE expiry_date < CURDATE()")->fetchColumn();
$expiring=(int)$pdo->query("SELECT COUNT(*) FROM medicines WHERE expiry_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY)")->fetchColumn();
$low=(int)$pdo->query("SELECT COUNT(*) FROM medicines WHERE quantity>0 AND quantity <= alert_threshold")->fetchColumn();
$out=(int)$pdo->query("SELECT COUNT(*) FROM medicines WHERE quantity<=0")->fetchColumn();

$salesRows=$pdo->prepare("SELECT s.*, c.full_name, u.name as cashier FROM sales s LEFT JOIN customers c ON c.id=s.customer_id LEFT JOIN users u ON u.id=s.user_id $whereSale ORDER BY s.created_at DESC LIMIT 100");
$salesRows->execute($paramsSale); $sales=$salesRows->fetchAll();
$expRows=$pdo->query("SELECT * FROM medicines WHERE expiry_date < CURDATE() ORDER BY expiry_date LIMIT 100")->fetchAll();
$lowRows=$pdo->query("SELECT * FROM medicines WHERE quantity>0 AND quantity <= alert_threshold ORDER BY quantity LIMIT 100")->fetchAll();

layout_header('Rapports');
?>
<style>
.rep-hero{background:linear-gradient(135deg,#0a2a5e 0%, #0b5ed7 100%); border-radius:18px; color:#fff; position:relative; overflow:hidden}
.rep-hero::after{content:""; position:absolute; width:380px; height:380px; background:radial-gradient(circle, rgba(255,255,255,.11), transparent 70%); right:-60px; top:-80px}
.kpi-mini{border-radius:14px; border:1px solid var(--gray-200); background:#fff; padding:14px; text-align:center; transition:.15s}
.kpi-mini:hover{transform:translateY(-2px); box-shadow:var(--shadow)}
.nav-pills .nav-link{border-radius:999px; font-size:13px; font-weight:600; padding:8px 16px; color:var(--gray-500); border:1px solid transparent}
.nav-pills .nav-link.active{background:var(--blue-dark); color:#fff; box-shadow:0 4px 12px rgba(10,42,94,.18)}
</style>

<div class="rep-hero p-4 mb-4">
  <div class="d-flex flex-wrap justify-content-between gap-3 align-items-center" style="position:relative; z-index:1">
    <div class="d-flex gap-3 align-items-center">
      <div style="width:48px;height:48px;background:#fff;color:var(--blue-dark);border-radius:12px;display:grid;place-items:center;font-size:18px"><i class="fa-solid fa-chart-line"></i></div>
      <div>
        <h4 class="fw-bold mb-0">Rapports & Inventaire</h4>
        <div class="small" style="opacity:.88">Pilotage stratégique • Filtres périodiques • Exports PDF/CSV</div>
      </div>
    </div>
    <div class="d-flex gap-2">
      <button onclick="window.print()" class="btn btn-light text-primary btn-sm fw-bold" style="border-radius:999px"><i class="fa-solid fa-print me-1"></i> Imprimer</button>
      <a href="reports.php?range=<?= e($range) ?>&from=<?= e($from) ?>&to=<?= e($to) ?>" class="btn btn-outline-light btn-sm" style="border-color:rgba(255,255,255,.35); border-radius:999px"><i class="fa-solid fa-file-csv me-1"></i> Exporter</a>
    </div>
  </div>
</div>

<div class="card mb-4" style="border-radius:16px">
  <div class="card-body">
    <form class="row g-3 align-items-end">
      <div class="col-md-3">
        <label class="form-label small fw-semibold" style="font-size:11px; letter-spacing:.06em; color:var(--gray-500)">PÉRIODE</label>
        <select name="range" class="form-select" style="border-radius:12px"><option value="today" <?= $range=='today'?'selected':'' ?>>Aujourd'hui</option><option value="week" <?= $range=='week'?'selected':'' ?>>Cette semaine</option><option value="month" <?= $range=='month'?'selected':'' ?>>Ce mois</option><option value="year" <?= $range=='year'?'selected':'' ?>>Cette année</option><option value="custom" <?= $range=='custom'?'selected':'' ?>>Personnalisée</option></select>
      </div>
      <div class="col-md-3"><label class="form-label small" style="font-size:11px; letter-spacing:.06em; color:var(--gray-500)">DU</label><input type="date" name="from" value="<?= e($from) ?>" class="form-control" style="border-radius:12px"></div>
      <div class="col-md-3"><label class="form-label small" style="font-size:11px; letter-spacing:.06em; color:var(--gray-500)">AU</label><input type="date" name="to" value="<?= e($to) ?>" class="form-control" style="border-radius:12px"></div>
      <div class="col-md-3 d-flex gap-2"><button class="btn btn-primary flex-fill" style="border-radius:12px"><i class="fa-solid fa-filter me-1"></i> Appliquer</button><a href="reports.php" class="btn btn-light border" style="border-radius:12px">Reset</a></div>
    </form>
  </div>
</div>

<div class="row g-3 mb-4">
  <div class="col-6 col-md-2"><div class="kpi-mini"><div class="small text-muted" style="font-size:11px; letter-spacing:.06em">VENTES</div><div class="fw-bold" style="font-size:22px; color:var(--blue-dark)"><?= $nbSales ?></div><div class="small text-muted" style="font-size:11px">Période sélectionnée</div></div></div>
  <div class="col-6 col-md-2"><div class="kpi-mini" style="background:linear-gradient(135deg,#e6f7ef 0%, #fff 100%); border-color:#a7f3d0"><div class="small text-muted" style="font-size:11px; letter-spacing:.06em">CHIFFRE D'AFFAIRES</div><div class="fw-bold" style="font-size:18px"><?= money($ca) ?></div><div class="small text-success" style="font-size:11px">Encaissé</div></div></div>
  <div class="col-6 col-md-2"><div class="kpi-mini"><div class="small text-muted" style="font-size:11px; letter-spacing:.06em">RÉFÉRENCES</div><div class="fw-bold" style="font-size:22px"><?= $stockTotal ?></div><div class="small text-muted" style="font-size:11px">En stock</div></div></div>
  <div class="col-6 col-md-2"><div class="kpi-mini" style="border-color:#fecaca"><div class="small text-muted" style="font-size:11px; letter-spacing:.06em">EXPIRÉS</div><div class="fw-bold text-danger" style="font-size:22px"><?= $expired ?></div><div class="small text-muted" style="font-size:11px">À retirer</div></div></div>
  <div class="col-6 col-md-2"><div class="kpi-mini" style="border-color:#fde68a"><div class="small text-muted" style="font-size:11px; letter-spacing:.06em">STOCK FAIBLE</div><div class="fw-bold" style="color:#92400e; font-size:22px"><?= $low ?></div><div class="small text-muted" style="font-size:11px">À réapprovisionner</div></div></div>
  <div class="col-6 col-md-2"><div class="kpi-mini"><div class="small text-muted" style="font-size:11px; letter-spacing:.06em">RUPTURES</div><div class="fw-bold text-danger" style="font-size:22px"><?= $out ?></div><div class="small text-muted" style="font-size:11px">Critique</div></div></div>
</div>

<ul class="nav nav-pills mb-3 gap-2" role="tablist">
  <li class="nav-item"><button class="nav-link active" data-bs-toggle="pill" data-bs-target="#p-sales"><i class="fa-solid fa-receipt me-1"></i> Ventes</button></li>
  <li class="nav-item"><button class="nav-link" data-bs-toggle="pill" data-bs-target="#p-stock"><i class="fa-solid fa-boxes-stacked me-1"></i> Stock</button></li>
  <li class="nav-item"><button class="nav-link" data-bs-toggle="pill" data-bs-target="#p-exp"><i class="fa-solid fa-skull-crossbones me-1"></i> Expirés</button></li>
  <li class="nav-item"><button class="nav-link" data-bs-toggle="pill" data-bs-target="#p-low"><i class="fa-solid fa-triangle-exclamation me-1"></i> Faible</button></li>
  <li class="nav-item"><button class="nav-link" data-bs-toggle="pill" data-bs-target="#p-fin"><i class="fa-solid fa-coins me-1"></i> Financier</button></li>
</ul>

<div class="tab-content">
  <div class="tab-pane fade show active" id="p-sales">
    <div class="card" style="border-radius:16px; overflow:hidden"><div class="table-responsive"><table class="table table-hover mb-0">
      <thead><tr><th>Date</th><th>Facture</th><th>Client</th><th>Caissier</th><th class="text-end">Total</th></tr></thead>
      <tbody><?php foreach($sales as $s): ?><tr><td class="small"><span class="badge bg-light border text-dark" style="font-size:11px"><?= date('d/m/Y H:i', strtotime($s['created_at'])) ?></span></td><td><span class="badge bg-light border text-dark" style="border-radius:999px; font-size:11px"><?= e($s['invoice_number']) ?></span></td><td class="fw-semibold" style="font-size:13px"><?= e($s['full_name'] ?? 'Comptoir') ?></td><td class="small text-muted"><?= e($s['cashier']) ?></td><td class="text-end fw-bold text-primary"><?= money($s['total']) ?></td></tr><?php endforeach; ?><?php if(!$sales): ?><tr><td colspan="5" class="text-center py-4 text-muted">Aucune vente sur la période.</td></tr><?php endif; ?></tbody>
    </table></div></div>
  </div>
  <div class="tab-pane fade" id="p-stock">
    <div class="card" style="border-radius:16px; overflow:hidden"><div class="table-responsive"><table class="table table-hover mb-0">
      <thead><tr><th>Code</th><th>Médicament</th><th class="text-center">Qté</th><th class="text-center">Seuil</th><th>Expiration</th></tr></thead>
      <tbody><?php foreach($pdo->query("SELECT * FROM medicines ORDER BY quantity ASC LIMIT 100")->fetchAll() as $m): ?><tr><td><span class="badge bg-light border text-dark" style="font-size:11px"><?= e($m['code']) ?></span></td><td class="fw-semibold" style="font-size:13px"><?= e($m['name']) ?></td><td class="text-center fw-bold"><?= $m['quantity'] ?></td><td class="text-center"><?= $m['alert_threshold'] ?></td><td class="small"><?= e($m['expiry_date']) ?></td></tr><?php endforeach; ?></tbody>
    </table></div></div>
  </div>
  <div class="tab-pane fade" id="p-exp">
    <div class="card" style="border-radius:16px; overflow:hidden"><div class="table-responsive"><table class="table table-hover mb-0">
      <thead><tr><th>Médicament</th><th>Code</th><th>Expiration</th><th>Jours dépassés</th></tr></thead>
      <tbody><?php foreach($expRows as $m): $days=(int)floor((time()-strtotime($m['expiry_date']))/86400); ?><tr><td class="fw-semibold" style="font-size:13px"><?= e($m['name']) ?></td><td><span class="badge bg-light border text-dark"><?= e($m['code']) ?></span></td><td><span class="badge bg-danger" style="border-radius:999px"><?= e($m['expiry_date']) ?></span></td><td class="fw-bold text-danger"><?= $days ?> j</td></tr><?php endforeach; ?></tbody>
    </table></div></div>
  </div>
  <div class="tab-pane fade" id="p-low">
    <div class="card" style="border-radius:16px; overflow:hidden"><div class="table-responsive"><table class="table table-hover mb-0">
      <thead><tr><th>Médicament</th><th class="text-center">Qté</th><th class="text-center">Seuil</th><th class="text-center">Manque</th></tr></thead>
      <tbody><?php foreach($lowRows as $m): ?><tr><td class="fw-semibold" style="font-size:13px"><?= e($m['name']) ?></td><td class="text-center fw-bold text-warning"><?= $m['quantity'] ?></td><td class="text-center"><?= $m['alert_threshold'] ?></td><td class="text-center"><span class="badge bg-warning text-dark" style="border-radius:999px"><?= $m['alert_threshold']-$m['quantity'] ?></span></td></tr><?php endforeach; ?></tbody>
    </table></div></div>
  </div>
  <div class="tab-pane fade" id="p-fin">
    <div class="card" style="border-radius:16px"><div class="card-body">
      <div class="row g-3">
        <div class="col-md-4"><div class="p-4 text-center" style="background:linear-gradient(135deg,#e6f7ef 0%, #fff 100%); border:1px solid #a7f3d0; border-radius:14px"><div class="small text-muted" style="font-size:11px; letter-spacing:.06em">CA PÉRIODE</div><div class="fw-bold" style="font-size:22px; color:#065f46"><?= money($ca) ?></div></div></div>
        <div class="col-md-4"><div class="p-4 text-center" style="background:#f8fafc; border:1px solid var(--gray-200); border-radius:14px"><div class="small text-muted" style="font-size:11px; letter-spacing:.06em">NB VENTES</div><div class="fw-bold" style="font-size:22px"><?= $nbSales ?></div></div></div>
        <div class="col-md-4"><div class="p-4 text-center" style="background:#e8f0fe; border:1px solid #cbd5e1; border-radius:14px"><div class="small text-muted" style="font-size:11px; letter-spacing:.06em">PANIER MOYEN</div><div class="fw-bold" style="font-size:22px; color:var(--blue-dark)"><?= $nbSales? money($ca/$nbSales) : money(0) ?></div></div></div>
      </div>
    </div></div>
  </div>
</div>
<?php layout_footer(); ?>
