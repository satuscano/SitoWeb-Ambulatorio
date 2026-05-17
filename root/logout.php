<?php
session_start(); // Apri la sessione
session_unset(); // Rimuovi tutte le variabili di sessione
session_destroy(); // Distruggi la sessione
header("Location: index.php"); // Torna alla pagina di login
exit();
?>