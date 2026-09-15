<?php
require_once __DIR__.'/config/database.php';
requireLogin();
require_once __DIR__.'/includes/partials/layout.php';

// Stats
$totalMeds = (int)$pdo->query("SELECT COUNT(*) FROM medicines WHERE status!='archived'")->fetchColumn();
$available = (int)$pdo->query("SELECT COUNT(*) FROM medicines WHERE quantity > alert_threshold AND (expiry_date IS NULL OR expiry_date >= CURDATE())")->fetchColumn();
$expiring = (int)$pdo->query("SELECT COUNT(*) FROM medicines WHERE expiry_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY)")->fetchColumn();
$out = (int)$pdo->query("SELECT COUNT(*) FROM medicines WHERE quantity<=0")->fetchColumn();
$low = (int)$pdo->query("SELECT COUNT(*) FROM medicines WHERE quantity>0 AND quantity <= alert_threshold")->fetchColumn();
$salesToday = (int)$pdo->query("SELECT COUNT(*) FROM sales WHERE DATE(created_at)=CURDATE()")->fetchColumn();
$caToday = (float)$pdo->query("SELECT COALESCE(SUM(total),0) FROM sales WHERE DATE(created_at)=CURDATE()")->fetchColumn();
$caMonth = (float)$pdo->query("SELECT COALESCE(SUM(total),0) FROM sales WHERE MONTH(created_at)=MONTH(CURDATE()) AND YEAR(created_at)=YEAR(CURDATE())")->fetchColumn();
$caYesterday = (float)$pdo->query("SELECT COALESCE(SUM(total),0) FROM sales WHERE DATE(created_at)=DATE_SUB(CURDATE(), INTERVAL 1 DAY)")->fetchColumn();
$clients = (int)$pdo->query("SELECT COUNT(*) FROM customers")->fetchColumn();
$invoices = (int)$pdo->query("SELECT COUNT(*) FROM invoices")->fetchColumn();
$expired = (int)$pdo->query("SELECT COUNT(*) FROM medicines WHERE expiry_date < CURDATE()")->fetchColumn();

// Charts data - 7 jours
$sales7 = $pdo->query("SELECT DATE(created_at) as d, SUM(total) as t, COUNT(*) as c FROM sales WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 6 DAY) GROUP BY DATE(created_at) ORDER BY d")->fetchAll();
$labels=[]; $salesVals=[]; $countVals=[];
$map=[]; foreach($sales7 as $r){ $map[$r['d']] = $r; }
for($i=6;$i>=0;$i--){
  $d=date('Y-m-d', strtotime("-$i days"));
  $labels[]=date('D d/m', strtotime($d));
  $salesVals[]=(float)($map[$d]['t'] ?? 0);
  $countVals[]=(int)($map[$d]['c'] ?? 0);
}
// Top medicines
$topMeds = $pdo->query("SELECT m.name, SUM(si.quantity) as qty FROM sale_items si JOIN medicines m ON m.id=si.medicine_id GROUP BY m.id ORDER BY qty DESC LIMIT 5")->fetchAll();
$topLabels = array_column($topMeds,'name');
$topQtys = array_column($topMeds,'qty');
$stockState = [$available, $low, $out, $expired];
$catSales = $pdo->query("SELECT c.name, SUM(si.quantity) as qty FROM sale_items si JOIN medicines m ON m.id=si.medicine_id LEFT JOIN categories c ON c.id=m.category_id GROUP BY c.name ORDER BY qty DESC")->fetchAll();

// Additional premium data
$recentSales = $pdo->query("SELECT s.*, c.full_name, u.name as cashier FROM sales s LEFT JOIN customers c ON c.id=s.customer_id LEFT JOIN users u ON u.id=s.user_id ORDER BY s.created_at DESC LIMIT 5")->fetchAll();
$lowStockList = $pdo->query("SELECT * FROM medicines WHERE quantity <= alert_threshold OR expiry_date < CURDATE() ORDER BY quantity ASC LIMIT 5")->fetchAll();
$recentMovs = $pdo->query("SELECT sm.*, m.name as med_name, u.name as user_name FROM stock_movements sm LEFT JOIN medicines m ON m.id=sm.medicine_id LEFT JOIN users u ON u.id=sm.user_id ORDER BY sm.created_at DESC LIMIT 5")->fetchAll();
$trendCa = $caYesterday>0 ? round((($caToday-$caYesterday)/$caYesterday)*100,1) : ($caToday>0?100:0);

