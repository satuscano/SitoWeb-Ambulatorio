<?php
include("../../inc/auth.inc");
$cf = $_SESSION['codiceFiscale'];
include("../../inc/start.inc");

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['codiceEsame'])) {
    $codiceEsame = (int)$_POST['codiceEsame'];

    // Verifica che l'esame appartenga a questo paziente e sia prenotato
    $stmt = $conn->prepare("
        SELECT COUNT(*) FROM storico
        WHERE codiceEsame = ? AND codiceFiscale = ? AND stato = 'prenotato'
    ");
    $stmt->execute([$codiceEsame, $cf]);

    if ($stmt->fetchColumn() > 0) {
        $stmtUpdate = $conn->prepare("UPDATE storico SET stato = 'cancellato' WHERE codiceEsame = ? AND codiceFiscale = ?");
        $stmtUpdate->execute([$codiceEsame, $cf]);
    }
}

header("Location: dashboard.php?success=cancellato");
exit;