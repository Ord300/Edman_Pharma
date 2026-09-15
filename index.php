<?php
require_once __DIR__.'/config/config.php';
if(isLoggedIn()) {
  // Les utilisateurs connectés peuvent accéder à la page d'accueil ou se diriger vers leur espace
}
?>
<!doctype html>
<html lang="fr">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Boyambi Pharmacy — Pharmacie hospitalière à Kisangani</title>
<meta name="description" content="Pharmacie de l'Hôpital Boyambi à Kisangani. Accès aux traitements essentiels, conseils pharmaceutiques et plateforme de gestion pour un service rapide et sécurisé.">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&family=Plus+Jakarta+Sans:wght@600;700;800&display=swap" rel="stylesheet">
<link href="assets/css/style.css" rel="stylesheet">
<style>
body{background:#f4f7fb; overflow-x:hidden}
.landing-nav{position:sticky; top:0; z-index:1030; background:rgba(255,255,255,.86); backdrop-filter:blur(16px) saturate(180%); border-bottom:1px solid rgba(226,232,240,.8)}
.nav-link{font-size:13.5px; font-weight:500; color:var(--gray-700) !important; padding:8px 14px !important; border-radius:999px}
.nav-link:hover{background:var(--gray-50); color:var(--gray-900) !important}
.nav-link.active{background:var(--blue-dark); color:#fff !important}
.hero-wrap{position:relative; overflow:hidden; color:#fff; background:linear-gradient(100deg,rgba(4,23,58,.95),rgba(7,48,108,.78)),url('assets/images/medicine-blisters.jpg') center/cover; border-bottom:0}
.hero-wrap::before{content:""; position:absolute; width:440px; height:440px; border:1px solid rgba(255,255,255,.18); border-radius:50%; right:-180px; top:48px; box-shadow:0 0 0 52px rgba(255,255,255,.04),0 0 0 104px rgba(255,255,255,.025); animation:orbit 14s linear infinite}
@keyframes orbit{to{transform:rotate(360deg)}}
.eyebrow{display:inline-flex; align-items:center; gap:8px; background:rgba(255,255,255,.12); border:1px solid rgba(255,255,255,.2); padding:6px 12px; border-radius:999px; font-size:12px; font-weight:600; box-shadow:var(--shadow); color:#fff; backdrop-filter:blur(8px)}
.eyebrow .dot{width:8px; height:8px; background:var(--green); border-radius:50%; box-shadow:0 0 0 6px rgba(16,185,129,.15); animation:pulse 2s infinite}
@keyframes pulse{0%{box-shadow:0 0 0 6px rgba(16,185,129,.15)}50%{box-shadow:0 0 0 9px rgba(16,185,129,.08)}100%{box-shadow:0 0 0 6px rgba(16,185,129,.15)}}
.hero-title{font-family:'Plus Jakarta Sans',Inter,sans-serif; font-weight:800; letter-spacing:-.04em; line-height:.92; font-size:clamp(32px,5vw,54px); color:#fff}
.hero-title span{color:#8ee9c0; -webkit-text-fill-color:#8ee9c0}
.hero-sub{font-size:16.5px; line-height:1.65; color:rgba(255,255,255,.78); max-width:520px}
.hero-sub strong{color:#fff}
.btn-cta-primary{background:var(--blue-dark); border:none; color:#fff; padding:14px 26px; border-radius:999px; font-weight:700; font-size:15px; box-shadow:0 10px 28px rgba(10,42,94,.22), 0 2px 8px rgba(10,42,94,.12); transition:.2s}
.btn-cta-primary:hover{background:#081f47; transform:translateY(-1px); box-shadow:0 14px 36px rgba(10,42,94,.28); color:#fff}
.btn-cta-ghost{background:rgba(255,255,255,.12); border:1px solid rgba(255,255,255,.28); padding:14px 22px; border-radius:999px; font-weight:600; color:#fff; transition:.2s; backdrop-filter:blur(8px)}
.btn-cta-ghost:hover{border-color:#fff; background:rgba(255,255,255,.2); color:#fff; transform:translateY(-1px)}
.btn-boutique{background:#10b981; border:none; color:#fff; padding:10px 18px; border-radius:999px; font-weight:700; font-size:13.5px; box-shadow:0 8px 20px rgba(16,185,129,.28); transition:.2s}
.btn-boutique:hover{background:#0e9a6b; color:#fff; transform:translateY(-1px)}
.trust-row{display:flex; flex-wrap:wrap; gap:16px; align-items:center; font-size:12.5px; color:rgba(255,255,255,.78); font-weight:500}
.trust-row i{color:var(--green)}
.hero-visual{position:relative; perspective:1200px}
.hero-visual::before{content:""; position:absolute; inset:10% 2% auto auto; width:88%; aspect-ratio:1; border-radius:50%; background:radial-gradient(circle,rgba(16,185,129,.14),transparent 65%); filter:blur(6px)}
.hero-slider{height:270px; border:1px solid rgba(255,255,255,.26); border-radius:22px; overflow:hidden; display:flex; box-shadow:0 22px 54px rgba(0,8,30,.3); background:#071f49}
.hero-slider__slide{flex:0 0 100%; background:center/cover no-repeat; animation:hero-slide 15s infinite ease-in-out}
.hero-slider__slide:nth-child(1){background-image:linear-gradient(rgba(4,23,58,.18),rgba(4,23,58,.18)),url('assets/images/medicine-blisters.jpg')}
.hero-slider__slide:nth-child(2){background-image:linear-gradient(rgba(4,23,58,.18),rgba(4,23,58,.18)),url('assets/images/medicine-selection.jpg')}
.hero-slider__slide:nth-child(3){background-image:linear-gradient(rgba(4,23,58,.18),rgba(4,23,58,.18)),url('assets/images/pharmacy-care.jpg')}
.hero-visual>.mockup-shell,.hero-visual>.floating-card{display:none!important}
@keyframes hero-slide{0%,27%{transform:translateX(0)}33%,60%{transform:translateX(-100%)}66%,94%{transform:translateX(-200%)}100%{transform:translateX(0)}}
.mockup-shell{background:#0f172a; border-radius:20px; padding:10px; box-shadow:0 24px 64px rgba(15,23,42,.18), 0 12px 24px rgba(15,23,42,.12); border:1px solid rgba(255,255,255,.08); transform:rotateY(-6deg) rotateX(4deg); transition:.5s}
.mockup-shell:hover{transform:rotateY(-2deg) rotateX(1deg)}
.mockup-top{display:flex; align-items:center; gap:8px; padding:10px 14px}
.mockup-dot{width:10px; height:10px; border-radius:50%}
.mockup-screen{background:#fff; border-radius:14px; overflow:hidden; border:1px solid #e2e8f0}
.floating-card{position:absolute; background:#fff; border:1px solid var(--gray-200); border-radius:14px; padding:12px 14px; box-shadow:0 12px 32px rgba(15,23,42,.12); display:flex; gap:10px; align-items:center; animation:float 6s ease-in-out infinite}
.floating-card.c1{right:-14px; top:18%; animation-delay:0s}
.floating-card.c2{left:-18px; bottom:14%; animation-delay:1.5s}
.floating-card.c3{right:6%; bottom:-12px; animation-delay:3s}
@keyframes float{0%,100%{transform:translateY(0)}50%{transform:translateY(-8px)}}
.logos-bar{border-top:1px solid var(--gray-200); border-bottom:1px solid var(--gray-200); background:#fff}
.logo-pill{height:38px; padding:0 16px; border:1px solid var(--gray-200); border-radius:999px; display:inline-flex; align-items:center; gap:8px; font-size:12px; font-weight:600; color:var(--gray-500); background:var(--gray-50)}
.section-label{font-size:11px; letter-spacing:.14em; font-weight:700; color:var(--blue); text-transform:uppercase}
.section-title{font-family:'Plus Jakarta Sans',sans-serif; font-weight:800; letter-spacing:-.03em; line-height:1; font-size:clamp(26px,3.5vw,38px)}
.feature-card{border:1px solid var(--gray-200); border-radius:16px; background:#fff; padding:22px; transition:.2s; height:100%}
.feature-card:hover{border-color:#cbd5e1; box-shadow:var(--shadow-lg); transform:translateY(-2px)}
.icon-box{width:44px; height:44px; border-radius:12px; display:grid; place-items:center; font-size:16px}
.step-number{width:36px; height:36px; border-radius:999px; background:var(--blue-dark); color:#fff; display:grid; place-items:center; font-weight:800; font-size:13px}
.platform-card{border:1px solid var(--gray-200); border-radius:16px; overflow:hidden; background:#fff; box-shadow:var(--shadow)}
.platform-card .pc-head{padding:14px 16px; border-bottom:1px solid var(--gray-200); display:flex; justify-content:space-between; align-items:center; background:var(--gray-50)}
.cta-final{background:linear-gradient(135deg,#0a2a5e 0%, #0b5ed7 60%, #3b82f6 100%); border-radius:24px; color:#fff; position:relative; overflow:hidden}
.cta-final::after{content:""; position:absolute; width:600px; height:600px; background:radial-gradient(circle, rgba(255,255,255,.12) 0%, transparent 70%); right:-120px; top:-200px}
.pharma-card{border:1px solid var(--gray-200); border-radius:16px; background:#fff; padding:20px; height:100%}
.photo-panel{height:280px; position:relative; overflow:hidden; background:#0a2a5e}
.photo-panel img{width:100%; height:100%; object-fit:cover; object-position:center; mix-blend-mode:luminosity; opacity:.94; transform:scale(1.04); transition:transform .8s ease}
.photo-panel:hover img{transform:scale(1.1)}
.photo-panel::after{content:""; position:absolute; inset:0; background:linear-gradient(135deg,rgba(7,35,79,.72),rgba(11,94,215,.16) 60%,rgba(16,185,129,.20))}
.photo-panel>div{z-index:1}
.reveal{animation:rise-in .7s both; animation-delay:var(--delay,0s)}
@keyframes rise-in{from{opacity:0; transform:translateY(20px)}to{opacity:1; transform:translateY(0)}}
@media (prefers-reduced-motion:reduce){*,*::before,*::after{animation-duration:.01ms!important; animation-iteration-count:1!important; transition-duration:.01ms!important}}
@media(max-width:992px){.mockup-shell{transform:none !important}.floating-card{position:static; animation:none; margin-top:12px} .hero-visual{display:grid; gap:12px}.hero-slider{height:220px}}
</style>
</head>
<body>

<!-- NAV -->
<nav class="landing-nav">
  <div class="container d-flex align-items-center justify-content-between py-3">
    <a href="index.php" class="d-flex align-items-center gap-3 text-decoration-none">
      <div class="brand-mark" style="width:42px; height:42px; background:var(--blue-dark); color:#fff; border-radius:11px; display:grid; place-items:center; font-size:18px"><i class="fa-solid fa-pills"></i></div>
      <div>
        <div style="font-weight:800; letter-spacing:.04em; line-height:1; font-size:15px; color:var(--gray-900)">BOYAMBI PHARMACY</div>
        <div style="font-size:10px; letter-spacing:.16em; color:var(--gray-500); font-weight:600">HÔPITAL • KISANGANI — RDC</div>
      </div>
    </a>
    <div class="d-none d-lg-flex align-items-center gap-1">
      <a href="#pharmacie" class="nav-link">Notre pharmacie</a>
      <a href="#plateforme" class="nav-link">Plateforme</a>
    </div>
    <div class="d-flex align-items-center gap-2">
      <a href="commande.php" class="btn btn-boutique d-none d-md-inline-flex"><i class="fa-solid fa-bag-shopping me-1"></i> Commander</a>
      <a href="commande.php" class="btn btn-boutique d-md-none" style="padding:9px 14px"><i class="fa-solid fa-bag-shopping"></i></a>
      <a href="login.php" class="btn btn-cta-primary py-2 px-4" style="font-size:13.5px">Se connecter</a>
    </div>
  </div>
</nav>

<!-- HERO -->
<section class="hero-wrap">
  <div class="container py-4 py-lg-4">
    <div class="row align-items-center g-4 py-1">
      <div class="col-lg-6 reveal" style="--delay:.08s">
        <div class="eyebrow mb-3">
          <span class="dot"></span>
          Pharmacie hospitalière • Hôpital Boyambi — Kisangani
          <span class="badge bg-success ms-2 d-none d-sm-inline" style="border-radius:999px; font-size:10px; padding:5px 8px">Ouvert 6j/7</span>
        </div>
        <h1 class="hero-title mb-3">
          Vos médicaments,<br>
          <span>sans attente.</span>
        </h1>
        <p class="hero-sub mb-4">
          Disponibilités vérifiées, retrait simple et conseil pharmaceutique à l’Hôpital Boyambi.
        </p>
        <div class="d-flex flex-wrap gap-3 mb-4">
          <a href="commande.php" class="btn btn-boutique" style="padding:14px 28px; font-size:15px"><i class="fa-solid fa-bag-shopping me-2"></i> Commander mes médicaments</a>
          <a href="#pharmacie" class="btn btn-cta-ghost"><i class="fa-solid fa-location-dot me-2"></i>Découvrir la pharmacie</a>
        </div>
        <div class="trust-row mb-4">
          <span><i class="fa-solid fa-check"></i> Disponibilité vérifiée</span>
          <span><i class="fa-solid fa-check"></i> Conseil inclus</span>
          <span><i class="fa-solid fa-check"></i> Retrait express</span>
        </div>
        <div class="d-flex align-items-center gap-3 d-none d-sm-flex">
          <div class="d-flex" style="margin-left:6px">
            <div style="width:32px;height:32px;border-radius:50%;background:#0a2a5e;color:#fff;display:grid;place-items:center;font-weight:800;font-size:12px;border:2px solid #fff;margin-left:-6px">B</div>
            <div style="width:32px;height:32px;border-radius:50%;background:#10b981;color:#fff;display:grid;place-items:center;font-weight:800;font-size:12px;border:2px solid #fff;margin-left:-6px">P</div>
            <div style="width:32px;height:32px;border-radius:50%;background:#f59e0b;color:#fff;display:grid;place-items:center;font-weight:800;font-size:12px;border:2px solid #fff;margin-left:-6px">S</div>
          </div>
          <div class="small">
            <div style="font-weight:700; font-size:13px">Une équipe à votre écoute</div>
          </div>
        </div>
      </div>

      <div class="col-lg-6 reveal" style="--delay:.22s">
        <div class="hero-visual">
          <div class="hero-slider" aria-label="Aperçu de nos services pharmaceutiques">
            <div class="hero-slider__slide"></div><div class="hero-slider__slide"></div><div class="hero-slider__slide"></div>
          </div>
          <!-- main mockup -->
          <div class="mockup-shell">
            <div class="mockup-top">
              <span class="mockup-dot" style="background:#ef4444"></span><span class="mockup-dot" style="background:#f59e0b"></span><span class="mockup-dot" style="background:#10b981"></span>
              <span class="ms-3 small text-white-50" style="font-size:11px; letter-spacing:.08em">BOYAMBI — PHARMACIE • SERVICE</span>
              <span class="ms-auto badge bg-white text-dark" style="border-radius:999px; font-size:10px"><i class="fa-solid fa-circle text-success me-1" style="font-size:7px"></i> Service actif</span>
            </div>
            <div class="mockup-screen">
              <div class="d-flex" style="min-height:340px">
                <!-- fake sidebar -->
                <div class="d-none d-sm-block" style="width:150px; background:#0a2a5e; padding:14px">
                  <div class="d-flex align-items-center gap-2 mb-3"><div style="width:26px;height:26px;background:#fff;border-radius:7px;display:grid;place-items:center;color:#0a2a5e"><i class="fa-solid fa-pills" style="font-size:12px"></i></div><strong style="color:#fff;font-size:11px">BOYAMBI</strong></div>
                  <div class="small" style="color:rgba(255,255,255,.6); font-size:10px; letter-spacing:.12em">PHARMACIE</div>
                  <div class="mt-2 d-grid gap-1">
                    <div style="background:#fff;color:#0a2a5e;padding:7px 10px;border-radius:8px;font-size:11px;font-weight:700"><i class="fa-solid fa-table-columns me-1"></i> Accueil</div>
                    <div style="color:rgba(255,255,255,.8);font-size:11px;padding:6px 10px"><i class="fa-solid fa-capsules me-1"></i> Médicaments</div>
                    <div style="color:rgba(255,255,255,.8);font-size:11px;padding:6px 10px"><i class="fa-solid fa-bag-shopping me-1"></i> Ma commande</div>
                    <div style="color:rgba(255,255,255,.8);font-size:11px;padding:6px 10px"><i class="fa-solid fa-hand-holding-medical me-1"></i> Conseils</div>
                  </div>
                </div>
                <!-- content -->
                <div class="flex-fill p-3" style="background:#f8fafc">
                  <div class="d-flex justify-content-between align-items-center mb-3">
                    <strong style="font-size:13px">Pharmacie Boyambi</strong>
                    <span class="badge bg-success" style="font-size:10px">Disponibilités à jour</span>
                  </div>
                  <div class="row g-2 mb-3">
                    <div class="col-6"><div style="background:#fff;border:1px solid #e2e8f0;border-radius:12px;padding:10px"><div class="small text-muted" style="font-size:10px">Traitements disponibles</div><div style="font-weight:800">850+</div><div class="small text-success" style="font-size:11px">Vérifiés aujourd'hui</div></div></div>
                    <div class="col-6"><div style="background:#e6f7ef;border:1px solid #a7f3d0;border-radius:12px;padding:10px"><div class="small text-muted" style="font-size:10px">Patients accompagnés</div><div style="font-weight:800">1 200+</div><div class="small text-success" style="font-size:11px">Ce mois-ci</div></div></div>
                    <div class="col-12"><div style="background:#fff;border:1px solid #e2e8f0;border-radius:12px;padding:8px 10px;display:flex;gap:10px;align-items:center"><div style="width:28px;height:28px;background:#0b5ed7;color:#fff;border-radius:8px;display:grid;place-items:center"><i class="fa-solid fa-hand-holding-heart" style="font-size:12px"></i></div><div class="flex-fill"><div style="height:6px;background:#e2e8f0;border-radius:999px;overflow:hidden"><div style="width:72%;height:100%;background:linear-gradient(90deg,#0b5ed7,#3b82f6)"></div></div><div class="small text-muted" style="font-size:10px">Satisfaction patients • 96% recommandent</div></div></div></div>
                  </div>
                  <div style="background:#fff;border:1px solid #e2e8f0;border-radius:12px;padding:10px">
                    <div class="d-flex justify-content-between small mb-2"><strong style="font-size:11px">Votre accompagnement</strong><span class="badge bg-primary" style="font-size:10px">3 étapes</span></div>
                    <div class="small d-flex gap-2 align-items-center mb-1"><span style="width:20px;height:20px;background:#e6f7ef;color:#065f46;border-radius:6px;display:grid;place-items:center"><i class="fa-solid fa-check" style="font-size:10px"></i></span> Accueil & écoute du besoin</div>
                    <div class="small d-flex gap-2 align-items-center"><span style="width:20px;height:20px;background:#e8f0fe;color:#0b5ed7;border-radius:6px;display:grid;place-items:center"><i class="fa-solid fa-pills" style="font-size:10px"></i></span> Vérification & remise avec conseils</div>
                  </div>
                </div>
              </div>
            </div>
          </div>
          <!-- floating -->
          <div class="floating-card c1 d-none d-lg-flex">
            <div class="icon-box" style="background:var(--green-light); color:var(--green)"><i class="fa-solid fa-file-circle-check"></i></div>
            <div><div style="font-weight:700; font-size:12px">Ordonnance prise en charge</div><div class="small text-muted" style="font-size:11px">Vérification par le pharmacien</div></div>
          </div>
          <div class="floating-card c2 d-none d-lg-flex">
            <div class="icon-box" style="background:#e8f0fe; color:var(--blue)"><i class="fa-solid fa-heart-pulse"></i></div>
            <div><div style="font-weight:700; font-size:12px">Suivi patient complet</div><div class="small text-muted" style="font-size:11px">Conseils & posologie</div></div>
          </div>
          <div class="floating-card c3 d-none d-lg-flex">
            <div class="icon-box" style="background:var(--orange-light); color:var(--orange)"><i class="fa-solid fa-boxes-stacked"></i></div>
            <div><div style="font-weight:700; font-size:12px">Médicaments vérifiés</div><div class="small text-muted" style="font-size:11px">Qualité & traçabilité</div></div>
          </div>
        </div>
      </div>
    </div>
  </div>
</section>

<!-- TRUST BAR -->
<section class="logos-bar py-3">
  <div class="container d-flex flex-wrap gap-2 align-items-center justify-content-between">
    <div class="small text-muted" style="font-weight:600">Une pharmacie au service de l'hôpital</div>
    <div class="d-flex flex-wrap gap-2">
      <span class="logo-pill"><i class="fa-solid fa-hospital text-primary"></i> Hôpital Boyambi</span>
      <span class="logo-pill"><i class="fa-solid fa-hand-holding-heart text-success"></i> Soins & humanité</span>
      <span class="logo-pill"><i class="fa-solid fa-capsules text-warning"></i> 850+ références</span>
      <span class="logo-pill"><i class="fa-solid fa-people-group text-primary"></i> Équipe dédiée</span>
      <span class="logo-pill"><i class="fa-solid fa-location-dot"></i> Makiso, Kisangani</span>
    </div>
  </div>
</section>

<!-- NOTRE PHARMACIE -->
<section id="pharmacie" class="container py-5">
  <div class="row g-5 align-items-center">
    <div class="col-lg-6">
      <div class="section-label mb-2">Notre pharmacie à Kisangani</div>
      <h2 class="section-title mb-3">Au service des patients,<br>chaque jour</h2>
      <p class="text-muted" style="line-height:1.7">
        Située au sein de l'Hôpital Boyambi à Makiso, notre pharmacie hospitalière est le lien essentiel entre la prescription médicale et le traitement du patient.
        Nous mettons à disposition les <strong style="color:var(--gray-900)">médicaments essentiels, génériques et spécialités</strong> avec un contrôle rigoureux de la qualité, des dates et des stocks.
      </p>
      <div class="row g-3 mt-1">
        <div class="col-sm-6">
          <div class="pharma-card">
            <div class="icon-box mb-2" style="background:#e8f0fe; color:var(--blue)"><i class="fa-solid fa-location-dot"></i></div>
            <div class="fw-bold small">Nous trouver</div>
            <div class="small text-muted">Hôpital Boyambi, Commune Makiso<br>Kisangani, Province de la Tshopo<br><a href="#contact" class="text-primary small">Voir sur la carte <i class="fa-solid fa-arrow-right" style="font-size:10px"></i></a></div>
          </div>
        </div>
        <div class="col-sm-6">
          <div class="pharma-card">
            <div class="icon-box mb-2" style="background:var(--green-light); color:var(--green)"><i class="fa-solid fa-clock"></i></div>
            <div class="fw-bold small">Horaires d'accueil</div>
            <div class="small text-muted">Lun – Sam : 07h30 – 17h30<br>Dimanche : garde urgences<br><span class="badge bg-success mt-1" style="font-size:10px">Accueil continu</span></div>
          </div>
        </div>
        <div class="col-12">
          <div class="pharma-card d-flex gap-3 align-items-center">
            <div class="icon-box" style="background:var(--orange-light); color:var(--orange)"><i class="fa-solid fa-user-doctor"></i></div>
            <div>
              <div class="fw-bold small">Une équipe pharmaceutique à votre écoute</div>
              <div class="small text-muted">Pharmaciens et préparateurs vous conseillent sur la posologie, les interactions et la conservation. Chaque dispensation est vérifiée.</div>
            </div>
          </div>
        </div>
      </div>
    </div>
    <div class="col-lg-6">
      <div class="position-relative">
        <div style="border-radius:20px; overflow:hidden; border:1px solid var(--gray-200); box-shadow:var(--shadow-lg); background:#f8fafc">
          <div class="photo-panel">
            <img src="assets/images/pharmacy-care.jpg" alt="Matériel de soin et médicaments" loading="lazy">
            <div style="position:absolute; bottom:14px; left:14px; right:14px; background:rgba(255,255,255,.96); border-radius:12px; padding:12px; display:flex; gap:10px; align-items:center">
              <div style="width:36px;height:36px; background:var(--green); color:#fff; border-radius:10px; display:grid; place-items:center"><i class="fa-solid fa-pills"></i></div>
              <div>
                <div style="font-weight:800; font-size:13px; line-height:1">Boyambi Pharmacy</div>
                <div class="small text-muted" style="font-size:11px">Pharmacie hospitalière • Depuis 2018 • Hôpital Boyambi</div>
              </div>
              <span class="ms-auto badge bg-light border text-dark" style="border-radius:999px; font-size:10px"><i class="fa-solid fa-shield-halved text-success me-1"></i> Agréée</span>
            </div>
          </div>
          <div class="p-3 bg-white">
            <div class="row g-2 small">
              <div class="col-4 text-center p-2" style="background:#f8fafc; border:1px solid #e2e8f0; border-radius:10px"><div class="fw-bold">850+</div><div class="text-muted" style="font-size:11px">Références</div></div>
              <div class="col-4 text-center p-2" style="background:#e6f7ef; border:1px solid #a7f3d0; border-radius:10px"><div class="fw-bold">1200+</div><div class="text-muted" style="font-size:11px">Patients / mois</div></div>
              <div class="col-4 text-center p-2" style="background:#fff7e6; border:1px solid #fde68a; border-radius:10px"><div class="fw-bold">98%</div><div class="text-muted" style="font-size:11px">Disponibilité</div></div>
            </div>
          </div>
        </div>
        <div class="d-none d-lg-flex" style="position:absolute; right:-12px; top:22%; background:#fff; border:1px solid var(--gray-200); border-radius:14px; padding:10px 12px; box-shadow:var(--shadow-lg); gap:10px; align-items:center">
          <div class="icon-box" style="background:#e6f7ef; color:var(--green); width:36px; height:36px"><i class="fa-solid fa-comments"></i></div>
          <div><div style="font-weight:700; font-size:12px">Conseil personnalisé</div><div class="small text-muted" style="font-size:11px">À chaque remise de traitement</div></div>
        </div>
      </div>
    </div>
  </div>
</section>

<!-- PLATEFORME -->
<section id="plateforme" class="container py-5">
  <div class="text-center mx-auto mb-5" style="max-width:700px">
    <div class="section-label mb-2">Plateforme de la pharmacie</div>
    <h2 class="section-title mb-3">Simple, sûre, utile.</h2>
    <p class="text-muted">Tout l’essentiel pour votre retrait.</p>
  </div>
  <div class="row g-4">
    <div class="col-md-4">
      <div class="feature-card">
        <div class="icon-box mb-3" style="background:#e8f0fe; color:var(--blue)"><i class="fa-solid fa-boxes-stacked"></i></div>
        <h5 class="fw-bold" style="font-size:16px">Disponibilité en temps réel</h5>
        <p class="small text-muted mb-0">Disponibilités et stocks actualisés.</p>
      </div>
    </div>
    <div class="col-md-4">
      <div class="feature-card" style="border-color:var(--blue); box-shadow:0 10px 30px rgba(11,94,215,.12)">
        <div class="icon-box mb-3" style="background:var(--green-light); color:var(--green)"><i class="fa-solid fa-hand-holding-medical"></i></div>
        <div class="badge bg-primary mb-2" style="border-radius:999px; font-size:10px; letter-spacing:.08em">AVANTAGE PATIENT</div>
        <h5 class="fw-bold" style="font-size:16px">Dispensation sécurisée</h5>
        <p class="small text-muted mb-0">Traitement contrôlé avant la remise.</p>
      </div>
    </div>
    <div class="col-md-4">
      <div class="feature-card">
        <div class="icon-box mb-3" style="background:var(--orange-light); color:var(--orange)"><i class="fa-solid fa-shield-halved"></i></div>
        <h5 class="fw-bold" style="font-size:16px">Suivi & confidentialité</h5>
        <p class="small text-muted mb-0">Vos informations restent protégées.</p>
      </div>
    </div>
  </div>
</section>

<!-- CTA final -->
<section class="container pb-5">
  <div class="cta-final p-4 p-md-5">
    <div class="row align-items-center g-4" style="position:relative; z-index:1">
      <div class="col-lg-8">
        <h2 class="fw-bold mb-2" style="letter-spacing:-.03em; line-height:1">Besoin d'un médicament aujourd'hui ?</h2>
        <p class="mb-0" style="opacity:.9">Parcourez la boutique, vérifiez la disponibilité et préparez votre retrait. Notre équipe vous accueille à l'Hôpital Boyambi.</p>
      </div>
      <div class="col-lg-4 text-lg-end d-flex flex-wrap gap-2 justify-content-lg-end">
        <a href="commande.php" class="btn btn-light text-primary fw-bold px-4 py-2" style="border-radius:999px"><i class="fa-solid fa-bag-shopping me-1"></i> Commander</a>
        <a href="login.php" class="btn btn-outline-light px-4 py-2" style="border-radius:999px">Espace pro</a>
      </div>
    </div>
  </div>
  <div id="contact" class="d-flex flex-wrap gap-2 justify-content-center small text-muted mt-3">
    <span><i class="fa-solid fa-location-dot me-1"></i> Hôpital Boyambi, Makiso, Kisangani</span>•
    <span><i class="fa-solid fa-phone me-1"></i> +243 81 000 0001</span>•
    <span><i class="fa-solid fa-envelope me-1"></i> contact@boyambi.cd</span>•
    <span><i class="fa-solid fa-clock me-1"></i> Lun–Sam 07h30–17h30</span>
  </div>
</section>

<footer class="border-top py-4" style="background:#fff">
  <div class="container d-flex flex-wrap justify-content-between gap-3 small">
    <div>
      <div class="fw-bold" style="letter-spacing:.04em">BOYAMBI PHARMACY</div>
      <div class="text-muted">© <?= date('Y') ?> Hôpital Boyambi — Kisangani, RDC • Pharmacie hospitalière • Tous droits réservés</div>
    </div>
    <div class="text-muted">
      <span class="me-3"><i class="fa-solid fa-location-dot me-1"></i> Makiso, Kisangani</span>
      <span class="me-3"><i class="fa-solid fa-phone me-1"></i> +243 81 000 0001</span>
      <span><i class="fa-solid fa-envelope me-1"></i> contact@boyambi.cd</span>
    </div>
  </div>
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
