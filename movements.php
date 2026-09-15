<?php
require_once __DIR__.'/config/database.php';
requireLogin();
requireRole(['admin','pharmacien','gestionnaire_stock']);
require_once __DIR__.'/includes/partials/layout.php';

if($_SERVER['REQUEST_METHOD']==='POST' && ($_POST['action'] ?? '')==='adjust'){
    if(!verify_csrf($_POST['csrf_token'] ?? '')) flash('error','CSRF invalide');
    else {
        $mid=(int)$_POST['medicine_id']; $type=$_POST['type']; $qty=(int)$_POST['quantity']; $reason=trim($_POST['reason']);
        $m=$pdo->prepare("SELECT quantity FROM medicines WHERE id=?"); $m->execute([$mid]); $old=(int)$m->fetchColumn();
        $new=$old;
        if($type==='entree') $new=$old+$qty;
        elseif($type==='sortie') $new=max(0,$old-$qty);
        elseif($type==='ajustement') $new=$qty;
        elseif($type==='retour') $new=$old+$qty;
        $pdo->prepare("UPDATE medicines SET quantity=?, status=CASE WHEN ?<=0 THEN 'out' WHEN ? <= alert_threshold THEN 'low' ELSE 'available' END WHERE id=?")->execute([$new,$new,$new,$mid]);
        $pdo->prepare("INSERT INTO stock_movements (medicine_id,type,quantity,old_stock,new_stock,user_id,reason) VALUES (?,?,?,?,?,?,?)")->execute([$mid,$type,$qty,$old,$new,$_SESSION['user']['id'],$reason]);
        logActivity($pdo,'mouvement_stock',"$type $qty sur médicament #$mid");
        flash('success','Mouvement enregistré. Nouveau stock: '.$new);
    }
    redirect('movements.php');
}

$medicines=$pdo->query("SELECT id, name, code FROM medicines ORDER BY name")->fetchAll();
$medFilter = $_GET['medicine'] ?? '';
$typeFilter = $_GET['type'] ?? '';
$where=" WHERE 1=1 "; $params=[];
if($medFilter){ $where.=" AND sm.medicine_id=? "; $params[]=$medFilter; }
if($typeFilter){ $where.=" AND sm.type=? "; $params[]=$typeFilter; }
$logs=$pdo->prepare("SELECT sm.*, m.name as med_name, m.code, u.name as user_name FROM stock_movements sm LEFT JOIN medicines m ON m.id=sm.medicine_id LEFT JOIN users u ON u.id=sm.user_id $where ORDER BY sm.created_at DESC LIMIT 200");
$logs->execute($params); $rows=$logs->fetchAll();

