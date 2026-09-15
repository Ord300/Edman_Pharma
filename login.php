<?php
require_once __DIR__.'/config/database.php';
if(isLoggedIn()) redirect('dashboard.php');

$error='';
if($_SERVER['REQUEST_METHOD']==='POST'){
    if(!verify_csrf($_POST['csrf_token'] ?? '')) $error="Token CSRF invalide.";
    else {
        $email=trim($_POST['email'] ?? '');
        $pass=$_POST['password'] ?? '';
        $remember = isset($_POST['remember']);
        $stmt=$pdo->prepare("SELECT * FROM users WHERE email=? LIMIT 1");
        $stmt->execute([$email]);
        $u=$stmt->fetch();
        if($u && password_verify($pass,$u['password'])){
            if(!$u['is_active']){ $error="Compte désactivé. Contactez l'administrateur."; }
            else {
                $_SESSION['user']=['id'=>$u['id'],'name'=>$u['name'],'email'=>$u['email'],'role'=>$u['role']];
                logActivity($pdo,'connexion','Login '.$u['email']);
                // Caissier → commande, autres → dashboard
                if($u['role']==='caissier') redirect('commande.php'); else redirect('dashboard.php');
            }
        } else $error="Identifiants incorrects.";
    }
}
?>
<!doctype html>
<html lang="fr">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Connexion — Boyambi Pharmacy</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link href="assets/css/style.css" rel="stylesheet">
<style>
  .login-page{min-height:100vh; background:#071f49; overflow:hidden}
  .login-scene{min-height:100vh; position:relative; display:grid; place-items:center; padding:28px 16px; isolation:isolate; background:linear-gradient(110deg,rgba(4,23,58,.88),rgba(7,48,108,.63)),url('assets/images/pharmacy-care.jpg') center/cover no-repeat}
  .login-scene::before,.login-scene::after{content:""; position:absolute; z-index:-1; border-radius:50%; border:1px solid rgba(255,255,255,.18)}
  .login-scene::before{width:540px;height:540px;right:-150px;top:-190px;box-shadow:0 0 0 70px rgba(255,255,255,.035),0 0 0 140px rgba(255,255,255,.025);animation:login-orbit 16s linear infinite}
  .login-scene::after{width:360px;height:360px;left:-130px;bottom:-130px;background:rgba(16,185,129,.16);filter:blur(2px);animation:login-float 8s ease-in-out infinite}
  .login-shell{width:min(100%,1040px); display:grid; grid-template-columns:1.02fr .98fr; align-items:stretch; border:1px solid rgba(255,255,255,.22); border-radius:28px; overflow:hidden; background:rgba(255,255,255,.13); box-shadow:0 28px 90px rgba(1,12,35,.45); backdrop-filter:blur(12px); animation:login-enter .7s both}
  .login-intro{padding:54px 48px; color:#fff; display:flex; flex-direction:column; justify-content:space-between; min-height:590px; background:linear-gradient(145deg,rgba(10,42,94,.7),rgba(11,94,215,.28))}
  .login-logo{width:50px;height:50px;border-radius:14px;background:#fff;color:var(--blue-dark);display:grid;place-items:center;font-size:21px;box-shadow:0 12px 24px rgba(0,0,0,.16)}
  .login-intro h1{font-size:clamp(30px,3.3vw,43px);letter-spacing:-.05em;line-height:1.03;font-weight:800;max-width:390px}
  .login-intro p{max-width:390px;line-height:1.7;color:rgba(255,255,255,.8)}
  .login-points{display:flex;flex-wrap:wrap;gap:10px}.login-points span{font-size:12px;background:rgba(255,255,255,.13);border:1px solid rgba(255,255,255,.18);border-radius:999px;padding:7px 11px}
  .login-card{background:rgba(255,255,255,.98); padding:48px; display:flex; flex-direction:column; justify-content:center}
  .login-card h2{font-weight:800;letter-spacing:-.035em;color:var(--gray-900)}
  .login-card .form-label{font-size:11px;letter-spacing:.08em;text-transform:uppercase;color:var(--gray-500)}
  .login-card .input-group{border:1px solid var(--gray-200);border-radius:12px;transition:.2s}.login-card .input-group:focus-within{border-color:var(--blue);box-shadow:0 0 0 4px var(--blue-light)}
  .login-card .input-group-text,.login-card .form-control{border:0!important;box-shadow:none!important;background:transparent}.login-card .form-control{padding:12px 0;font-size:14px}
  .login-submit{border-radius:12px!important;padding:13px 16px!important;background:var(--blue-dark)!important;border-color:var(--blue-dark)!important;box-shadow:0 10px 22px rgba(10,42,94,.2);transition:.2s}.login-submit:hover{transform:translateY(-2px);box-shadow:0 14px 28px rgba(10,42,94,.28)}
  @keyframes login-enter{from{opacity:0;transform:translateY(24px) scale(.98)}to{opacity:1;transform:translateY(0) scale(1)}}@keyframes login-orbit{to{transform:rotate(360deg)}}@keyframes login-float{50%{transform:translate(18px,-15px)}}
  @media(max-width:767.98px){.login-scene{padding:0}.login-shell{min-height:100vh;border-radius:0;grid-template-columns:1fr}.login-intro{display:none}.login-card{padding:32px 25px}.login-scene::before{width:340px;height:340px}}
  @media(prefers-reduced-motion:reduce){.login-scene *,.login-scene::before,.login-scene::after{animation:none!important;transition:none!important}}
</style>
</head>
<body class="login-page">
<main class="login-scene">
  <div class="login-shell">
    <section class="login-intro">
      <div>
        <a href="index.php" class="login-logo text-decoration-none mb-5"><i class="fa-solid fa-pills"></i></a>
        <div class="small text-white-50 text-uppercase fw-bold mb-3" style="letter-spacing:.16em">Boyambi Pharmacy</div>
        <h1>La gestion pharmaceutique, avec sérénité.</h1>
        <p class="mt-3 mb-4">Un espace professionnel simple et sûr pour suivre les stocks, les ventes et la traçabilité de votre pharmacie hospitalière.</p>
        <div class="login-points"><span><i class="fa-solid fa-circle-check me-1 text-success"></i> Temps réel</span><span><i class="fa-solid fa-shield-halved me-1 text-info"></i> Sécurisé</span><span><i class="fa-solid fa-chart-line me-1 text-warning"></i> Rapports</span></div>
      </div>
      <div class="small text-white-50">© <?= date('Y') ?> Boyambi Pharmacy · Kisangani, RDC</div>
    </section>
    <section class="login-card">
      <a href="index.php" class="d-flex d-md-none align-items-center gap-2 text-decoration-none text-dark mb-5"><span class="login-logo" style="width:38px;height:38px;font-size:16px;background:var(--blue-dark);color:#fff"><i class="fa-solid fa-pills"></i></span><strong style="font-size:13px;letter-spacing:.04em">BOYAMBI PHARMACY</strong></a>
      <div class="mb-4"><h2 class="h3 mb-2">Bienvenue</h2><p class="text-muted small mb-0">Connectez-vous à votre espace professionnel.</p></div>
      <form method="post" novalidate>
            <?= csrf_field() ?>
            <?php if($error): ?><div class="alert alert-danger py-2 small mb-4" style="border-radius:12px"><i class="fa-solid fa-triangle-exclamation me-2"></i><?= e($error) ?></div><?php endif; ?>
            <div class="mb-3">
              <label class="form-label small fw-semibold" style="font-size:11px; letter-spacing:.06em">Adresse e-mail</label>
              <div class="input-group">
                <span class="input-group-text"><i class="fa-solid fa-envelope text-muted"></i></span>
                <input type="email" name="email" class="form-control" placeholder="vous@boyambi.cd" required value="<?= e($_POST['email'] ?? '') ?>" autocomplete="email">
              </div>
            </div>
            <div class="mb-3">
              <label class="form-label small fw-semibold" style="font-size:11px; letter-spacing:.06em">Mot de passe</label>
              <div class="input-group">
                <span class="input-group-text"><i class="fa-solid fa-lock text-muted"></i></span>
                <input type="password" name="password" class="form-control" placeholder="••••••••" required id="pwd" autocomplete="current-password">
                <button type="button" class="btn btn-link text-muted px-3" id="passwordToggle" aria-label="Afficher le mot de passe"><i class="fa-solid fa-eye"></i></button>
              </div>
            </div>
            <div class="d-flex justify-content-between align-items-center mb-4">
              <label class="small d-flex align-items-center gap-2 m-0"><input type="checkbox" name="remember" id="remember" style="accent-color:var(--blue-dark)"> Se souvenir de moi</label>
              <a href="#" class="small text-decoration-none text-primary" onclick="alert('Contactez l\'administrateur.'); return false">Mot de passe oublié ?</a>
            </div>
            <button class="btn btn-primary login-submit w-100 fw-bold">Se connecter <i class="fa-solid fa-arrow-right ms-1"></i></button>
            <div class="text-center mt-3"><a href="index.php" class="small text-muted text-decoration-none"><i class="fa-solid fa-arrow-left me-1"></i> Retour à l'accueil</a></div>
            <div class="text-center small text-muted mt-4"><i class="fa-solid fa-lock me-1"></i> Connexion chiffrée & sécurisée</div>
      </form>
    </section>
  </div>
</main>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function(){
  const pwdInput = document.getElementById('pwd');
  const eyeBtn = document.getElementById('passwordToggle');
  if(eyeBtn){
    eyeBtn.addEventListener('click', function(){
      const newType = pwdInput.type === 'password' ? 'text' : 'password';
      pwdInput.type = newType;
      this.querySelector('i').className = newType === 'password' ? 'fa-solid fa-eye' : 'fa-solid fa-eye-slash';
    });
  }
});
</script>
</body>
</html>
