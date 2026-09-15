<?php
require_once __DIR__.'/config/database.php';
requireLogin();
require_once __DIR__.'/includes/partials/layout.php';
if($_SERVER['REQUEST_METHOD']==='POST' && ($_POST['action'] ?? '')==='password'){
 if(!verify_csrf($_POST['csrf_token'] ?? '')) flash('error','CSRF invalide');
 else {
  $cur=$_POST['current'] ?? ''; $new=$_POST['new'] ?? '';
  $u=$pdo->prepare("SELECT password FROM users WHERE id=?"); $u->execute([$_SESSION['user']['id']]); $hash=$u->fetchColumn();
  if(!password_verify($cur,$hash)) flash('error','Mot de passe actuel incorrect.');
  elseif(strlen($new)<6) flash('error','Nouveau mot de passe trop court (6 min).');
  else { $pdo->prepare("UPDATE users SET password=? WHERE id=?")->execute([password_hash($new,PASSWORD_DEFAULT), $_SESSION['user']['id']]); flash('success','Mot de passe mis à jour.'); logActivity($pdo,'changement_mdp','Changement mot de passe'); }
 }
 redirect('settings.php');
}
$user=$pdo->prepare("SELECT * FROM users WHERE id=?"); $user->execute([$_SESSION['user']['id']]); $me=$user->fetch();
layout_header('Paramètres');
?>
<style>
.set-hero{background:linear-gradient(135deg,#0a2a5e 0%, #0b5ed7 100%); border-radius:18px; color:#fff; position:relative; overflow:hidden}
.set-hero::after{content:""; position:absolute; width:360px; height:360px; background:radial-gradient(circle, rgba(255,255,255,.11), transparent 70%); right:-60px; top:-80px}
.profile-card{border-radius:16px; overflow:hidden; border:1px solid var(--gray-200); background:#fff}
</style>
<div class="set-hero p-4 mb-4">
  <div class="d-flex flex-wrap gap-3 align-items-center" style="position:relative; z-index:1">
    <div style="width:48px;height:48px;background:#fff;color:var(--blue-dark);border-radius:12px;display:grid;place-items:center;font-size:18px"><i class="fa-solid fa-gear"></i></div>
    <div>
      <h4 class="fw-bold mb-0">Paramètres</h4>
      <div class="small" style="opacity:.88">Profil • Sécurité • Informations pharmacie</div>
    </div>
    <span class="ms-auto badge bg-white text-primary d-none d-md-inline" style="border-radius:999px; padding:8px 12px">v1.0 PRO • Sécurisé</span>
  </div>
</div>

<div class="row g-4">
  <div class="col-lg-5">
    <div class="profile-card">
      <div style="height:90px; background:linear-gradient(135deg,#0a2a5e 0%, #3b82f6 100%)"></div>
      <div class="p-4 text-center" style="margin-top:-46px">
        <div style="width:72px;height:72px; background:#fff; color:var(--blue-dark); border-radius:50%; display:grid; place-items:center; font-weight:800; font-size:24px; margin:0 auto; border:4px solid #fff; box-shadow:0 8px 24px rgba(0,0,0,.18)"><?= strtoupper(substr($me['name'],0,1)) ?></div>
        <div class="fw-bold mt-2" style="font-size:18px"><?= e($me['name']) ?></div>
        <div class="small text-muted"><?= e($me['email']) ?></div>
        <span class="badge mt-2" style="border-radius:999px; background:#e8f0fe; color:var(--blue-dark); border:1px solid #cbd5e1; padding:6px 12px; text-transform:capitalize"><i class="fa-solid fa-shield-halved me-1"></i><?= e($me['role']) ?></span>
        <div class="small text-muted mt-3"><i class="fa-regular fa-calendar me-1"></i> Membre depuis <?= date('d F Y', strtotime($me['created_at'])) ?></div>
      </div>
    </div>

    <div class="card mt-4" style="border-radius:16px">
      <div class="card-header bg-white" style="border-radius:16px 16px 0 0"><i class="fa-solid fa-lock text-primary me-2"></i> Changer mon mot de passe</div>
      <div class="card-body p-4">
        <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="password">
          <div class="mb-3"><label class="form-label small fw-semibold">Mot de passe actuel</label><input type="password" name="current" class="form-control" style="border-radius:12px" required placeholder="••••••••"></div>
          <div class="mb-3"><label class="form-label small fw-semibold">Nouveau mot de passe</label><input type="password" name="new" class="form-control" style="border-radius:12px" required placeholder="Minimum 6 caractères"></div>
          <button class="btn btn-primary w-100" style="border-radius:12px; padding:10px; font-weight:700"><i class="fa-solid fa-key me-1"></i> Mettre à jour</button>
        </form>
      </div>
    </div>
  </div>

  <div class="col-lg-7">
    <div class="card" style="border-radius:16px">
      <div class="card-header bg-white"><i class="fa-solid fa-hospital text-primary me-2"></i> Informations pharmacie</div>
      <div class="card-body">
        <div class="row g-3">
          <div class="col-md-6"><div class="p-3" style="background:#f8fafc; border:1px solid var(--gray-200); border-radius:12px"><div class="small text-muted" style="font-size:11px; letter-spacing:.06em">NOM</div><div class="fw-semibold">Boyambi Pharmacy</div></div></div>
          <div class="col-md-6"><div class="p-3" style="background:#f8fafc; border:1px solid var(--gray-200); border-radius:12px"><div class="small text-muted" style="font-size:11px; letter-spacing:.06em">HÔPITAL</div><div class="fw-semibold">Hôpital Boyambi — Kisangani</div></div></div>
          <div class="col-md-6"><div class="p-3" style="background:#f8fafc; border:1px solid var(--gray-200); border-radius:12px"><div class="small text-muted" style="font-size:11px; letter-spacing:.06em">TÉLÉPHONE</div><div class="fw-semibold"><i class="fa-solid fa-phone text-primary me-1"></i> +243 81 000 0001</div></div></div>
          <div class="col-md-6"><div class="p-3" style="background:#f8fafc; border:1px solid var(--gray-200); border-radius:12px"><div class="small text-muted" style="font-size:11px; letter-spacing:.06em">EMAIL</div><div class="fw-semibold"><i class="fa-solid fa-envelope text-primary me-1"></i> contact@boyambi.cd</div></div></div>
          <div class="col-12"><div class="p-3" style="background:#e8f0fe; border:1px solid #cbd5e1; border-radius:12px"><div class="small text-muted" style="font-size:11px; letter-spacing:.06em">ADRESSE</div><div class="fw-semibold"><i class="fa-solid fa-location-dot text-primary me-1"></i> Avenue de l'Hôpital, Commune Makiso, Kisangani, RDC</div></div></div>
        </div>
        <div class="alert d-flex gap-2 mt-4" style="border-radius:12px; background:#e8f0fe; border:1px solid #cbd5e1; color:#0a2a5e"><i class="fa-solid fa-circle-info mt-1"></i><div class="small"><strong>Version 1.0.0 PRO</strong> • PHP 8+ • MySQL • PDO sécurisé • Bootstrap 5 • Chart.js • Responsive<br><span class="text-muted">Build production locale — XAMPP compatible</span></div></div>
      </div>
    </div>

    <div class="card mt-4" style="border-radius:16px">
      <div class="card-header bg-white"><i class="fa-solid fa-shield-halved text-success me-2"></i> Sécurité & conformité</div>
      <div class="card-body">
        <div class="row g-2">
          <div class="col-md-6 d-flex gap-2 align-items-center p-2" style="background:#e6f7ef; border-radius:10px"><i class="fa-solid fa-check text-success"></i><span class="small fw-semibold">Mots de passe bcrypt</span></div>
          <div class="col-md-6 d-flex gap-2 align-items-center p-2" style="background:#e8f0fe; border-radius:10px"><i class="fa-solid fa-check text-primary"></i><span class="small fw-semibold">Protection CSRF & XSS</span></div>
          <div class="col-md-6 d-flex gap-2 align-items-center p-2" style="background:#f8fafc; border:1px solid var(--gray-200); border-radius:10px"><i class="fa-solid fa-check text-success"></i><span class="small fw-semibold">Requêtes PDO préparées</span></div>
          <div class="col-md-6 d-flex gap-2 align-items-center p-2" style="background:#fff7e6; border-radius:10px"><i class="fa-solid fa-check" style="color:#d97706"></i><span class="small fw-semibold">Rôles & permissions</span></div>
          <div class="col-12 d-flex gap-2 align-items-center p-2" style="background:#f1f5f9; border-radius:10px"><i class="fa-solid fa-check text-success"></i><span class="small fw-semibold">Journal d'activité avec IP — traçabilité complète</span></div>
        </div>
      </div>
    </div>
  </div>
</div>
<?php layout_footer(); ?>
