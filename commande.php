<?php
require_once __DIR__.'/config/database.php';
requireLogin();
requireRole(['caissier']);

// Boutique : réservée au caissier uniquement
// Gestion commande
$successMsg = null;
$errorMsg = null;

$categories = $pdo->query("SELECT * FROM categories ORDER BY name")->fetchAll();
$medicines = $pdo->query("SELECT m.*, c.name as cat_name FROM medicines m LEFT JOIN categories c ON c.id=m.category_id WHERE m.status!='archived' ORDER BY m.quantity=0, m.expiry_date < CURDATE(), m.name")->fetchAll();

if($_SERVER['REQUEST_METHOD']==='POST' && ($_POST['action'] ?? '')==='create_order'){
    if(!verify_csrf($_POST['csrf_token'] ?? '')){
        $errorMsg = "Session expirée, veuillez réessayer.";
    } else {
        $customer_name = trim($_POST['customer_name'] ?? '');
        $customer_phone = trim($_POST['customer_phone'] ?? '');
        $payment = $_POST['payment_method'] ?? 'especes';
        $discountGlobal = (float)($_POST['discount_global'] ?? 0);
        $itemsJson = $_POST['items_json'] ?? '[]';
        $items = json_decode($itemsJson, true);
        if(!$items || count($items)==0){
            $errorMsg = "Votre panier est vide.";
        } else {
            // Validation stock & péremption
            $errors=[];
            foreach($items as $it){
                $stmt=$pdo->prepare("SELECT quantity, expiry_date, sale_price, name FROM medicines WHERE id=?");
                $stmt->execute([(int)$it['id']]);
                $med=$stmt->fetch();
                if(!$med) $errors[]="Médicament #".(int)$it['id']." introuvable";
                elseif($med['expiry_date'] && $med['expiry_date'] < date('Y-m-d')) $errors[]=$med['name']." est indisponible (date dépassée)";
                elseif($med['quantity'] < (int)$it['qty']) $errors[]=$med['name']." stock insuffisant (".$med['quantity']." disponible)";
            }
            if($errors){
                $errorMsg = implode(' • ', $errors);
            } else {
                // Gestion client : créer si nom fourni et non existant
                $customer_id = null;
                if($customer_name !== ''){
                    // Recherche par téléphone si fourni
                    if($customer_phone !== ''){
                        $stmt=$pdo->prepare("SELECT id FROM customers WHERE phone=? LIMIT 1");
                        $stmt->execute([$customer_phone]);
                        $customer_id = $stmt->fetchColumn();
                    }
                    if(!$customer_id){
                        $stmt=$pdo->prepare("INSERT INTO customers (full_name, phone, address) VALUES (?,?,?)");
                        $stmt->execute([$customer_name, $customer_phone ?: null, trim($_POST['customer_address'] ?? '') ?: null]);
                        $customer_id = $pdo->lastInsertId();
                    }
                }
                $pdo->beginTransaction();
                try{
                    $subtotal=0;
                    foreach($items as $it) $subtotal += (float)$it['price'] * (int)$it['qty'] - (float)($it['discount'] ?? 0);
                    $total = max(0, $subtotal - $discountGlobal);
                    $invNum = generateInvoiceNumber($pdo);
                    $userId = $_SESSION['user']['id'] ?? null;
                    $stmt=$pdo->prepare("INSERT INTO sales (invoice_number,customer_id,user_id,subtotal,discount,total,payment_method) VALUES (?,?,?,?,?,?,?)");
                    $stmt->execute([$invNum,$customer_id,$userId,$subtotal,$discountGlobal,$total,$payment]);
                    $saleId=$pdo->lastInsertId();
                    foreach($items as $it){
                        $lineSub = (float)$it['price']*(int)$it['qty'] - (float)($it['discount']??0);
                        $pdo->prepare("INSERT INTO sale_items (sale_id,medicine_id,quantity,unit_price,discount,subtotal) VALUES (?,?,?,?,?,?)")->execute([$saleId,$it['id'],$it['qty'],$it['price'],$it['discount']??0,$lineSub]);
                        $old=$pdo->prepare("SELECT quantity FROM medicines WHERE id=?"); $old->execute([$it['id']]); $oldQty=(int)$old->fetchColumn();
                        $new=$oldQty - (int)$it['qty'];
                        $pdo->prepare("UPDATE medicines SET quantity=?, status=CASE WHEN ?<=0 THEN 'out' WHEN ? <= alert_threshold THEN 'low' ELSE 'available' END WHERE id=?")->execute([$new,$new,$new,$it['id']]);
                        $pdo->prepare("INSERT INTO stock_movements (medicine_id,type,quantity,old_stock,new_stock,user_id,reason) VALUES (?,?,?,?,?,?,?)")->execute([$it['id'],'sortie',(int)$it['qty'],$oldQty,$new,$userId,'Commande boutique '.$invNum]);
                    }
                    $pdo->prepare("INSERT INTO invoices (invoice_number,sale_id,customer_id,total) VALUES (?,?,?,?)")->execute([$invNum,$saleId,$customer_id,$total]);
                    $iid=$pdo->lastInsertId();
                    foreach($items as $it){
                        $stmtName=$pdo->prepare("SELECT name FROM medicines WHERE id=?"); $stmtName->execute([$it['id']]); $mn=$stmtName->fetchColumn();
                        $pdo->prepare("INSERT INTO invoice_items (invoice_id,medicine_name,quantity,unit_price,subtotal) VALUES (?,?,?,?,?)")->execute([$iid,$mn,$it['qty'],$it['price'], (float)$it['price']*(int)$it['qty'] - (float)($it['discount']??0)]);
                    }
                    $pdo->prepare("INSERT INTO payments (sale_id,amount,method) VALUES (?,?,?)")->execute([$saleId,$total,$payment]);
                    $pdo->commit();
                    logActivity($pdo,'vente_boutique',"Commande boutique $invNum total $total");
                    // Redirection vers facture
                    header("Location: invoice.php?num=$invNum");
                    exit;
                }catch(Exception $e){ $pdo->rollBack(); $errorMsg='Erreur commande: '.$e->getMessage(); }
            }
        }
    }
}

