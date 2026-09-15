<?php
// Boyambi Pharmacy - Configuration
define('APP_NAME', 'Boyambi Pharmacy');
define('APP_VERSION', '1.0.0');
define('BASE_URL', (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS']==='on' ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . rtrim(dirname($_SERVER['SCRIPT_NAME'] ?? ''), '/\\') . '/');
define('DB_HOST', '127.0.0.1');
define('DB_NAME', 'boyambi_pharmacy');
define('DB_USER', 'boyambi');
define('DB_PASS', 'Boyambi123!@#');
define('DB_CHARSET', 'utf8mb4');
date_default_timezone_set('Africa/Kinshasa');
session_start();

// CSRF
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
function csrf_token(){ return $_SESSION['csrf_token']; }
function csrf_field(){ return '<input type="hidden" name="csrf_token" value="'.htmlspecialchars(csrf_token()).'">'; }
function verify_csrf($token){ return hash_equals($_SESSION['csrf_token'] ?? '', $token ?? ''); }

// Helpers
function e($str){ return htmlspecialchars($str ?? '', ENT_QUOTES, 'UTF-8'); }
function redirect($path){ header("Location: $path"); exit; }
function isLoggedIn(){ return isset($_SESSION['user']); }
function requireLogin(){ if(!isLoggedIn()) redirect('login.php'); }

// Centralisation des rôles & permissions — chaque rôle voit uniquement ce qui le concerne
const ROLES = ['admin','pharmacien','caissier','gestionnaire_stock'];
const ROLE_LABELS = [
    'admin' => 'Admin',
    'pharmacien' => 'Pharmacien',
    'caissier' => 'Caissier',
    'gestionnaire_stock' => 'Gestionnaire stock',
];
const PAGE_PERMISSIONS = [
    'dashboard.php'      => ['admin','pharmacien','caissier','gestionnaire_stock'],
    'medicines.php'      => ['admin','pharmacien','gestionnaire_stock'],
    'stocks.php'         => ['admin','pharmacien','gestionnaire_stock'],
    'movements.php'      => ['admin','pharmacien','gestionnaire_stock'],
    'sales.php'          => ['admin','pharmacien','caissier'],
    'invoices.php'       => ['admin','pharmacien','caissier'],
    'invoice.php'        => ['admin','pharmacien','caissier'],
    'commande.php'       => ['caissier'],
    'customers.php'      => ['admin','pharmacien','caissier'],
    'suppliers.php'      => ['admin','pharmacien','gestionnaire_stock'],
    'reports.php'        => ['admin','pharmacien'],
    'users.php'          => ['admin'],
    'logs.php'           => ['admin'],
    'settings.php'       => ['admin','pharmacien','caissier','gestionnaire_stock'],
];
function currentRole(): string { return $_SESSION['user']['role'] ?? ''; }
function roleLabel(string $role): string { return ROLE_LABELS[$role] ?? ucfirst($role); }
function allowedRolesForPage(string $page): array { return PAGE_PERMISSIONS[basename($page)] ?? []; }
function canAccessPage(string $page, ?string $role=null): bool {
    $role = $role ?? currentRole();
    $allowed = allowedRolesForPage($page);
    if(empty($allowed)) return true; // page publique ou non listée
    return in_array($role, $allowed);
}
function userCan($allowedRoles){
    if(!isLoggedIn()) return false;
    $role = currentRole();
    if(is_string($allowedRoles)) $allowedRoles = [$allowedRoles];
    return in_array($role, $allowedRoles);
}
function requireRole($roles){
    requireLogin();
    if(!userCan($roles)){
        http_response_code(403);
        $role = currentRole();
        $label = roleLabel($role);
        die("<div style='font-family:Inter, sans-serif; max-width:640px; margin:60px auto; padding:32px; border:1px solid #e2e8f0; border-radius:16px; text-align:center'><h1 style='color:#0a2a5e'>403 — Accès refusé</h1><p>Votre rôle <strong style='text-transform:capitalize'>".e($label)." (".e($role).")</strong> ne permet pas d'accéder à cette page.</p><p style='color:#64748b; font-size:13px'>Vous voyez uniquement les modules autorisés pour votre rôle.</p><a href='dashboard.php' style='display:inline-block; margin-top:12px; background:#0a2a5e; color:#fff; padding:10px 18px; border-radius:999px; text-decoration:none; font-weight:700'>Retour au dashboard</a></div>");
    }
}
function flash($key,$msg=null){
    if($msg!==null){ $_SESSION['flash'][$key]=$msg; return; }
    if(isset($_SESSION['flash'][$key])){ $m=$_SESSION['flash'][$key]; unset($_SESSION['flash'][$key]); return $m; }
    return null;
}
function money($n){ return number_format((float)$n, 2, ',', ' ') . ' CDF'; }
function moneyRaw($n){ return number_format((float)$n,2,'.',''); }
function generateInvoiceNumber($pdo){
    $prefix = 'FAC-'.date('Ymd').'-';
    $stmt=$pdo->query("SELECT COUNT(*) FROM invoices WHERE DATE(created_at)=CURDATE()");
    $count=(int)$stmt->fetchColumn()+1;
    return $prefix . str_pad($count,4,'0',STR_PAD_LEFT);
}
function logActivity($pdo,$action,$details=''){
    if(!isset($_SESSION['user'])) return;
    $stmt=$pdo->prepare("INSERT INTO activity_logs (user_id, action, details, ip_address) VALUES (?,?,?,?)");
    $stmt->execute([$_SESSION['user']['id'],$action,$details,$_SERVER['REMOTE_ADDR'] ?? '']);
}
function getAlerts($pdo){
    $alerts=[];
    // Expirés
    $stmt=$pdo->query("SELECT id, name, code, expiry_date FROM medicines WHERE expiry_date < CURDATE() AND status!='archived' LIMIT 5");
    foreach($stmt->fetchAll() as $r){ $alerts[]=['type'=>'danger','icon'=>'fa-triangle-exclamation','msg'=> e($r['name'])." (".e($r['code']).") : produit expiré le ".e($r['expiry_date']),"link"=>"medicines.php?filter=expired"]; }
    // Bientôt périmés (30j)
    $stmt=$pdo->query("SELECT id,name,code,expiry_date,DATEDIFF(expiry_date,CURDATE()) as d FROM medicines WHERE expiry_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY) LIMIT 5");
    foreach($stmt->fetchAll() as $r){ $alerts[]=['type'=>'warning','icon'=>'fa-hourglass-half','msg'=> e($r['name'])." : expire dans {$r['d']} jours ({$r['expiry_date']})","link"=>"medicines.php?filter=expiring"]; }
    // Stock faible
    $stmt=$pdo->query("SELECT name,code,quantity,alert_threshold FROM medicines WHERE quantity <= alert_threshold AND quantity>0 LIMIT 5");
    foreach($stmt->fetchAll() as $r){ $alerts[]=['type'=>'warning','icon'=>'fa-box-open','msg'=> e($r['name'])." : stock faible — {$r['quantity']} unités restantes","link"=>"stocks.php?filter=low"]; }
    // Rupture
    $stmt=$pdo->query("SELECT name,code FROM medicines WHERE quantity<=0 LIMIT 5");
    foreach($stmt->fetchAll() as $r){ $alerts[]=['type'=>'danger','icon'=>'fa-ban','msg'=> e($r['name'])." : rupture de stock","link"=>"stocks.php?filter=out"]; }
    return $alerts;
}
