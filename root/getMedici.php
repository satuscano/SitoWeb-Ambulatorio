<?php
include("../inc/auth.inc");
include("../inc/start.inc");

header('Content-Type: application/json'); // indica che la risposta sarà in formato JSON

$codiceReparto = $_GET['codiceReparto'] ?? '';

if (!$codiceReparto) {
    echo json_encode([]); // restituisce un array vuoto se non viene fornito il codice reparto
    exit;
}

$stmt = $conn->prepare("
    SELECT m.codiceMedico, m.nome, m.cognome, m.orario,
           s.tipo AS specializzazione
    FROM medico m
    LEFT JOIN specializzazione s ON m.codiceMedico = s.codiceMedico
    WHERE m.codiceReparto = ?
    ORDER BY m.cognome
");
$stmt->execute([$codiceReparto]);
$medici = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo json_encode($medici); // restituisce i dati dei medici in formato JSON


// come funziona la getMedici?
// 1. Il client (dashboard.php) invia una richiesta AJAX a getMedici.php con il codice del reparto selezionato.
// 2. getMedici.php riceve la richiesta, esegue una query per ottenere i medici associati a quel reparto e restituisce i dati in formato JSON.
// 3. Il client riceve la risposta JSON, la elabora e aggiorna dinamicamente la sezione dei medici e degli orari nel dashboard senza
// 
?>