?>
<!doctype html>
<html lang="fr">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Commande — Boyambi Pharmacy</title>
<meta name="description" content="Boutique en ligne de la Pharmacie Boyambi, Hôpital Boyambi Kisangani. Parcourez nos médicaments, vérifiez la disponibilité et commandez en quelques clics.">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=Plus+Jakarta+Sans:wght@700;800&display=swap" rel="stylesheet">
<link href="assets/css/style.css" rel="stylesheet">
<style>
body{background:#edf2f8}
.landing-nav{position:sticky; top:0; z-index:1030; background:rgba(255,255,255,.86); backdrop-filter:blur(16px) saturate(180%); border-bottom:1px solid rgba(226,232,240,.8)}
.nav-link{font-size:13.5px; font-weight:500; color:var(--gray-700) !important; padding:8px 14px !important; border-radius:999px}
.nav-link:hover{background:var(--gray-50);color:var(--gray-900)!important}.nav-link.active{background:var(--blue-dark); color:#fff !important}
.hero-boutique{background:linear-gradient(105deg,rgba(4,23,58,.96),rgba(7,48,108,.74)),url('assets/images/medicine-selection.jpg') center/cover; color:#fff; position:relative; overflow:hidden; min-height:330px; display:flex; align-items:center}
.hero-boutique::after{content:""; position:absolute; width:600px; height:600px; border:1px solid rgba(255,255,255,.16); border-radius:50%; box-shadow:0 0 0 70px rgba(255,255,255,.035),0 0 0 140px rgba(255,255,255,.02); right:-180px; top:-250px; animation:shop-orbit 16s linear infinite}
.hero-search{background:rgba(255,255,255,.95); border-radius:18px; padding:8px; box-shadow:0 16px 42px rgba(0,15,45,.32); border:1px solid rgba(255,255,255,.35)}
.hero-search input{border:none; box-shadow:none !important; font-size:15px}
.hero-search input:focus{box-shadow:none}
.btn-cta-primary{background:var(--blue-dark); border:none; color:#fff; padding:14px 26px; border-radius:999px; font-weight:700; font-size:15px; box-shadow:0 10px 28px rgba(10,42,94,.22), 0 2px 8px rgba(10,42,94,.12); transition:.2s}
.btn-cta-primary:hover{background:#081f47; transform:translateY(-1px); box-shadow:0 14px 36px rgba(10,42,94,.28); color:#fff}
.cat-pill{padding:8px 16px; border-radius:999px; border:1px solid rgba(10,42,94,.1); background:rgba(255,255,255,.75); font-size:13px; font-weight:600; cursor:pointer; transition:.15s; color:var(--gray-700); backdrop-filter:blur(8px)}
.cat-pill.active{background:var(--blue-dark); color:#fff; border-color:var(--blue-dark); box-shadow:0 8px 18px rgba(10,42,94,.22)}
.cat-pill:hover{transform:translateY(-1px); border-color:#cbd5e1}
.medicine-card{border:1px solid rgba(255,255,255,.75); border-radius:18px; background:rgba(255,255,255,.86); padding:14px; height:100%; display:flex; flex-direction:column; transition:.24s; position:relative; overflow:hidden; box-shadow:0 8px 22px rgba(15,23,42,.05); backdrop-filter:blur(10px)}
.medicine-card::before{content:"";position:absolute;inset:0;background:linear-gradient(145deg,rgba(255,255,255,.2),rgba(11,94,215,.04)),url('assets/images/medicine-blisters.jpg') right -55px bottom -75px/220px auto no-repeat;opacity:.18;pointer-events:none}.medicine-card>*{position:relative;z-index:1}
.medicine-card:hover{border-color:#0b5ed7; box-shadow:0 16px 38px rgba(11,94,215,.15); transform:translateY(-4px)}
.medicine-card.disabled{opacity:.58; background:#f8fafc; pointer-events:none}
.medicine-card .thumb{width:48px; height:48px; border-radius:14px; background:linear-gradient(145deg,rgba(11,94,215,.85),rgba(16,185,129,.85)),url('assets/images/medicine-blisters.jpg') center/cover; color:#fff; display:grid; place-items:center; flex-shrink:0; box-shadow:0 8px 16px rgba(11,94,215,.18)}
.price{font-weight:800; color:var(--blue-dark); letter-spacing:-.02em}
.stock-badge{font-size:10px; border-radius:999px; padding:5px 8px; font-weight:700; letter-spacing:.02em}
.cart-panel{position:sticky; top:84px; background:rgba(255,255,255,.94); border:1px solid rgba(255,255,255,.9); border-radius:20px; overflow:hidden; box-shadow:0 16px 40px rgba(15,23,42,.12); backdrop-filter:blur(12px)}
.cart-head{background:linear-gradient(135deg,#0a2a5e 0%, #0b5ed7 100%); color:#fff; padding:14px 16px; display:flex; align-items:center; justify-content:space-between}
.cart-list{max-height:380px; overflow:auto; padding:12px; background:#f8fafc}
.cart-item{background:#fff; border:1px solid #e2e8f0; border-radius:12px; padding:10px; margin-bottom:10px; display:flex; gap:10px; align-items:center}
.qty-btn{width:30px; height:30px; border-radius:999px; border:1px solid #e2e8f0; background:#fff; display:grid; place-items:center; font-weight:700; transition:.15s}
.qty-btn:hover{background:#f1f5f9}
.total-box{background:#f8fafc; border-top:1px solid #e2e8f0; padding:16px}
.trust-badges{font-size:12px; color:var(--gray-500); display:flex; gap:12px; flex-wrap:wrap}
.hero-boutique .trust-badges{color:rgba(255,255,255,.8)}
.trust-badges i{color:var(--green)}
@keyframes shop-orbit{to{transform:rotate(360deg)}}
@media(prefers-reduced-motion:reduce){.hero-boutique::after{animation:none}}
@media(max-width:992px){ .cart-panel{position:static} }
.offcanvas-cart{width:420px !important; max-width:92vw}
.shop-footer{background:#071f49;color:#fff;border:0!important}.shop-footer .text-muted{color:rgba(255,255,255,.64)!important}
</style>
</head>
<body>

<!-- NAV BOUTIQUE -->
<nav class="landing-nav">
  <div class="container d-flex align-items-center justify-content-between py-3">
    <a href="index.php" class="d-flex align-items-center gap-3 text-decoration-none">
      <div style="width:42px; height:42px; background:var(--blue-dark); color:#fff; border-radius:11px; display:grid; place-items:center; font-size:18px"><i class="fa-solid fa-pills"></i></div>
      <div>
        <div style="font-weight:800; letter-spacing:.04em; line-height:1; font-size:15px; color:var(--gray-900)">BOYAMBI PHARMACY</div>
        <div style="font-size:10px; letter-spacing:.16em; color:var(--gray-500); font-weight:600">COMMANDE • KISANGANI — RDC</div>
      </div>
    </a>
    <div class="d-none d-lg-flex align-items-center gap-1">
      <a href="index.php" class="nav-link">Accueil</a>
      <a href="commande.php" class="nav-link active"><i class="fa-solid fa-bag-shopping me-1"></i> Commander</a>
    </div>
    <div class="d-flex align-items-center gap-2">
      <a href="dashboard.php" class="btn btn-light border text-primary btn-sm fw-bold" style="border-radius:999px; font-size:11px"><i class="fa-solid fa-table-columns me-1"></i> Dashboard</a>
      <button class="btn btn-light border d-lg-none position-relative" data-bs-toggle="offcanvas" data-bs-target="#cartOffcanvas" style="border-radius:999px; width:42px; height:42px; display:grid; place-items:center">
        <i class="fa-solid fa-cart-shopping"></i><span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger" id="cartBadgeMobile">0</span>
      </button>
      <button class="btn btn-primary d-none d-lg-inline-flex position-relative" data-bs-toggle="offcanvas" data-bs-target="#cartOffcanvas" style="border-radius:999px; font-weight:700; padding:9px 18px">
        <i class="fa-solid fa-cart-shopping me-2"></i><span class="badge bg-white text-primary ms-2" style="border-radius:999px" id="cartBadgeDesktop">0</span>
      </button>
      <button class="btn btn-icon d-lg-none" data-bs-toggle="offcanvas" data-bs-target="#navOffcanvas" style="width:42px; height:42px; border-radius:999px; border:1px solid #e2e8f0; background:#fff; display:grid; place-items:center"><i class="fa-solid fa-bars"></i></button>
      <span class="badge bg-white text-primary" style="border-radius:999px; padding:7px 12px; font-size:12px"><i class="fa-solid fa-user me-1"></i><?= e($_SESSION['user']['name'] ?? '') ?></span>
      <a href="logout.php" class="btn btn-light border py-2 px-3" style="border-radius:999px; font-size:12px"><i class="fa-solid fa-right-from-bracket"></i></a>
    </div>
  </div>
</nav>

<!-- HERO -->
<section class="hero-boutique">
  <div class="container py-4 py-lg-5" style="position:relative; z-index:1">
    <div class="row align-items-center g-4">
      <div class="col-lg-6">
        <div class="badge bg-white text-primary mb-3" style="border-radius:999px; padding:7px 12px; font-size:11px; letter-spacing:.06em"><i class="fa-solid fa-shield-halved me-1"></i> PHARMACIE HOSPITALIÈRE • SERVICE SÉCURISÉ</div>
        <h1 class="fw-bold mb-2" style="font-family:'Plus Jakarta Sans',sans-serif; letter-spacing:-.03em; line-height:1; font-size:clamp(28px,4vw,40px)">Commandez vos<br>médicaments en toute<br>sérénité</h1>
        <p class="mb-3" style="opacity:.92; font-size:15px; line-height:1.6">Parcourez notre catalogue, vérifiez la disponibilité en temps réel et préparez votre retrait à la pharmacie de l'Hôpital Boyambi.</p>
        <div class="trust-badges mb-3">
          <span><i class="fa-solid fa-check"></i> Disponibilité vérifiée</span>
          <span><i class="fa-solid fa-check"></i> Conseil pharmacien</span>
          <span><i class="fa-solid fa-check"></i> Retrait express</span>
        </div>
      </div>
      <div class="col-lg-6">
        <div class="hero-search d-flex align-items-center gap-2">
          <div class="flex-fill position-relative">
            <i class="fa-solid fa-magnifying-glass" style="position:absolute; left:14px; top:50%; transform:translateY(-50%); color:#94a3b8"></i>
            <input id="searchMed" class="form-control" style="padding-left:40px; height:48px; border-radius:12px; background:#f8fafc" placeholder="Rechercher un médicament, un dosage, un laboratoire...">
          </div>
          <select id="filterCat" class="form-select d-none d-md-block" style="max-width:190px; height:48px; border-radius:12px; background:#f8fafc; border:1px solid #e2e8f0">
            <option value="">Toutes catégories</option>
            <?php foreach($categories as $c): ?><option value="<?= e($c['name']) ?>"><?= e($c['name']) ?></option><?php endforeach; ?>
          </select>
          <button class="btn btn-primary" style="height:48px; border-radius:12px; padding:0 20px; font-weight:700" onclick="document.getElementById('grid').scrollIntoView({behavior:'smooth'})"><i class="fa-solid fa-arrow-down me-1"></i> Voir</button>
        </div>
        <div class="d-flex gap-2 mt-3 align-items-center small" style="opacity:.9">
          <i class="fa-solid fa-location-dot"></i> Retrait : Hôpital Boyambi, Makiso — <span class="badge bg-white text-primary" style="border-radius:999px">Ouvert Lun–Sam 07h30–17h30</span>
        </div>
      </div>
    </div>
  </div>
</section>

<!-- MAIN -->
<div class="container py-4">
  <?php if($errorMsg): ?><div class="alert alert-danger d-flex gap-2 align-items-center" style="border-radius:12px"><i class="fa-solid fa-triangle-exclamation"></i><?= e($errorMsg) ?></div><?php endif; ?>
  <?php if($successMsg): ?><div class="alert alert-success" style="border-radius:12px"><?= e($successMsg) ?></div><?php endif; ?>

  <div class="d-flex flex-wrap gap-2 mb-3" id="catPills">
    <button class="cat-pill active" data-cat="">Tous</button>
    <?php foreach($categories as $c): ?><button class="cat-pill" data-cat="<?= e($c['name']) ?>"><?= e($c['name']) ?></button><?php endforeach; ?>
  </div>
  <div class="d-flex justify-content-between align-items-center mb-3">
    <div class="small text-muted"><span id="countVisible"><?= count($medicines) ?></span> médicaments • Disponibilités à jour • <span class="text-success fw-semibold">Conseil inclus</span></div>
    <div class="d-none d-md-flex gap-2 small text-muted"><span><i class="fa-solid fa-truck me-1"></i> Retrait express</span><span>•</span><span><i class="fa-solid fa-shield-halved me-1"></i> Dispensation vérifiée</span></div>
  </div>

  <div class="row g-4">
    <!-- GRID -->
    <div class="col-lg-8" id="grid">
      <div class="row g-3" id="medGrid">
        <?php foreach($medicines as $m):
          $isExpired = $m['expiry_date'] && $m['expiry_date'] < date('Y-m-d');
          $isOut = (int)$m['quantity']<=0;
          $isLow = !$isOut && !$isExpired && (int)$m['quantity'] <= (int)$m['alert_threshold'];
          $disabled = $isExpired || $isOut;
          if($isExpired) $badge='<span class="stock-badge" style="background:#fee2e2; color:#991b1b; border:1px solid #fecaca"><i class="fa-solid fa-ban me-1"></i>Indisponible</span>';
          elseif($isOut) $badge='<span class="stock-badge" style="background:#f1f5f9; color:#334155; border:1px solid #e2e8f0"><i class="fa-solid fa-box-open me-1"></i>Rupture</span>';
          elseif($isLow) $badge='<span class="stock-badge" style="background:#fff7e6; color:#92400e; border:1px solid #fde68a"><i class="fa-solid fa-triangle-exclamation me-1"></i>Bientôt épuisé</span>';
          else $badge='<span class="stock-badge" style="background:#e6f7ef; color:#065f46; border:1px solid #a7f3d0"><i class="fa-solid fa-check me-1"></i>Disponible</span>';
          $expText = $m['expiry_date'] ? e($m['expiry_date']) : '—';
          $expClass = $isExpired ? 'text-danger fw-bold' : ($m['expiry_date'] && $m['expiry_date'] < date('Y-m-d', strtotime('+30 days')) ? 'text-warning fw-semibold' : 'text-muted');
        ?>
        <div class="col-12 col-md-6 med-card" data-name="<?= e(strtolower($m['name'].' '.$m['code'].' '.$m['laboratory'].' '.$m['dosage'].' '.$m['form'])) ?>" data-cat="<?= e($m['cat_name'] ?? '') ?>">
          <div class="medicine-card <?= $disabled?'disabled':'' ?>" data-id="<?= $m['id'] ?>" data-name="<?= e($m['name']) ?>" data-price="<?= $m['sale_price'] ?>" data-qty="<?= $m['quantity'] ?>" data-expiry="<?= e($m['expiry_date']) ?>">
            <div class="d-flex justify-content-between align-items-start mb-2">
              <span class="badge bg-light border text-dark" style="font-size:10px; border-radius:999px; padding:6px 8px; letter-spacing:.04em"><?= e($m['code']) ?> • <?= e($m['cat_name'] ?? '—') ?></span>
              <?= $badge ?>
            </div>
            <div class="d-flex gap-3 mb-2">
              <div class="thumb"><i class="fa-solid fa-pills"></i></div>
              <div class="flex-fill">
                <div class="fw-bold" style="font-size:14px; line-height:1.2"><?= e($m['name']) ?></div>
                <div class="small text-muted" style="font-size:11.5px"><?= e($m['form'] ?? '—') ?> • <?= e($m['dosage'] ?? '—') ?> • <?= e($m['laboratory'] ?? '—') ?></div>
                <div class="small <?= $expClass ?>" style="font-size:11px">Exp : <?= $expText ?> • <i class="fa-solid fa-location-dot me-1"></i><?= e($m['location'] ?? '—') ?> • Stock : <?= (int)$m['quantity'] ?></div>
              </div>
            </div>
            <div class="mt-auto d-flex justify-content-between align-items-center pt-2" style="border-top:1px dashed #e2e8f0">
              <div>
                <div class="price" style="font-size:15px"><?= number_format((float)$m['sale_price'],0,',',' ') ?> CDF</div>
                <div class="small text-muted" style="font-size:11px">Prix pharmacie</div>
              </div>
              <button class="btn btn-primary" style="border-radius:999px; font-weight:700; padding:8px 16px; font-size:13px" <?= $disabled?'disabled':'' ?>>
                <i class="fa-solid fa-plus me-1"></i> Ajouter
              </button>
            </div>
            <?php if($isExpired): ?><div class="small text-danger mt-2" style="font-size:11px"><i class="fa-solid fa-circle-info me-1"></i>Ce médicament n'est plus disponible.</div><?php endif; ?>
          </div>
        </div>
        <?php endforeach; ?>
      </div>
    </div>

    <!-- CART DESKTOP -->
    <div class="col-lg-4 d-none d-lg-block">
      <div class="cart-panel" id="cartDesktopPanel">
        <div class="cart-head">
          <div class="d-flex gap-2 align-items-center"><i class="fa-solid fa-cart-shopping"></i><strong>Mon panier</strong><span class="badge bg-white text-primary" style="border-radius:999px" id="cartCountDesktop">0</span></div>
          <span class="small" style="opacity:.85">Retrait à la pharmacie</span>
        </div>
        <div class="cart-list" id="cartListDesktop">
          <div class="text-center py-4" id="emptyCartDesktop" style="border:1px dashed #e2e8f0; border-radius:12px; background:#fff">
            <i class="fa-solid fa-basket-shopping text-muted" style="font-size:22px"></i>
            <div class="small text-muted mt-2">Votre panier est vide<br>Ajoutez vos médicaments</div>
          </div>
        </div>
        <form method="post" id="saleFormDesktop">
          <?= csrf_field() ?><input type="hidden" name="action" value="create_order"><input type="hidden" name="items_json" id="itemsJsonDesktop">
          <div class="p-3" style="background:#fff; border-top:1px solid #e2e8f0">
            <label class="form-label small fw-semibold" style="font-size:11px; letter-spacing:.06em; color:var(--gray-500)">VOS COORDONNÉES (pour le retrait)</label>
            <input type="text" name="customer_name" class="form-control mb-2" style="border-radius:12px" placeholder="Nom complet">
            <div class="row g-2 mb-2">
              <div class="col-6"><input type="tel" name="customer_phone" class="form-control" style="border-radius:12px" placeholder="Téléphone"></div>
              <div class="col-6"><input type="text" name="customer_address" class="form-control" style="border-radius:12px" placeholder="Adresse (optionnel)"></div>
            </div>
            <div class="small text-muted mb-3" style="font-size:11px"><i class="fa-solid fa-circle-info me-1"></i>Vos informations restent confidentielles et servent uniquement au retrait.</div>
          </div>
          <div class="total-box">
            <div class="d-flex justify-content-between small mb-2"><span class="text-muted">Sous-total</span><strong id="subTotalDesktop">0 CDF</strong></div>
            <div class="d-flex justify-content-between align-items-center mb-3 p-2 bg-white border" style="border-radius:12px">
              <span class="fw-bold">Total à payer</span><strong class="text-primary" id="totalValDesktop" style="font-size:20px; letter-spacing:-.02em">0 CDF</strong>
            </div>
            <label class="form-label small fw-semibold" style="font-size:11px; letter-spacing:.06em; color:var(--gray-500)">PAIEMENT AU RETRAIT</label>
            <select name="payment_method" class="form-select mb-3" style="border-radius:12px"><option value="especes">💵 Espèces</option><option value="mobile_money">📱 Mobile Money</option><option value="carte">💳 Carte</option><option value="credit">📝 Crédit (sur accord)</option></select>
            <div class="d-grid gap-2">
              <button type="submit" class="btn btn-primary fw-bold" style="border-radius:12px; padding:12px; background:#0a2a5e; border-color:#0a2a5e"><i class="fa-solid fa-check me-1"></i> Confirmer la commande</button>
              <button type="button" class="btn btn-light border" style="border-radius:12px" onclick="clearCart()"><i class="fa-solid fa-trash me-1"></i> Vider le panier</button>
            </div>
            <div class="small text-muted text-center mt-2" style="font-size:11px"><i class="fa-solid fa-lock me-1"></i>Paiement sécurisé au comptoir • Facture remise sur place</div>
          </div>
        </form>
      </div>
      <div class="mt-3 p-3 bg-white border" style="border-radius:14px">
        <div class="small fw-bold mb-2"><i class="fa-solid fa-hand-holding-medical text-primary me-1"></i> Besoin de conseil ?</div>
        <div class="small text-muted">Notre pharmacien est disponible au comptoir pour vérifier votre ordonnance et vous conseiller.</div>
        <div class="small mt-2"><i class="fa-solid fa-phone text-success me-1"></i> +243 81 000 0001 • <i class="fa-solid fa-location-dot ms-1 me-1"></i> Makiso, Kisangani</div>
      </div>
    </div>
  </div>
</div>

<!-- OFFCANVAS NAV MOBILE -->
<div class="offcanvas offcanvas-start" tabindex="-1" id="navOffcanvas">
  <div class="offcanvas-header"><strong>Menu</strong><button type="button" class="btn-close" data-bs-dismiss="offcanvas"></button></div>
  <div class="offcanvas-body d-grid gap-2">
     <a href="index.php" class="btn btn-light border text-start"><i class="fa-solid fa-house me-2"></i> Accueil</a>
     <a href="commande.php" class="btn btn-primary text-start"><i class="fa-solid fa-bag-shopping me-2"></i> Commander</a>
     <a href="index.php#pharmacie" class="btn btn-light border text-start"><i class="fa-solid fa-hospital me-2"></i> Notre pharmacie</a>
     <a href="dashboard.php" class="btn btn-light border text-start"><i class="fa-solid fa-table-columns me-2"></i> Mon dashboard</a>
     <a href="logout.php" class="btn btn-light border text-start text-danger"><i class="fa-solid fa-right-from-bracket me-2"></i> Déconnexion</a>
  </div>
</div>

<!-- OFFCANVAS CART MOBILE -->
<div class="offcanvas offcanvas-end offcanvas-cart" tabindex="-1" id="cartOffcanvas">
  <div class="offcanvas-header" style="background:linear-gradient(135deg,#0a2a5e 0%, #0b5ed7 100%); color:#fff">
    <div class="d-flex gap-2 align-items-center"><i class="fa-solid fa-cart-shopping"></i><strong>Mon panier</strong><span class="badge bg-white text-primary" style="border-radius:999px" id="cartCountMobile">0</span></div>
    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="offcanvas"></button>
  </div>
  <div class="offcanvas-body p-0 d-flex flex-column">
    <div class="cart-list flex-fill" id="cartListMobile">
      <div class="text-center py-4" id="emptyCartMobile" style="border:1px dashed #e2e8f0; border-radius:12px; background:#fff; margin:12px">
        <i class="fa-solid fa-basket-shopping text-muted" style="font-size:22px"></i>
        <div class="small text-muted mt-2">Panier vide</div>
      </div>
    </div>
    <form method="post" id="saleFormMobile" class="border-top">
      <?= csrf_field() ?><input type="hidden" name="action" value="create_order"><input type="hidden" name="items_json" id="itemsJsonMobile">
      <div class="p-3 bg-white">
        <input type="text" name="customer_name" class="form-control mb-2" style="border-radius:12px" placeholder="Nom complet">
        <input type="tel" name="customer_phone" class="form-control mb-2" style="border-radius:12px" placeholder="Téléphone">
        <input type="text" name="customer_address" class="form-control mb-2" style="border-radius:12px" placeholder="Adresse (optionnel)">
      </div>
      <div class="total-box">
        <div class="d-flex justify-content-between small mb-2"><span class="text-muted">Sous-total</span><strong id="subTotalMobile">0 CDF</strong></div>
        <div class="d-flex justify-content-between align-items-center mb-3 p-2 bg-white border" style="border-radius:12px"><span class="fw-bold">Total</span><strong class="text-primary" id="totalValMobile" style="font-size:18px">0 CDF</strong></div>
        <select name="payment_method" class="form-select mb-3" style="border-radius:12px"><option value="especes">💵 Espèces</option><option value="mobile_money">📱 Mobile Money</option><option value="carte">💳 Carte</option><option value="credit">📝 Crédit</option></select>
        <div class="d-grid gap-2">
          <button type="submit" class="btn btn-primary fw-bold" style="border-radius:12px; padding:12px"><i class="fa-solid fa-check me-1"></i> Confirmer</button>
          <button type="button" class="btn btn-light border" style="border-radius:12px" onclick="clearCart()">Vider</button>
        </div>
      </div>
    </form>
  </div>
</div>

<footer class="shop-footer py-4 mt-4">
  <div class="container d-flex flex-wrap justify-content-between gap-3 small">
    <div>
      <div class="fw-bold" style="letter-spacing:.04em">BOYAMBI PHARMACY — BOUTIQUE</div>
      <div class="text-muted">© <?= date('Y') ?> Hôpital Boyambi — Kisangani • Retrait à la pharmacie • Conseils au comptoir</div>
    </div>
    <div class="text-muted"><i class="fa-solid fa-location-dot me-1"></i> Makiso, Kisangani • <i class="fa-solid fa-phone ms-2 me-1"></i> +243 81 000 0001 • <i class="fa-solid fa-envelope ms-2 me-1"></i> contact@boyambi.cd</div>
  </div>
</footer>

<div class="toast-container"></div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="assets/js/app.js"></script>
<script>
let cart=[];
function syncBadges(){
  const n = cart.reduce((a,b)=>a+b.qty,0);
  document.getElementById('cartBadgeDesktop').textContent=n;
  document.getElementById('cartBadgeMobile').textContent=n;
  const cd=document.getElementById('cartCountDesktop'); if(cd) cd.textContent=n;
  const cm=document.getElementById('cartCountMobile'); if(cm) cm.textContent=n;
}
function renderCart(){
  syncBadges();
  const lists = [
    document.getElementById('cartListDesktop'),
    document.getElementById('cartListMobile')
  ];
  lists.forEach(list=>{
    if(!list) return;
    const isDesktop = list.id==='cartListDesktop';
    if(cart.length===0){
      list.innerHTML='<div class="text-center py-4" style="border:1px dashed #e2e8f0; border-radius:12px; background:#fff; margin:'+(isDesktop?'0':'12px')+'"><i class="fa-solid fa-basket-shopping text-muted" style="font-size:22px"></i><div class="small text-muted mt-2">Votre panier est vide</div></div>';
      return;
    }
    list.innerHTML=cart.map((it,idx)=>`
      <div class="cart-item">
        <div class="flex-fill">
          <div class="fw-semibold" style="font-size:13px">${it.name}</div>
          <div class="small text-muted">${Number(it.price).toLocaleString('fr-FR')} CDF × ${it.qty}</div>
          <div class="d-flex gap-1 mt-1 align-items-center">
            <button type="button" class="qty-btn" onclick="changeQty(${idx},-1)">−</button>
            <span class="badge bg-light border text-dark" style="min-width:36px; text-align:center; padding:6px">${it.qty}</span>
            <button type="button" class="qty-btn" onclick="changeQty(${idx},1)">+</button>
          </div>
        </div>
        <div class="text-end">
          <div class="fw-bold" style="font-size:13px">${(it.price*it.qty).toLocaleString('fr-FR')} CDF</div>
          <button type="button" class="btn btn-sm btn-light border text-danger mt-1" style="border-radius:999px" onclick="removeItem(${idx})"><i class="fa-solid fa-trash" style="font-size:11px"></i></button>
        </div>
      </div>
    `).join('');
  });
  updateTotals();
}
function updateTotals(){
  let sub=0; cart.forEach(it=> sub+= it.price*it.qty);
  const fmt=v=>v.toLocaleString('fr-FR')+' CDF';
  const a=document.getElementById('subTotalDesktop'); if(a) a.textContent=fmt(sub);
  const b=document.getElementById('totalValDesktop'); if(b) b.textContent=fmt(Math.max(0,sub));
  const c=document.getElementById('subTotalMobile'); if(c) c.textContent=fmt(sub);
  const d=document.getElementById('totalValMobile'); if(d) d.textContent=fmt(Math.max(0,sub));
  const jd=document.getElementById('itemsJsonDesktop'); if(jd) jd.value=JSON.stringify(cart);
  const jm=document.getElementById('itemsJsonMobile'); if(jm) jm.value=JSON.stringify(cart);
}
function changeQty(idx,delta){
  cart[idx].qty=Math.max(1, cart[idx].qty+delta);
  const tile=document.querySelector(`.medicine-card[data-id="${cart[idx].id}"]`);
  if(tile){ let max=parseInt(tile.dataset.qty); if(cart[idx].qty>max){ if(typeof toast==='function') toast('Stock insuffisant: '+max+' disponible','error'); else alert('Stock insuffisant'); cart[idx].qty=max; } }
  renderCart();
}
function removeItem(idx){ cart.splice(idx,1); renderCart(); }
function clearCart(){ cart=[]; renderCart(); }
// Med click
document.querySelectorAll('.medicine-card').forEach(tile=>{
  const btn=tile.querySelector('button');
  const handler=()=>{
    if(tile.classList.contains('disabled')) return;
    const expiry=tile.dataset.expiry;
    if(expiry && expiry < new Date().toISOString().slice(0,10)){ if(typeof toast==='function') toast('Médicament indisponible — date dépassée','error'); return; }
    const id=parseInt(tile.dataset.id);
    const stock=parseInt(tile.dataset.qty);
    const existing=cart.find(c=>c.id===id);
    if(existing){
      if(existing.qty+1>stock){ if(typeof toast==='function') toast('Stock insuffisant','error'); return; }
      existing.qty++;
    } else {
      cart.push({id, name:tile.dataset.name, price:parseFloat(tile.dataset.price), qty:1, discount:0});
    }
    renderCart();
    // feedback
    if(btn){ btn.innerHTML='<i class="fa-solid fa-check me-1"></i> Ajouté'; setTimeout(()=>btn.innerHTML='<i class="fa-solid fa-plus me-1"></i> Ajouter',900); }
  };
  tile.addEventListener('click', (e)=>{ if(e.target.closest('button')) return; handler(); });
  if(btn) btn.addEventListener('click', (e)=>{ e.stopPropagation(); handler(); });
});
// Search & filter
const search=document.getElementById('searchMed');
const catSel=document.getElementById('filterCat');
const pills=document.querySelectorAll('.cat-pill');
function applyFilter(){
  const q=(search.value||'').toLowerCase();
  const cat=catSel.value;
  let visible=0;
  document.querySelectorAll('.med-card').forEach(card=>{
    const name=card.dataset.name;
    const c=card.dataset.cat;
    const okQ=!q || name.includes(q);
    const okC=!cat || c===cat;
    const show=okQ&&okC;
    card.style.display=show?'':'none';
    if(show) visible++;
  });
  document.getElementById('countVisible').textContent=visible;
}
search?.addEventListener('input', applyFilter);
catSel?.addEventListener('change',()=>{
  pills.forEach(p=>p.classList.toggle('active', p.dataset.cat===catSel.value));
  applyFilter();
});
pills.forEach(p=> p.addEventListener('click',()=>{
  const cat=p.dataset.cat;
  catSel.value=cat; pills.forEach(x=>x.classList.remove('active')); p.classList.add('active'); applyFilter();
}));
// Submit guards
document.getElementById('saleFormDesktop')?.addEventListener('submit',e=>{
  if(cart.length===0){ e.preventDefault(); if(typeof toast==='function') toast('Votre panier est vide','error'); }
});
document.getElementById('saleFormMobile')?.addEventListener('submit',e=>{
  if(cart.length===0){ e.preventDefault(); if(typeof toast==='function') toast('Votre panier est vide','error'); }
  // Copy address fields to desktop form? No, each form submits independently, already has its own fields
});
renderCart();
</script>
</body>
</html>
