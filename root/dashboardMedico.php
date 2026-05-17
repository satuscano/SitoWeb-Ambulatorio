<?php
include("../../inc/auth.inc");
if ($_SESSION['ruolo'] !== 'medico') {
    header("Location: /AMBULATORIO/root/loginPage.php");
    exit;
}
$cfMedico = $_SESSION['codiceFiscale'];
include("../../inc/start.inc");

// Dati medico
$stmtMed = $conn->prepare("
    SELECT m.codiceMedico, m.nome, m.cognome, m.orario, m.primario,
           r.nomeReparto, s.tipo AS specializzazione
    FROM medico m
    JOIN reparto r ON m.codiceReparto = r.codiceReparto
    LEFT JOIN specializzazione s ON m.codiceMedico = s.codiceMedico
    WHERE m.codiceFiscale = ?
");
$stmtMed->execute([$cfMedico]);
$medico = $stmtMed->fetch(PDO::FETCH_ASSOC);
$codiceMedico = $medico['codiceMedico'];

// Esami di oggi
$stmtOggi = $conn->prepare("
    SELECT s.codiceEsame, s.oraInizio, s.oraFine, s.stato,
           p.nome, p.cognome, p.codiceFiscale, r.nomeReparto
    FROM storico s
    JOIN esame e ON s.codiceEsame = e.codiceEsame
    JOIN paziente p ON s.codiceFiscale = p.codiceFiscale
    JOIN ambulatorio a ON e.codiceAmbulatorio = a.codiceAmbulatorio
    JOIN reparto r ON a.codiceReparto = r.codiceReparto
    WHERE e.codiceMedico = ? AND s.data = CURDATE() AND s.stato = 'prenotato'
    ORDER BY s.oraInizio ASC
");
$stmtOggi->execute([$codiceMedico]);
$esamiOggi = $stmtOggi->fetchAll(PDO::FETCH_ASSOC);

// Prossimi esami (futuri, non oggi)
$stmtFuturi = $conn->prepare("
    SELECT s.codiceEsame, s.data, s.oraInizio, s.stato,
           p.nome, p.cognome, p.codiceFiscale
    FROM storico s
    JOIN esame e ON s.codiceEsame = e.codiceEsame
    JOIN paziente p ON s.codiceFiscale = p.codiceFiscale
    WHERE e.codiceMedico = ? AND s.data > CURDATE() AND s.stato = 'prenotato'
    ORDER BY s.data ASC, s.oraInizio ASC
    LIMIT 8
");
$stmtFuturi->execute([$codiceMedico]);
$esamisFuturi = $stmtFuturi->fetchAll(PDO::FETCH_ASSOC);

// Statistiche
$stmtTot = $conn->prepare("SELECT COUNT(*) FROM storico s JOIN esame e ON s.codiceEsame = e.codiceEsame WHERE e.codiceMedico = ? AND s.stato = 'completato'");
$stmtTot->execute([$codiceMedico]);
$totaleCompletati = $stmtTot->fetchColumn();

$stmtMese = $conn->prepare("SELECT COUNT(*) FROM storico s JOIN esame e ON s.codiceEsame = e.codiceEsame WHERE e.codiceMedico = ? AND s.stato = 'completato' AND MONTH(s.data) = MONTH(CURDATE()) AND YEAR(s.data) = YEAR(CURDATE())");
$stmtMese->execute([$codiceMedico]);
$esamisMese = $stmtMese->fetchColumn();

$stmtPazienti = $conn->prepare("SELECT COUNT(DISTINCT s.codiceFiscale) FROM storico s JOIN esame e ON s.codiceEsame = e.codiceEsame WHERE e.codiceMedico = ?");
$stmtPazienti->execute([$codiceMedico]);
$totalePazienti = $stmtPazienti->fetchColumn();

// Gestione referto/diagnosi
$msgSuccess = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['azione'])) {
    if ($_POST['azione'] === 'salva_referto') {
        $codiceEsame = (int)$_POST['codiceEsame'];
        $diagnosi    = trim($_POST['diagnosi'] ?? '');
        $prescrizione = trim($_POST['prescrizione'] ?? '');
        $referto     = trim($_POST['referto'] ?? '');

        // Aggiorna storico
        $stmtUpdStor = $conn->prepare("UPDATE storico SET diagnosi=?, prescrizione=?, stato='completato' WHERE codiceEsame=?");
        $stmtUpdStor->execute([$diagnosi, $prescrizione, $codiceEsame]);

        // Aggiorna esame
        $stmtUpdEsame = $conn->prepare("UPDATE esame SET diagnosi=?, referto=? WHERE codiceEsame=?");
        $stmtUpdEsame->execute([$diagnosi, $referto, $codiceEsame]);

        $msgSuccess = 'Referto salvato con successo!';

        // Ricarica
        $stmtOggi->execute([$codiceMedico]);
        $esamiOggi = $stmtOggi->fetchAll(PDO::FETCH_ASSOC);
    }
}
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <?php include("../../inc/header.inc"); ?>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Dashboard Medico</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: 'Nunito', sans-serif; background: #f0f4f8; color: #2d3748; }

        .sidebar { position: fixed; top: 0; left: 0; width: 240px; height: 100vh; background: linear-gradient(180deg, #1e1a3a 0%, #130f2d 100%); display: flex; flex-direction: column; z-index: 100; box-shadow: 4px 0 20px rgba(0,0,0,0.15); }
        .sidebar-header { padding: 28px 24px 20px; border-bottom: 1px solid rgba(255,255,255,0.08); }
        .sidebar-logo { font-size: 13px; font-weight: 800; color: #7464a1; text-transform: uppercase; letter-spacing: 2px; }
        .sidebar-subtitle { font-size: 11px; color: rgba(255,255,255,0.35); margin-top: 2px; letter-spacing: 1px; }
        .sidebar-user { padding: 20px 24px; display: flex; align-items: center; gap: 12px; border-bottom: 1px solid rgba(255,255,255,0.08); }
        .user-avatar { width: 40px; height: 40px; background: linear-gradient(135deg, #7464a1, #64a19d); border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 16px; font-weight: 700; color: white; flex-shrink: 0; }
        .user-name { font-size: 14px; font-weight: 700; color: white; }
        .user-role { font-size: 11px; color: #7464a1; text-transform: uppercase; letter-spacing: 1px; }
        .sidebar-nav { flex: 1; padding: 16px 0; }
        .nav-section-title { font-size: 10px; font-weight: 700; color: rgba(255,255,255,0.25); text-transform: uppercase; letter-spacing: 2px; padding: 12px 24px 6px; }
        .sidebar-nav a { display: flex; align-items: center; gap: 12px; padding: 10px 24px; color: rgba(255,255,255,0.55); text-decoration: none; font-size: 13.5px; font-weight: 600; transition: all 0.2s; border-left: 3px solid transparent; }
        .sidebar-nav a:hover { color: white; background: rgba(255,255,255,0.05); border-left-color: #7464a1; }
        .sidebar-nav a.active { color: white; background: rgba(116,100,161,0.15); border-left-color: #7464a1; }
        .sidebar-nav a i { width: 18px; text-align: center; font-size: 14px; }
        .sidebar-footer { padding: 16px 24px; border-top: 1px solid rgba(255,255,255,0.08); }
        .sidebar-footer a { display: flex; align-items: center; gap: 10px; color: rgba(255,255,255,0.4); text-decoration: none; font-size: 13px; transition: color 0.2s; }
        .sidebar-footer a:hover { color: #a16468; }

        .main-content { margin-left: 240px; min-height: 100vh; padding: 32px; }
        .page-header { margin-bottom: 28px; }
        .page-header h1 { font-size: 26px; font-weight: 800; color: #1a2a3a; }
        .page-header p { font-size: 14px; color: #718096; margin-top: 4px; }

        .stats-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 20px; margin-bottom: 28px; }
        .stat-card { background: white; border-radius: 14px; padding: 22px; box-shadow: 0 2px 12px rgba(0,0,0,0.06); display: flex; align-items: center; gap: 16px; border-left: 4px solid transparent; }
        .stat-card.purple { border-left-color: #7464a1; }
        .stat-card.teal { border-left-color: #64a19d; }
        .stat-card.info { border-left-color: #1cabc4; }
        .stat-icon { width: 50px; height: 50px; border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 20px; flex-shrink: 0; }
        .stat-icon.purple { background: rgba(116,100,161,0.12); color: #7464a1; }
        .stat-icon.teal { background: rgba(100,161,157,0.12); color: #64a19d; }
        .stat-icon.info { background: rgba(28,171,196,0.12); color: #1cabc4; }
        .stat-value { font-size: 28px; font-weight: 800; color: #1a2a3a; line-height: 1; }
        .stat-label { font-size: 12px; color: #718096; margin-top: 4px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px; }

        .content-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 20px; }

        .card { background: white; border-radius: 14px; box-shadow: 0 2px 12px rgba(0,0,0,0.06); }
        .card-header { padding: 18px 24px; border-bottom: 1px solid #f0f4f8; display: flex; align-items: center; justify-content: space-between; }
        .card-header h3 { font-size: 15px; font-weight: 700; color: #1a2a3a; display: flex; align-items: center; gap: 8px; }
        .card-header h3 i { color: #7464a1; }
        .card-body { padding: 20px 24px; }

        /* ESAMI OGGI */
        .esame-oggi-item {
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            padding: 16px;
            margin-bottom: 12px;
            transition: border-color 0.2s;
        }
        .esame-oggi-item:hover { border-color: #7464a1; }
        .esame-oggi-item:last-child { margin-bottom: 0; }

        .esame-header { display: flex; align-items: center; justify-content: space-between; margin-bottom: 10px; }
        .esame-ora { font-size: 13px; font-weight: 800; color: #7464a1; background: rgba(116,100,161,0.1); padding: 4px 12px; border-radius: 20px; }
        .esame-paziente { font-size: 15px; font-weight: 700; color: #1a2a3a; }
        .esame-cf { font-size: 11px; color: #a0aec0; font-family: monospace; }

        .btn-referto { padding: 7px 14px; background: rgba(116,100,161,0.1); color: #7464a1; border: none; border-radius: 8px; font-size: 12px; font-weight: 700; cursor: pointer; transition: all 0.2s; display: inline-flex; align-items: center; gap: 6px; }
        .btn-referto:hover { background: #7464a1; color: white; }

        .btn-cartella { padding: 7px 14px; background: rgba(100,161,157,0.1); color: #64a19d; border: none; border-radius: 8px; font-size: 12px; font-weight: 700; cursor: pointer; transition: all 0.2s; text-decoration: none; display: inline-flex; align-items: center; gap: 6px; }
        .btn-cartella:hover { background: #64a19d; color: white; }

        /* LISTA FUTURI */
        .futuro-item { display: flex; align-items: center; gap: 14px; padding: 11px 0; border-bottom: 1px solid #f7fafc; }
        .futuro-item:last-child { border-bottom: none; }
        .futuro-data { font-size: 12px; font-weight: 700; color: #64a19d; background: rgba(100,161,157,0.1); padding: 4px 10px; border-radius: 8px; white-space: nowrap; }
        .futuro-nome { font-size: 13px; font-weight: 700; color: #1a2a3a; }
        .futuro-ora { font-size: 12px; color: #718096; }

        /* MODAL REFERTO */
        .modal-overlay { display: none; position: fixed; top:0; left:0; width:100%; height:100%; background: rgba(0,0,0,0.5); z-index: 1000; align-items: center; justify-content: center; }
        .modal-overlay.show { display: flex; }
        .modal-box { background: white; border-radius: 16px; padding: 28px; max-width: 560px; width: 90%; box-shadow: 0 20px 60px rgba(0,0,0,0.2); }
        .modal-box h4 { font-size: 18px; font-weight: 800; margin-bottom: 4px; color: #1a2a3a; }
        .modal-box .paziente-label { font-size: 13px; color: #718096; margin-bottom: 20px; }
        .modal-form-group { margin-bottom: 16px; }
        .modal-label { display: block; font-size: 11px; font-weight: 700; color: #718096; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 6px; }
        .modal-input { width: 100%; padding: 10px 14px; border: 2px solid #e2e8f0; border-radius: 8px; font-size: 13px; font-family: 'Nunito', sans-serif; color: #2d3748; transition: border-color 0.2s; }
        .modal-input:focus { outline: none; border-color: #7464a1; }
        textarea.modal-input { resize: vertical; min-height: 70px; }
        .modal-actions { display: flex; gap: 12px; justify-content: flex-end; margin-top: 20px; }
        .btn-cancel-modal { padding: 10px 20px; border-radius: 8px; border: 1px solid #e2e8f0; background: white; color: #718096; font-weight: 600; cursor: pointer; font-size: 13px; }
        .btn-save-modal { padding: 10px 24px; border-radius: 8px; border: none; background: #7464a1; color: white; font-weight: 700; cursor: pointer; font-size: 13px; }

        .empty-state { text-align: center; padding: 28px; color: #a0aec0; }
        .empty-state i { font-size: 32px; margin-bottom: 10px; display: block; }
        .empty-state p { font-size: 13px; }

        .alert-success { background: rgba(103,194,156,0.12); border: 1px solid rgba(103,194,156,0.3); border-radius: 10px; padding: 12px 16px; color: #2d6a4f; font-size: 13px; margin-bottom: 20px; display: flex; align-items: center; gap: 10px; }

        .badge-primario { display: inline-block; background: rgba(228,198,98,0.15); color: #a07a00; border: 1px solid rgba(228,198,98,0.4); padding: 2px 10px; border-radius: 20px; font-size: 11px; font-weight: 700; margin-left: 8px; }

        .orario-info { background: rgba(116,100,161,0.06); border-radius: 10px; padding: 12px 16px; font-size: 13px; color: #555; margin-bottom: 0; }
        .orario-info strong { color: #7464a1; }
    </style>
</head>
<body>

<div class="sidebar">
    <div class="sidebar-header">
        <div class="sidebar-logo">Ambulatorio</div>
        <div class="sidebar-subtitle">Area Medici</div>
    </div>
    <div class="sidebar-user">
        <div class="user-avatar"><?= strtoupper(substr($medico['nome'], 0, 1) . substr($medico['cognome'], 0, 1)) ?></div>
        <div>
            <div class="user-name">Dr. <?= htmlspecialchars($medico['nome'] . ' ' . $medico['cognome']) ?></div>
            <div class="user-role"><?= htmlspecialchars($medico['nomeReparto']) ?></div>
        </div>
    </div>
    <nav class="sidebar-nav">
        <div class="nav-section-title">Principale</div>
        <a href="dashboardMedico.php" class="active"><i class="fas fa-home"></i> Dashboard</a>
        <a href="esamiMedico.php"><i class="fas fa-list-alt"></i> Tutti gli Esami</a>
        <a href="pazientiMedico.php"><i class="fas fa-users"></i> Pazienti</a>
        <div class="nav-section-title">Account</div>
        <a href="orarioMedico.php"><i class="fas fa-clock"></i> Il mio Orario</a>
    </nav>
    <div class="sidebar-footer">
        <a href="../../root/loginPage.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
    </div>
</div>

<div class="main-content">

    <?php if ($msgSuccess): ?>
    <div class="alert-success"><i class="fas fa-check-circle"></i> <?= htmlspecialchars($msgSuccess) ?></div>
    <?php endif; ?>

    <div class="page-header">
        <h1>Benvenuto, Dr. <?= htmlspecialchars($medico['cognome']) ?> <?php if ($medico['primario']): ?><span class="badge-primario"><i class="fas fa-star"></i> Primario</span><?php endif; ?></h1>
        <p><?= htmlspecialchars($medico['nomeReparto']) ?> &nbsp;·&nbsp; <?= date('l d F Y') ?></p>
    </div>

    <!-- STATS -->
    <div class="stats-grid">
        <div class="stat-card purple">
            <div class="stat-icon purple"><i class="fas fa-calendar-day"></i></div>
            <div>
                <div class="stat-value"><?= count($esamiOggi) ?></div>
                <div class="stat-label">Esami Oggi</div>
            </div>
        </div>
        <div class="stat-card teal">
            <div class="stat-icon teal"><i class="fas fa-check-circle"></i></div>
            <div>
                <div class="stat-value"><?= $esamisMese ?></div>
                <div class="stat-label">Completati questo mese</div>
            </div>
        </div>
        <div class="stat-card info">
            <div class="stat-icon info"><i class="fas fa-user-injured"></i></div>
            <div>
                <div class="stat-value"><?= $totalePazienti ?></div>
                <div class="stat-label">Pazienti Totali</div>
            </div>
        </div>
    </div>

    <div class="content-grid">

        <!-- ESAMI DI OGGI -->
        <div class="card">
            <div class="card-header">
                <h3><i class="fas fa-calendar-day"></i> Esami di Oggi</h3>
                <span style="font-size:12px; color:#a0aec0;"><?= date('d/m/Y') ?></span>
            </div>
            <div class="card-body">
                <?php if (empty($esamiOggi)): ?>
                <div class="empty-state">
                    <i class="fas fa-coffee"></i>
                    <p>Nessun esame programmato per oggi.</p>
                </div>
                <?php else: ?>
                <?php foreach ($esamiOggi as $esame): ?>
                <div class="esame-oggi-item">
                    <div class="esame-header">
                        <span class="esame-ora">ore <?= $esame['oraInizio'] ?>:00</span>
                        <div style="display:flex;gap:8px;">
                            <a href="cartellaPaziente.php?cf=<?= urlencode($esame['codiceFiscale']) ?>" class="btn-cartella">
                                <i class="fas fa-folder-open"></i> Cartella
                            </a>
                            <button class="btn-referto" onclick="openReferto(<?= $esame['codiceEsame'] ?>, '<?= htmlspecialchars($esame['nome'] . ' ' . $esame['cognome']) ?>')">
                                <i class="fas fa-file-medical-alt"></i> Referto
                            </button>
                        </div>
                    </div>
                    <div class="esame-paziente"><?= htmlspecialchars($esame['nome'] . ' ' . $esame['cognome']) ?></div>
                    <div class="esame-cf"><?= htmlspecialchars($esame['codiceFiscale']) ?></div>
                </div>
                <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

        <!-- PROSSIMI APPUNTAMENTI -->
        <div class="card">
            <div class="card-header">
                <h3><i class="fas fa-calendar-alt"></i> Prossimi Appuntamenti</h3>
                <a href="esamiMedico.php" style="font-size:12px; color:#7464a1; text-decoration:none; font-weight:700;">Vedi tutti →</a>
            </div>
            <div class="card-body">
                <?php if (empty($esamisFuturi)): ?>
                <div class="empty-state">
                    <i class="fas fa-calendar-check"></i>
                    <p>Nessun appuntamento futuro.</p>
                </div>
                <?php else: ?>
                <?php foreach ($esamisFuturi as $fut): ?>
                <div class="futuro-item">
                    <div class="futuro-data"><?= date('d/m', strtotime($fut['data'])) ?></div>
                    <div style="flex:1;">
                        <div class="futuro-nome"><?= htmlspecialchars($fut['nome'] . ' ' . $fut['cognome']) ?></div>
                        <div class="futuro-ora">ore <?= $fut['oraInizio'] ?>:00</div>
                    </div>
                    <a href="cartellaPaziente.php?cf=<?= urlencode($fut['codiceFiscale']) ?>" class="btn-cartella" style="font-size:11px; padding:5px 10px;">
                        <i class="fas fa-eye"></i>
                    </a>
                </div>
                <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

    </div>

    <!-- ORARIO -->
    <div class="card">
        <div class="card-header">
            <h3><i class="fas fa-clock"></i> Il mio Orario</h3>
        </div>
        <div class="card-body">
            <div class="orario-info">
                <strong><?= htmlspecialchars($medico['specializzazione'] ?? 'Specialista') ?></strong> &nbsp;·&nbsp;
                Orario: <strong><?= htmlspecialchars($medico['orario'] ?? 'Non definito') ?></strong>
            </div>
        </div>
    </div>

</div>

<!-- MODAL REFERTO -->
<div class="modal-overlay" id="refertoModal">
    <div class="modal-box">
        <h4><i class="fas fa-file-medical-alt" style="color:#7464a1;"></i> Inserisci Referto</h4>
        <p class="paziente-label" id="pazienteLabel"></p>
        <form method="POST">
            <input type="hidden" name="azione" value="salva_referto">
            <input type="hidden" name="codiceEsame" id="codiceEsameInput">

            <div class="modal-form-group">
                <label class="modal-label">Diagnosi</label>
                <input type="text" class="modal-input" name="diagnosi" required placeholder="Es. Controllo cardiologico regolare">
            </div>
            <div class="modal-form-group">
                <label class="modal-label">Referto</label>
                <textarea class="modal-input" name="referto" rows="3" placeholder="Descrizione dettagliata del referto..."></textarea>
            </div>
            <div class="modal-form-group">
                <label class="modal-label">Prescrizione</label>
                <textarea class="modal-input" name="prescrizione" rows="2" placeholder="Terapia, farmaci o indicazioni..."></textarea>
            </div>
            <div class="modal-actions">
                <button type="button" class="btn-cancel-modal" onclick="closeReferto()">Annulla</button>
                <button type="submit" class="btn-save-modal"><i class="fas fa-save"></i> Salva Referto</button>
            </div>
        </form>
    </div>
</div>

<script>
function openReferto(id, paziente) {
    document.getElementById('codiceEsameInput').value = id;
    document.getElementById('pazienteLabel').textContent = 'Paziente: ' + paziente;
    document.getElementById('refertoModal').classList.add('show');
}
function closeReferto() {
    document.getElementById('refertoModal').classList.remove('show');
}
document.getElementById('refertoModal').addEventListener('click', function(e) {
    if (e.target === this) closeReferto();
});
</script>

</body>
</html>