layout_header('Mouvements de stock');
?>
<style>
.move-hero{background:linear-gradient(135deg,#0a2a5e 0%, #334155 100%); border-radius:18px; color:#fff; position:relative; overflow:hidden}
.move-hero::after{content:""; position:absolute; width:360px; height:360px; background:radial-gradient(circle, rgba(255,255,255,.1), transparent 70%); right:-40px; top:-80px}
.timeline-badge{padding:6px 10px; border-radius:999px; font-size:11px; font-weight:700; letter-spacing:.03em}
</style>

<div class="move-hero p-4 mb-4">
  <div class="d-flex flex-wrap justify-content-between gap-3 align-items-center" style="position:relative; z-index:1">
    <div class="d-flex gap-3 align-items-center">
      <div style="width:48px;height:48px;background:#fff;color:#0a2a5e;border-radius:12px;display:grid;place-items:center;font-size:18px"><i class="fa-solid fa-arrow-right-arrow-left"></i></div>
      <div>
        <h4 class="fw-bold mb-0" style="letter-spacing:-.02em">Mouvements de stock</h4>
        <div class="small" style="opacity:.86"><?= count($rows) ?> opérations • Traçabilité complète • Audit IP & utilisateur</div>
      </div>
    </div>
    <button class="btn btn-light text-primary btn-sm fw-bold" style="border-radius:999px" data-bs-toggle="modal" data-bs-target="#adjModal"><i class="fa-solid fa-plus me-1"></i> Nouveau mouvement</button>
  </div>
</div>

<div class="card mb-3" style="border-radius:16px">
  <div class="card-body">
    <form class="row g-3 align-items-end">
      <div class="col-md-5">
        <label class="form-label small fw-semibold" style="font-size:11px; letter-spacing:.06em; color:var(--gray-500)">MÉDICAMENT</label>
        <select name="medicine" class="form-select" style="border-radius:12px"><option value="">Tous médicaments</option><?php foreach($medicines as $me): ?><option value="<?= $me['id'] ?>" <?= $medFilter==$me['id']?'selected':'' ?>><?= e($me['name']) ?> (<?= e($me['code']) ?>)</option><?php endforeach; ?></select>
      </div>
      <div class="col-md-3">
        <label class="form-label small fw-semibold" style="font-size:11px; letter-spacing:.06em; color:var(--gray-500)">TYPE</label>
        <select name="type" class="form-select" style="border-radius:12px"><option value="">Tous types</option><option value="entree" <?= $typeFilter=='entree'?'selected':'' ?>>Entrée</option><option value="sortie" <?= $typeFilter=='sortie'?'selected':'' ?>>Sortie</option><option value="ajustement" <?= $typeFilter=='ajustement'?'selected':'' ?>>Ajustement</option><option value="retour" <?= $typeFilter=='retour'?'selected':'' ?>>Retour</option></select>
      </div>
      <div class="col-md-2"><button class="btn btn-primary w-100" style="border-radius:12px"><i class="fa-solid fa-filter me-1"></i> Filtrer</button></div>
      <div class="col-md-2"><a href="movements.php" class="btn btn-light border w-100" style="border-radius:12px">Reset</a></div>
    </form>
  </div>
</div>

<div class="card" style="border-radius:16px; overflow:hidden">
  <div class="table-responsive">
    <table class="table table-hover mb-0" id="mainTable">
      <thead><tr><th>Date</th><th>Médicament</th><th>Type</th><th class="text-center">Qté</th><th class="text-center">Ancien → Nouveau</th><th>Utilisateur</th><th>Motif</th></tr></thead>
      <tbody>
        <?php if(!$rows): ?><tr><td colspan="7" class="text-center py-5 text-muted">Aucun mouvement.</td></tr><?php endif; ?>
        <?php foreach($rows as $r):
          $typeBg = $r['type']=='entree'?'#e6f7ef':($r['type']=='sortie'?'#fee2e2':($r['type']=='retour'?'#e0f2fe':'#fff7e6'));
          $typeColor = $r['type']=='entree'?'#065f46':($r['type']=='sortie'?'#991b1b':($r['type']=='retour'?'#0c4a6e':'#92400e'));
        ?>
        <tr>
          <td class="small"><span class="badge bg-light border text-dark" style="font-size:11px"><i class="fa-regular fa-clock me-1"></i><?= date('d/m/Y H:i', strtotime($r['created_at'])) ?></span></td>
          <td><div class="d-flex gap-2 align-items-center"><div style="width:32px;height:32px;border-radius:8px;background:#e8f0fe;color:var(--blue);display:grid;place-items:center"><i class="fa-solid fa-pills" style="font-size:11px"></i></div><div><div class="fw-semibold" style="font-size:13px"><?= e($r['med_name']) ?></div><small class="text-muted"><?= e($r['code']) ?></small></div></div></td>
          <td><span class="timeline-badge" style="background:<?= $typeBg ?>; color:<?= $typeColor ?>; border:1px solid rgba(0,0,0,.06)"><i class="fa-solid <?= $r['type']=='entree'?'fa-arrow-down':($r['type']=='sortie'?'fa-arrow-up':'fa-rotate') ?> me-1"></i><?= e($r['type']) ?></span></td>
          <td class="text-center fw-bold">+<?= $r['quantity'] ?></td>
          <td class="text-center"><span class="badge bg-light border text-dark"><?= $r['old_stock'] ?></span> <i class="fa-solid fa-arrow-right text-muted mx-1" style="font-size:10px"></i> <span class="badge bg-primary"><?= $r['new_stock'] ?></span></td>
          <td class="small"><i class="fa-solid fa-user text-muted me-1"></i><?= e($r['user_name'] ?? '—') ?></td>
          <td class="small text-muted" style="max-width:200px; white-space:normal"><?= e($r['reason'] ?? '—') ?></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<div class="modal fade" id="adjModal" tabindex="-1">
  <div class="modal-dialog">
    <form method="post" class="modal-content" style="border-radius:18px; overflow:hidden; border:none">
      <?= csrf_field() ?><input type="hidden" name="action" value="adjust">
      <div class="modal-header" style="background:linear-gradient(135deg,#0a2a5e 0%, #0b5ed7 100%); color:#fff; border:none"><h5 class="modal-title fw-bold" style="font-size:15px"><i class="fa-solid fa-plus me-2"></i>Nouveau mouvement</h5><button class="btn-close" style="filter:invert(1)" data-bs-dismiss="modal"></button></div>
      <div class="modal-body row g-3 p-4" style="background:#f8fafc">
        <div class="col-12"><label class="form-label small fw-semibold">Médicament *</label><select name="medicine_id" class="form-select bg-white" required style="border-radius:12px"><option value="">— Choisir —</option><?php foreach($medicines as $me): ?><option value="<?= $me['id'] ?>"><?= e($me['name']) ?> (<?= e($me['code']) ?>)</option><?php endforeach; ?></select></div>
        <div class="col-md-6"><label class="form-label small">Type</label><select name="type" class="form-select bg-white" style="border-radius:12px"><option value="entree">Entrée</option><option value="sortie">Sortie</option><option value="ajustement">Ajustement (stock = valeur)</option><option value="retour">Retour</option></select></div>
        <div class="col-md-6"><label class="form-label small">Quantité</label><input type="number" name="quantity" class="form-control bg-white" required min="1" style="border-radius:12px"></div>
        <div class="col-12"><label class="form-label small">Motif</label><input name="reason" class="form-control bg-white" placeholder="Livraison fournisseur, inventaire..." style="border-radius:12px"></div>
      </div>
      <div class="modal-footer bg-white"><button type="button" class="btn btn-light border" style="border-radius:12px" data-bs-dismiss="modal">Annuler</button><button class="btn btn-primary" style="border-radius:12px">Enregistrer</button></div>
    </form>
  </div>
</div>
<?php layout_footer(); ?>
