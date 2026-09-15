<?php
require_once __DIR__.'/config.php';
try{
    $dsn = "mysql:host=".DB_HOST.";dbname=".DB_NAME.";charset=".DB_CHARSET;
    $pdo = new PDO($dsn, DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
}catch(PDOException $e){
    // Try to create DB if not exists
    if(strpos($e->getMessage(),'Unknown database')!==false){
        $tmp = new PDO("mysql:host=".DB_HOST.";charset=".DB_CHARSET, DB_USER, DB_PASS);
        $tmp->exec("CREATE DATABASE `".DB_NAME."` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
        $pdo = new PDO("mysql:host=".DB_HOST.";dbname=".DB_NAME.";charset=".DB_CHARSET, DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
        // import schema if exists
        $sqlFile = __DIR__.'/../database/schema.sql';
        if(file_exists($sqlFile)){
            $sql=file_get_contents($sqlFile);
            $pdo->exec($sql);
        }
    } else {
        die("Erreur connexion DB: ".htmlspecialchars($e->getMessage()));
    }
}
