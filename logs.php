<?php
require_once __DIR__.'/config/database.php';
requireLogin();
requireRole(['admin']);
require_once __DIR__.'/includes/partials/layout.php';
$logs=$pdo->query("SELECT l.*, u.name as user_name, u.email, u.role FROM activity_logs l LEFT JOIN users u ON u.id=l.user_id ORDER BY l.created_at DESC LIMIT 200")->fetchAll();
layout_header('Traçabilité');
?>
<style>
.log-hero{background:linear-gradient(135deg,#0a2a5e 0%, #1e293b 100%); border-radius:18px; color:#fff; position:relative; overflow:hidden}
.log-hero::after{content:""; position:absolute; width:380px; height:380px; background:radial-gradient(circle, rgba(255,255,255,.1), transparent 70%); right:-60px; top:-80px}
.action-badge{padding:6px 10px; border-radius:999px; font-size:11px; font-weight:700; letter-spacing:.02em; border:1px solid rgba(0,0,0,.06)}
</style>
<div class="log-hero p-4 mb-4">
  <div class="d-flex flex-wrap justify-content-between gap-3 align-items-center" style="position:relative; z-index:1">
    <div class="d-flex gap-3 align-items-center">
      <div style="width:48px;height:48px;background:#fff;color:#0a2a5e;border-radius:12px;display:grid;place-items:center;font-size:18px"><i class="fa-solid fa-clock-rotate-left"></i></div>
      <div>
        <h4 class="fw-bold mb-0">Journal d'activité</h4>
        <div class="small" style="opacity:.88"><?= count($logs) ?> événements • Traçabilité complète • IP & horodatage</div>
      </div>
    </div>
    <button onclick="window.print()" class="btn btn-light text-primary btn-sm fw-bold" style="border-radius:999px"><i class="fa-solid fa-print me-1"></i> Imprimer</button>
  </div>
</div>

<div class="card" style="border-radius:16px; overflow:hidden">
  <div class="table-responsive"><table class="table table-hover mb-0">
    <thead><tr><th>Date / Heure</th><th>Utilisateur</th><th>Action</th><th>Détails</th><th>IP</th></tr></thead>
    <tbody>
      <?php foreach($logs as $l):
        $icon = match($l['action']){
          'connexion'=>'fa-right-to-bracket', 'deconnexion'=>'fa-right-from-bracket', 'vente'=>'fa-cash-register', 'ajout_medicament'=>'fa-plus', 'modification_medicament'=>'fa-pen', 'suppression_medicament'=>'fa-trash', 'mouvement_stock'=>'fa-arrow-right-arrow-left', default=>'fa-circle-info'
        };
        $bg = str_contains($l['action'],'vente')?'#e6f7ef':(str_contains($l['action'],'suppression')?'#fee2e2':'#e8f0fe');
        $col = str_contains($l['action'],'vente')?'#065f46':(str_contains($l['action'],'suppression')?'#991b1b':'#0a2a5e');
      ?>
      <tr>
        <td class="small"><span class="badge bg-light border text-dark" style="font-size:11px; border-radius:999px"><i class="fa-regular fa-clock me-1"></i><?= date('d/m/Y H:i', strtotime($l['created_at'])) ?></span></td>
        <td><div class="d-flex gap-2 align-items-center"><div style="width:34px;height:34px;border-radius:10px;background:<?= $bg ?>;color:<?= $col ?>;display:grid;place-items:center; font-weight:800; font-size:12px"><?= strtoupper(substr($l['user_name'] ?? 'S',0,1)) ?></div><div><div class="fw-semibold" style="font-size:13px"><?= e($l['user_name'] ?? 'Système') ?></div><small class="text-muted" style="font-size:11px"><?= e($l['email'] ?? '—') ?> • <?= e($l['role'] ?? '') ?></small></div></div></td>
        <td><span class="action-badge" style="background:<?= $bg ?>; color:<?= $col ?>"><i class="fa-solid <?= $icon ?> me-1"></i><?= e($l['action']) ?></span></td>
        <td class="small text-muted" style="max-width:320px; white-space:normal; font-size:12px"><?= e($l['details']) ?></td>
        <td><span class="badge bg-light border text-dark" style="font-size:11px; border-radius:999px"><i class="fa-solid fa-network-wired me-1 text-muted"></i><?= e($l['ip_address']) ?></span></td>
      </tr>
      <?php endforeach; ?>
      <?php if(!$logs): ?><tr><td colspan="5" class="text-center py-5 text-muted">Aucune activité.</td></tr><?php endif; ?>
    </tbody>
  </table></div>
</div>
<?php layout_footer(); ?>
