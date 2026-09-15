<?php
require_once __DIR__.'/config/database.php';
requireLogin();
requireRole(['admin','pharmacien','gestionnaire_stock']);
require_once __DIR__.'/includes/partials/layout.php';

$filter=$_GET['filter'] ?? '';
$where=" WHERE 1=1 ";
if($filter==='low') $where.=" AND quantity>0 AND quantity <= alert_threshold ";
elseif($filter==='out') $where.=" AND quantity<=0 ";
elseif($filter==='expired') $where.=" AND expiry_date < CURDATE() ";
elseif($filter==='expiring') $where.=" AND expiry_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY) ";
elseif($filter==='normal') $where.=" AND quantity > alert_threshold AND (expiry_date IS NULL OR expiry_date >= CURDATE()) ";

$total = (int)$pdo->query("SELECT COUNT(*) FROM medicines")->fetchColumn();
$normal = (int)$pdo->query("SELECT COUNT(*) FROM medicines WHERE quantity > alert_threshold AND (expiry_date IS NULL OR expiry_date >= CURDATE())")->fetchColumn();
$low = (int)$pdo->query("SELECT COUNT(*) FROM medicines WHERE quantity>0 AND quantity <= alert_threshold")->fetchColumn();
$out = (int)$pdo->query("SELECT COUNT(*) FROM medicines WHERE quantity<=0")->fetchColumn();
$expired = (int)$pdo->query("SELECT COUNT(*) FROM medicines WHERE expiry_date < CURDATE()")->fetchColumn();
$expiring = (int)$pdo->query("SELECT COUNT(*) FROM medicines WHERE expiry_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY)")->fetchColumn();

$q=trim($_GET['q'] ?? '');
$params=[]; $cond=$where;
if($q!==''){ $cond.=" AND (name LIKE ? OR code LIKE ?) "; $params=["%$q%","%$q%"]; }
$meds=$pdo->prepare("SELECT * FROM medicines $cond ORDER BY quantity ASC, expiry_date ASC LIMIT 100");
$meds->execute($params); $list=$meds->fetchAll();

