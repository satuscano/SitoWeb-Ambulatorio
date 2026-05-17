<?php
include("../../inc/auth.inc");
include("../../inc/start.inc");

header('Content-Type: application/json');

$codiceMedico = $_GET['codiceMedico'] ?? '';
$data         = $_GET['data'] ?? '';

if (!$codiceMedico || !$data) {
    echo json_encode(['disponibili' => [], 'occupati' => []]);
    exit;
}

// Giorno della settimana in italiano (L, M, M, G, V, S, D)
$giorni = ['D', 'L', 'M', 'M', 'G', 'V', 'S'];
$numGiorno = (int)date('w', strtotime($data)); // 0=domenica
$giornoLetter = $giorni[$numGiorno];

// Trova orari di lavoro del medico per quel giorno
$stmtOrario = $conn->prepare("
    SELECT o.oraInizio, o.oraFine
    FROM medico_orariolavoro mol
    JOIN orariolavoro o ON mol.giorno = o.giorno AND mol.oraInizio = o.oraInizio
    WHERE mol.codiceMedico = ? AND mol.giorno = ?
    LIMIT 1
");
$stmtOrario->execute([$codiceMedico, $giornoLetter]);
$orario = $stmtOrario->fetch(PDO::FETCH_ASSOC);

if (!$orario) {
    echo json_encode(['disponibili' => [], 'occupati' => []]);
    exit;
}

// Trova orari già occupati
$stmtOccupati = $conn->prepare("
    SELECT s.oraInizio FROM storico s
    JOIN esame e ON s.codiceEsame = e.codiceEsame
    WHERE e.codiceMedico = ? AND s.data = ? AND s.stato = 'prenotato'
");
$stmtOccupati->execute([$codiceMedico, $data]);
$occupatiRaw = $stmtOccupati->fetchAll(PDO::FETCH_COLUMN);

$disponibili = [];
$occupati = [];

for ($ora = (int)$orario['oraInizio']; $ora < (int)$orario['oraFine']; $ora++) {
    if (in_array($ora, $occupatiRaw)) {
        $occupati[] = $ora;
    } else {
        $disponibili[] = $ora;
    }
}

echo json_encode(['disponibili' => $disponibili, 'occupati' => $occupati]);