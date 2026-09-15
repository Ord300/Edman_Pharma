<?php
require_once __DIR__.'/config/database.php';
requireLogin();
requireRole(['admin','pharmacien','gestionnaire_stock']);
require_once __DIR__.'/includes/partials/layout.php';

// Handle create/update/delete
$categories = $pdo->query("SELECT * FROM categories ORDER BY name")->fetchAll();
$suppliers = $pdo->query("SELECT * FROM suppliers ORDER BY name")->fetchAll();

if($_SERVER['REQUEST_METHOD']==='POST'){
    if(!verify_csrf($_POST['csrf_token'] ?? '')) flash('error','CSRF invalide');
    else {
        $action = $_POST['action'] ?? '';
        if($action==='delete'){
            $id=(int)$_POST['id'];
            $pdo->prepare("DELETE FROM medicines WHERE id=?")->execute([$id]);
            logActivity($pdo,'suppression_medicament',"Suppression médicament #$id");
            flash('success','Médicament supprimé.');
        } else {
            $data=[
                'code'=>trim($_POST['code'] ?? ''),
                'name'=>trim($_POST['name'] ?? ''),
                'category_id'=> $_POST['category_id'] ? (int)$_POST['category_id'] : null,
                'description'=>trim($_POST['description'] ?? ''),
                'dosage'=>trim($_POST['dosage'] ?? ''),
                'form'=>trim($_POST['form'] ?? ''),
                'laboratory'=>trim($_POST['laboratory'] ?? ''),
                'purchase_price'=> (float)($_POST['purchase_price'] ?? 0),
                'sale_price'=> (float)($_POST['sale_price'] ?? 0),
                'quantity'=> (int)($_POST['quantity'] ?? 0),
                'alert_threshold'=> (int)($_POST['alert_threshold'] ?? 10),
                'manufacture_date'=> $_POST['manufacture_date'] ?: null,
                'expiry_date'=> $_POST['expiry_date'] ?: null,
                'location'=>trim($_POST['location'] ?? ''),
                'supplier_id'=> $_POST['supplier_id'] ? (int)$_POST['supplier_id'] : null,
            ];
            if($data['name']==='' || $data['code']===''){ flash('error','Nom et code obligatoires.'); }
            else {
                $status='available';
                if($data['quantity']<=0) $status='out';
                elseif($data['quantity'] <= $data['alert_threshold']) $status='low';
                if($data['expiry_date'] && $data['expiry_date'] < date('Y-m-d')) $status='expired';
                $data['status']=$status;
                if($action==='create'){
                    try{
                        $stmt=$pdo->prepare("INSERT INTO medicines (code,name,category_id,description,dosage,form,laboratory,purchase_price,sale_price,quantity,alert_threshold,manufacture_date,expiry_date,location,supplier_id,status) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)");
                        $stmt->execute(array_values($data));
                        $mid=$pdo->lastInsertId();
                        $pdo->prepare("INSERT INTO stock_movements (medicine_id,type,quantity,old_stock,new_stock,user_id,reason) VALUES (?,?,?,?,?,?,?)")->execute([$mid,'entree',$data['quantity'],0,$data['quantity'],$_SESSION['user']['id'],'Stock initial']);
                        logActivity($pdo,'ajout_medicament',"Ajout {$data['name']} ({$data['code']})");
                        flash('success','Médicament ajouté avec succès.');
                    }catch(PDOException $e){ flash('error','Erreur: code déjà existant.');}
                } elseif($action==='update'){
                    $id=(int)$_POST['id'];
                    $old=$pdo->prepare("SELECT quantity FROM medicines WHERE id=?"); $old->execute([$id]); $oldQty=(int)$old->fetchColumn();
                    $stmt=$pdo->prepare("UPDATE medicines SET code=?,name=?,category_id=?,description=?,dosage=?,form=?,laboratory=?,purchase_price=?,sale_price=?,quantity=?,alert_threshold=?,manufacture_date=?,expiry_date=?,location=?,supplier_id=?,status=? WHERE id=?");
                    $vals=array_values($data); $vals[]=$id;
                    $stmt->execute($vals);
                    if($oldQty !== $data['quantity']){
                        $type = $data['quantity'] > $oldQty ? 'entree' : 'ajustement';
                        $diff = abs($data['quantity'] - $oldQty);
                        $pdo->prepare("INSERT INTO stock_movements (medicine_id,type,quantity,old_stock,new_stock,user_id,reason) VALUES (?,?,?,?,?,?,?)")->execute([$id,$type,$diff,$oldQty,$data['quantity'],$_SESSION['user']['id'],'Mise à jour médicament']);
                    }
                    logActivity($pdo,'modification_medicament',"Modif médicament #$id");
                    flash('success','Médicament modifié.');
                }
            }
        }
    }
    redirect('medicines.php');
}

