<?php
require_once __DIR__.'/config/database.php';
requireLogin();
requireRole(['admin']);
require_once __DIR__.'/includes/partials/layout.php';
if($_SERVER['REQUEST_METHOD']==='POST'){
 if(!verify_csrf($_POST['csrf_token'] ?? '')) flash('error','CSRF invalide');
 else {
  $a=$_POST['action'] ?? '';
  if($a==='delete'){ if((int)$_POST['id']===$_SESSION['user']['id']) flash('error','Vous ne pouvez pas vous supprimer.'); else { $pdo->prepare("DELETE FROM users WHERE id=?")->execute([(int)$_POST['id']]); flash('success','Utilisateur supprimé.'); } }
  else {
    $name=trim($_POST['name']); $email=trim($_POST['email']); $role=$_POST['role']; $active=(int)($_POST['is_active'] ?? 1);
    if($a==='create'){
      $pass=password_hash($_POST['password'] ?? 'password123', PASSWORD_DEFAULT);
      try{ $pdo->prepare("INSERT INTO users (name,email,password,role,is_active) VALUES (?,?,?,?,?)")->execute([$name,$email,$pass,$role,$active]); flash('success','Utilisateur créé.'); }catch(PDOException $e){ flash('error','Email déjà utilisé.');}
    } else {
      $id=(int)$_POST['id']; $sql="UPDATE users SET name=?,email=?,role=?,is_active=? WHERE id=?"; $params=[$name,$email,$role,$active,$id];
      if(!empty($_POST['password'])){ $pdo->prepare("UPDATE users SET password=? WHERE id=?")->execute([password_hash($_POST['password'],PASSWORD_DEFAULT),$id]);}
      $pdo->prepare($sql)->execute($params); flash('success','Utilisateur modifié.');
    }
  }
 }
 redirect('users.php');
}
$users=$pdo->query("SELECT * FROM users ORDER BY id DESC")->fetchAll();
layout_header('Utilisateurs');
?>
<style>
.users-hero{background:linear-gradient(135deg,#0a2a5e 0%, #334155 100%); border-radius:18px; color:#fff; position:relative; overflow:hidden}
.users-hero::after{content:""; position:absolute; width:360px; height:360px; background:radial-gradient(circle, rgba(255,255,255,.1), transparent 70%); right:-40px; top:-80px}
.role-badge{padding:6px 10px; border-radius:999px; font-size:11px; font-weight:700; letter-spacing:.03em}
</style>
<div class="users-hero p-4 mb-4">
  <div class="d-flex flex-wrap justify-content-between gap-3 align-items-center" style="position:relative; z-index:1">
    <div class="d-flex gap-3 align-items-center">
      <div style="width:48px;height:48px;background:#fff;color:#0a2a5e;border-radius:12px;display:grid;place-items:center;font-size:18px"><i class="fa-solid fa-user-gear"></i></div>
      <div>
        <h4 class="fw-bold mb-0">Utilisateurs & Rôles</h4>
        <div class="small" style="opacity:.88"><?= count($users) ?> comptes • Permissions granulaires • Sécurité bcrypt</div>
      </div>
    </div>
    <button class="btn btn-light text-primary btn-sm fw-bold" style="border-radius:999px" data-bs-toggle="modal" data-bs-target="#uModal" onclick="openCreate()"><i class="fa-solid fa-plus me-1"></i> Nouvel utilisateur</button>
  </div>
</div>

<div class="row g-3 mb-3">
  <?php
    $counts=array_count_values(array_column($users,'role'));
    foreach(['admin'=>'Admin','pharmacien'=>'Pharmacien','caissier'=>'Caissier','gestionnaire_stock'=>'Stock'] as $k=>$label): ?>
  <div class="col-6 col-md-3"><div class="card text-center" style="border-radius:14px"><div class="card-body p-3"><div class="small text-muted" style="font-size:11px; letter-spacing:.06em"><?= e($label) ?></div><div class="fw-bold" style="font-size:20px"><?= $counts[$k] ?? 0 ?></div></div></div></div>
  <?php endforeach; ?>
</div>

<div class="card" style="border-radius:16px; overflow:hidden">
  <div class="table-responsive"><table class="table table-hover mb-0">
    <thead><tr><th>Utilisateur</th><th>Email</th><th>Rôle</th><th>Statut</th><th>Créé</th><th class="text-end">Actions</th></tr></thead>
    <tbody>
      <?php foreach($users as $u):
        $roleColor = $u['role']=='admin'?'#0a2a5e':($u['role']=='pharmacien'?'#0b5ed7':($u['role']=='caissier'?'#065f46':'#92400e'));
        $roleBg = $u['role']=='admin'?'#e8f0fe':($u['role']=='pharmacien'?'#e8f0fe':($u['role']=='caissier'?'#e6f7ef':'#fff7e6'));
      ?>
      <tr>
        <td><div class="d-flex gap-2 align-items-center"><div style="width:38px;height:38px;border-radius:11px;background:<?= $roleBg ?>;color:<?= $roleColor ?>;display:grid;place-items:center;font-weight:800"><?= strtoupper(substr($u['name'],0,1)) ?></div><div><div class="fw-semibold" style="font-size:13.5px"><?= e($u['name']) ?></div><small class="text-muted">#<?= $u['id'] ?></small></div></div></td>
        <td class="small"><?= e($u['email']) ?></td>
        <td><span class="role-badge" style="background:<?= $roleBg ?>; color:<?= $roleColor ?>; border:1px solid rgba(0,0,0,.06)"><?= e($u['role']) ?></span></td>
        <td><?= $u['is_active']?'<span class="badge bg-success" style="border-radius:999px; font-size:11px"><i class="fa-solid fa-circle me-1" style="font-size:7px"></i>Actif</span>':'<span class="badge bg-secondary" style="border-radius:999px">Inactif</span>' ?></td>
        <td class="small text-muted"><?= date('d/m/Y', strtotime($u['created_at'])) ?></td>
        <td class="text-end">
          <div class="d-flex gap-1 justify-content-end">
            <button class="btn btn-sm btn-light border" style="border-radius:999px; width:32px; height:32px; display:grid; place-items:center" onclick='openEdit(<?= json_encode($u) ?>)'><i class="fa-solid fa-pen" style="font-size:11px"></i></button>
            <form method="post" onsubmit="return confirm('Supprimer cet utilisateur ?')" class="d-inline"><?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= $u['id'] ?>"><button class="btn btn-sm btn-light border text-danger" style="border-radius:999px; width:32px; height:32px; display:grid; place-items:center"><i class="fa-solid fa-trash" style="font-size:11px"></i></button></form>
          </div>
        </td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table></div>
</div>

<div class="modal fade" id="uModal" tabindex="-1">
 <div class="modal-dialog"><form method="post" class="modal-content" style="border-radius:18px; overflow:hidden; border:none"><?= csrf_field() ?><input type="hidden" name="action" id="uAct" value="create"><input type="hidden" name="id" id="uId">
  <div class="modal-header" style="background:linear-gradient(135deg,#0a2a5e 0%, #334155 100%); color:#fff; border:none"><h5 class="modal-title fw-bold" id="uTitle" style="font-size:15px"><i class="fa-solid fa-user-plus me-2"></i>Nouvel utilisateur</h5><button class="btn-close" style="filter:invert(1)" data-bs-dismiss="modal"></button></div>
  <div class="modal-body row g-3 p-4" style="background:#f8fafc">
    <div class="col-12"><label class="form-label small fw-semibold">Nom *</label><input name="name" id="uName" class="form-control bg-white" style="border-radius:12px" required></div>
    <div class="col-12"><label class="form-label small fw-semibold">Email *</label><input type="email" name="email" id="uEmail" class="form-control bg-white" style="border-radius:12px" required></div>
    <div class="col-md-6"><label class="form-label small">Rôle</label><select name="role" id="uRole" class="form-select bg-white" style="border-radius:12px"><option value="admin">Admin</option><option value="pharmacien">Pharmacien</option><option value="caissier">Caissier</option><option value="gestionnaire_stock">Gestionnaire stock</option></select></div>
    <div class="col-md-6"><label class="form-label small">Statut</label><select name="is_active" id="uActive" class="form-select bg-white" style="border-radius:12px"><option value="1">Actif</option><option value="0">Inactif</option></select></div>
    <div class="col-12"><label class="form-label small">Mot de passe <small class="text-muted">(laisser vide si inchangé)</small></label><input type="password" name="password" id="uPass" class="form-control bg-white" style="border-radius:12px" placeholder="••••••••"></div>
  </div>
  <div class="modal-footer bg-white"><button type="button" class="btn btn-light border" style="border-radius:12px" data-bs-dismiss="modal">Annuler</button><button class="btn btn-primary" style="border-radius:12px">Enregistrer</button></div>
 </form></div>
</div>
<script>
function openCreate(){ document.getElementById('uTitle').innerHTML='<i class="fa-solid fa-user-plus me-2"></i>Nouvel utilisateur'; document.getElementById('uAct').value='create'; document.getElementById('uId').value=''; ['uName','uEmail','uPass'].forEach(i=>document.getElementById(i).value=''); document.getElementById('uRole').value='pharmacien';}
function openEdit(u){ document.getElementById('uTitle').textContent='Modifier — '+u.name; document.getElementById('uAct').value='update'; document.getElementById('uId').value=u.id; document.getElementById('uName').value=u.name; document.getElementById('uEmail').value=u.email; document.getElementById('uRole').value=u.role; document.getElementById('uActive').value=u.is_active; document.getElementById('uPass').value=''; new bootstrap.Modal(document.getElementById('uModal')).show();}
</script>
<?php layout_footer(); ?>
