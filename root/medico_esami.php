<?php
include("../../inc/auth.inc");
if ($_SESSION['ruolo'] !== 'medico') {
    header("Location: /AMBULATORIO/root/loginPage.php");
    exit;
}
$cfMedico = $_SESSION['codiceFiscale'];
include("../../inc/start.inc");

$stmtMed = $conn->prepare("SELECT m.codiceMedico, m.nome, m.cognome, r.nomeReparto FROM medico m JOIN reparto r ON m.codiceReparto = r.codiceReparto WHERE m.codiceFiscale = ?");
$stmtMed->execute([$cfMedico]);
$medico = $stmtMed->fetch(PDO::FETCH_ASSOC);
$codiceMedico = $medico['codiceMedico'];

// Filtri
$filtroStato  = $_GET['stato'] ?? '';
$filtroDal    = $_GET['dal'] ?? '';
$filtroAl     = $_GET['al'] ?? '';
$cercaPaziente = trim($_GET['cerca'] ?? '');

$where = ["e.codiceMedico = ?"];
$params = [$codiceMedico];

if ($filtroStato) { $where[] = "s.stato = ?"; $params[] = $filtroStato; }
if ($filtroDal)   { $where[] = "s.data >= ?"; $params[] = $filtroDal; }
if ($filtroAl)    { $where[] = "s.data <= ?"; $params[] = $filtroAl; }
if ($cercaPaziente) {
    $where[] = "(p.nome LIKE ? OR p.cognome LIKE ? OR p.codiceFiscale LIKE ?)";
    $params[] = "%$cercaPaziente%"; $params[] = "%$cercaPaziente%"; $params[] = "%$cercaPaziente%";
}

$whereSQL = implode(' AND ', $where);

