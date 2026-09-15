<?php
require_once __DIR__.'/config/database.php';
requireLogin();
requireRole(['admin','pharmacien','caissier']);
require_once __DIR__.'/includes/partials/layout.php';

$customers=$pdo->query("SELECT * FROM customers ORDER BY full_name")->fetchAll();
$medicines=$pdo->query("SELECT m.*, c.name as cat FROM medicines m LEFT JOIN categories c ON c.id=m.category_id WHERE m.status!='archived' ORDER BY m.name")->fetchAll();
$categories=$pdo->query("SELECT * FROM categories ORDER BY name")->fetchAll();

if($_SERVER['REQUEST_METHOD']==='POST' && ($_POST['action'] ?? '')==='create_sale'){
    if(!verify_csrf($_POST['csrf_token'] ?? '')) flash('error','CSRF invalide');
    else {
        $customer_id = $_POST['customer_id'] ? (int)$_POST['customer_id'] : null;
        $payment = $_POST['payment_method'] ?? 'especes';
        $discountGlobal = (float)($_POST['discount_global'] ?? 0);
        $itemsJson = $_POST['items_json'] ?? '[]';
        $items = json_decode($itemsJson, true);
        if(!$items || count($items)==0){ flash('error','Panier vide.'); }
        else {
            $errors=[];
            foreach($items as $it){
                $stmt=$pdo->prepare("SELECT quantity, expiry_date, sale_price, name FROM medicines WHERE id=?");
                $stmt->execute([$it['id']]);
                $med=$stmt->fetch();
                if(!$med) $errors[]="Médicament #{$it['id']} introuvable";
                elseif($med['expiry_date'] < date('Y-m-d')) $errors[]=$med['name']." est expiré";
                elseif($med['quantity'] < $it['qty']) $errors[]=$med['name']." stock insuffisant ({$med['quantity']} dispo)";
            }
            if($errors){ flash('error', implode(' • ', $errors)); }
            else {
                $pdo->beginTransaction();
                try{
                    $subtotal=0;
                    foreach($items as $it) $subtotal += (float)$it['price'] * (int)$it['qty'] - (float)($it['discount'] ?? 0);
                    $total = max(0, $subtotal - $discountGlobal);
                    $invNum = generateInvoiceNumber($pdo);
                    $stmt=$pdo->prepare("INSERT INTO sales (invoice_number,customer_id,user_id,subtotal,discount,total,payment_method) VALUES (?,?,?,?,?,?,?)");
                    $stmt->execute([$invNum,$customer_id,$_SESSION['user']['id'],$subtotal,$discountGlobal,$total,$payment]);
                    $saleId=$pdo->lastInsertId();
                    foreach($items as $it){
                        $lineSub = (float)$it['price']*(int)$it['qty'] - (float)($it['discount']??0);
                        $pdo->prepare("INSERT INTO sale_items (sale_id,medicine_id,quantity,unit_price,discount,subtotal) VALUES (?,?,?,?,?,?)")->execute([$saleId,$it['id'],$it['qty'],$it['price'],$it['discount']??0,$lineSub]);
                        $old=$pdo->prepare("SELECT quantity FROM medicines WHERE id=?"); $old->execute([$it['id']]); $oldQty=(int)$old->fetchColumn();
                        $new=$oldQty - (int)$it['qty'];
                        $pdo->prepare("UPDATE medicines SET quantity=?, status=CASE WHEN ?<=0 THEN 'out' WHEN ? <= alert_threshold THEN 'low' ELSE 'available' END WHERE id=?")->execute([$new,$new,$new,$it['id']]);
                        $pdo->prepare("INSERT INTO stock_movements (medicine_id,type,quantity,old_stock,new_stock,user_id,reason) VALUES (?,?,?,?,?,?,?)")->execute([$it['id'],'sortie',(int)$it['qty'],$oldQty,$new,$_SESSION['user']['id'],'Vente '.$invNum]);
                    }
                    $pdo->prepare("INSERT INTO invoices (invoice_number,sale_id,customer_id,total) VALUES (?,?,?,?)")->execute([$invNum,$saleId,$customer_id,$total]);
                    $iid=$pdo->lastInsertId();
                    foreach($items as $it){
                        $stmtName=$pdo->prepare("SELECT name FROM medicines WHERE id=?"); $stmtName->execute([$it['id']]); $mn=$stmtName->fetchColumn();
                        $pdo->prepare("INSERT INTO invoice_items (invoice_id,medicine_name,quantity,unit_price,subtotal) VALUES (?,?,?,?,?)")->execute([$iid,$mn,$it['qty'],$it['price'], (float)$it['price']*(int)$it['qty'] - (float)($it['discount']??0)]);
                    }
                    $pdo->prepare("INSERT INTO payments (sale_id,amount,method) VALUES (?,?,?)")->execute([$saleId,$total,$payment]);
                    $pdo->commit();
                    logActivity($pdo,'vente',"Vente $invNum total $total");
                    flash('success',"Vente enregistrée et facture $invNum générée.");
                    redirect("invoice.php?num=$invNum");
                }catch(Exception $e){ $pdo->rollBack(); flash('error','Erreur vente: '.$e->getMessage()); }
            }
        }
    }
    if(!isset($_GET['num'])) redirect('sales.php');
}

