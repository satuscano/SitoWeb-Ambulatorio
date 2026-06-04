<?php
include("../inc/auth.inc");
$cf = $_SESSION['codiceFiscale'];
include("../inc/start.inc");

$stmtInfo = $conn->prepare("SELECT nome, cognome FROM paziente WHERE codiceFiscale = ?");
$stmtInfo->execute([$cf]);
$paziente = $stmtInfo->fetch(PDO::FETCH_ASSOC);

// Filtri
$filtroReparto = $_GET['reparto'] ?? '';
$filtroMedico  = $_GET['medico'] ?? '';
$filtroDal     = $_GET['dal'] ?? '';
$filtroAl      = $_GET['al'] ?? '';
$cercaDiagnosi = trim($_GET['cerca'] ?? '');

// Tutti i reparti per il filtro
$stmtRep = $conn->query("SELECT codiceReparto, nomeReparto FROM reparto ORDER BY nomeReparto");
$reparti = $stmtRep->fetchAll(PDO::FETCH_ASSOC);

// Medici che hanno visitato questo paziente
$stmtMedici = $conn->prepare("
    SELECT DISTINCT m.codiceMedico, m.nome, m.cognome
    FROM storico s
    JOIN esame e ON s.codiceEsame = e.codiceEsame
    JOIN medico m ON e.codiceMedico = m.codiceMedico
    WHERE s.codiceFiscale = ? AND s.stato = 'completato'
    ORDER BY m.cognome
");
$stmtMedici->execute([$cf]);
$mediciVisti = $stmtMedici->fetchAll(PDO::FETCH_ASSOC);

// Query storico con filtri
$where = ["s.codiceFiscale = ?", "s.stato = 'completato'"];
$params = [$cf];

if ($filtroReparto) {
    $where[] = "r.codiceReparto = ?";
    $params[] = $filtroReparto;
}
if ($filtroMedico) {
    $where[] = "m.codiceMedico = ?";
    $params[] = $filtroMedico;
}
if ($filtroDal) {
    $where[] = "s.data >= ?";
    $params[] = $filtroDal;
}
if ($filtroAl) {
    $where[] = "s.data <= ?";
    $params[] = $filtroAl;
}
if ($cercaDiagnosi) {
    $where[] = "(s.diagnosi LIKE ? OR s.prescrizione LIKE ? OR e.referto LIKE ?)";
    $params[] = "%$cercaDiagnosi%";
    $params[] = "%$cercaDiagnosi%";
    $params[] = "%$cercaDiagnosi%";
}

$whereSQL = implode(' AND ', $where);

$stmtStorico = $conn->prepare("
    SELECT s.codiceEsame, s.data, s.oraInizio, s.oraFine,
           s.diagnosi, s.prescrizione, e.referto,
           m.nome AS nomeMedico, m.cognome AS cognomeMedico,
           r.nomeReparto, a.piano
    FROM storico s
    JOIN esame e ON s.codiceEsame = e.codiceEsame
    JOIN medico m ON e.codiceMedico = m.codiceMedico
    JOIN ambulatorio a ON e.codiceAmbulatorio = a.codiceAmbulatorio
    JOIN reparto r ON a.codiceReparto = r.codiceReparto
    WHERE $whereSQL
    ORDER BY s.data DESC, s.oraInizio DESC
");
$stmtStorico->execute($params);
$storico = $stmtStorico->fetchAll(PDO::FETCH_ASSOC);

// Totale esami (senza filtri)
$stmtTot = $conn->prepare("SELECT COUNT(*) FROM storico WHERE codiceFiscale = ? AND stato = 'completato'");
$stmtTot->execute([$cf]);
$totale = $stmtTot->fetchColumn();
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <?php include("../inc/header.inc"); ?>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Storico Esami</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: 'Nunito', sans-serif; background: #f0f4f8; color: #2d3748; }

        .sidebar { position: fixed; top: 0; left: 0; width: 240px; height: 100vh; background: linear-gradient(180deg, #1a2a3a 0%, #0f1e2d 100%); display: flex; flex-direction: column; z-index: 100; box-shadow: 4px 0 20px rgba(0,0,0,0.15); }
        .sidebar-header { padding: 28px 24px 20px; border-bottom: 1px solid rgba(255,255,255,0.08); }
        .sidebar-logo { font-size: 13px; font-weight: 800; color: #64a19d; text-transform: uppercase; letter-spacing: 2px; }
        .sidebar-subtitle { font-size: 11px; color: rgba(255,255,255,0.35); margin-top: 2px; }
        .sidebar-user { padding: 20px 24px; display: flex; align-items: center; gap: 12px; border-bottom: 1px solid rgba(255,255,255,0.08); }
        .user-avatar { width: 40px; height: 40px; background: linear-gradient(135deg, #64a19d, #7464a1); border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 16px; font-weight: 700; color: white; flex-shrink: 0; }
        .user-name { font-size: 14px; font-weight: 700; color: white; }
        .user-role { font-size: 11px; color: #64a19d; text-transform: uppercase; letter-spacing: 1px; }
        .sidebar-nav { flex: 1; padding: 16px 0; }
        .nav-section-title { font-size: 10px; font-weight: 700; color: rgba(255,255,255,0.25); text-transform: uppercase; letter-spacing: 2px; padding: 12px 24px 6px; }
        .sidebar-nav a { display: flex; align-items: center; gap: 12px; padding: 10px 24px; color: rgba(255,255,255,0.55); text-decoration: none; font-size: 13.5px; font-weight: 600; transition: all 0.2s; border-left: 3px solid transparent; }
        .sidebar-nav a:hover { color: white; background: rgba(255,255,255,0.05); border-left-color: #64a19d; }
        .sidebar-nav a.active { color: white; background: rgba(100,161,157,0.15); border-left-color: #64a19d; }
        .sidebar-nav a i { width: 18px; text-align: center; font-size: 14px; }
        .sidebar-footer { padding: 16px 24px; border-top: 1px solid rgba(255,255,255,0.08); }
        .sidebar-footer a { display: flex; align-items: center; gap: 10px; color: rgba(255,255,255,0.4); text-decoration: none; font-size: 13px; transition: color 0.2s; }
        .sidebar-footer a:hover { color: #a16468; }

        .main-content { margin-left: 240px; min-height: 100vh; padding: 32px; }

        .page-header { margin-bottom: 24px; display: flex; align-items: flex-start; justify-content: space-between; }
        .page-header h1 { font-size: 26px; font-weight: 800; color: #1a2a3a; }
        .page-header p { font-size: 14px; color: #718096; margin-top: 4px; }
        .total-badge { background: white; border-radius: 10px; padding: 10px 18px; box-shadow: 0 2px 10px rgba(0,0,0,0.06); font-size: 13px; font-weight: 700; color: #718096; display: flex; align-items: center; gap: 8px; }
        .total-badge span { font-size: 20px; font-weight: 900; color: #64a19d; }

        /* FILTRI */
        .filtri-card { background: white; border-radius: 14px; box-shadow: 0 2px 12px rgba(0,0,0,0.06); padding: 20px 24px; margin-bottom: 24px; }
        .filtri-grid { display: grid; grid-template-columns: 2fr 1fr 1fr 1fr 1fr auto; gap: 12px; align-items: end; }
        .form-group-filtro { display: flex; flex-direction: column; gap: 5px; }
        .form-label-filtro { font-size: 11px; font-weight: 700; color: #a0aec0; text-transform: uppercase; letter-spacing: 0.5px; }
        .form-control-filtro { padding: 9px 12px; border: 2px solid #e2e8f0; border-radius: 8px; font-size: 13px; font-family: 'Nunito', sans-serif; color: #2d3748; transition: border-color 0.2s; background: white; }
        .form-control-filtro:focus { outline: none; border-color: #64a19d; }
        select.form-control-filtro { cursor: pointer; appearance: none; background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 12 12'%3E%3Cpath fill='%23718096' d='M6 8L1 3h10z'/%3E%3C/svg%3E"); background-repeat: no-repeat; background-position: right 10px center; padding-right: 28px; }
        .btn-filtro { padding: 9px 18px; background: linear-gradient(135deg, #64a19d, #4a8480); color: white; border: none; border-radius: 8px; font-size: 13px; font-weight: 700; font-family: 'Nunito', sans-serif; cursor: pointer; white-space: nowrap; display: flex; align-items: center; gap: 6px; }
        .btn-reset { padding: 9px 14px; background: #f7fafc; color: #718096; border: 1px solid #e2e8f0; border-radius: 8px; font-size: 13px; font-weight: 700; font-family: 'Nunito', sans-serif; cursor: pointer; text-decoration: none; display: flex; align-items: center; gap: 6px; }
        .btn-reset:hover { background: #edf2f7; color: #2d3748; }

        /* RISULTATI INFO */
        .risultati-info { font-size: 13px; color: #718096; margin-bottom: 16px; display: flex; align-items: center; gap: 8px; }
        .risultati-info strong { color: #2d3748; }

        /* CARDS ESAMI */
        .esame-card { background: white; border-radius: 14px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); margin-bottom: 14px; overflow: hidden; border-left: 4px solid #67c29c; transition: box-shadow 0.2s, transform 0.2s; }
        .esame-card:hover { box-shadow: 0 6px 24px rgba(0,0,0,0.1); transform: translateY(-1px); }

        .esame-card-header { padding: 18px 24px; display: flex; align-items: center; gap: 18px; cursor: pointer; }

        .esame-data-block { width: 58px; height: 58px; background: rgba(103,194,156,0.1); border-radius: 12px; display: flex; flex-direction: column; align-items: center; justify-content: center; flex-shrink: 0; }
        .esame-data-day { font-size: 20px; font-weight: 900; color: #67c29c; line-height: 1; }
        .esame-data-month { font-size: 10px; font-weight: 700; color: #67c29c; text-transform: uppercase; }

        .esame-main { flex: 1; }
        .esame-reparto { font-size: 15px; font-weight: 800; color: #1a2a3a; }
        .esame-meta { display: flex; gap: 16px; margin-top: 5px; flex-wrap: wrap; }
        .esame-meta-item { display: flex; align-items: center; gap: 5px; font-size: 12px; color: #718096; }
        .esame-meta-item i { color: #a0aec0; font-size: 11px; }

        .esame-ora { padding: 4px 12px; background: rgba(103,194,156,0.1); color: #3d9970; border-radius: 20px; font-size: 12px; font-weight: 800; }

        .esame-toggle { width: 28px; height: 28px; border-radius: 50%; background: #f7fafc; border: none; cursor: pointer; display: flex; align-items: center; justify-content: center; color: #a0aec0; font-size: 12px; transition: all 0.2s; flex-shrink: 0; }
        .esame-toggle:hover { background: rgba(100,161,157,0.1); color: #64a19d; }
        .esame-toggle.open { background: rgba(100,161,157,0.1); color: #64a19d; transform: rotate(180deg); }

        .esame-card-body { display: none; padding: 0 24px 20px; border-top: 1px solid #f0f4f8; }
        .esame-card-body.show { display: block; }

        .dettagli-grid { display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 12px; margin-top: 16px; }
        .dettaglio-box { background: #f7fafc; border-radius: 10px; padding: 14px; }
        .dettaglio-label { font-size: 10px; font-weight: 800; text-transform: uppercase; letter-spacing: 0.5px; color: #a0aec0; margin-bottom: 6px; display: flex; align-items: center; gap: 5px; }
        .dettaglio-value { font-size: 13px; color: #2d3748; line-height: 1.5; }
        .dettaglio-empty { color: #cbd5e0; font-style: italic; }

        /* EMPTY */
        .empty-state { text-align: center; padding: 60px 20px; color: #a0aec0; background: white; border-radius: 14px; box-shadow: 0 2px 12px rgba(0,0,0,0.06); }
        .empty-state i { font-size: 48px; margin-bottom: 16px; display: block; }
        .empty-state h3 { font-size: 17px; font-weight: 700; color: #718096; margin-bottom: 8px; }
        .empty-state p { font-size: 14px; }
    </style>
</head>
<body>

<div class="sidebar">
    <div class="sidebar-header">
        <div class="sidebar-logo">Ambulatorio</div>
        <div class="sidebar-subtitle">Polispecialistico A. Tuscano</div>
    </div>
    <div class="sidebar-user">
        <div class="user-avatar"><?= strtoupper(substr($paziente['nome'], 0, 1) . substr($paziente['cognome'], 0, 1)) ?></div>
        <div>
            <div class="user-name"><?= htmlspecialchars($paziente['nome'] . ' ' . $paziente['cognome']) ?></div>
            <div class="user-role">Paziente</div>
        </div>
    </div>
    <nav class="sidebar-nav">
        <div class="nav-section-title">Principale</div>
        <a href="dashboard.php"><i class="fas fa-home"></i> Dashboard</a>
        <a href="prenotaEsame.php"><i class="fas fa-calendar-plus"></i> Prenota Esame</a>
        <a href="appuntamenti.php"><i class="fas fa-calendar-alt"></i> Appuntamenti</a>
        <a href="storico.php" class="active"><i class="fas fa-file-medical"></i> Storico Esami</a>
        <div class="nav-section-title">Account</div>
        <a href="modificaProfilo.php"><i class="fas fa-user-edit"></i> Modifica Profilo</a>
        <a href="pagamenti.php"><i class="fas fa-credit-card"></i> Pagamenti</a>
    </nav>
    <div class="sidebar-footer">
        <a href="../../root/loginPage.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
    </div>
</div>

<div class="main-content">

    <div class="page-header">
        <div>
            <h1>Storico Esami</h1>
            <p>Tutti i tuoi esami completati con diagnosi e referti</p>
        </div>
        <div class="total-badge">
            <span><?= $totale ?></span> esami totali
        </div>
    </div>

    <!-- FILTRI -->
    <div class="filtri-card">
        <form method="GET">
            <div class="filtri-grid">
                <div class="form-group-filtro">
                    <label class="form-label-filtro"><i class="fas fa-search"></i> Cerca</label>
                    <input type="text" class="form-control-filtro" name="cerca" placeholder="Diagnosi, referto, prescrizione..." value="<?= htmlspecialchars($cercaDiagnosi) ?>">
                </div>
                <div class="form-group-filtro">
                    <label class="form-label-filtro"><i class="fas fa-hospital"></i> Reparto</label>
                    <select class="form-control-filtro" name="reparto">
                        <option value="">Tutti</option>
                        <?php foreach ($reparti as $r): ?>
                        <option value="<?= $r['codiceReparto'] ?>" <?= $filtroReparto == $r['codiceReparto'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($r['nomeReparto']) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group-filtro">
                    <label class="form-label-filtro"><i class="fas fa-user-md"></i> Medico</label>
                    <select class="form-control-filtro" name="medico">
                        <option value="">Tutti</option>
                        <?php foreach ($mediciVisti as $m): ?>
                        <option value="<?= $m['codiceMedico'] ?>" <?= $filtroMedico == $m['codiceMedico'] ? 'selected' : '' ?>>
                            Dr. <?= htmlspecialchars($m['cognome'] . ' ' . $m['nome']) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group-filtro">
                    <label class="form-label-filtro"><i class="fas fa-calendar"></i> Dal</label>
                    <input type="date" class="form-control-filtro" name="dal" value="<?= htmlspecialchars($filtroDal) ?>">
                </div>
                <div class="form-group-filtro">
                    <label class="form-label-filtro"><i class="fas fa-calendar"></i> Al</label>
                    <input type="date" class="form-control-filtro" name="al" value="<?= htmlspecialchars($filtroAl) ?>">
                </div>
                <div style="display:flex; gap:8px;">
                    <button type="submit" class="btn-filtro"><i class="fas fa-filter"></i> Filtra</button>
                    <a href="storico.php" class="btn-reset"><i class="fas fa-times"></i></a>
                </div>
            </div>
        </form>
    </div>

    <!-- RISULTATI -->
    <?php if (!empty($cercaDiagnosi) || $filtroReparto || $filtroMedico || $filtroDal || $filtroAl): ?>
    <div class="risultati-info">
        <i class="fas fa-info-circle" style="color:#64a19d;"></i>
        Trovati <strong><?= count($storico) ?></strong> esami con i filtri applicati.
    </div>
    <?php endif; ?>

    <?php if (empty($storico)): ?>
    <div class="empty-state">
        <?php if (!empty($cercaDiagnosi) || $filtroReparto || $filtroMedico || $filtroDal || $filtroAl): ?>
            <i class="fas fa-search"></i>
            <h3>Nessun risultato</h3>
            <p>Nessun esame trovato con i filtri selezionati.<br>
            <a href="storico.php" style="color:#64a19d; font-weight:700;">Rimuovi i filtri →</a></p>
        <?php else: ?>
            <i class="fas fa-file-medical"></i>
            <h3>Nessun esame completato</h3>
            <p>Non hai ancora completato nessun esame.<br>
            <a href="prenotaEsame.php" style="color:#64a19d; font-weight:700;">Prenota il tuo primo esame →</a></p>
        <?php endif; ?>
    </div>
    <?php else: ?>

    <?php foreach ($storico as $esame): ?>
    <div class="esame-card">
        <div class="esame-card-header" onclick="toggleEsame(<?= $esame['codiceEsame'] ?>)">
            <div class="esame-data-block">
                <div class="esame-data-day"><?= date('d', strtotime($esame['data'])) ?></div>
                <div class="esame-data-month"><?= date('M', strtotime($esame['data'])) ?></div>
            </div>
            <div class="esame-main">
                <div class="esame-reparto"><?= htmlspecialchars($esame['nomeReparto']) ?></div>
                <div class="esame-meta">
                    <span class="esame-meta-item"><i class="fas fa-user-md"></i> Dr. <?= htmlspecialchars($esame['nomeMedico'] . ' ' . $esame['cognomeMedico']) ?></span>
                    <span class="esame-meta-item"><i class="fas fa-calendar"></i> <?= date('d/m/Y', strtotime($esame['data'])) ?></span>
                    <span class="esame-meta-item"><i class="fas fa-building"></i> Piano <?= $esame['piano'] ?></span>
                    <?php if ($esame['diagnosi']): ?>
                    <span class="esame-meta-item"><i class="fas fa-stethoscope"></i> <?= htmlspecialchars(substr($esame['diagnosi'], 0, 40)) ?><?= strlen($esame['diagnosi']) > 40 ? '...' : '' ?></span>
                    <?php endif; ?>
                </div>
            </div>
            <div class="esame-ora"><?= $esame['oraInizio'] ?>:00</div>
            <button class="esame-toggle" id="toggle-<?= $esame['codiceEsame'] ?>" onclick="event.stopPropagation(); toggleEsame(<?= $esame['codiceEsame'] ?>)">
                <i class="fas fa-chevron-down"></i>
            </button>
        </div>

        <div class="esame-card-body" id="body-<?= $esame['codiceEsame'] ?>">
            <div class="dettagli-grid">
                <div class="dettaglio-box">
                    <div class="dettaglio-label"><i class="fas fa-stethoscope" style="color:#64a19d;"></i> Diagnosi</div>
                    <div class="dettaglio-value">
                        <?= $esame['diagnosi'] ? htmlspecialchars($esame['diagnosi']) : '<span class="dettaglio-empty">Non disponibile</span>' ?>
                    </div>
                </div>
                <div class="dettaglio-box">
                    <div class="dettaglio-label"><i class="fas fa-pills" style="color:#7464a1;"></i> Prescrizione</div>
                    <div class="dettaglio-value">
                        <?= $esame['prescrizione'] ? htmlspecialchars($esame['prescrizione']) : '<span class="dettaglio-empty">Nessuna prescrizione</span>' ?>
                    </div>
                </div>
                <div class="dettaglio-box">
                    <div class="dettaglio-label"><i class="fas fa-file-medical-alt" style="color:#1cabc4;"></i> Referto</div>
                    <div class="dettaglio-value">
                        <?= $esame['referto'] ? htmlspecialchars($esame['referto']) : '<span class="dettaglio-empty">Referto non disponibile</span>' ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php endforeach; ?>

    <?php endif; ?>
</div>

<script>
function toggleEsame(id) {
    const body = document.getElementById('body-' + id);
    const toggle = document.getElementById('toggle-' + id);
    body.classList.toggle('show');
    toggle.classList.toggle('open');
}
</script>

</body>
</html>