layout_header('Stocks');
?>
<style>
.stock-hero{background:linear-gradient(135deg,#0a2a5e 0%, #0b5ed7 100%); border-radius:18px; color:#fff; position:relative; overflow:hidden}
.stock-hero::after{content:""; position:absolute; width:380px; height:380px; background:radial-gradient(circle, rgba(255,255,255,.11), transparent 70%); right:-60px; top:-100px}
.stock-kpi{border-radius:16px; border:1px solid var(--gray-200); background:#fff; padding:14px; transition:.18s; position:relative; overflow:hidden}
.stock-kpi:hover{transform:translateY(-2px); box-shadow:var(--shadow-lg)}
.stock-kpi.active{border-color:var(--blue); box-shadow:0 8px 24px rgba(11,94,215,.12); background:#f8fafc}
.progress-premium{height:8px; border-radius:999px; background:#f1f5f9; overflow:hidden}
</style>

<div class="stock-hero p-4 mb-4">
  <div class="d-flex flex-wrap justify-content-between gap-3 align-items-center" style="position:relative; z-index:1">
    <div class="d-flex gap-3 align-items-center">
      <div style="width:48px;height:48px;background:#fff;color:var(--blue-dark);border-radius:12px;display:grid;place-items:center;font-size:18px"><i class="fa-solid fa-boxes-stacked"></i></div>
      <div>
        <h4 class="fw-bold mb-0" style="letter-spacing:-.02em">Gestion des stocks</h4>
        <div class="small" style="opacity:.88">Vue synthétique • <?= $total ?> références • Alertes intelligentes en temps réel</div>
      </div>
    </div>
    <div class="d-flex gap-2">
      <a href="movements.php" class="btn btn-light text-primary btn-sm fw-bold" style="border-radius:999px"><i class="fa-solid fa-arrow-right-arrow-left me-1"></i> Mouvements</a>
      <a href="medicines.php" class="btn btn-outline-light btn-sm" style="border-color:rgba(255,255,255,.35); border-radius:999px">Médicaments</a>
    </div>
  </div>
</div>

<div class="row g-3 mb-4">
  <?php
  $kpis=[
    ['key'=>'normal','label'=>'Stock normal','val'=>$normal,'icon'=>'fa-check','bg'=>'#e6f7ef','c'=>'#065f46','border'=>'#a7f3d0'],
    ['key'=>'low','label'=>'Stock faible','val'=>$low,'icon'=>'fa-triangle-exclamation','bg'=>'#fff7e6','c'=>'#92400e','border'=>'#fde68a'],
    ['key'=>'out','label'=>'Rupture','val'=>$out,'icon'=>'fa-ban','bg'=>'#fee2e2','c'=>'#991b1b','border'=>'#fecaca'],
    ['key'=>'expired','label'=>'Expirés','val'=>$expired,'icon'=>'fa-skull-crossbones','bg'=>'#fee2e2','c'=>'#991b1b','border'=>'#fecaca'],
    ['key'=>'expiring','label'=>'Bientôt périmés','val'=>$expiring,'icon'=>'fa-hourglass-half','bg'=>'#fff7e6','c'=>'#92400e','border'=>'#fde68a'],
    ['key'=>'','label'=>'Total','val'=>$total,'icon'=>'fa-layer-group','bg'=>'#e8f0fe','c'=>'#0a2a5e','border'=>'#cbd5e1'],
  ];
  foreach($kpis as $k):
    $isActive = $filter===$k['key'];
  ?>
  <div class="col-6 col-md-2">
    <a href="stocks.php?filter=<?= e($k['key']) ?>" class="stock-kpi d-block text-decoration-none <?= $isActive?'active':'' ?>" style="border-color:<?= $isActive?'var(--blue)':$k['border'] ?>">
      <div class="d-flex justify-content-between align-items-start mb-2">
        <div style="width:36px;height:36px;border-radius:10px;background:<?= e($k['bg']) ?>;color:<?= e($k['c']) ?>;display:grid;place-items:center"><i class="fa-solid <?= e($k['icon']) ?>" style="font-size:13px"></i></div>
        <?php if($isActive): ?><span class="badge bg-primary" style="border-radius:999px; font-size:10px">Actif</span><?php endif; ?>
      </div>
      <div class="fw-bold" style="font-size:20px; letter-spacing:-.02em; color:var(--gray-900)"><?= $k['val'] ?></div>
      <div class="small" style="font-weight:600; color:var(--gray-700); font-size:12px"><?= e($k['label']) ?></div>
    </a>
  </div>
  <?php endforeach; ?>
</div>

<div class="card" style="border-radius:16px; overflow:hidden">
  <div class="card-header bg-white" style="border-bottom:1px solid var(--gray-200)">
    <form class="d-flex flex-wrap gap-2 w-100 align-items-end">
      <div style="min-width:180px">
        <label class="small fw-semibold" style="font-size:11px; letter-spacing:.06em; color:var(--gray-500)">FILTRE</label>
        <select name="filter" class="form-select" style="border-radius:12px"><option value="">Tous les statuts</option><option value="normal" <?= $filter=='normal'?'selected':'' ?>>Normal</option><option value="low" <?= $filter=='low'?'selected':'' ?>>Faible</option><option value="out" <?= $filter=='out'?'selected':'' ?>>Rupture</option><option value="expired" <?= $filter=='expired'?'selected':'' ?>>Expirés</option><option value="expiring" <?= $filter=='expiring'?'selected':'' ?>>Bientôt périmés</option></select>
      </div>
      <button class="btn btn-primary" style="border-radius:12px; height:42px"><i class="fa-solid fa-filter me-1"></i> Filtrer</button>
      <a href="stocks.php" class="btn btn-light border" style="border-radius:12px; height:42px"><i class="fa-solid fa-rotate"></i></a>
    </form>
  </div>
  <div class="table-responsive">
    <table class="table table-hover mb-0" id="mainTable" style="font-size:13.2px">
      <thead><tr><th>Médicament</th><th class="text-center">Qté</th><th class="text-center">Seuil</th><th style="min-width:160px">Jauge</th><th>Expiration</th><th>Statut</th><th>Emplacement</th></tr></thead>
      <tbody>
        <?php if(!$list): ?><tr><td colspan="7" class="text-center py-5 text-muted">Aucun résultat.</td></tr><?php endif; ?>
        <?php foreach($list as $m):
          $pct = $m['alert_threshold']>0 ? min(100, max(6, round($m['quantity']/max(1,$m['alert_threshold'])*45))) : 100;
          $isExpired = $m['expiry_date'] && $m['expiry_date']<date('Y-m-d');
          $isLow = $m['quantity']>0 && $m['quantity']<= $m['alert_threshold'];
          $isOut = $m['quantity']<=0;
          $bar = $isOut?'#ef4444':($isLow?'#f59e0b':($isExpired?'#991b1b':'#10b981'));
        ?>
        <tr>
          <td><div class="d-flex gap-2 align-items-center"><div style="width:38px;height:38px;border-radius:10px;background:<?= $isExpired?'#fee2e2':($isOut?'#fee2e2':($isLow?'#fff7e6':'#e6f7ef')) ?>;color:<?= $isExpired?'#991b1b':($isOut?'#991b1b':($isLow?'#92400e':'#065f46')) ?>;display:grid;place-items:center"><i class="fa-solid fa-pills" style="font-size:13px"></i></div><div><div class="fw-semibold" style="line-height:1.1"><?= e($m['name']) ?></div><small class="text-muted"><?= e($m['code']) ?></small></div></div></td>
          <td class="text-center fw-bold" style="color:<?= $isOut?'#ef4444':($isLow?'#d97706':'#0f172a') ?>; font-size:15px"><?= $m['quantity'] ?></td>
          <td class="text-center small text-muted"><?= $m['alert_threshold'] ?></td>
          <td><div class="progress-premium"><div class="progress-bar" style="width:<?= $pct ?>%; background:<?= $bar ?>; height:100%"></div></div><div class="small text-muted" style="font-size:11px"><?= $pct ?>% du seuil</div></td>
          <td class="small <?= $isExpired?'text-danger fw-bold':'' ?>"><?= e($m['expiry_date'] ?? '—') ?><?php if($m['expiry_date'] && !$isExpired){ $d=(int)ceil((strtotime($m['expiry_date'])-time())/86400); if($d<=30) echo " <span class='badge bg-warning text-dark' style='font-size:10px'>$d j</span>"; } ?></td>
          <td><?php if($isExpired) echo '<span class="badge-soft badge-expired">Expiré</span>'; elseif($isOut) echo '<span class="badge-soft badge-out">Rupture</span>'; elseif($isLow) echo '<span class="badge-soft badge-low">Faible</span>'; else echo '<span class="badge-soft badge-available">Normal</span>'; ?></td>
          <td><span class="badge bg-light border text-dark" style="border-radius:999px; font-size:11px"><i class="fa-solid fa-location-dot text-muted me-1"></i><?= e($m['location'] ?? '—') ?></span></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
<?php layout_footer(); ?>