layout_header('Ventes — Caisse');
?>
<style>
.pos-hero{background:linear-gradient(135deg,#0a2a5e 0%, #0b5ed7 100%); border-radius:18px; color:#fff; position:relative; overflow:hidden}
.pos-hero::after{content:""; position:absolute; width:400px; height:400px; background:radial-gradient(circle, rgba(255,255,255,.11), transparent 70%); right:-80px; top:-100px}
.cat-pill{padding:7px 14px; border-radius:999px; border:1px solid var(--gray-200); background:#fff; font-size:12px; font-weight:600; cursor:pointer; transition:.15s}
.cat-pill.active{background:var(--blue-dark); color:#fff; border-color:var(--blue-dark); box-shadow:0 4px 12px rgba(10,42,94,.18)}
.cat-pill:hover{border-color:#cbd5e1; transform:translateY(-1px)}
.medicine-tile{border-radius:14px; border:1px solid var(--gray-200); background:#fff; padding:13px; cursor:pointer; transition:.18s; position:relative; overflow:hidden}
.medicine-tile:hover{border-color:var(--blue); box-shadow:0 10px 28px rgba(11,94,215,.12); transform:translateY(-2px)}
.medicine-tile.disabled{opacity:.55; pointer-events:none; background:#f8fafc}
.cart-sticky{position:sticky; top:78px; border-radius:16px; overflow:hidden; box-shadow:var(--shadow-lg); border:1px solid var(--gray-200)}
.cart-head{background:linear-gradient(135deg,#0a2a5e 0%, #0b5ed7 100%); color:#fff; padding:14px 16px; display:flex; justify-content:space-between; align-items:center}
</style>

<div class="pos-hero p-4 mb-4">
  <div class="d-flex flex-wrap justify-content-between gap-3 align-items-center" style="position:relative; z-index:1">
    <div class="d-flex gap-3 align-items-center">
      <div style="width:48px;height:48px;background:#fff;color:var(--blue-dark);border-radius:12px;display:grid;place-items:center;font-size:18px"><i class="fa-solid fa-cash-register"></i></div>
      <div>
        <h4 class="fw-bold mb-0" style="letter-spacing:-.02em">Caisse — Ventes POS</h4>
        <div class="small" style="opacity:.88">Interface caisse professionnelle • Contrôle péremption & stock en temps réel</div>
      </div>
    </div>
    <div class="d-flex gap-2">
      <a href="invoices.php" class="btn btn-light text-primary btn-sm fw-bold" style="border-radius:999px"><i class="fa-solid fa-file-invoice me-1"></i> Voir factures</a>
      <span class="badge bg-white text-dark d-none d-md-inline" style="border-radius:999px; padding:8px 12px"><i class="fa-solid fa-bolt text-warning me-1"></i> Mode caisse actif</span>
    </div>
  </div>
</div>

<div class="pos-grid">
  <div>
    <div class="card mb-3" style="border-radius:16px">
      <div class="card-body">
        <div class="d-flex gap-2 mb-3 flex-wrap">
          <div class="flex-fill position-relative">
            <i class="fa-solid fa-magnifying-glass" style="position:absolute; left:13px; top:50%; transform:translateY(-50%); color:#94a3b8"></i>
            <input id="searchMed" class="form-control" style="padding-left:38px; border-radius:12px; height:42px" placeholder="Rechercher médicament, code, laboratoire...">
          </div>
          <select id="filterCat" class="form-select" style="max-width:190px; border-radius:12px; height:42px"><option value="">Toutes catégories</option><?php foreach($categories as $c): ?><option value="<?= e($c['name']) ?>"><?= e($c['name']) ?></option><?php endforeach; ?></select>
        </div>
        <div class="d-flex gap-2 mb-3 flex-wrap" id="catPills">
          <button class="cat-pill active" data-cat="">Tous</button>
          <?php foreach($categories as $c): ?><button class="cat-pill" data-cat="<?= e($c['name']) ?>"><?= e($c['name']) ?></button><?php endforeach; ?>
        </div>
        <div class="row g-3" id="medGrid">
          <?php foreach($medicines as $m):
            $isExpired = $m['expiry_date'] && $m['expiry_date'] < date('Y-m-d');
            $isOut = $m['quantity']<=0;
            $disabled = $isExpired || $isOut;
            $stockBadge = $isExpired?'<span class="badge bg-danger" style="font-size:10px; border-radius:999px">Expiré</span>':($isOut?'<span class="badge bg-dark" style="font-size:10px; border-radius:999px">Rupture</span>':($m['quantity']<=$m['alert_threshold']?'<span class="badge bg-warning text-dark" style="font-size:10px; border-radius:999px">Stock faible</span>':'<span class="badge bg-success" style="font-size:10px; border-radius:999px">'.$m['quantity'].' dispo</span>'));
          ?>
          <div class="col-6 col-md-4 med-card" data-name="<?= e(strtolower($m['name'].' '.$m['code'].' '.$m['laboratory'])) ?>" data-cat="<?= e($m['cat'] ?? '') ?>">
            <div class="medicine-tile <?= $disabled?'disabled':'' ?>" data-id="<?= $m['id'] ?>" data-name="<?= e($m['name']) ?>" data-price="<?= $m['sale_price'] ?>" data-qty="<?= $m['quantity'] ?>" data-expiry="<?= e($m['expiry_date']) ?>">
              <div class="d-flex justify-content-between align-items-start mb-2">
                <span class="badge bg-light border text-dark" style="font-size:10px; border-radius:999px; padding:5px 8px"><?= e($m['code']) ?></span>
                <?= $stockBadge ?>
              </div>
              <div class="d-flex gap-2 align-items-start mb-1">
                <div style="width:36px;height:36px;border-radius:10px;background:#e8f0fe;color:var(--blue);display:grid;place-items:center; flex-shrink:0"><i class="fa-solid fa-pills" style="font-size:13px"></i></div>
                <div>
                  <div class="fw-bold" style="font-size:13px; line-height:1.15"><?= e($m['name']) ?></div>
                  <div class="small text-muted" style="font-size:11px"><?= e($m['form']) ?> • <?= e($m['dosage']) ?> • <?= e($m['cat']) ?></div>
                </div>
              </div>
              <div class="d-flex justify-content-between align-items-center mt-2">
                <div><div class="fw-bold text-primary" style="font-size:13px"><?= money($m['sale_price']) ?></div><small class="text-muted" style="font-size:10px">Exp <?= e($m['expiry_date'] ?? '—') ?></small></div>
                <button class="btn btn-sm btn-primary" style="border-radius:999px; width:34px; height:34px; display:grid; place-items:center" <?= $disabled?'disabled':'' ?>><i class="fa-solid fa-plus" style="font-size:11px"></i></button>
              </div>
            </div>
          </div>
          <?php endforeach; ?>
        </div>
      </div>
    </div>
  </div>

  <div>
    <div class="cart-sticky">
      <div class="cart-head">
        <div class="d-flex gap-2 align-items-center"><i class="fa-solid fa-cart-shopping"></i><strong>Panier</strong><span class="badge bg-white text-primary" style="border-radius:999px" id="cartCount">0</span></div>
        <span class="small" style="opacity:.85">Caisse POS</span>
      </div>
      <div class="bg-white p-3">
        <form method="post" id="saleForm">
          <?= csrf_field() ?><input type="hidden" name="action" value="create_sale"><input type="hidden" name="items_json" id="itemsJson">
          <div class="mb-3">
            <label class="form-label small fw-semibold" style="font-size:11px; letter-spacing:.06em; color:var(--gray-500)">CLIENT</label>
            <select name="customer_id" class="form-select" style="border-radius:12px"><option value="">Client comptoir</option><?php foreach($customers as $c): ?><option value="<?= $c['id'] ?>"><?= e($c['full_name']) ?> — <?= e($c['phone']) ?></option><?php endforeach; ?></select>
          </div>
          <div id="cartList" class="mb-3" style="max-height:360px; overflow:auto">
            <div class="text-center py-4" id="emptyCart" style="border:1px dashed var(--gray-200); border-radius:12px; background:#f8fafc">
              <i class="fa-solid fa-basket-shopping text-muted" style="font-size:22px"></i>
              <div class="small text-muted mt-2">Panier vide — cliquez sur un médicament</div>
            </div>
          </div>
          <div class="border-top pt-3" style="background:#f8fafc; margin:0 -16px -16px; padding:16px; border-radius:0 0 16px 16px">
            <div class="d-flex justify-content-between small mb-2"><span class="text-muted">Sous-total</span><strong id="subTotal">0 CDF</strong></div>
            <div class="d-flex justify-content-between align-items-center mb-3">
              <label class="small text-muted">Remise globale</label><input type="number" name="discount_global" id="discountGlobal" class="form-control form-control-sm" style="width:120px; border-radius:999px; text-align:center" value="0" min="0" placeholder="0 CDF">
            </div>
            <div class="d-flex justify-content-between align-items-center mb-3 p-2 bg-white border" style="border-radius:12px">
              <span class="fw-bold">Total à payer</span><strong class="text-primary" id="totalVal" style="font-size:20px; letter-spacing:-.02em">0 CDF</strong>
            </div>
            <label class="form-label small fw-semibold" style="font-size:11px; letter-spacing:.06em; color:var(--gray-500)">MODE DE PAIEMENT</label>
            <select name="payment_method" class="form-select mb-3" style="border-radius:12px"><option value="especes">💵 Espèces</option><option value="mobile_money">📱 Mobile Money</option><option value="carte">💳 Carte</option><option value="credit">📝 Crédit</option></select>
            <div class="d-grid gap-2">
              <button type="submit" class="btn btn-primary fw-bold" style="border-radius:12px; padding:12px"><i class="fa-solid fa-check me-1"></i> Valider & générer facture</button>
              <button type="button" class="btn btn-light border" style="border-radius:12px" onclick="clearCart()"><i class="fa-solid fa-trash me-1"></i> Vider le panier</button>
            </div>
          </div>
        </form>
      </div>
    </div>
  </div>
</div>

<script>
let cart=[];
function renderCart(){
  const list=document.getElementById('cartList');
  const count=document.getElementById('cartCount');
  count.textContent=cart.reduce((a,b)=>a+b.qty,0);
  if(cart.length===0){ list.innerHTML='<div class="text-center py-4" style="border:1px dashed var(--gray-200); border-radius:12px; background:#f8fafc"><i class="fa-solid fa-basket-shopping text-muted" style="font-size:22px"></i><div class="small text-muted mt-2">Panier vide</div></div>'; updateTotals(); return; }
  list.innerHTML=cart.map((it,idx)=>`
    <div class="d-flex gap-2 align-items-center p-2 mb-2 bg-white border" style="border-radius:12px">
      <div class="flex-fill">
        <div class="fw-semibold" style="font-size:13px">${it.name}</div>
        <div class="small text-muted">${Number(it.price).toLocaleString('fr-FR')} CDF × ${it.qty}</div>
        <div class="d-flex gap-1 mt-1">
          <button type="button" class="btn btn-sm btn-light border" style="border-radius:999px; width:28px; height:28px; display:grid; place-items:center" onclick="changeQty(${idx},-1)">−</button>
          <span class="badge bg-light border text-dark" style="min-width:36px; text-align:center; padding:6px">${it.qty}</span>
          <button type="button" class="btn btn-sm btn-light border" style="border-radius:999px; width:28px; height:28px; display:grid; place-items:center" onclick="changeQty(${idx},1)">+</button>
        </div>
      </div>
      <div class="text-end">
        <div class="fw-bold" style="font-size:13px">${(it.price*it.qty - (it.discount||0)).toLocaleString('fr-FR')} CDF</div>
        <button type="button" class="btn btn-sm btn-light border text-danger mt-1" style="border-radius:999px" onclick="removeItem(${idx})"><i class="fa-solid fa-trash" style="font-size:11px"></i></button>
      </div>
    </div>
  `).join('');
  updateTotals();
}
function updateTotals(){
  let sub=0; cart.forEach(it=> sub+= it.price*it.qty - (it.discount||0));
  let disc=parseFloat(document.getElementById('discountGlobal').value||0);
  let tot=Math.max(0, sub-disc);
  document.getElementById('subTotal').textContent=sub.toLocaleString('fr-FR')+' CDF';
  document.getElementById('totalVal').textContent=tot.toLocaleString('fr-FR')+' CDF';
  document.getElementById('itemsJson').value=JSON.stringify(cart);
}
function changeQty(idx,delta){
  cart[idx].qty=Math.max(1, cart[idx].qty+delta);
  const tile=document.querySelector(`.medicine-tile[data-id="${cart[idx].id}"]`);
  if(tile){ let max=parseInt(tile.dataset.qty); if(cart[idx].qty>max){ toast('Stock insuffisant: '+max+' disponible','error'); cart[idx].qty=max; } }
  renderCart();
}
function removeItem(idx){ cart.splice(idx,1); renderCart(); }
function clearCart(){ cart=[]; renderCart(); }
document.getElementById('discountGlobal').addEventListener('input', updateTotals);
document.querySelectorAll('.medicine-tile').forEach(tile=>{
  tile.addEventListener('click',()=>{
    if(tile.classList.contains('disabled')) return;
    const id=parseInt(tile.dataset.id);
    const expiry=tile.dataset.expiry;
    if(expiry && expiry < new Date().toISOString().slice(0,10)){ toast('Médicament expiré — vente interdite','error'); return; }
    const existing=cart.find(c=>c.id===id);
    const stock=parseInt(tile.dataset.qty);
    if(existing){
      if(existing.qty+1>stock){ toast('Stock insuffisant','error'); return; }
      existing.qty++;
    } else {
      cart.push({id, name:tile.dataset.name, price:parseFloat(tile.dataset.price), qty:1, discount:0});
    }
    renderCart();
  });
});
const search=document.getElementById('searchMed');
const catSel=document.getElementById('filterCat');
const pills=document.querySelectorAll('.cat-pill');
function applyFilter(){
  const q=(search.value||'').toLowerCase();
  const cat=catSel.value;
  document.querySelectorAll('.med-card').forEach(card=>{
    const name=card.dataset.name;
    const c=card.dataset.cat;
    const okQ=!q || name.includes(q);
    const okC=!cat || c===cat;
    card.style.display=(okQ&&okC)?'':'none';
  });
}
search.addEventListener('input', applyFilter);
catSel.addEventListener('change',()=>{
  pills.forEach(p=>p.classList.toggle('active', p.dataset.cat===catSel.value));
  applyFilter();
});
pills.forEach(p=> p.addEventListener('click',()=>{
  catSel.value=p.dataset.cat; pills.forEach(x=>x.classList.remove('active')); p.classList.add('active'); applyFilter();
}));
document.getElementById('saleForm').addEventListener('submit',e=>{
  if(cart.length===0){ e.preventDefault(); toast('Panier vide','error'); }
});
</script>
<?php layout_footer(); ?>