// Filters
$q = trim($_GET['q'] ?? '');
$filter = $_GET['filter'] ?? '';
$catFilter = $_GET['category'] ?? '';
$where=" WHERE status!='archived' "; $params=[];
if($q!==''){ $where.=" AND (m.name LIKE ? OR m.code LIKE ? OR m.laboratory LIKE ?) "; $like="%$q%"; array_push($params,$like,$like,$like); }
if($catFilter) { $where.=" AND m.category_id=? "; $params[]=$catFilter; }
if($filter==='low'){ $where.=" AND m.quantity>0 AND m.quantity <= m.alert_threshold "; }
elseif($filter==='out'){ $where.=" AND m.quantity<=0 "; }
elseif($filter==='expired'){ $where.=" AND m.expiry_date < CURDATE() "; }
elseif($filter==='expiring'){ $where.=" AND m.expiry_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY) "; }

$page = max(1,(int)($_GET['page'] ?? 1)); $per=10; $off=($page-1)*$per;
$countStmt=$pdo->prepare("SELECT COUNT(*) FROM medicines m $where"); $countStmt->execute($params); $total=(int)$countStmt->fetchColumn(); $pages=ceil($total/$per);
$stmt=$pdo->prepare("SELECT m.*, c.name as cat_name, s.name as sup_name FROM medicines m LEFT JOIN categories c ON c.id=m.category_id LEFT JOIN suppliers s ON s.id=m.supplier_id $where ORDER BY m.id DESC LIMIT $per OFFSET $off");
$stmt->execute($params); $meds=$stmt->fetchAll();

// Mini stats for header
$lowCount=(int)$pdo->query("SELECT COUNT(*) FROM medicines WHERE quantity>0 AND quantity <= alert_threshold")->fetchColumn();
$outCount=(int)$pdo->query("SELECT COUNT(*) FROM medicines WHERE quantity<=0")->fetchColumn();
$expCount=(int)$pdo->query("SELECT COUNT(*) FROM medicines WHERE expiry_date < CURDATE()")->fetchColumn();

