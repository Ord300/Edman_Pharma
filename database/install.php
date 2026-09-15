<?php
require_once __DIR__.'/../config/database.php';
echo "<h2>Installation Boyambi Pharmacy</h2>";
// schema already imported via database.php fallback, but ensure
$sqlFile = __DIR__.'/schema.sql';
if(file_exists($sqlFile) && isset($_GET['force'])){
    $sql=file_get_contents($sqlFile);
    try{ $pdo->exec($sql); echo "<p style='color:green'>Base réinstallée avec succès.</p>"; }catch(Exception $e){ echo "<p style='color:red'>".$e->getMessage()."</p>"; }
}
$tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
echo "<p>Tables: ".implode(', ', $tables)."</p>";
echo "<p><a href='../login.php'>Aller à la connexion</a></p>";
echo "<p>Comptes: admin@boyambi.cd / pharmacien@boyambi.cd / caisse@boyambi.cd / stock@boyambi.cd — mot de passe: <b>password123</b></p>";