$stmtEsami = $conn->prepare("
    SELECT s.codiceEsame, s.data, s.oraInizio, s.oraFine, s.stato, s.diagnosi, s.prescrizione,
           e.referto, p.nome, p.cognome, p.codiceFiscale, r.nomeReparto, a.piano
    FROM storico s
    JOIN esame e ON s.codiceEsame = e.codiceEsame
    JOIN paziente p ON s.codiceFiscale = p.codiceFiscale
    JOIN ambulatorio a ON e.codiceAmbulatorio = a.codiceAmbulatorio
    JOIN reparto r ON a.codiceReparto = r.codiceReparto
    WHERE $whereSQL
    ORDER BY s.data DESC, s.oraInizio DESC
");
$stmtEsami->execute($params);
$esami = $stmtEsami->fetchAll(PDO::FETCH_ASSOC);

// Conteggi per stato
$statiLabels = ['prenotato' => 'Prenotati', 'completato' => 'Completati', 'cancellato' => 'Cancellati'];
$conteggi = [];
foreach (array_keys($statiLabels) as $s) {
    $stmtC = $conn->prepare("SELECT COUNT(*) FROM storico s JOIN esame e ON s.codiceEsame = e.codiceEsame WHERE e.codiceMedico = ? AND s.stato = ?");
    $stmtC->execute([$codiceMedico, $s]);
    $conteggi[$s] = $stmtC->fetchColumn();
}

// Gestione salva referto
$msgSuccess = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['azione']) && $_POST['azione'] === 'salva_referto') {
    $codiceEsame  = (int)$_POST['codiceEsame'];
    $diagnosi     = trim($_POST['diagnosi'] ?? '');
    $prescrizione = trim($_POST['prescrizione'] ?? '');
    $referto      = trim($_POST['referto'] ?? '');

    $stmtUpdStor = $conn->prepare("UPDATE storico SET diagnosi=?, prescrizione=?, stato='completato' WHERE codiceEsame=?");
    $stmtUpdStor->execute([$diagnosi, $prescrizione, $codiceEsame]);
    $stmtUpdEsame = $conn->prepare("UPDATE esame SET diagnosi=?, referto=? WHERE codiceEsame=?");
    $stmtUpdEsame->execute([$diagnosi, $referto, $codiceEsame]);

    $msgSuccess = 'Referto salvato con successo!';
    // Ricarica
    $stmtEsami->execute($params);
    $esami = $stmtEsami->fetchAll(PDO::FETCH_ASSOC);
}
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <?php include("../../inc/header.inc"); ?>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Gestione Esami</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: 'Nunito', sans-serif; background: #f0f4f8; color: #2d3748; }

        .sidebar { position: fixed; top:0; left:0; width:240px; height:100vh; background: linear-gradient(180deg,#1e1a3a 0%,#130f2d 100%); display:flex; flex-direction:column; z-index:100; box-shadow:4px 0 20px rgba(0,0,0,0.15); }
        .sidebar-header { padding:28px 24px 20px; border-bottom:1px solid rgba(255,255,255,0.08); }
        .sidebar-logo { font-size:13px; font-weight:800; color:#7464a1; text-transform:uppercase; letter-spacing:2px; }
        .sidebar-subtitle { font-size:11px; color:rgba(255,255,255,0.35); margin-top:2px; }
        .sidebar-user { padding:20px 24px; display:flex; align-items:center; gap:12px; border-bottom:1px solid rgba(255,255,255,0.08); }
        .user-avatar { width:40px; height:40px; background:linear-gradient(135deg,#7464a1,#64a19d); border-radius:50%; display:flex; align-items:center; justify-content:center; font-size:16px; font-weight:700; color:white; flex-shrink:0; }
        .user-name { font-size:14px; font-weight:700; color:white; }
        .user-role { font-size:11px; color:#7464a1; text-transform:uppercase; letter-spacing:1px; }
        .sidebar-nav { flex:1; padding:16px 0; }
        .nav-section-title { font-size:10px; font-weight:700; color:rgba(255,255,255,0.25); text-transform:uppercase; letter-spacing:2px; padding:12px 24px 6px; }
        .sidebar-nav a { display:flex; align-items:center; gap:12px; padding:10px 24px; color:rgba(255,255,255,0.55); text-decoration:none; font-size:13.5px; font-weight:600; transition:all 0.2s; border-left:3px solid transparent; }
        .sidebar-nav a:hover { color:white; background:rgba(255,255,255,0.05); border-left-color:#7464a1; }
        .sidebar-nav a.active { color:white; background:rgba(116,100,161,0.15); border-left-color:#7464a1; }
        .sidebar-nav a i { width:18px; text-align:center; font-size:14px; }
        .sidebar-footer { padding:16px 24px; border-top:1px solid rgba(255,255,255,0.08); }
        .sidebar-footer a { display:flex; align-items:center; gap:10px; color:rgba(255,255,255,0.4); text-decoration:none; font-size:13px; transition:color 0.2s; }
        .sidebar-footer a:hover { color:#a16468; }

        .main-content { margin-left:240px; min-height:100vh; padding:32px; }
        .page-header { margin-bottom:24px; display:flex; align-items:center; justify-content:space-between; }
        .page-header h1 { font-size:26px; font-weight:800; color:#1a2a3a; }
        .page-header p { font-size:14px; color:#718096; margin-top:4px; }

        /* TABS */
        .tabs { display:flex; gap:4px; background:white; border-radius:12px; padding:6px; box-shadow:0 2px 12px rgba(0,0,0,0.06); margin-bottom:20px; width:fit-content; }
        .tab-btn { padding:9px 20px; border-radius:8px; border:none; font-size:13px; font-weight:700; font-family:'Nunito',sans-serif; cursor:pointer; transition:all 0.2s; color:#718096; background:transparent; display:flex; align-items:center; gap:8px; text-decoration:none; }
        .tab-btn:hover { color:#1a2a3a; }
        .tab-btn.active.prenotato { background:rgba(100,161,157,0.12); color:#64a19d; }
        .tab-btn.active.completato { background:rgba(103,194,156,0.12); color:#3d9970; }
        .tab-btn.active.cancellato { background:rgba(161,100,104,0.12); color:#a16468; }
        .tab-btn.active.tutti { background:rgba(116,100,161,0.12); color:#7464a1; }
        .tab-badge { display:inline-flex; align-items:center; justify-content:center; width:20px; height:20px; border-radius:50%; font-size:11px; font-weight:800; background:rgba(116,100,161,0.12); color:#7464a1; }

        /* FILTRI */
        .filtri-card { background:white; border-radius:14px; box-shadow:0 2px 12px rgba(0,0,0,0.06); padding:18px 22px; margin-bottom:20px; }
        .filtri-row { display:flex; gap:12px; align-items:flex-end; }
        .fg { display:flex; flex-direction:column; gap:5px; flex:1; }
        .fl { font-size:11px; font-weight:700; color:#a0aec0; text-transform:uppercase; letter-spacing:0.5px; }
        .fc { padding:9px 12px; border:2px solid #e2e8f0; border-radius:8px; font-size:13px; font-family:'Nunito',sans-serif; color:#2d3748; transition:border-color 0.2s; background:white; }
        .fc:focus { outline:none; border-color:#7464a1; }
        .btn-filtro { padding:9px 18px; background:linear-gradient(135deg,#7464a1,#5d5081); color:white; border:none; border-radius:8px; font-size:13px; font-weight:700; font-family:'Nunito',sans-serif; cursor:pointer; white-space:nowrap; display:flex; align-items:center; gap:6px; }
        .btn-reset { padding:9px 12px; background:#f7fafc; color:#718096; border:1px solid #e2e8f0; border-radius:8px; font-size:13px; font-weight:700; font-family:'Nunito',sans-serif; cursor:pointer; text-decoration:none; display:flex; align-items:center; }

        /* ESAMI */
        .esame-card { background:white; border-radius:14px; box-shadow:0 2px 10px rgba(0,0,0,0.05); margin-bottom:12px; overflow:hidden; border-left:4px solid #e2e8f0; transition:box-shadow 0.2s, transform 0.2s; }
        .esame-card:hover { box-shadow:0 6px 24px rgba(0,0,0,0.1); transform:translateY(-1px); }
        .esame-card.prenotato { border-left-color:#64a19d; }
        .esame-card.completato { border-left-color:#67c29c; }
        .esame-card.cancellato { border-left-color:#a16468; opacity:0.75; }

        .esame-row { padding:16px 22px; display:flex; align-items:center; gap:16px; }

        .data-block { width:54px; height:54px; border-radius:10px; display:flex; flex-direction:column; align-items:center; justify-content:center; flex-shrink:0; }
        .data-block.prenotato { background:rgba(100,161,157,0.1); }
        .data-block.completato { background:rgba(103,194,156,0.1); }
        .data-block.cancellato { background:rgba(161,100,104,0.1); }
        .data-day { font-size:18px; font-weight:900; line-height:1; }
        .data-day.prenotato { color:#64a19d; }
        .data-day.completato { color:#67c29c; }
        .data-day.cancellato { color:#a16468; }
        .data-month { font-size:10px; font-weight:700; text-transform:uppercase; }
        .data-month.prenotato { color:#64a19d; }
        .data-month.completato { color:#67c29c; }
        .data-month.cancellato { color:#a16468; }

        .esame-main { flex:1; }
        .esame-paziente { font-size:15px; font-weight:800; color:#1a2a3a; }
        .esame-cf { font-size:11px; color:#a0aec0; font-family:monospace; }
        .esame-meta { display:flex; gap:14px; margin-top:5px; flex-wrap:wrap; }
        .meta-item { display:flex; align-items:center; gap:4px; font-size:12px; color:#718096; }
        .meta-item i { color:#a0aec0; font-size:11px; }

        .stato-badge { padding:4px 12px; border-radius:20px; font-size:11px; font-weight:800; white-space:nowrap; }
        .stato-badge.prenotato { background:rgba(100,161,157,0.1); color:#64a19d; }
        .stato-badge.completato { background:rgba(103,194,156,0.1); color:#3d9970; }
        .stato-badge.cancellato { background:rgba(161,100,104,0.1); color:#a16468; }

        .esame-actions { display:flex; gap:8px; flex-shrink:0; }
        .btn-sm { padding:7px 13px; border-radius:8px; font-size:12px; font-weight:700; border:none; cursor:pointer; font-family:'Nunito',sans-serif; transition:all 0.2s; display:inline-flex; align-items:center; gap:5px; text-decoration:none; white-space:nowrap; }
        .btn-referto { background:rgba(116,100,161,0.1); color:#7464a1; }
        .btn-referto:hover { background:#7464a1; color:white; }
        .btn-cartella { background:rgba(100,161,157,0.1); color:#64a19d; }
        .btn-cartella:hover { background:#64a19d; color:white; }
        .btn-dettagli { background:rgba(28,171,196,0.1); color:#1cabc4; }
        .btn-dettagli:hover { background:#1cabc4; color:white; }

        .esame-extra { display:none; padding:0 22px 16px; border-top:1px solid #f0f4f8; }
        .esame-extra.show { display:block; }
        .extra-grid { display:grid; grid-template-columns:1fr 1fr 1fr; gap:10px; margin-top:14px; }
        .extra-box { background:#f7fafc; border-radius:10px; padding:12px; }
        .extra-label { font-size:10px; font-weight:800; text-transform:uppercase; letter-spacing:0.5px; color:#a0aec0; margin-bottom:5px; }
        .extra-value { font-size:13px; color:#2d3748; }
        .extra-empty { color:#cbd5e0; font-style:italic; }

        /* MODAL REFERTO */
        .modal-overlay { display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.5); z-index:1000; align-items:center; justify-content:center; }
        .modal-overlay.show { display:flex; }
        .modal-box { background:white; border-radius:16px; padding:28px; max-width:560px; width:90%; box-shadow:0 20px 60px rgba(0,0,0,0.2); }
        .modal-box h4 { font-size:18px; font-weight:800; margin-bottom:4px; color:#1a2a3a; }
        .modal-paziente { font-size:13px; color:#718096; margin-bottom:20px; }
        .mfg { margin-bottom:15px; }
        .ml { display:block; font-size:11px; font-weight:700; color:#718096; text-transform:uppercase; letter-spacing:0.5px; margin-bottom:6px; }
        .mi { width:100%; padding:10px 14px; border:2px solid #e2e8f0; border-radius:8px; font-size:13px; font-family:'Nunito',sans-serif; color:#2d3748; transition:border-color 0.2s; }
        .mi:focus { outline:none; border-color:#7464a1; }
        textarea.mi { resize:vertical; min-height:70px; }
        .modal-actions { display:flex; gap:12px; justify-content:flex-end; margin-top:18px; }
        .btn-cancel-m { padding:10px 20px; border-radius:8px; border:1px solid #e2e8f0; background:white; color:#718096; font-weight:600; cursor:pointer; font-size:13px; font-family:'Nunito',sans-serif; }
        .btn-save-m { padding:10px 24px; border-radius:8px; border:none; background:#7464a1; color:white; font-weight:700; cursor:pointer; font-size:13px; font-family:'Nunito',sans-serif; display:flex; align-items:center; gap:6px; }

        .alert-success { background:rgba(103,194,156,0.12); border:1px solid rgba(103,194,156,0.3); border-radius:10px; padding:12px 16px; color:#2d6a4f; font-size:13px; margin-bottom:20px; display:flex; align-items:center; gap:10px; }

        .empty-state { text-align:center; padding:50px 20px; color:#a0aec0; background:white; border-radius:14px; box-shadow:0 2px 12px rgba(0,0,0,0.06); }
        .empty-state i { font-size:40px; margin-bottom:12px; display:block; }
        .empty-state p { font-size:14px; }
    </style>
</head>
<body>

<div class="sidebar">
    <div class="sidebar-header">
        <div class="sidebar-logo">Ambulatorio</div>
        <div class="sidebar-subtitle">Area Medici</div>
    </div>
    <div class="sidebar-user">
        <div class="user-avatar"><?= strtoupper(substr($medico['nome'],0,1).substr($medico['cognome'],0,1)) ?></div>
        <div>
            <div class="user-name">Dr. <?= htmlspecialchars($medico['nome'].' '.$medico['cognome']) ?></div>
            <div class="user-role"><?= htmlspecialchars($medico['nomeReparto']) ?></div>
        </div>
    </div>
    <nav class="sidebar-nav">
        <div class="nav-section-title">Principale</div>
        <a href="dashboardMedico.php"><i class="fas fa-home"></i> Dashboard</a>
        <a href="esamiMedico.php" class="active"><i class="fas fa-list-alt"></i> Tutti gli Esami</a>
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
        <div>
            <h1>Gestione Esami</h1>
            <p>Visualizza, filtra e gestisci tutti i tuoi esami</p>
        </div>
    </div>

    <!-- TABS -->
    <div class="tabs">
        <a href="esamiMedico.php" class="tab-btn <?= !$filtroStato ? 'active tutti' : '' ?>">
            Tutti <span class="tab-badge"><?= array_sum($conteggi) ?></span>
        </a>
        <?php foreach ($statiLabels as $s => $label): ?>
        <a href="?stato=<?= $s ?><?= $filtroDal ? '&dal='.$filtroDal : '' ?><?= $filtroAl ? '&al='.$filtroAl : '' ?><?= $cercaPaziente ? '&cerca='.urlencode($cercaPaziente) : '' ?>" class="tab-btn <?= $filtroStato === $s ? 'active '.$s : '' ?>">
            <?= $label ?> <span class="tab-badge"><?= $conteggi[$s] ?></span>
        </a>
        <?php endforeach; ?>
    </div>

    <!-- FILTRI -->
    <div class="filtri-card">
        <form method="GET">
            <?php if ($filtroStato): ?><input type="hidden" name="stato" value="<?= htmlspecialchars($filtroStato) ?>"><?php endif; ?>
            <div class="filtri-row">
                <div class="fg">
                    <label class="fl"><i class="fas fa-search"></i> Cerca Paziente</label>
                    <input type="text" class="fc" name="cerca" placeholder="Nome, cognome o codice fiscale..." value="<?= htmlspecialchars($cercaPaziente) ?>">
                </div>
                <div class="fg">
                    <label class="fl"><i class="fas fa-calendar"></i> Dal</label>
                    <input type="date" class="fc" name="dal" value="<?= htmlspecialchars($filtroDal) ?>">
                </div>
                <div class="fg">
                    <label class="fl"><i class="fas fa-calendar"></i> Al</label>
                    <input type="date" class="fc" name="al" value="<?= htmlspecialchars($filtroAl) ?>">
                </div>
                <button type="submit" class="btn-filtro"><i class="fas fa-filter"></i> Filtra</button>
                <a href="esamiMedico.php" class="btn-reset"><i class="fas fa-times"></i></a>
            </div>
        </form>
    </div>

    <!-- LISTA -->
    <?php if (empty($esami)): ?>
    <div class="empty-state">
        <i class="fas fa-list-alt"></i>
        <p>Nessun esame trovato con i filtri selezionati.</p>
    </div>
    <?php else: ?>

    <p style="font-size:13px; color:#718096; margin-bottom:14px;">
        <i class="fas fa-info-circle" style="color:#7464a1;"></i>
        Trovati <strong><?= count($esami) ?></strong> esami
    </p>

    <?php foreach ($esami as $esame): ?>
    <div class="esame-card <?= $esame['stato'] ?>">
        <div class="esame-row">
            <div class="data-block <?= $esame['stato'] ?>">
                <div class="data-day <?= $esame['stato'] ?>"><?= date('d', strtotime($esame['data'])) ?></div>
                <div class="data-month <?= $esame['stato'] ?>"><?= date('M', strtotime($esame['data'])) ?></div>
            </div>
            <div class="esame-main">
                <div class="esame-paziente"><?= htmlspecialchars($esame['nome'].' '.$esame['cognome']) ?></div>
                <div class="esame-cf"><?= htmlspecialchars($esame['codiceFiscale']) ?></div>
                <div class="esame-meta">
                    <span class="meta-item"><i class="fas fa-calendar"></i> <?= date('d/m/Y', strtotime($esame['data'])) ?></span>
                    <span class="meta-item"><i class="fas fa-clock"></i> ore <?= $esame['oraInizio'] ?>:00</span>
                    <span class="meta-item"><i class="fas fa-building"></i> Piano <?= $esame['piano'] ?></span>
                    <?php if ($esame['diagnosi']): ?>
                    <span class="meta-item"><i class="fas fa-stethoscope"></i> <?= htmlspecialchars(substr($esame['diagnosi'],0,35)) ?><?= strlen($esame['diagnosi'])>35 ? '...' : '' ?></span>
                    <?php endif; ?>
                </div>
            </div>
            <span class="stato-badge <?= $esame['stato'] ?>">
                <?= ['prenotato'=>'Prenotato','completato'=>'Completato','cancellato'=>'Cancellato'][$esame['stato']] ?>
            </span>
            <div class="esame-actions">
                <a href="cartellaPaziente.php?cf=<?= urlencode($esame['codiceFiscale']) ?>" class="btn-sm btn-cartella">
                    <i class="fas fa-folder-open"></i> Cartella
                </a>
                <?php if ($esame['stato'] === 'prenotato'): ?>
                <button class="btn-sm btn-referto" onclick="openReferto(<?= $esame['codiceEsame'] ?>, '<?= htmlspecialchars($esame['nome'].' '.$esame['cognome']) ?>')">
                    <i class="fas fa-file-medical-alt"></i> Referto
                </button>
                <?php elseif ($esame['stato'] === 'completato'): ?>
                <button class="btn-sm btn-dettagli" onclick="toggleExtra(<?= $esame['codiceEsame'] ?>)">
                    <i class="fas fa-eye"></i> Dettagli
                </button>
                <?php endif; ?>
            </div>
        </div>
        <?php if ($esame['stato'] === 'completato' && ($esame['diagnosi'] || $esame['prescrizione'] || $esame['referto'])): ?>
        <div class="esame-extra" id="extra-<?= $esame['codiceEsame'] ?>">
            <div class="extra-grid">
                <div class="extra-box">
                    <div class="extra-label"><i class="fas fa-stethoscope" style="color:#64a19d;"></i> Diagnosi</div>
                    <div class="extra-value"><?= $esame['diagnosi'] ? htmlspecialchars($esame['diagnosi']) : '<span class="extra-empty">—</span>' ?></div>
                </div>
                <div class="extra-box">
                    <div class="extra-label"><i class="fas fa-pills" style="color:#7464a1;"></i> Prescrizione</div>
                    <div class="extra-value"><?= $esame['prescrizione'] ? htmlspecialchars($esame['prescrizione']) : '<span class="extra-empty">—</span>' ?></div>
                </div>
                <div class="extra-box">
                    <div class="extra-label"><i class="fas fa-file-alt" style="color:#1cabc4;"></i> Referto</div>
                    <div class="extra-value"><?= $esame['referto'] ? htmlspecialchars($esame['referto']) : '<span class="extra-empty">—</span>' ?></div>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>
    <?php endforeach; ?>
    <?php endif; ?>
</div>

<!-- MODAL REFERTO -->
<div class="modal-overlay" id="refertoModal">
    <div class="modal-box">
        <h4><i class="fas fa-file-medical-alt" style="color:#7464a1;"></i> Inserisci Referto</h4>
        <p class="modal-paziente" id="pazienteLabel"></p>
        <form method="POST">
            <input type="hidden" name="azione" value="salva_referto">
            <input type="hidden" name="codiceEsame" id="codiceEsameInput">
            <?php if ($filtroStato): ?><input type="hidden" name="stato_redirect" value="<?= htmlspecialchars($filtroStato) ?>"><?php endif; ?>
            <div class="mfg">
                <label class="ml">Diagnosi *</label>
                <input type="text" class="mi" name="diagnosi" required placeholder="Es. Controllo cardiologico nella norma">
            </div>
            <div class="mfg">
                <label class="ml">Referto</label>
                <textarea class="mi" name="referto" rows="3" placeholder="Descrizione dettagliata..."></textarea>
            </div>
            <div class="mfg">
                <label class="ml">Prescrizione</label>
                <textarea class="mi" name="prescrizione" rows="2" placeholder="Farmaci, terapie, indicazioni..."></textarea>
            </div>
            <div class="modal-actions">
                <button type="button" class="btn-cancel-m" onclick="closeReferto()">Annulla</button>
                <button type="submit" class="btn-save-m"><i class="fas fa-save"></i> Salva e Completa Esame</button>
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
function closeReferto() { document.getElementById('refertoModal').classList.remove('show'); }
document.getElementById('refertoModal').addEventListener('click', function(e) { if(e.target===this) closeReferto(); });
function toggleExtra(id) { document.getElementById('extra-'+id).classList.toggle('show'); }
</script>
</body>
</html>