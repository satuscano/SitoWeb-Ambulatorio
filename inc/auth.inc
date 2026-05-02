<?php
session_start();

if(!isset($_SESSION['codiceFiscale'])){
    header("Location: /AMBULATORIO/root/loginPage.php");
    exit;
}
?>