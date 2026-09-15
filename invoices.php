<?php
require_once __DIR__.'/config/database.php';
requireLogin();
require_once __DIR__.'/includes/partials/layout.php';
$q=trim($_GET['q'] ?? '');
$where=" WHERE 1=1 "; $params=[];
if($q!==''){ $where.=" AND (s.invoice_number LIKE ? OR c.full_name LIKE ?) "; $params=["%$q%","%$q%"]; }
$stmt=$pdo->prepare("SELECT s.*, c.full_name, u.name as cashier FROM sales s LEFT JOIN customers c ON c.id=s.customer_id LEFT JOIN users u ON u.id=s.user_id $where ORDER BY s.created_at DESC LIMIT 100");
$stmt->execute($params); $sales=$stmt->fetchAll();
$totalCA = array_sum(array_column($sales,'total'));
layout_header('Factures');
?>
<style>
.inv-hero{background:linear-gradient(135deg,#0a2a5e 0%, #0b5ed7 100%); border-radius:18px; color:#fff; position:relative; overflow:hidden}
.inv-hero::after{content:""; position:absolute; width:380px; height:380px; background:radial-gradient(circle, rgba(255,255,255,.11), transparent 70%); right:-60px; top:-100px}
</style>
<div class="inv-hero p-4 mb-4">
  <div class="d-flex flex-wrap justify-content-between gap-3 align-items-center" style="position:relative; z-index:1">
    <div class="d-flex gap-3 align-items-center">
      <div style="width:48px;height:48px;background:#fff;color:var(--blue-dark);border-radius:12px;display:grid;place-items:center;font-size:18px"><i class="fa-solid fa-file-invoice"></i></div>
      <div>
        <h4 class="fw-bold mb-0">Factures</h4>
        <div class="small" style="opacity:.88"><?= count($sales) ?> factures • <?= money($totalCA) ?> encaissés • Impression & PDF</div>
      </div>
    </div>
    <div class="d-flex gap-2">
      <a href="sales.php" class="btn btn-light text-primary btn-sm fw-bold" style="border-radius:999px"><i class="fa-solid fa-cash-register me-1"></i> Nouvelle vente</a>
    </div>
  </div>
</div>

<div class="card" style="border-radius:16px; overflow:hidden">
  <div class="table-responsive">
    <table class="table table-hover mb-0" id="mainTable">
      <thead><tr><th>N° Facture</th><th>Date</th><th>Client</th><th>Caissier</th><th class="text-end">Total</th><th>Paiement</th><th>Statut</th><th class="text-end">Actions</th></tr></thead>
      <tbody>
        <?php foreach($sales as $s): ?>
        <tr>
          <td><div class="d-flex gap-2 align-items-center"><div style="width:32px;height:32px;border-radius:8px;background:#e8f0fe;color:var(--blue);display:grid;place-items:center"><i class="fa-solid fa-receipt" style="font-size:12px"></i></div><span class="badge bg-light border text-dark" style="border-radius:999px; font-size:11px; padding:6px 10px"><?= e($s['invoice_number']) ?></span></div></td>
          <td class="small"><span class="badge bg-light border text-dark" style="font-size:11px"><i class="fa-regular fa-clock me-1"></i><?= date('d/m/Y H:i', strtotime($s['created_at'])) ?></span></td>
          <td><span class="fw-semibold" style="font-size:13px"><?= e($s['full_name'] ?? 'Comptoir') ?></span></td>
          <td class="small"><i class="fa-solid fa-user text-muted me-1"></i><?= e($s['cashier'] ?? '—') ?></td>
          <td class="text-end fw-bold text-primary"><?= money($s['total']) ?></td>
          <td><span class="badge bg-light border text-dark text-capitalize" style="border-radius:999px; font-size:11px; padding:6px 10px"><?= e($s['payment_method']) ?></span></td>
          <td><span class="badge <?= $s['status']=='completed'?'bg-success':'bg-danger' ?>" style="border-radius:999px; font-size:11px; padding:6px 10px"><i class="fa-solid <?= $s['status']=='completed'?'fa-check':'fa-xmark' ?> me-1"></i><?= e($s['status']) ?></span></td>
          <td class="text-end">
            <div class="d-flex gap-1 justify-content-end">
              <a href="invoice.php?num=<?= e($s['invoice_number']) ?>" class="btn btn-sm btn-primary" style="border-radius:999px; padding:6px 12px; font-size:12px"><i class="fa-solid fa-eye me-1"></i>Voir</a>
              <a href="invoice.php?num=<?= e($s['invoice_number']) ?>&print=1" target="_blank" class="btn btn-sm btn-light border" style="border-radius:999px; width:32px; height:32px; display:grid; place-items:center"><i class="fa-solid fa-print" style="font-size:11px"></i></a>
            </div>
          </td>
        </tr>
        <?php endforeach; ?>
        <?php if(!$sales): ?><tr><td colspan="8" class="text-center py-5 text-muted">Aucune facture.</td></tr><?php endif; ?>
      </tbody>
    </table>
  </div>
</div>
<?php layout_footer(); ?>
