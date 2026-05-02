<?php
$servername = "localhost";
$dbname = "databaseprogetto";
$username = "root";
$password = "";
// creo un nuovo oggetto PDO (connessione)
$conn = new PDO("mysql:host=$servername;dbname=$dbname", $username, $password);
$conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
?>