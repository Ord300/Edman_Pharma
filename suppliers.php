<?php
require_once __DIR__.'/config/database.php';
requireLogin();
requireRole(['admin','pharmacien','gestionnaire_stock']);
require_once __DIR__.'/includes/partials/layout.php';
if($_SERVER['REQUEST_METHOD']==='POST'){
 if(!verify_csrf($_POST['csrf_token'] ?? '')) flash('error','CSRF invalide');
 else {
  $a=$_POST['action'] ?? '';
  if($a==='delete'){ $pdo->prepare("DELETE FROM suppliers WHERE id=?")->execute([(int)$_POST['id']]); flash('success','Fournisseur supprimé.'); }
  else {
   $d=[trim($_POST['name']), trim($_POST['company']), trim($_POST['phone']), trim($_POST['email']), trim($_POST['address']), trim($_POST['products_supplied'])];
   if($a==='create'){ $pdo->prepare("INSERT INTO suppliers (name,company,phone,email,address,products_supplied) VALUES (?,?,?,?,?,?)")->execute($d); flash('success','Fournisseur ajouté.');}
   else { $id=(int)$_POST['id']; $pdo->prepare("UPDATE suppliers SET name=?,company=?,phone=?,email=?,address=?,products_supplied=? WHERE id=?")->execute([...$d,$id]); flash('success','Fournisseur modifié.');}
  }
 }
 redirect('suppliers.php');
}
$sups=$pdo->query("SELECT s.*, (SELECT COUNT(*) FROM medicines m WHERE m.supplier_id=s.id) as nb FROM suppliers s ORDER BY s.id DESC")->fetchAll();
layout_header('Fournisseurs');
?>
<style>
.sup-hero{background:linear-gradient(135deg,#0a2a5e 0%, #0b5ed7 100%); border-radius:18px; color:#fff; position:relative; overflow:hidden}
.sup-hero::after{content:""; position:absolute; width:380px; height:380px; background:radial-gradient(circle, rgba(255,255,255,.11), transparent 70%); right:-60px; top:-80px}
.sup-card{border-radius:16px; border:1px solid var(--gray-200); background:#fff; transition:.18s; overflow:hidden}
.sup-card:hover{transform:translateY(-3px); box-shadow:var(--shadow-lg); border-color:#cbd5e1}
</style>
<div class="sup-hero p-4 mb-4">
  <div class="d-flex flex-wrap justify-content-between gap-3 align-items-center" style="position:relative; z-index:1">
    <div class="d-flex gap-3 align-items-center">
      <div style="width:48px;height:48px;background:#fff;color:var(--blue-dark);border-radius:12px;display:grid;place-items:center;font-size:18px"><i class="fa-solid fa-truck-field"></i></div>
      <div>
        <h4 class="fw-bold mb-0">Fournisseurs</h4>
        <div class="small" style="opacity:.88"><?= count($sups) ?> partenaires • Approvisionnements tracés</div>
      </div>
    </div>
    <button class="btn btn-light text-primary btn-sm fw-bold" style="border-radius:999px" data-bs-toggle="modal" data-bs-target="#supModal" onclick="openCreate()"><i class="fa-solid fa-plus me-1"></i> Nouveau fournisseur</button>
  </div>
</div>

<div class="row g-4">
<?php foreach($sups as $s): ?>
  <div class="col-md-6 col-lg-4">
    <div class="sup-card h-100">
      <div class="p-4">
        <div class="d-flex justify-content-between align-items-start mb-2">
          <div style="width:44px;height:44px;border-radius:11px;background:#e8f0fe;color:var(--blue);display:grid;place-items:center"><i class="fa-solid fa-building"></i></div>
          <span class="badge bg-light border text-dark" style="border-radius:999px; font-size:11px; padding:6px 10px"><?= $s['nb'] ?> produits</span>
        </div>
        <h6 class="fw-bold mb-0" style="font-size:15px"><?= e($s['name']) ?></h6>
        <div class="small text-primary fw-semibold"><?= e($s['company'] ?: '—') ?></div>
        <div class="small text-muted mt-2 d-flex flex-column gap-1">
          <span><i class="fa-solid fa-phone me-1"></i><?= e($s['phone'] ?: '—') ?> • <?= e($s['email'] ?: '—') ?></span>
          <span><i class="fa-solid fa-location-dot me-1"></i><?= e($s['address'] ?: '—') ?></span>
        </div>
        <div class="mt-3 p-2" style="background:#f8fafc; border:1px solid var(--gray-200); border-radius:10px">
          <div class="small fw-semibold" style="font-size:11px; letter-spacing:.06em; color:var(--gray-500)">PRODUITS FOURNIS</div>
          <div class="small"><?= e($s['products_supplied'] ?: '—') ?></div>
        </div>
      </div>
      <div class="d-flex gap-2 p-3 bg-light border-top">
        <button class="btn btn-sm btn-primary flex-fill" style="border-radius:999px" onclick='openEdit(<?= json_encode($s) ?>)'><i class="fa-solid fa-pen me-1"></i>Modifier</button>
        <form method="post" onsubmit="return confirm('Supprimer ?')" class="d-inline"><?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= $s['id'] ?>"><button class="btn btn-sm btn-light border text-danger" style="border-radius:999px"><i class="fa-solid fa-trash"></i></button></form>
      </div>
    </div>
  </div>
<?php endforeach; ?>
<?php if(!$sups): ?><div class="col-12"><div class="card" style="border-radius:16px"><div class="card-body text-center py-5 text-muted">Aucun fournisseur. <button class="btn btn-sm btn-primary ms-2" style="border-radius:999px" onclick="openCreate()">Ajouter</button></div></div></div><?php endif; ?>
</div>

<div class="modal fade" id="supModal" tabindex="-1">
 <div class="modal-dialog"><form method="post" class="modal-content" style="border-radius:18px; overflow:hidden; border:none"><?= csrf_field() ?><input type="hidden" name="action" id="sAct" value="create"><input type="hidden" name="id" id="sId">
  <div class="modal-header" style="background:linear-gradient(135deg,#0a2a5e 0%, #0b5ed7 100%); color:#fff; border:none"><h5 class="modal-title fw-bold" id="sTitle" style="font-size:15px">Nouveau fournisseur</h5><button class="btn-close" style="filter:invert(1)" data-bs-dismiss="modal"></button></div>
  <div class="modal-body row g-3 p-4" style="background:#f8fafc">
    <div class="col-12"><label class="form-label small fw-semibold">Nom *</label><input name="name" id="sName" class="form-control bg-white" style="border-radius:12px" required></div>
    <div class="col-12"><label class="form-label small">Entreprise</label><input name="company" id="sComp" class="form-control bg-white" style="border-radius:12px"></div>
    <div class="col-md-6"><label class="form-label small">Téléphone</label><input name="phone" id="sPhone" class="form-control bg-white" style="border-radius:12px"></div>
    <div class="col-md-6"><label class="form-label small">E-mail</label><input name="email" id="sEmail" class="form-control bg-white" style="border-radius:12px"></div>
    <div class="col-12"><label class="form-label small">Adresse</label><input name="address" id="sAddr" class="form-control bg-white" style="border-radius:12px"></div>
    <div class="col-12"><label class="form-label small">Produits fournis</label><input name="products_supplied" id="sProd" class="form-control bg-white" style="border-radius:12px" placeholder="Antalgiques, Antibiotiques..."></div>
  </div>
  <div class="modal-footer bg-white"><button type="button" class="btn btn-light border" style="border-radius:12px" data-bs-dismiss="modal">Annuler</button><button class="btn btn-primary" style="border-radius:12px">Enregistrer</button></div>
 </form></div>
</div>
<script>
function openCreate(){ document.getElementById('sTitle').textContent='Nouveau fournisseur'; document.getElementById('sAct').value='create'; document.getElementById('sId').value=''; ['sName','sComp','sPhone','sEmail','sAddr','sProd'].forEach(i=>document.getElementById(i).value='');}
function openEdit(s){ document.getElementById('sTitle').textContent='Modifier — '+s.name; document.getElementById('sAct').value='update'; document.getElementById('sId').value=s.id; document.getElementById('sName').value=s.name; document.getElementById('sComp').value=s.company||''; document.getElementById('sPhone').value=s.phone||''; document.getElementById('sEmail').value=s.email||''; document.getElementById('sAddr').value=s.address||''; document.getElementById('sProd').value=s.products_supplied||''; new bootstrap.Modal(document.getElementById('supModal')).show();}
</script>
<?php layout_footer(); ?>
