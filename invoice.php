<?php
require_once __DIR__.'/config/database.php';
requireLogin();
$num=$_GET['num'] ?? '';
if(!$num) die("Numéro manquant");
$stmt=$pdo->prepare("SELECT s.*, c.full_name, c.phone, c.address, u.name as cashier FROM sales s LEFT JOIN customers c ON c.id=s.customer_id LEFT JOIN users u ON u.id=s.user_id WHERE s.invoice_number=?");
$stmt->execute([$num]); $sale=$stmt->fetch();
if(!$sale) die("Facture introuvable");
$items=$pdo->prepare("SELECT si.*, m.name FROM sale_items si LEFT JOIN medicines m ON m.id=si.medicine_id WHERE si.sale_id=?");
$items->execute([$sale['id']]); $lines=$items->fetchAll();
$print = isset($_GET['print']);
?>
<!doctype html>
<html lang="fr">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Facture <?= e($num) ?> — Boyambi Pharmacy</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700;800&family=Plus+Jakarta+Sans:wght@700;800&display=swap" rel="stylesheet">
<link href="assets/css/style.css" rel="stylesheet">
<style>
@media print{ .no-print{display:none !important} body{background:#fff !important} .invoice-paper{border:none !important; box-shadow:none !important; padding:18px !important} }
.invoice-paper{background:#fff; border:1px solid var(--gray-200); border-radius:18px; padding:32px; box-shadow:0 12px 40px rgba(15,23,42,.08); position:relative; overflow:hidden}
.invoice-paper::before{content:""; position:absolute; top:0; left:0; right:0; height:4px; background:linear-gradient(90deg,#0a2a5e 0%, #0b5ed7 50%, #3b82f6 100%)}
</style>
</head>
<body style="background:#f1f5f9">
<div class="container py-4" style="max-width:860px">
  <div class="d-flex justify-content-between align-items-center mb-3 no-print">
    <a href="invoices.php" class="btn btn-light border btn-sm" style="border-radius:999px"><i class="fa-solid fa-arrow-left me-1"></i> Retour factures</a>
    <div class="d-flex gap-2">
      <button onclick="window.print()" class="btn btn-primary btn-sm fw-bold" style="border-radius:999px"><i class="fa-solid fa-print me-1"></i> Imprimer</button>
      <button onclick="window.print()" class="btn btn-light border btn-sm" style="border-radius:999px"><i class="fa-solid fa-download me-1"></i> PDF</button>
    </div>
  </div>
  <div class="invoice-paper">
    <div class="d-flex justify-content-between align-items-start mb-4 flex-wrap gap-3">
      <div class="d-flex gap-3">
        <div style="width:58px;height:58px; background:var(--blue-dark); color:#fff; border-radius:14px; display:grid; place-items:center; font-size:22px; box-shadow:0 6px 16px rgba(10,42,94,.18)"><i class="fa-solid fa-pills"></i></div>
        <div>
          <h4 class="fw-bold mb-0" style="letter-spacing:.04em; font-family:'Plus Jakarta Sans',sans-serif">BOYAMBI PHARMACY</h4>
          <div class="small text-muted">Hôpital Boyambi — Kisangani, RDC</div>
          <div class="small text-muted"><i class="fa-solid fa-phone me-1 text-primary"></i> +243 81 000 0001 • <i class="fa-solid fa-envelope ms-1 text-primary me-1"></i> contact@boyambi.cd</div>
          <div class="small text-muted"><i class="fa-solid fa-location-dot me-1 text-primary"></i> Makiso — Avenue de l'Hôpital</div>
        </div>
      </div>
      <div class="text-end">
        <div class="badge" style="background:linear-gradient(135deg,#0a2a5e 0%, #0b5ed7 100%); color:#fff; border-radius:999px; padding:8px 14px; font-size:11px; letter-spacing:.08em">FACTURE</div>
        <div class="fw-bold mt-2" style="font-size:15px; letter-spacing:.04em"><?= e($sale['invoice_number']) ?></div>
        <div class="small text-muted"><?= date('d F Y • H:i', strtotime($sale['created_at'])) ?></div>
        <span class="badge bg-success mt-2" style="border-radius:999px; padding:6px 12px"><i class="fa-solid fa-check me-1"></i> Payée — <span class="text-capitalize"><?= e($sale['payment_method']) ?></span></span>
      </div>
    </div>
    <div class="row g-3 mb-4">
      <div class="col-md-6">
        <div class="p-3" style="background:linear-gradient(135deg,#f8fafc 0%, #fff 100%); border:1px solid #e2e8f0; border-radius:14px">
          <div class="small fw-bold" style="font-size:10px; letter-spacing:.12em; color:var(--blue)">FACTURÉ À</div>
          <div class="fw-bold mt-1" style="font-size:15px"><?= e($sale['full_name'] ?? 'Client comptoir') ?></div>
          <div class="small text-muted"><i class="fa-solid fa-phone me-1"></i><?= e($sale['phone'] ?? '—') ?> • <?= e($sale['address'] ?? '—') ?></div>
        </div>
      </div>
      <div class="col-md-6">
        <div class="p-3" style="background:#e8f0fe; border:1px solid #cbd5e1; border-radius:14px">
          <div class="small fw-bold" style="font-size:10px; letter-spacing:.12em; color:var(--blue-dark)">CAISSIER & PAIEMENT</div>
          <div class="fw-bold mt-1"><?= e($sale['cashier'] ?? '—') ?></div>
          <div class="small text-muted">Méthode: <span class="badge bg-white border text-dark text-capitalize" style="border-radius:999px"><?= e($sale['payment_method']) ?></span> • <?= date('d/m/Y', strtotime($sale['created_at'])) ?></div>
        </div>
      </div>
    </div>
    <div class="table-responsive" style="border:1px solid var(--gray-200); border-radius:12px; overflow:hidden">
      <table class="table mb-0">
        <thead><tr style="background:#0a2a5e; color:#fff"><th style="color:#fff; font-size:11px; padding:12px">#</th><th style="color:#fff; font-size:11px">Médicament</th><th class="text-end" style="color:#fff; font-size:11px">Qté</th><th class="text-end" style="color:#fff; font-size:11px">PU</th><th class="text-end" style="color:#fff; font-size:11px">Remise</th><th class="text-end" style="color:#fff; font-size:11px">Total</th></tr></thead>
        <tbody>
          <?php $i=1; foreach($lines as $l): ?>
          <tr style="border-bottom:1px solid #f1f5f9"><td class="small text-muted"><?= $i++ ?></td><td class="fw-semibold" style="font-size:13px"><i class="fa-solid fa-capsules text-primary me-1" style="font-size:11px"></i><?= e($l['name'] ?? '—') ?></td><td class="text-end fw-bold"><?= $l['quantity'] ?></td><td class="text-end small"><?= money($l['unit_price']) ?></td><td class="text-end small text-muted"><?= money($l['discount']) ?></td><td class="text-end fw-bold"><?= money($l['subtotal']) ?></td></tr>
          <?php endforeach; ?>
        </tbody>
        <tfoot>
          <tr><td colspan="5" class="text-end small text-muted" style="padding:12px">Sous-total</td><td class="text-end fw-bold" style="padding:12px"><?= money($sale['subtotal']) ?></td></tr>
          <tr><td colspan="5" class="text-end small text-muted" style="padding:12px">Remise globale</td><td class="text-end fw-bold text-danger" style="padding:12px">-<?= money($sale['discount']) ?></td></tr>
          <tr style="background:#0a2a5e; color:#fff"><td colspan="5" class="text-end fw-bold" style="padding:14px; letter-spacing:.04em">TOTAL À PAYER</td><td class="text-end fw-bold" style="padding:14px; font-size:18px; letter-spacing:-.02em"><?= money($sale['total']) ?></td></tr>
        </tfoot>
      </table>
    </div>
    <div class="d-flex justify-content-between align-items-center mt-4 small text-muted flex-wrap gap-2" style="border-top:1px dashed #cbd5e1; padding-top:14px">
      <span><i class="fa-solid fa-heart text-danger me-1"></i> Merci pour votre confiance — Boyambi vous souhaite une bonne santé.</span>
      <span>Facture générée le <?= date('d/m/Y H:i') ?> • Document sans signature</span>
    </div>
  </div>
  <?php if($print): ?><script>window.print();</script><?php endif; ?>
</div>
</body>
</html>
