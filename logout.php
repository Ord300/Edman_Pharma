<?php
require_once __DIR__.'/config/database.php';
if(isLoggedIn()){
    logActivity($pdo,'deconnexion','Logout '.$_SESSION['user']['email']);
}
session_destroy();
header("Location: login.php");
