<?php
require_once __DIR__.'/config/database.php';
requireLogin();
requireRole(['admin','pharmacien','caissier']);
require_once __DIR__.'/includes/partials/layout.php';

if($_SERVER['REQUEST_METHOD']==='POST'){
  if(!verify_csrf($_POST['csrf_token'] ?? '')) flash('error','CSRF invalide');
  else {
    $action=$_POST['action'] ?? '';
    if($action==='delete'){ $pdo->prepare("DELETE FROM customers WHERE id=?")->execute([(int)$_POST['id']]); flash('success','Client supprimé.'); }
    else {
      $data=[trim($_POST['full_name']), $_POST['gender'] ?? 'M', trim($_POST['phone']), trim($_POST['address'])];
      if($action==='create'){ $pdo->prepare("INSERT INTO customers (full_name,gender,phone,address) VALUES (?,?,?,?)")->execute($data); flash('success','Client ajouté.'); }
      else { $id=(int)$_POST['id']; $pdo->prepare("UPDATE customers SET full_name=?,gender=?,phone=?,address=? WHERE id=?")->execute([...$data,$id]); flash('success','Client modifié.'); }
    }
  }
  redirect('customers.php');
}
$q=trim($_GET['q'] ?? '');
$where=" WHERE 1=1 "; $params=[];
if($q){ $where.=" AND (full_name LIKE ? OR phone LIKE ?) "; $params=["%$q%","%$q%"]; }
$stmt=$pdo->prepare("SELECT c.*, (SELECT COUNT(*) FROM sales s WHERE s.customer_id=c.id) as nb, (SELECT COALESCE(SUM(total),0) FROM sales s WHERE s.customer_id=c.id) as total FROM customers c $where ORDER BY c.id DESC LIMIT 100");
$stmt->execute($params); $customers=$stmt->fetchAll();
layout_header('Clients');
?>
<style>
.cust-hero{background:linear-gradient(135deg,#0a2a5e 0%, #334155 100%); border-radius:18px; color:#fff; position:relative; overflow:hidden}
.cust-hero::after{content:""; position:absolute; width:380px; height:380px; background:radial-gradient(circle, rgba(255,255,255,.1), transparent 70%); right:-60px; top:-80px}
.avatar-cust{width:42px;height:42px;border-radius:11px; display:grid; place-items:center; font-weight:800; font-size:13px; flex-shrink:0}
</style>
<div class="cust-hero p-4 mb-4">
  <div class="d-flex flex-wrap justify-content-between gap-3 align-items-center" style="position:relative; z-index:1">
    <div class="d-flex gap-3 align-items-center">
      <div style="width:48px;height:48px;background:#fff;color:#0a2a5e;border-radius:12px;display:grid;place-items:center;font-size:18px"><i class="fa-solid fa-users"></i></div>
      <div>
        <h4 class="fw-bold mb-0">Clients / Patients</h4>
        <div class="small" style="opacity:.88"><?= count($customers) ?> clients • Fiches complètes • Historique achats</div>
      </div>
    </div>
    <button class="btn btn-light text-primary btn-sm fw-bold" style="border-radius:999px" data-bs-toggle="modal" data-bs-target="#custModal" onclick="openCreate()"><i class="fa-solid fa-plus me-1"></i> Nouveau client</button>
  </div>
</div>

<div class="card" style="border-radius:16px; overflow:hidden">
  <div class="table-responsive"><table class="table table-hover mb-0" id="mainTable">
    <thead><tr><th>Patient</th><th>Sexe</th><th>Téléphone</th><th>Adresse</th><th>Inscription</th><th class="text-center">Ventes</th><th class="text-end">Total</th><th class="text-end">Actions</th></tr></thead>
    <tbody>
      <?php foreach($customers as $c):
        $initial = strtoupper(substr($c['full_name'],0,1));
        $bg = $c['gender']=='F' ? '#fce7f3' : ($c['gender']=='M' ? '#e0f2fe' : '#f1f5f9');
        $col = $c['gender']=='F' ? '#be185d' : ($c['gender']=='M' ? '#0c4a6e' : '#475569');
      ?>
      <tr>
        <td><div class="d-flex gap-2 align-items-center"><div class="avatar-cust" style="background:<?= $bg ?>; color:<?= $col ?>"><?= e($initial) ?></div><div><div class="fw-semibold" style="font-size:13.5px"><?= e($c['full_name']) ?></div><small class="text-muted">#<?= $c['id'] ?></small></div></div></td>
        <td><span class="badge" style="border-radius:999px; font-size:11px; background:<?= $bg ?>; color:<?= $col ?>; border:1px solid rgba(0,0,0,.06)"><?= e($c['gender']=='M'?'M':($c['gender']=='F'?'F':'—')) ?></span></td>
        <td><span class="badge bg-light border text-dark" style="border-radius:999px; font-size:11px"><i class="fa-solid fa-phone me-1 text-muted"></i><?= e($c['phone'] ?: '—') ?></span></td>
        <td class="small text-muted"><?= e($c['address'] ?: '—') ?></td>
        <td class="small"><span class="badge bg-light border text-dark" style="font-size:11px"><?= date('d/m/Y', strtotime($c['created_at'])) ?></span></td>
        <td class="text-center"><span class="badge bg-primary" style="border-radius:999px; font-size:11px; padding:6px 10px"><?= $c['nb'] ?></span></td>
        <td class="text-end fw-bold text-primary"><?= money($c['total']) ?></td>
        <td class="text-end">
          <div class="d-flex gap-1 justify-content-end">
            <button class="btn btn-sm btn-light border" style="border-radius:999px; width:32px; height:32px; display:grid; place-items:center" onclick='openEdit(<?= json_encode($c) ?>)'><i class="fa-solid fa-pen" style="font-size:11px"></i></button>
            <a href="invoices.php?q=<?= urlencode($c['full_name']) ?>" class="btn btn-sm btn-light border" style="border-radius:999px; width:32px; height:32px; display:grid; place-items:center"><i class="fa-solid fa-file-invoice" style="font-size:11px"></i></a>
            <form method="post" onsubmit="return confirm('Supprimer ?')" class="d-inline"><?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= $c['id'] ?>"><button class="btn btn-sm btn-light border text-danger" style="border-radius:999px; width:32px; height:32px; display:grid; place-items:center"><i class="fa-solid fa-trash" style="font-size:11px"></i></button></form>
          </div>
        </td>
      </tr>
      <?php endforeach; ?>
      <?php if(!$customers): ?><tr><td colspan="8" class="text-center py-5 text-muted">Aucun client.</td></tr><?php endif; ?>
    </tbody>
  </table></div>
</div>

<div class="modal fade" id="custModal" tabindex="-1">
  <div class="modal-dialog"><form method="post" class="modal-content" style="border-radius:18px; overflow:hidden; border:none"><?= csrf_field() ?><input type="hidden" name="action" id="cAction" value="create"><input type="hidden" name="id" id="cId">
    <div class="modal-header" style="background:linear-gradient(135deg,#0a2a5e 0%, #334155 100%); color:#fff; border:none"><h5 class="modal-title fw-bold" id="cTitle" style="font-size:15px"><i class="fa-solid fa-user-plus me-2"></i>Nouveau client</h5><button class="btn-close" style="filter:invert(1)" data-bs-dismiss="modal"></button></div>
    <div class="modal-body row g-3 p-4" style="background:#f8fafc">
      <div class="col-12"><label class="form-label small fw-semibold">Nom complet *</label><input name="full_name" id="cName" class="form-control bg-white" style="border-radius:12px" required></div>
      <div class="col-md-6"><label class="form-label small">Sexe</label><select name="gender" id="cGender" class="form-select bg-white" style="border-radius:12px"><option value="M">Masculin</option><option value="F">Féminin</option><option value="Autre">Autre</option></select></div>
      <div class="col-md-6"><label class="form-label small">Téléphone</label><input name="phone" id="cPhone" class="form-control bg-white" style="border-radius:12px" placeholder="+243 ..."></div>
      <div class="col-12"><label class="form-label small">Adresse</label><input name="address" id="cAddr" class="form-control bg-white" style="border-radius:12px" placeholder="Commune, avenue..."></div>
    </div>
    <div class="modal-footer bg-white"><button type="button" class="btn btn-light border" style="border-radius:12px" data-bs-dismiss="modal">Annuler</button><button class="btn btn-primary" style="border-radius:12px">Enregistrer</button></div>
  </form></div>
</div>
<script>
function openCreate(){ document.getElementById('cTitle').innerHTML='<i class="fa-solid fa-user-plus me-2"></i>Nouveau client'; document.getElementById('cAction').value='create'; document.getElementById('cId').value=''; ['cName','cPhone','cAddr'].forEach(i=>document.getElementById(i).value=''); }
function openEdit(c){ document.getElementById('cTitle').textContent='Modifier — '+c.full_name; document.getElementById('cAction').value='update'; document.getElementById('cId').value=c.id; document.getElementById('cName').value=c.full_name; document.getElementById('cGender').value=c.gender; document.getElementById('cPhone').value=c.phone||''; document.getElementById('cAddr').value=c.address||''; new bootstrap.Modal(document.getElementById('custModal')).show(); }
</script>
<?php layout_footer(); ?>