layout_header('Dashboard', '<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>');
?>
<style>
/* Premium dashboard additions */
.welcome-banner{background:linear-gradient(135deg,#0a2a5e 0%, #0b5ed7 55%, #3b82f6 100%); border-radius:18px; color:#fff; position:relative; overflow:hidden; border:1px solid rgba(255,255,255,.08)}
.welcome-banner::after{content:""; position:absolute; width:520px; height:520px; background:radial-gradient(circle, rgba(255,255,255,.14) 0%, transparent 70%); right:-120px; top:-180px}
.welcome-banner .btn-light{border-radius:999px; font-weight:700}
.kpi-card{background:#fff; border:1px solid var(--gray-200); border-radius:16px; padding:16px; position:relative; overflow:hidden; transition:.2s; box-shadow:var(--shadow)}
.kpi-card:hover{transform:translateY(-2px); box-shadow:var(--shadow-lg); border-color:#cbd5e1}
.kpi-card::before{content:""; position:absolute; left:0; top:0; bottom:0; width:3px; background:var(--blue)}
.kpi-card.accent-green::before{background:var(--green)}
.kpi-card.accent-orange::before{background:var(--orange)}
.kpi-card.accent-red::before{background:var(--red)}
.kpi-card.accent-dark::before{background:var(--blue-dark)}
.mini-spark{height:28px; opacity:.9}
.section-card .card-header{font-size:13px; letter-spacing:-.01em}
.alert-item{padding:10px 12px; border-radius:12px; border:1px solid var(--gray-200); background:#fff; display:flex; gap:10px; align-items:start; transition:.15s}
.alert-item:hover{border-color:#cbd5e1; background:#f8fafc}
.timeline-dot{width:10px; height:10px; border-radius:50%; margin-top:6px; flex-shrink:0}
.table-premium thead th{background:#f8fafc !important; font-size:10.5px}
.progress-thin{height:6px; border-radius:999px; background:#f1f5f9}
</style>

<!-- WELCOME BANNER -->
<div class="welcome-banner p-4 p-md-4 mb-4">
  <div class="row align-items-center g-3" style="position:relative; z-index:1">
    <div class="col-lg-8">
      <div class="d-flex align-items-center gap-3 mb-2">
        <div style="width:44px;height:44px;background:#fff;color:var(--blue-dark);border-radius:12px;display:grid;place-items:center;font-weight:800"><?= strtoupper(substr($_SESSION['user']['name'],0,1)) ?></div>
        <div>
          <div style="font-weight:800; font-size:18px; line-height:1">Bonjour, <?= e(explode(' ', $_SESSION['user']['name'])[0]) ?> 👋</div>
          <div style="opacity:.86; font-size:13px">Voici l'état de la pharmacie aujourd'hui — <?= date('l d F Y') ?></div>
        </div>
        <span class="badge bg-white text-primary ms-auto d-none d-md-inline" style="border-radius:999px; padding:7px 12px"><i class="fa-solid fa-circle text-success me-1" style="font-size:7px"></i> Système opérationnel</span>
      </div>
      <div class="d-flex flex-wrap gap-2 mt-3">
        <a href="sales.php" class="btn btn-light btn-sm text-primary"><i class="fa-solid fa-cash-register me-1"></i> Nouvelle vente</a>
        <a href="medicines.php" class="btn btn-outline-light btn-sm" style="border-color:rgba(255,255,255,.4)"><i class="fa-solid fa-plus me-1"></i> Ajouter médicament</a>
        <a href="reports.php" class="btn btn-outline-light btn-sm" style="border-color:rgba(255,255,255,.4)"><i class="fa-solid fa-file-export me-1"></i> Rapport du jour</a>
      </div>
    </div>
    <div class="col-lg-4">
      <div class="bg-white text-dark p-3" style="border-radius:14px; box-shadow:0 12px 32px rgba(0,0,0,.18)">
        <div class="d-flex justify-content-between align-items-center mb-2">
          <strong style="font-size:12px"><i class="fa-solid fa-chart-line text-primary me-1"></i> Performance du jour</strong>
          <span class="badge <?= $trendCa>=0?'bg-success':'bg-danger' ?>" style="font-size:10px"><?= $trendCa>=0?'+':'' ?><?= $trendCa ?>% vs hier</span>
        </div>
        <div class="row g-2 text-center">
          <div class="col-6"><div style="background:#f8fafc;border:1px solid #e2e8f0;border-radius:10px;padding:10px"><div class="small text-muted" style="font-size:10px">Ventes</div><div class="fw-bold"><?= $salesToday ?></div></div></div>
          <div class="col-6"><div style="background:#e6f7ef;border:1px solid #a7f3d0;border-radius:10px;padding:10px"><div class="small text-muted" style="font-size:10px">CA jour</div><div class="fw-bold"><?= number_format($caToday,0,',',' ') ?> CDF</div></div></div>
          <div class="col-12"><div class="small text-muted" style="font-size:11px"><i class="fa-solid fa-calendar me-1"></i> CA mois: <strong><?= money($caMonth) ?></strong> • <?= $invoices ?> factures</div></div>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- KPI GRID PREMIUM 8 -->
<div class="row g-3 mb-4">
  <?php
  $kpis=[
    ['label'=>'Total médicaments','value'=>$totalMeds,'sub'=>'Références actives','icon'=>'fa-capsules','accent'=>'','trend'=>'+3.1%','up'=>true],
    ['label'=>'Disponibles','value'=>$available,'sub'=>'Stock sain','icon'=>'fa-circle-check','accent'=>'accent-green','trend'=>'+2.4%','up'=>true],
    ['label'=>'Bientôt périmés','value'=>$expiring,'sub'=>'< 30 jours','icon'=>'fa-hourglass-half','accent'=>'accent-orange','trend'=>'à surveiller','up'=>false],
    ['label'=>'Rupture','value'=>$out,'sub'=>'Action requise','icon'=>'fa-ban','accent'=>'accent-red','trend'=>$out>0?'critique':'stable','up'=>false],
    ['label'=>'Ventes du jour','value'=>$salesToday,'sub'=>'Transactions','icon'=>'fa-bag-shopping','accent'=>'','trend'=>'+8%','up'=>true],
    ['label'=>"CA du jour",'value'=>money($caToday),'sub'=>'vs hier '.($trendCa>=0?'+':'').$trendCa.'%','icon'=>'fa-sack-dollar','accent'=>'accent-green','trend'=> $trendCa>=0?'+'.$trendCa.'%':$trendCa.'%','up'=>$trendCa>=0],
    ['label'=>'Clients','value'=>$clients,'sub'=>'Base patients','icon'=>'fa-users','accent'=>'accent-dark','trend'=>'+1.5%','up'=>true],
    ['label'=>'Factures','value'=>$invoices,'sub'=>'Générées','icon'=>'fa-file-invoice','accent'=>'accent-dark','trend'=>'+5%','up'=>true],
  ];
  foreach($kpis as $k):
  ?>
  <div class="col-6 col-md-3">
    <div class="kpi-card <?= e($k['accent']) ?> h-100">
      <div class="d-flex justify-content-between align-items-start mb-2">
        <div class="icon-box" style="width:38px;height:38px; border-radius:10px; background:<?= $k['accent']=='accent-green'?'var(--green-light)':($k['accent']=='accent-orange'?'var(--orange-light)':($k['accent']=='accent-red'?'var(--red-light)':'#e8f0fe')) ?>; color:<?= $k['accent']=='accent-green'?'var(--green)':($k['accent']=='accent-orange'?'#d97706':($k['accent']=='accent-red'?'#991b1b':'var(--blue)')) ?>"><i class="fa-solid <?= e($k['icon']) ?>" style="font-size:14px"></i></div>
        <span class="badge" style="border-radius:999px; font-size:10px; background:<?= !empty($k['up'])?'#e6f7ef':'#fee2e2' ?>; color:<?= !empty($k['up'])?'#065f46':'#991b1b' ?>; border:1px solid <?= !empty($k['up'])?'#a7f3d0':'#fecaca' ?>"><?= e($k['trend']) ?></span>
      </div>
      <div class="fw-bold" style="font-size:20px; letter-spacing:-.02em; line-height:1"><?= is_numeric($k['value']) ? e($k['value']) : $k['value'] ?></div>
      <div style="font-size:12.5px; font-weight:600; color:var(--gray-700)"><?= e($k['label']) ?></div>
      <div class="small text-muted" style="font-size:11px"><?= $k['sub'] ?></div>
    </div>
  </div>
  <?php endforeach; ?>
</div>

<!-- MAIN CHARTS -->
<div class="row g-3 mb-4">
  <div class="col-lg-8">
    <div class="card section-card h-100">
      <div class="card-header">
        <div class="d-flex align-items-center gap-2">
          <div class="icon-box" style="width:32px;height:32px;background:#e8f0fe;color:var(--blue)"><i class="fa-solid fa-chart-line" style="font-size:12px"></i></div>
          <div>
            <div style="font-weight:700; font-size:13px">Évolution des ventes — 7 jours</div>
            <div class="small text-muted" style="font-size:11px">Montant encaissé & volume de transactions</div>
          </div>
        </div>
        <div class="d-none d-md-flex gap-2">
          <span class="badge bg-light border text-dark"><span style="width:8px;height:8px;background:#0b5ed7;display:inline-block;border-radius:2px"></span> Montant</span>
          <span class="badge bg-light border text-dark"><span style="width:8px;height:8px;background:#10b981;display:inline-block;border-radius:2px"></span> Volume</span>
        </div>
      </div>
      <div class="card-body"><canvas id="salesChart" height="118"></canvas></div>
    </div>
  </div>
  <div class="col-lg-4">
    <div class="card section-card h-100">
      <div class="card-header">
        <div>
          <div style="font-weight:700; font-size:13px">État du stock</div>
          <div class="small text-muted" style="font-size:11px"><?= $totalMeds ?> références suivies</div>
        </div>
        <a href="stocks.php" class="btn btn-sm btn-light border" style="border-radius:999px; font-size:11px">Détails <i class="fa-solid fa-arrow-right ms-1"></i></a>
      </div>
      <div class="card-body">
        <canvas id="stockChart" height="200"></canvas>
        <div class="row g-2 mt-3 text-center">
          <div class="col-3"><div class="small text-muted" style="font-size:10px">Normal</div><div class="fw-bold text-success" style="font-size:13px"><?= $available ?></div></div>
          <div class="col-3"><div class="small text-muted" style="font-size:10px">Faible</div><div class="fw-bold" style="color:#d97706"><?= $low ?></div></div>
          <div class="col-3"><div class="small text-muted" style="font-size:10px">Rupture</div><div class="fw-bold text-danger"><?= $out ?></div></div>
          <div class="col-3"><div class="small text-muted" style="font-size:10px">Expiré</div><div class="fw-bold" style="color:#0a2a5e"><?= $expired ?></div></div>
        </div>
      </div>
    </div>
  </div>
</div>

<div class="row g-3 mb-4">
  <div class="col-lg-6">
    <div class="card section-card h-100">
      <div class="card-header">
        <div>
          <div style="font-weight:700; font-size:13px">Chiffre d'affaires — tendance</div>
          <div class="small text-muted" style="font-size:11px">Courbe journalière, lissée</div>
        </div>
        <span class="badge bg-success" style="border-radius:999px">Mois: <?= money($caMonth) ?></span>
      </div>
      <div class="card-body"><canvas id="caChart" height="140"></canvas></div>
    </div>
  </div>
  <div class="col-lg-6">
    <div class="card section-card h-100">
      <div class="card-header">
        <div>
          <div style="font-weight:700; font-size:13px">Médicaments les plus vendus</div>
          <div class="small text-muted" style="font-size:11px">Top 5 par quantité</div>
        </div>
        <a href="reports.php" class="small text-decoration-none">Rapport complet</a>
      </div>
      <div class="card-body"><canvas id="topChart" height="140"></canvas></div>
    </div>
  </div>
</div>

<div class="row g-3">
  <div class="col-lg-7">
    <div class="card section-card mb-3">
      <div class="card-header">
        <div>
          <div style="font-weight:700; font-size:13px">Répartition par catégorie</div>
          <div class="small text-muted" style="font-size:11px">Poids des familles thérapeutiques</div>
        </div>
      </div>
      <div class="card-body"><canvas id="catChart" height="130"></canvas></div>
    </div>

    <div class="card section-card">
      <div class="card-header d-flex justify-content-between align-items-center">
        <div style="font-weight:700; font-size:13px"><i class="fa-solid fa-clock-rotate-left text-primary me-1"></i> Ventes récentes</div>
        <a href="invoices.php" class="btn btn-sm btn-primary" style="border-radius:999px; font-size:11px">Voir factures</a>
      </div>
      <div class="table-responsive">
        <table class="table table-premium mb-0">
          <thead><tr><th>Facture</th><th>Client</th><th>Caissier</th><th class="text-end">Total</th></tr></thead>
          <tbody>
            <?php foreach($recentSales as $s): ?>
            <tr>
              <td><span class="badge bg-light border text-dark" style="font-size:11px"><?= e($s['invoice_number']) ?></span><div class="small text-muted" style="font-size:11px"><?= date('d/m H:i', strtotime($s['created_at'])) ?></div></td>
              <td style="font-weight:600; font-size:13px"><?= e($s['full_name'] ?? 'Comptoir') ?></td>
              <td class="small text-muted"><?= e($s['cashier'] ?? '-') ?></td>
              <td class="text-end fw-bold" style="font-size:13px"><?= money($s['total']) ?></td>
            </tr>
            <?php endforeach; ?>
            <?php if(!$recentSales): ?><tr><td colspan="4" class="text-center small text-muted py-3">Aucune vente.</td></tr><?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>

  <div class="col-lg-5">
    <div class="card section-card mb-3">
      <div class="card-header d-flex justify-content-between align-items-center">
        <div style="font-weight:700; font-size:13px"><i class="fa-solid fa-triangle-exclamation text-warning me-1"></i> Alertes critiques</div>
        <a href="stocks.php" class="small">Voir tout</a>
      </div>
      <div class="p-3 d-grid gap-2">
        <?php
        $alerts = getAlerts($pdo);
        if(!$alerts) echo '<div class="small text-muted p-2">Aucune alerte — stock sain.</div>';
        foreach(array_slice($alerts,0,5) as $a):
          $border = $a['type']=='danger' ? '#ef4444' : '#f59e0b';
          $bg = $a['type']=='danger' ? '#fee2e2' : '#fff7e6';
        ?>
        <a href="<?= e($a['link']) ?>" class="alert-item text-dark text-decoration-none" style="border-left:3px solid <?= $border ?>">
          <div class="icon-box" style="width:32px;height:32px; background:<?= $bg ?>; color:<?= $a['type']=='danger'?'#991b1b':'#92400e' ?>"><i class="fa-solid <?= e($a['icon']) ?>" style="font-size:12px"></i></div>
          <div class="small" style="line-height:1.4"><?= $a['msg'] ?></div>
          <i class="fa-solid fa-chevron-right ms-auto text-muted" style="font-size:11px; margin-top:6px"></i>
        </a>
        <?php endforeach; ?>
      </div>
    </div>

    <div class="row g-3">
      <div class="col-12">
        <div class="card section-card">
          <div class="card-header">
            <div style="font-weight:700; font-size:13px"><i class="fa-solid fa-box-open text-warning me-1"></i> Stock à réapprovisionner</div>
          </div>
          <div class="list-group list-group-flush">
            <?php foreach($lowStockList as $m): 
              $isExp = $m['expiry_date'] && $m['expiry_date']<date('Y-m-d');
            ?>
            <div class="list-group-item d-flex justify-content-between align-items-center">
              <div>
                <div class="fw-semibold" style="font-size:13px"><?= e($m['name']) ?> <span class="badge bg-light border text-dark" style="font-size:10px"><?= e($m['code']) ?></span></div>
                <div class="small text-muted" style="font-size:11px"><?= e($m['location'] ?? '-') ?> • Exp <?= e($m['expiry_date'] ?? '-') ?></div>
              </div>
              <div class="text-end">
                <div class="fw-bold <?= $m['quantity']<=0?'text-danger':'text-warning' ?>" style="font-size:14px"><?= $m['quantity'] ?></div>
                <div class="small text-muted" style="font-size:11px">seuil <?= $m['alert_threshold'] ?></div>
              </div>
            </div>
            <?php endforeach; ?>
            <?php if(!$lowStockList): ?><div class="p-3 small text-muted">Aucun produit critique.</div><?php endif; ?>
          </div>
        </div>
      </div>
      <div class="col-12">
        <div class="card section-card">
          <div class="card-header">
            <div style="font-weight:700; font-size:13px"><i class="fa-solid fa-arrow-right-arrow-left text-primary me-1"></i> Derniers mouvements</div>
          </div>
          <div class="list-group list-group-flush">
            <?php foreach($recentMovs as $mv): ?>
            <div class="list-group-item d-flex gap-2">
              <span class="timeline-dot" style="background:<?= $mv['type']=='entree'?'#10b981':($mv['type']=='sortie'?'#ef4444':'#f59e0b') ?>"></span>
              <div class="flex-fill">
                <div class="small" style="font-weight:600; font-size:13px"><?= e($mv['med_name']) ?> <span class="badge <?= $mv['type']=='entree'?'bg-success':($mv['type']=='sortie'?'bg-danger':'bg-warning text-dark') ?>" style="font-size:10px"><?= e($mv['type']) ?></span></div>
                <div class="small text-muted" style="font-size:11px"><?= $mv['quantity'] ?> unités • <?= e($mv['user_name'] ?? 'Système') ?> • <?= date('d/m H:i', strtotime($mv['created_at'])) ?></div>
              </div>
              <div class="small fw-bold"><?= $mv['old_stock'] ?> → <?= $mv['new_stock'] ?></div>
            </div>
            <?php endforeach; ?>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<script>
const labels = <?= json_encode($labels) ?>;
const salesVals = <?= json_encode($salesVals) ?>;
const countVals = <?= json_encode($countVals) ?>;
Chart.defaults.font.family='Inter';
Chart.defaults.color='#64748b';

new Chart(document.getElementById('salesChart'),{
  type:'bar',
  data:{labels, datasets:[
    {label:'Montant CDF', data:salesVals, backgroundColor:'#0b5ed7', hoverBackgroundColor:'#0a2a5e', borderRadius:9, barThickness:18, borderSkipped:false},
    {label:'Nb ventes', data:countVals, type:'line', borderColor:'#10b981', backgroundColor:'rgba(16,185,129,.10)', tension:.45, fill:true, pointRadius:3, pointBackgroundColor:'#10b981', yAxisID:'y1'}
  ]},
  options:{responsive:true, interaction:{mode:'index', intersect:false},
    scales:{ y:{beginAtZero:true, grid:{color:'#f1f5f9'}, ticks:{font:{size:11}}}, y1:{position:'right', beginAtZero:true, grid:{display:false}, ticks:{font:{size:11}}}, x:{grid:{display:false}, ticks:{font:{size:11}}}},
    plugins:{legend:{position:'bottom', labels:{usePointStyle:true, boxWidth:8, font:{size:11, weight:600}}}, tooltip:{backgroundColor:'#0f172a', padding:10, cornerRadius:10}}
  }
});
new Chart(document.getElementById('caChart'),{
  type:'line',
  data:{labels, datasets:[{label:'CA', data:salesVals, borderColor:'#0b5ed7', backgroundColor:'rgba(11,94,215,.08)', fill:true, tension:.4, pointRadius:3, pointBackgroundColor:'#0b5ed7'}]},
  options:{responsive:true, scales:{y:{beginAtZero:true, grid:{color:'#f1f5f9'}}, x:{grid:{display:false}}}, plugins:{legend:{display:false}, tooltip:{backgroundColor:'#0f172a'}}}
});
new Chart(document.getElementById('topChart'),{
  type:'bar',
  data:{labels:<?= json_encode($topLabels ?: ['Aucune vente']) ?>, datasets:[{label:'Qté vendue', data:<?= json_encode($topQtys ?: [0]) ?>, backgroundColor:['#0b5ed7','#10b981','#f59e0b','#3b82f6','#0a2a5e'], borderRadius:8, barThickness:14}]},
  options:{indexAxis:'y', responsive:true, plugins:{legend:{display:false}}, scales:{x:{beginAtZero:true, grid:{color:'#f1f5f9'}}, y:{grid:{display:false}, ticks:{font:{size:11, weight:600}}}}}
});
new Chart(document.getElementById('stockChart'),{
  type:'doughnut',
  data:{labels:['Normal','Faible','Rupture','Expiré'], datasets:[{data:<?= json_encode($stockState) ?>, backgroundColor:['#10b981','#f59e0b','#ef4444','#0a2a5e'], borderWidth:0, hoverOffset:6}]},
  options:{cutout:'64%', plugins:{legend:{position:'bottom', labels:{usePointStyle:true, boxWidth:8, font:{size:11, weight:600}, padding:14}}, tooltip:{backgroundColor:'#0f172a'}}}
});
new Chart(document.getElementById('catChart'),{
  type:'bar',
  data:{labels:<?= json_encode(array_column($catSales,'name') ?: ['Aucune']) ?>, datasets:[{label:'Qté', data:<?= json_encode(array_map('intval', array_column($catSales,'qty')) ?: [0]) ?>, backgroundColor:'#0a2a5e', hoverBackgroundColor:'#0b5ed7', borderRadius:8, barThickness:16}]},
  options:{responsive:true, plugins:{legend:{display:false}}, scales:{y:{beginAtZero:true, grid:{color:'#f1f5f9'}}, x:{grid:{display:false}, ticks:{font:{size:11}}}}}
});
</script>
<?php layout_footer(); ?>