layout_header('Médicaments');
?>
<style>
.page-hero{background:linear-gradient(135deg,#0a2a5e 0%, #0b5ed7 100%); border-radius:18px; color:#fff; position:relative; overflow:hidden; border:1px solid rgba(255,255,255,.08)}
.page-hero::after{content:""; position:absolute; width:420px; height:420px; background:radial-gradient(circle, rgba(255,255,255,.12), transparent 70%); right:-80px; top:-100px}
.filter-card{border-radius:16px; border:1px solid var(--gray-200); box-shadow:var(--shadow); background:#fff}
.table-premium thead th{background:#f8fafc !important; font-size:10.5px; letter-spacing:.07em; padding:12px 14px}
.table-premium tbody td{padding:12px 14px; font-size:13.2px}
.med-icon{width:40px; height:40px; border-radius:10px; display:grid; place-items:center; font-size:14px; flex-shrink:0}
.modal-premium .modal-header{background:linear-gradient(135deg,#0a2a5e 0%, #0b5ed7 100%); color:#fff; border:none; padding:18px 22px}
.modal-premium .modal-header .btn-close{filter:invert(1)}
.section-label-mini{font-size:10px; letter-spacing:.14em; font-weight:700; color:var(--blue); text-transform:uppercase; margin-bottom:10px; display:flex; align-items:center; gap:8px}
.section-label-mini::after{content:""; flex:1; height:1px; background:var(--gray-200)}
</style>

<!-- HERO -->
<div class="page-hero p-4 mb-4">
  <div class="d-flex flex-wrap justify-content-between gap-3 align-items-center" style="position:relative; z-index:1">
    <div class="d-flex gap-3 align-items-center">
      <div style="width:48px;height:48px;background:#fff;color:var(--blue-dark);border-radius:12px;display:grid;place-items:center;font-size:18px; box-shadow:0 8px 24px rgba(0,0,0,.18)"><i class="fa-solid fa-capsules"></i></div>
      <div>
        <h4 class="fw-bold mb-0" style="letter-spacing:-.02em">Médicaments</h4>
        <div class="small" style="opacity:.88"><?= $total ?> références • Catalogue complet • <span class="badge bg-white text-primary" style="border-radius:999px; font-size:10px"><?= $lowCount ?> faible • <?= $outCount ?> rupture • <?= $expCount ?> expirés</span></div>
      </div>
    </div>
    <div class="d-flex gap-2">
      <a href="stocks.php" class="btn btn-outline-light btn-sm" style="border-color:rgba(255,255,255,.35); border-radius:999px"><i class="fa-solid fa-boxes-stacked me-1"></i> Stocks</a>
      <button class="btn btn-light text-primary btn-sm fw-bold" style="border-radius:999px" data-bs-toggle="modal" data-bs-target="#medModal" onclick="openCreate()"><i class="fa-solid fa-plus me-1"></i> Nouveau médicament</button>
    </div>
  </div>
</div>

<!-- FILTERS -->
<div class="filter-card p-3 mb-3">
  <form class="row g-3 align-items-end">
    <div class="col-md-3">
      <label class="form-label small fw-semibold" style="font-size:11px; letter-spacing:.06em; color:var(--gray-500)">CATÉGORIE</label>
      <select name="category" class="form-select" style="border-radius:12px"><option value="">Toutes catégories</option><?php foreach($categories as $c): ?><option value="<?= $c['id'] ?>" <?= $catFilter==$c['id']?'selected':'' ?>><?= e($c['name']) ?></option><?php endforeach; ?></select>
    </div>
    <div class="col-md-3">
      <label class="form-label small fw-semibold" style="font-size:11px; letter-spacing:.06em; color:var(--gray-500)">STATUT STOCK</label>
      <select name="filter" class="form-select" style="border-radius:12px"><option value="">Tous statuts</option><option value="low" <?= $filter=='low'?'selected':'' ?>>Stock faible</option><option value="out" <?= $filter=='out'?'selected':'' ?>>Rupture</option><option value="expired" <?= $filter=='expired'?'selected':'' ?>>Expirés</option><option value="expiring" <?= $filter=='expiring'?'selected':'' ?>>Bientôt périmés</option></select>
    </div>
    <div class="col-md-2 d-flex gap-2">
      <button class="btn btn-primary flex-fill" style="border-radius:12px"><i class="fa-solid fa-filter me-1"></i> Filtrer</button>
      <a href="medicines.php" class="btn btn-light border" style="border-radius:12px"><i class="fa-solid fa-rotate"></i></a>
    </div>
  </form>
</div>

<!-- TABLE -->
<div class="card" style="border-radius:16px; overflow:hidden">
  <div class="table-responsive">
    <table class="table table-premium table-hover align-middle mb-0" id="mainTable">
      <thead><tr><th style="width:60px">ID</th><th>Médicament</th><th>Catégorie</th><th>Forme</th><th>Prix</th><th>Stock</th><th>Péremption</th><th>Statut</th><th class="text-end" style="width:130px">Actions</th></tr></thead>
      <tbody>
        <?php if(!$meds): ?><tr><td colspan="9" class="text-center py-5"><div class="empty-state py-2"><i class="fa-solid fa-capsules"></i><div class="small">Aucun médicament trouvé.</div><a href="medicines.php" class="btn btn-sm btn-light border mt-2" style="border-radius:999px">Réinitialiser</a></div></td></tr><?php endif; ?>
        <?php foreach($meds as $m): 
          $badge='available'; $label='Disponible'; $badgeColor='#10b981';
          if($m['status']=='expired' || ($m['expiry_date'] && $m['expiry_date']<date('Y-m-d'))){ $badge='expired'; $label='Expiré'; $badgeColor='#991b1b';}
          elseif($m['quantity']<=0){ $badge='out'; $label='Rupture'; $badgeColor='#ef4444';}
          elseif($m['quantity'] <= $m['alert_threshold']){ $badge='low'; $label='Stock faible'; $badgeColor='#d97706';}
          $pct = $m['alert_threshold']>0 ? min(100, round($m['quantity']/max(1,$m['alert_threshold'])*40)) : 100;
        ?>
        <tr>
          <td class="small text-muted fw-semibold">#<?= $m['id'] ?></td>
          <td>
            <div class="d-flex gap-2 align-items-center">
              <div class="med-icon" style="background:<?= $badge=='expired'?'#fee2e2':($badge=='out'?'#fee2e2':($badge=='low'?'#fff7e6':'#e8f0fe')) ?>; color:<?= $badge=='expired'?'#991b1b':($badge=='out'?'#991b1b':($badge=='low'?'#92400e':'#0b5ed7')) ?>"><i class="fa-solid fa-pills"></i></div>
              <div>
                <div class="fw-semibold" style="font-size:13.5px; line-height:1.1"><?= e($m['name']) ?></div>
                <div class="small text-muted" style="font-size:11px"><span class="badge bg-light border text-dark" style="font-size:10px"><?= e($m['code']) ?></span> • <?= e($m['laboratory'] ?: '—') ?></div>
              </div>
            </div>
          </td>
          <td><span class="badge bg-light border text-dark" style="border-radius:999px; font-size:11px; padding:6px 10px"><?= e($m['cat_name'] ?? '—') ?></span></td>
          <td class="small"><span class="fw-semibold"><?= e($m['form']) ?></span><br><span class="text-muted" style="font-size:11px"><?= e($m['dosage']) ?></span></td>
          <td>
            <div class="small text-muted" style="font-size:11px">Achat <?= money($m['purchase_price']) ?></div>
            <div class="fw-bold text-primary" style="font-size:13px"><?= money($m['sale_price']) ?></div>
          </td>
          <td>
            <div class="d-flex align-items-center gap-2">
              <span class="fw-bold" style="color:<?= $m['quantity']<=0?'#ef4444':($m['quantity']<=$m['alert_threshold']?'#d97706':'#0f172a') ?>"><?= $m['quantity'] ?></span>
              <small class="text-muted" style="font-size:11px">/ <?= $m['alert_threshold'] ?></small>
            </div>
            <div class="progress progress-thin mt-1" style="width:70px"><div class="progress-bar" style="width:<?= $pct ?>%; background:<?= $badgeColor ?>"></div></div>
          </td>
          <td class="small <?= $m['expiry_date']<date('Y-m-d')?'text-danger fw-bold':'' ?>" style="font-size:12px"><?= e($m['expiry_date'] ?? '—') ?><?php if($m['expiry_date'] && $m['expiry_date']>=date('Y-m-d')){ $d=(int)ceil((strtotime($m['expiry_date'])-time())/86400); if($d<=30) echo " <span class='badge bg-warning text-dark' style='font-size:10px'>$d j</span>"; } ?></td>
          <td><span class="badge-soft badge-<?= $badge ?>"><?= $label ?></span></td>
          <td class="text-end">
            <div class="d-flex gap-1 justify-content-end">
              <button class="btn btn-sm btn-light border" style="border-radius:999px; width:32px; height:32px; display:grid; place-items:center" onclick='openEdit(<?= json_encode($m) ?>)' title="Modifier"><i class="fa-solid fa-pen" style="font-size:11px"></i></button>
              <a href="movements.php?medicine=<?= $m['id'] ?>" class="btn btn-sm btn-light border" style="border-radius:999px; width:32px; height:32px; display:grid; place-items:center" title="Historique"><i class="fa-solid fa-clock" style="font-size:11px"></i></a>
              <form method="post" onsubmit="return confirm('Supprimer ce médicament ?')" class="d-inline"><?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= $m['id'] ?>"><button class="btn btn-sm btn-light border text-danger" style="border-radius:999px; width:32px; height:32px; display:grid; place-items:center"><i class="fa-solid fa-trash" style="font-size:11px"></i></button></form>
            </div>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php if($pages>1): ?>
  <div class="card-footer d-flex justify-content-between align-items-center bg-white">
    <small class="text-muted">Page <?= $page ?> / <?= $pages ?> — <?= $total ?> résultats</small>
    <div class="btn-group">
      <?php for($i=1;$i<=$pages;$i++): ?><a href="?q=<?= urlencode($q) ?>&filter=<?= e($filter) ?>&category=<?= e($catFilter) ?>&page=<?= $i ?>" class="btn btn-sm <?= $i==$page?'btn-primary':'btn-light border' ?>" style="border-radius:10px; margin-left:4px"><?= $i ?></a><?php endfor; ?>
    </div>
  </div>
  <?php endif; ?>
</div>

<!-- MODAL PREMIUM -->
<div class="modal fade" id="medModal" tabindex="-1">
  <div class="modal-dialog modal-lg modal-dialog-scrollable">
    <form method="post" class="modal-content modal-premium" style="border-radius:18px; overflow:hidden; border:none">
      <?= csrf_field() ?>
      <input type="hidden" name="action" id="formAction" value="create">
      <input type="hidden" name="id" id="fieldId">
      <div class="modal-header"><div class="d-flex gap-3 align-items-center"><div style="width:42px;height:42px;background:#fff;color:var(--blue-dark);border-radius:11px;display:grid;place-items:center"><i class="fa-solid fa-capsules"></i></div><div><h5 class="modal-title fw-bold mb-0" id="modalTitle" style="font-size:16px">Nouveau médicament</h5><small style="opacity:.8">Renseignez les informations du produit</small></div></div><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
      <div class="modal-body p-4" style="background:#f8fafc">
        <div class="section-label-mini"><i class="fa-solid fa-tag"></i> Identité produit</div>
        <div class="row g-3 mb-3">
          <div class="col-md-4"><label class="form-label small fw-semibold">Code *</label><input name="code" id="f_code" class="form-control bg-white" required placeholder="MED-013"></div>
          <div class="col-md-8"><label class="form-label small fw-semibold">Nom *</label><input name="name" id="f_name" class="form-control bg-white" required placeholder="Paracétamol 500mg"></div>
          <div class="col-md-6"><label class="form-label small">Catégorie</label><select name="category_id" id="f_cat" class="form-select bg-white"><option value="">— Choisir —</option><?php foreach($categories as $c): ?><option value="<?= $c['id'] ?>"><?= e($c['name']) ?></option><?php endforeach; ?></select></div>
          <div class="col-md-6"><label class="form-label small">Fournisseur</label><select name="supplier_id" id="f_sup" class="form-select bg-white"><option value="">— Choisir —</option><?php foreach($suppliers as $s): ?><option value="<?= $s['id'] ?>"><?= e($s['name']) ?></option><?php endforeach; ?></select></div>
          <div class="col-12"><label class="form-label small">Description</label><textarea name="description" id="f_desc" class="form-control bg-white" rows="2" placeholder="Indications, notes..."></textarea></div>
          <div class="col-md-4"><label class="form-label small">Dosage</label><input name="dosage" id="f_dosage" class="form-control bg-white" placeholder="500mg"></div>
          <div class="col-md-4"><label class="form-label small">Forme</label><select name="form" id="f_form" class="form-select bg-white"><option>Comprimé</option><option>Gélule</option><option>Sirop</option><option>Solution</option><option>Injectable</option><option>Pommade</option></select></div>
          <div class="col-md-4"><label class="form-label small">Laboratoire</label><input name="laboratory" id="f_lab" class="form-control bg-white" placeholder="Sanofi"></div>
        </div>
        <div class="section-label-mini"><i class="fa-solid fa-coins"></i> Commercial & stock</div>
        <div class="row g-3 mb-3">
          <div class="col-md-3"><label class="form-label small">Prix achat</label><input type="number" step="0.01" name="purchase_price" id="f_pa" class="form-control bg-white" required></div>
          <div class="col-md-3"><label class="form-label small">Prix vente</label><input type="number" step="0.01" name="sale_price" id="f_pv" class="form-control bg-white" required></div>
          <div class="col-md-3"><label class="form-label small">Quantité</label><input type="number" name="quantity" id="f_qty" class="form-control bg-white" required></div>
          <div class="col-md-3"><label class="form-label small">Seuil alerte</label><input type="number" name="alert_threshold" id="f_thresh" class="form-control bg-white" value="10"></div>
        </div>
        <div class="section-label-mini"><i class="fa-solid fa-calendar"></i> Traçabilité & emplacement</div>
        <div class="row g-3">
          <div class="col-md-4"><label class="form-label small">Date fabrication</label><input type="date" name="manufacture_date" id="f_mfg" class="form-control bg-white"></div>
          <div class="col-md-4"><label class="form-label small">Date expiration</label><input type="date" name="expiry_date" id="f_exp" class="form-control bg-white"></div>
          <div class="col-md-4"><label class="form-label small">Emplacement</label><input name="location" id="f_loc" class="form-control bg-white" placeholder="A1-R1"></div>
        </div>
      </div>
      <div class="modal-footer bg-white"><button type="button" class="btn btn-light border" style="border-radius:12px" data-bs-dismiss="modal">Annuler</button><button class="btn btn-primary" style="border-radius:12px; padding:10px 22px; font-weight:700"><i class="fa-solid fa-check me-1"></i> Enregistrer</button></div>
    </form>
  </div>
</div>

<script>
function openCreate(){
  document.getElementById('modalTitle').textContent='Nouveau médicament';
  document.getElementById('formAction').value='create';
  document.getElementById('fieldId').value='';
  ['f_code','f_name','f_desc','f_dosage','f_lab','f_pa','f_pv','f_qty','f_mfg','f_exp','f_loc'].forEach(id=>{let e=document.getElementById(id); if(e) e.value='';});
  document.getElementById('f_thresh').value=10;
  document.getElementById('f_cat').value=''; document.getElementById('f_sup').value='';
}
function openEdit(m){
  document.getElementById('modalTitle').textContent='Modifier — '+m.name;
  document.getElementById('formAction').value='update';
  document.getElementById('fieldId').value=m.id;
  document.getElementById('f_code').value=m.code;
  document.getElementById('f_name').value=m.name;
  document.getElementById('f_cat').value=m.category_id||'';
  document.getElementById('f_sup').value=m.supplier_id||'';
  document.getElementById('f_desc').value=m.description||'';
  document.getElementById('f_dosage').value=m.dosage||'';
  document.getElementById('f_form').value=m.form||'Comprimé';
  document.getElementById('f_lab').value=m.laboratory||'';
  document.getElementById('f_pa').value=m.purchase_price;
  document.getElementById('f_pv').value=m.sale_price;
  document.getElementById('f_qty').value=m.quantity;
  document.getElementById('f_thresh').value=m.alert_threshold;
  document.getElementById('f_mfg').value=m.manufacture_date||'';
  document.getElementById('f_exp').value=m.expiry_date||'';
  document.getElementById('f_loc').value=m.location||'';
  new bootstrap.Modal(document.getElementById('medModal')).show();
}
</script>
<?php layout_footer(); ?>
