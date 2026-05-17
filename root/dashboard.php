<?php
include("../inc/auth.inc");
$cf = $_SESSION['codiceFiscale'];
include("../inc/start.inc");

// Info paziente
$stmtInfo = $conn->prepare("SELECT nome, cognome, dataNascita, ind_citta, ind_via, ind_civico, ind_cap, anamnesi FROM paziente WHERE codiceFiscale = ?");
$stmtInfo->execute([$cf]);
$paziente = $stmtInfo->fetch(PDO::FETCH_ASSOC);

// Totale esami completati
$stmt = $conn->prepare("SELECT COUNT(*) as tot FROM storico WHERE codiceFiscale = ? AND stato = 'completato'");
$stmt->execute([$cf]);
$totaleEsami = $stmt->fetch(PDO::FETCH_ASSOC)['tot'];

// Prossimo appuntamento
$stmtPross = $conn->prepare("
    SELECT s.data, s.oraInizio, m.nome AS nomeMedico, m.cognome AS cognomeMedico, r.nomeReparto
    FROM storico s
    JOIN esame e ON s.codiceEsame = e.codiceEsame
    JOIN medico m ON e.codiceMedico = m.codiceMedico
    JOIN ambulatorio a ON e.codiceAmbulatorio = a.codiceAmbulatorio
    JOIN reparto r ON a.codiceReparto = r.codiceReparto
    WHERE s.codiceFiscale = ? AND s.stato = 'prenotato' AND s.data >= CURDATE()
    ORDER BY s.data ASC, s.oraInizio ASC
    LIMIT 1
");
$stmtPross->execute([$cf]);
$prossimoApp = $stmtPross->fetch(PDO::FETCH_ASSOC);

// Ultimo esame completato
$stmtUlt = $conn->prepare("SELECT diagnosi, data FROM storico WHERE codiceFiscale = ? AND stato = 'completato' ORDER BY data DESC LIMIT 1");
$stmtUlt->execute([$cf]);
$ultimoEsame = $stmtUlt->fetch(PDO::FETCH_ASSOC);

// Esami per reparto (grafico)
$stmtRep = $conn->query("SELECT nomeReparto FROM reparto");
$reparti = array_column($stmtRep->fetchAll(PDO::FETCH_ASSOC), 'nomeReparto');
$conteggi = [];
foreach ($reparti as $rep) {
    $s = $conn->prepare("
        SELECT COUNT(*) as tot FROM storico s
        JOIN esame e ON s.codiceEsame = e.codiceEsame
        JOIN ambulatorio a ON e.codiceAmbulatorio = a.codiceAmbulatorio
        JOIN reparto r ON a.codiceReparto = r.codiceReparto
        WHERE s.codiceFiscale = ? AND r.nomeReparto = ? AND s.stato = 'completato'
    ");
    $s->execute([$cf, $rep]);
    $conteggi[] = (int)$s->fetch(PDO::FETCH_ASSOC)['tot'];
}

// Prossimi appuntamenti (lista)
$stmtApps = $conn->prepare("
    SELECT s.codiceEsame, s.data, s.oraInizio, s.diagnosi, m.nome AS nomeMedico, m.cognome AS cognomeMedico, r.nomeReparto
    FROM storico s
    JOIN esame e ON s.codiceEsame = e.codiceEsame
    JOIN medico m ON e.codiceMedico = m.codiceMedico
    JOIN ambulatorio a ON e.codiceAmbulatorio = a.codiceAmbulatorio
    JOIN reparto r ON a.codiceReparto = r.codiceReparto
    WHERE s.codiceFiscale = ? AND s.stato = 'prenotato' AND s.data >= CURDATE()
    ORDER BY s.data ASC
    LIMIT 5
");
$stmtApps->execute([$cf]);
$appuntamenti = $stmtApps->fetchAll(PDO::FETCH_ASSOC);

// Storico esami completati
$stmtStorico = $conn->prepare("
    SELECT s.codiceEsame, s.data, s.oraInizio, s.diagnosi, s.prescrizione, m.nome AS nomeMedico, m.cognome AS cognomeMedico, r.nomeReparto
    FROM storico s
    JOIN esame e ON s.codiceEsame = e.codiceEsame
    JOIN medico m ON e.codiceMedico = m.codiceMedico
    JOIN ambulatorio a ON e.codiceAmbulatorio = a.codiceAmbulatorio
    JOIN reparto r ON a.codiceReparto = r.codiceReparto
    WHERE s.codiceFiscale = ? AND s.stato = 'completato'
    ORDER BY s.data DESC
    LIMIT 10
");
$stmtStorico->execute([$cf]);
$storico = $stmtStorico->fetchAll(PDO::FETCH_ASSOC);

// Pagamenti
$stmtPag = $conn->prepare("SELECT codicePagamento, dataPagamento, somma, metodo FROM pagamento WHERE codiceFiscale = ? ORDER BY dataPagamento DESC LIMIT 5");
$stmtPag->execute([$cf]);
$pagamenti = $stmtPag->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <?php include("../inc/header.inc"); ?>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Dashboard Paziente</title>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }

        body {
            font-family: 'Nunito', sans-serif;
            background: #f0f4f8;
            color: #2d3748;
        }

        /* SIDEBAR */
        .sidebar {
            position: fixed;
            top: 0; left: 0;
            width: 240px;
            height: 100vh;
            background: linear-gradient(180deg, #1a2a3a 0%, #0f1e2d 100%);
            display: flex;
            flex-direction: column;
            padding: 0;
            z-index: 100;
            box-shadow: 4px 0 20px rgba(0,0,0,0.15);
        }

        .sidebar-header {
            padding: 28px 24px 20px;
            border-bottom: 1px solid rgba(255,255,255,0.08);
        }

        .sidebar-logo {
            font-size: 13px;
            font-weight: 800;
            color: #64a19d;
            text-transform: uppercase;
            letter-spacing: 2px;
        }

        .sidebar-subtitle {
            font-size: 11px;
            color: rgba(255,255,255,0.35);
            margin-top: 2px;
            letter-spacing: 1px;
        }

        .sidebar-user {
            padding: 20px 24px;
            display: flex;
            align-items: center;
            gap: 12px;
            border-bottom: 1px solid rgba(255,255,255,0.08);
        }

        .user-avatar {
            width: 40px;
            height: 40px;
            background: linear-gradient(135deg, #64a19d, #7464a1);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 16px;
            font-weight: 700;
            color: white;
            flex-shrink: 0;
        }

        .user-name {
            font-size: 14px;
            font-weight: 700;
            color: white;
        }

        .user-role {
            font-size: 11px;
            color: #64a19d;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .sidebar-nav {
            flex: 1;
            padding: 16px 0;
            overflow-y: auto;
        }

        .nav-section-title {
            font-size: 10px;
            font-weight: 700;
            color: rgba(255,255,255,0.25);
            text-transform: uppercase;
            letter-spacing: 2px;
            padding: 12px 24px 6px;
        }

        .sidebar-nav a {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 10px 24px;
            color: rgba(255,255,255,0.55);
            text-decoration: none;
            font-size: 13.5px;
            font-weight: 600;
            transition: all 0.2s;
            border-left: 3px solid transparent;
        }

        .sidebar-nav a:hover {
            color: white;
            background: rgba(255,255,255,0.05);
            border-left-color: #64a19d;
        }

        .sidebar-nav a.active {
            color: white;
            background: rgba(100,161,157,0.15);
            border-left-color: #64a19d;
        }

        .sidebar-nav a i {
            width: 18px;
            text-align: center;
            font-size: 14px;
        }

        .sidebar-footer {
            padding: 16px 24px;
            border-top: 1px solid rgba(255,255,255,0.08);
        }

        .sidebar-footer a {
            display: flex;
            align-items: center;
            gap: 10px;
            color: rgba(255,255,255,0.4);
            text-decoration: none;
            font-size: 13px;
            transition: color 0.2s;
        }

        .sidebar-footer a:hover { color: #a16468; }

        /* MAIN */
        .main-content {
            margin-left: 240px;
            min-height: 100vh;
            padding: 32px;
        }

        .page-header {
            margin-bottom: 28px;
        }

        .page-header h1 {
            font-size: 26px;
            font-weight: 800;
            color: #1a2a3a;
        }

        .page-header p {
            font-size: 14px;
            color: #718096;
            margin-top: 4px;
        }

        /* CARDS STATISTICHE */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 20px;
            margin-bottom: 28px;
        }

        .stat-card {
            background: white;
            border-radius: 14px;
            padding: 24px;
            box-shadow: 0 2px 12px rgba(0,0,0,0.06);
            display: flex;
            align-items: center;
            gap: 18px;
            border-left: 4px solid transparent;
            transition: transform 0.2s, box-shadow 0.2s;
        }

        .stat-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(0,0,0,0.1);
        }

        .stat-card.primary { border-left-color: #64a19d; }
        .stat-card.secondary { border-left-color: #7464a1; }
        .stat-card.info { border-left-color: #1cabc4; }

        .stat-icon {
            width: 52px;
            height: 52px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 22px;
            flex-shrink: 0;
        }

        .stat-icon.primary { background: rgba(100,161,157,0.12); color: #64a19d; }
        .stat-icon.secondary { background: rgba(116,100,161,0.12); color: #7464a1; }
        .stat-icon.info { background: rgba(28,171,196,0.12); color: #1cabc4; }

        .stat-value {
            font-size: 28px;
            font-weight: 800;
            color: #1a2a3a;
            line-height: 1;
        }

        .stat-label {
            font-size: 12px;
            color: #718096;
            margin-top: 4px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .stat-sub {
            font-size: 11px;
            color: #a0aec0;
            margin-top: 2px;
        }

        /* SEZIONE PROSSIMO APPUNTAMENTO */
        .next-appointment {
            background: linear-gradient(135deg, #64a19d 0%, #4a8480 100%);
            border-radius: 14px;
            padding: 24px;
            color: white;
            margin-bottom: 28px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            box-shadow: 0 4px 20px rgba(100,161,157,0.3);
        }

        .next-appointment .label {
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: 2px;
            opacity: 0.8;
            margin-bottom: 8px;
        }

        .next-appointment .date {
            font-size: 22px;
            font-weight: 800;
        }

        .next-appointment .details {
            font-size: 14px;
            opacity: 0.85;
            margin-top: 4px;
        }

        .next-appointment .no-app {
            font-size: 16px;
            font-weight: 600;
            opacity: 0.9;
        }

        .btn-prenotazione {
            background: white;
            color: #64a19d;
            border: none;
            padding: 12px 24px;
            border-radius: 10px;
            font-weight: 700;
            font-size: 13px;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: all 0.2s;
            white-space: nowrap;
        }

        .btn-prenotazione:hover {
            background: #f0f4f8;
            color: #4a8480;
            transform: translateY(-1px);
        }

        /* GRID PRINCIPALE */
        .content-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin-bottom: 20px;
        }

        .content-grid.full { grid-template-columns: 1fr; }

        .card {
            background: white;
            border-radius: 14px;
            box-shadow: 0 2px 12px rgba(0,0,0,0.06);
            overflow: hidden;
        }

        .card-header {
            padding: 18px 24px;
            border-bottom: 1px solid #f0f4f8;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .card-header h3 {
            font-size: 15px;
            font-weight: 700;
            color: #1a2a3a;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .card-header h3 i { color: #64a19d; }

        .card-body { padding: 20px 24px; }

        /* APPUNTAMENTI LIST */
        .appointment-item {
            display: flex;
            align-items: center;
            gap: 16px;
            padding: 12px 0;
            border-bottom: 1px solid #f7fafc;
        }

        .appointment-item:last-child { border-bottom: none; }

        .apt-date-box {
            width: 48px;
            height: 52px;
            background: rgba(100,161,157,0.1);
            border-radius: 10px;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }

        .apt-day {
            font-size: 18px;
            font-weight: 800;
            color: #64a19d;
            line-height: 1;
        }

        .apt-month {
            font-size: 10px;
            color: #64a19d;
            text-transform: uppercase;
            font-weight: 700;
        }

        .apt-info { flex: 1; }

        .apt-reparto {
            font-size: 13px;
            font-weight: 700;
            color: #1a2a3a;
        }

        .apt-medico {
            font-size: 12px;
            color: #718096;
            margin-top: 2px;
        }

        .apt-ora {
            font-size: 12px;
            font-weight: 700;
            color: #7464a1;
            background: rgba(116,100,161,0.1);
            padding: 3px 10px;
            border-radius: 20px;
        }

        .apt-actions {
            display: flex;
            gap: 6px;
        }

        .btn-sm-action {
            padding: 5px 10px;
            border-radius: 6px;
            font-size: 11px;
            font-weight: 700;
            border: none;
            cursor: pointer;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 4px;
            transition: all 0.2s;
        }

        .btn-edit { background: rgba(100,161,157,0.1); color: #64a19d; }
        .btn-edit:hover { background: #64a19d; color: white; }
        .btn-delete { background: rgba(161,100,104,0.1); color: #a16468; }
        .btn-delete:hover { background: #a16468; color: white; }

        /* STORICO TABLE */
        .storico-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 13px;
        }

        .storico-table th {
            text-align: left;
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: #a0aec0;
            padding: 8px 12px;
            background: #f7fafc;
        }

        .storico-table td {
            padding: 12px;
            border-bottom: 1px solid #f7fafc;
            color: #2d3748;
            vertical-align: middle;
        }

        .storico-table tr:last-child td { border-bottom: none; }

        .storico-table tr:hover td { background: #f7fafc; }

        .badge-reparto {
            display: inline-block;
            padding: 3px 10px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 700;
            background: rgba(100,161,157,0.1);
            color: #64a19d;
        }

        /* PAGAMENTI */
        .payment-item {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 12px 0;
            border-bottom: 1px solid #f7fafc;
        }

        .payment-item:last-child { border-bottom: none; }

        .payment-left { display: flex; align-items: center; gap: 12px; }

        .payment-icon {
            width: 36px;
            height: 36px;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 14px;
        }

        .payment-icon.card-pay { background: rgba(28,171,196,0.1); color: #1cabc4; }
        .payment-icon.cash-pay { background: rgba(103,194,156,0.1); color: #67c29c; }

        .payment-date { font-size: 12px; color: #718096; }
        .payment-method { font-size: 13px; font-weight: 600; color: #2d3748; }
        .payment-amount { font-size: 16px; font-weight: 800; color: #1a2a3a; }

        /* MODAL */
        .modal-overlay {
            display: none;
            position: fixed;
            top: 0; left: 0;
            width: 100%; height: 100%;
            background: rgba(0,0,0,0.5);
            z-index: 1000;
            align-items: center;
            justify-content: center;
        }

        .modal-overlay.show { display: flex; }

        .modal-box {
            background: white;
            border-radius: 16px;
            padding: 28px;
            max-width: 440px;
            width: 90%;
            box-shadow: 0 20px 60px rgba(0,0,0,0.2);
        }

        .modal-box h4 {
            font-size: 18px;
            font-weight: 800;
            margin-bottom: 8px;
            color: #1a2a3a;
        }

        .modal-box p {
            color: #718096;
            font-size: 14px;
            margin-bottom: 24px;
        }

        .modal-actions {
            display: flex;
            gap: 12px;
            justify-content: flex-end;
        }

        .btn-cancel-modal {
            padding: 10px 20px;
            border-radius: 8px;
            border: 1px solid #e2e8f0;
            background: white;
            color: #718096;
            font-weight: 600;
            cursor: pointer;
            font-size: 13px;
        }

        .btn-confirm-modal {
            padding: 10px 20px;
            border-radius: 8px;
            border: none;
            background: #a16468;
            color: white;
            font-weight: 700;
            cursor: pointer;
            font-size: 13px;
        }

        /* EMPTY STATE */
        .empty-state {
            text-align: center;
            padding: 32px;
            color: #a0aec0;
        }

        .empty-state i { font-size: 36px; margin-bottom: 12px; display: block; }
        .empty-state p { font-size: 14px; }

        /* ALERT */
        .alert-success-custom {
            background: rgba(103,194,156,0.12);
            border: 1px solid rgba(103,194,156,0.3);
            border-radius: 10px;
            padding: 12px 16px;
            color: #2d6a4f;
            font-size: 13px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        canvas { max-height: 200px; }
    </style>
</head>
<body>

<!-- SIDEBAR -->
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
        <a href="dashboard.php" class="active"><i class="fas fa-home"></i> Dashboard</a>
        <a href="prenotaEsame.php"><i class="fas fa-calendar-plus"></i> Prenota Esame</a>
        <a href="appuntamenti.php"><i class="fas fa-calendar-alt"></i> Appuntamenti</a>
        <a href="storico.php"><i class="fas fa-file-medical"></i> Storico Esami</a>

        <div class="nav-section-title">Account</div>
        <a href="modificaProfilo.php"><i class="fas fa-user-edit"></i> Modifica Profilo</a>
        <a href="pagamenti.php"><i class="fas fa-credit-card"></i> Pagamenti</a>
    </nav>

    <div class="sidebar-footer">
        <a href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
    </div>
</div>

<!-- MAIN CONTENT -->
<div class="main-content">

    <?php if (isset($_GET['success'])): ?>
    <div class="alert-success-custom">
        <i class="fas fa-check-circle"></i>
        <?php
        $msg = [
            'prenotato' => 'Prenotazione effettuata con successo!',
            'modificato' => 'Appuntamento modificato con successo!',
            'cancellato' => 'Appuntamento cancellato.',
            'profilo' => 'Profilo aggiornato con successo!'
        ];
        echo $msg[$_GET['success']] ?? 'Operazione completata.';
        ?>
    </div>
    <?php endif; ?>

    <div class="page-header">
        <h1>Benvenuto, <?= htmlspecialchars($paziente['nome']) ?></h1>
        <p><?= date('l d F Y') ?></p>
    </div>

    <!-- STATS -->
    <div class="stats-grid">
        <div class="stat-card primary">
            <div class="stat-icon primary"><i class="fas fa-flask"></i></div>
            <div>
                <div class="stat-value"><?= $totaleEsami ?></div>
                <div class="stat-label">Esami Completati</div>
            </div>
        </div>
        <div class="stat-card secondary">
            <div class="stat-icon secondary"><i class="fas fa-calendar-check"></i></div>
            <div>
                <div class="stat-value"><?= count($appuntamenti) ?></div>
                <div class="stat-label">Appuntamenti</div>
                <div class="stat-sub">prossimi</div>
            </div>
        </div>
        <div class="stat-card info">
            <div class="stat-icon info"><i class="fas fa-stethoscope"></i></div>
            <div>
                <div class="stat-value"><?= $ultimoEsame ? date('d/m/y', strtotime($ultimoEsame['data'])) : '-' ?></div>
                <div class="stat-label">Ultima Visita</div>
                <div class="stat-sub"><?= $ultimoEsame ? htmlspecialchars(substr($ultimoEsame['diagnosi'], 0, 20)) . '...' : 'Nessuna' ?></div>
            </div>
        </div>
    </div>

    <!-- PROSSIMO APPUNTAMENTO BANNER -->
    <div class="next-appointment">
        <div>
            <div class="label"><i class="fas fa-clock"></i> &nbsp; Prossimo Appuntamento</div>
            <?php if ($prossimoApp): ?>
                <div class="date"><?= date('d F Y', strtotime($prossimoApp['data'])) ?> — ore <?= $prossimoApp['oraInizio'] ?>:00</div>
                <div class="details">
                    <?= htmlspecialchars($prossimoApp['nomeReparto']) ?> &nbsp;·&nbsp;
                    Dr. <?= htmlspecialchars($prossimoApp['nomeMedico'] . ' ' . $prossimoApp['cognomeMedico']) ?>
                </div>
            <?php else: ?>
                <div class="no-app">Nessun appuntamento in programma</div>
            <?php endif; ?>
        </div>
        <a href="../root/prenotaEsame.php" class="btn-prenotazione">
            <i class="fas fa-plus"></i> Prenota Esame
        </a>
    </div>

    <!-- GRIGLIA: APPUNTAMENTI + GRAFICO -->
    <div class="content-grid">

        <!-- PROSSIMI APPUNTAMENTI -->
        <div class="card">
            <div class="card-header">
                <h3><i class="fas fa-calendar-alt"></i> Prossimi Appuntamenti</h3>
                <a href="appuntamenti.php" style="font-size:12px; color:#64a19d; text-decoration:none; font-weight:700;">Vedi tutti →</a>
            </div>
            <div class="card-body">
                <?php if (empty($appuntamenti)): ?>
                    <div class="empty-state">
                        <i class="fas fa-calendar-times"></i>
                        <p>Nessun appuntamento in programma.<br>Prenota il tuo prossimo esame!</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($appuntamenti as $apt): ?>
                    <div class="appointment-item">
                        <div class="apt-date-box">
                            <div class="apt-day"><?= date('d', strtotime($apt['data'])) ?></div>
                            <div class="apt-month"><?= date('M', strtotime($apt['data'])) ?></div>
                        </div>
                        <div class="apt-info">
                            <div class="apt-reparto"><?= htmlspecialchars($apt['nomeReparto']) ?></div>
                            <div class="apt-medico">Dr. <?= htmlspecialchars($apt['nomeMedico'] . ' ' . $apt['cognomeMedico']) ?></div>
                        </div>
                        <div class="apt-ora">ore <?= $apt['oraInizio'] ?>:00</div>
                        <div class="apt-actions">
                            <a href="modificaEsame.php?id=<?= $apt['codiceEsame'] ?>" class="btn-sm-action btn-edit"><i class="fas fa-pen"></i></a>
                            <button class="btn-sm-action btn-delete" onclick="openModal(<?= $apt['codiceEsame'] ?>)"><i class="fas fa-times"></i></button>
                        </div>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

        <!-- GRAFICO -->
        <div class="card">
            <div class="card-header">
                <h3><i class="fas fa-chart-bar"></i> Esami per Reparto</h3>
            </div>
            <div class="card-body">
                <?php if (array_sum($conteggi) == 0): ?>
                    <div class="empty-state">
                        <i class="fas fa-chart-bar"></i>
                        <p>Nessun esame completato ancora.</p>
                    </div>
                <?php else: ?>
                    <canvas id="grafico"></canvas>
                <?php endif; ?>
            </div>
        </div>

    </div>

    <!-- STORICO ESAMI -->
    <div class="card" style="margin-bottom: 20px;">
        <div class="card-header">
            <h3><i class="fas fa-file-medical-alt"></i> Storico Esami</h3>
            <a href="../root/storico.php" style="font-size:12px; color:#64a19d; text-decoration:none; font-weight:700;">Vedi tutti →</a>
        </div>
        <div class="card-body" style="padding: 0 24px;">
            <?php if (empty($storico)): ?>
                <div class="empty-state">
                    <i class="fas fa-file-medical"></i>
                    <p>Nessun esame completato.</p>
                </div>
            <?php else: ?>
                <table class="storico-table">
                    <thead>
                        <tr>
                            <th>Data</th>
                            <th>Reparto</th>
                            <th>Medico</th>
                            <th>Diagnosi</th>
                            <th>Prescrizione</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($storico as $row): ?>
                        <tr>
                            <td><?= date('d/m/Y', strtotime($row['data'])) ?></td>
                            <td><span class="badge-reparto"><?= htmlspecialchars($row['nomeReparto']) ?></span></td>
                            <td>Dr. <?= htmlspecialchars($row['nomeMedico'] . ' ' . $row['cognomeMedico']) ?></td>
                            <td><?= htmlspecialchars($row['diagnosi'] ?? '-') ?></td>
                            <td><?= htmlspecialchars($row['prescrizione'] ?? '-') ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>

    <!-- PAGAMENTI -->
    <div class="card">
        <div class="card-header">
            <h3><i class="fas fa-credit-card"></i> Ultimi Pagamenti</h3>
            <a href="pagamenti.php" style="font-size:12px; color:#64a19d; text-decoration:none; font-weight:700;">Vedi tutti →</a>
        </div>
        <div class="card-body">
            <?php if (empty($pagamenti)): ?>
                <div class="empty-state">
                    <i class="fas fa-receipt"></i>
                    <p>Nessun pagamento registrato.</p>
                </div>
            <?php else: ?>
                <?php foreach ($pagamenti as $pag): ?>
                <div class="payment-item">
                    <div class="payment-left">
                        <div class="payment-icon <?= $pag['metodo'] == 'card' ? 'card-pay' : 'cash-pay' ?>">
                            <i class="fas <?= $pag['metodo'] == 'card' ? 'fa-credit-card' : 'fa-money-bill' ?>"></i>
                        </div>
                        <div>
                            <div class="payment-method"><?= ucfirst($pag['metodo']) ?></div>
                            <div class="payment-date"><?= date('d/m/Y', strtotime($pag['dataPagamento'])) ?></div>
                        </div>
                    </div>
                    <div class="payment-amount"><?= number_format($pag['somma'], 2) ?>€</div>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

</div>

<!-- MODAL CANCELLAZIONE -->
<div class="modal-overlay" id="deleteModal">
    <div class="modal-box">
        <h4>Cancella Appuntamento</h4>
        <p>Sei sicuro di voler cancellare questo appuntamento? L'operazione non può essere annullata.</p>
        <div class="modal-actions">
            <button class="btn-cancel-modal" onclick="closeModal()">Annulla</button>
            <form method="POST" action="cancellaEsame.php" style="margin:0;">
                <input type="hidden" name="codiceEsame" id="deleteId">
                <button type="submit" class="btn-confirm-modal">Sì, cancella</button>
            </form>
        </div>
    </div>
</div>

<script>
function openModal(id) {
    document.getElementById('deleteId').value = id;
    document.getElementById('deleteModal').classList.add('show');
}
function closeModal() {
    document.getElementById('deleteModal').classList.remove('show');
}
document.getElementById('deleteModal').addEventListener('click', function(e) {
    if (e.target === this) closeModal();
});

<?php if (array_sum($conteggi) > 0): ?>
new Chart(document.getElementById('grafico'), {
    type: 'bar',
    data: {
        labels: <?= json_encode($reparti) ?>,
        datasets: [{
            label: 'Esami completati',
            data: <?= json_encode($conteggi) ?>,
            backgroundColor: [
                'rgba(100,161,157,0.7)',
                'rgba(116,100,161,0.7)',
                'rgba(28,171,196,0.7)',
                'rgba(228,198,98,0.7)',
                'rgba(161,100,104,0.7)'
            ],
            borderRadius: 6,
            borderSkipped: false
        }]
    },
    options: {
        responsive: true,
        plugins: { legend: { display: false } },
        scales: {
            y: { beginAtZero: true, ticks: { stepSize: 1 }, grid: { color: '#f0f4f8' } },
            x: { grid: { display: false } }
        }
    }
});
<?php endif; ?>
</script>

</body>
</html>