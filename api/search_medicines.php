<?php
require_once __DIR__.'/../config/database.php';
header('Content-Type: application/json');
if(!isLoggedIn()){ http_response_code(401); echo json_encode(['error'=>'Unauthorized']); exit; }
$q=trim($_GET['q'] ?? '');
$stmt=$pdo->prepare("SELECT id, code, name, sale_price, quantity, expiry_date FROM medicines WHERE (name LIKE ? OR code LIKE ?) AND status!='archived' LIMIT 20");
$like="%$q%"; $stmt->execute([$like,$like]);
echo json_encode($stmt->fetchAll());
