<?php
include("../../inc/auth.inc");
include("../../inc/start.inc");

header('Content-Type: application/json');

$codiceReparto = $_GET['codiceReparto'] ?? '';

if (!$codiceReparto) {
    echo json_encode([]);
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

echo json_encode($medici);