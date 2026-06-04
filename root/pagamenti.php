<?php
include("../inc/auth.inc");
$cf = $_SESSION['codiceFiscale'];
include("../inc/start.inc");

$stmtInfo = $conn->prepare("SELECT nome, cognome FROM paziente WHERE codiceFiscale = ?");
$stmtInfo->execute([$cf]);
$paziente = $stmtInfo->fetch(PDO::FETCH_ASSOC);

// Filtri
$filtroMetodo = $_GET['metodo'] ?? '';
$filtroDal    = $_GET['dal'] ?? '';
$filtroAl     = $_GET['al'] ?? '';

$where = ["codiceFiscale = ?"];
$params = [$cf];

if ($filtroMetodo) {
    $where[] = "metodo = ?";
    $params[] = $filtroMetodo;
}
if ($filtroDal) {
    $where[] = "dataPagamento >= ?";
    $params[] = $filtroDal;
}
if ($filtroAl) {
    $where[] = "dataPagamento <= ?";
    $params[] = $filtroAl;
}

$whereSQL = implode(' AND ', $where);

$stmtPag = $conn->prepare("
    SELECT codicePagamento, dataPagamento, ora, minuti, somma, metodo
    FROM pagamento
    WHERE $whereSQL
    ORDER BY dataPagamento DESC, ora DESC
");
$stmtPag->execute($params);
$pagamenti = $stmtPag->fetchAll(PDO::FETCH_ASSOC);

// Statistiche
$stmtTotale = $conn->prepare("SELECT SUM(somma), COUNT(*) FROM pagamento WHERE codiceFiscale = ?");
$stmtTotale->execute([$cf]);
[$totaleSomma, $totalePagamenti] = $stmtTotale->fetch(PDO::FETCH_NUM);

$stmtAnno = $conn->prepare("SELECT SUM(somma) FROM pagamento WHERE codiceFiscale = ? AND YEAR(dataPagamento) = YEAR(CURDATE())");
$stmtAnno->execute([$cf]);
$sommaAnno = $stmtAnno->fetchColumn() ?? 0;

$stmtCard = $conn->prepare("SELECT SUM(somma) FROM pagamento WHERE codiceFiscale = ? AND metodo = 'card'");
$stmtCard->execute([$cf]);
$sommaCard = $stmtCard->fetchColumn() ?? 0;

$stmtCash = $conn->prepare("SELECT SUM(somma) FROM pagamento WHERE codiceFiscale = ? AND metodo = 'cash'");
$stmtCash->execute([$cf]);
$sommaCash = $stmtCash->fetchColumn() ?? 0;

// Somma filtrata
$sommaFiltrata = array_sum(array_column($pagamenti, 'somma'));
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <?php include("../inc/header.inc"); ?>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Pagamenti</title>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
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

        .page-header { margin-bottom: 28px; }
        .page-header h1 { font-size: 26px; font-weight: 800; color: #1a2a3a; }
        .page-header p { font-size: 14px; color: #718096; margin-top: 4px; }

        /* STATS */
        .stats-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 16px; margin-bottom: 24px; }

        .stat-card { background: white; border-radius: 14px; padding: 20px; box-shadow: 0 2px 12px rgba(0,0,0,0.06); display: flex; align-items: center; gap: 14px; border-left: 4px solid transparent; }
        .stat-card.teal { border-left-color: #64a19d; }
        .stat-card.purple { border-left-color: #7464a1; }
        .stat-card.blue { border-left-color: #1cabc4; }
        .stat-card.green { border-left-color: #67c29c; }

        .stat-icon { width: 46px; height: 46px; border-radius: 10px; display: flex; align-items: center; justify-content: center; font-size: 18px; flex-shrink: 0; }
        .stat-icon.teal { background: rgba(100,161,157,0.12); color: #64a19d; }
        .stat-icon.purple { background: rgba(116,100,161,0.12); color: #7464a1; }
        .stat-icon.blue { background: rgba(28,171,196,0.12); color: #1cabc4; }
        .stat-icon.green { background: rgba(103,194,156,0.12); color: #67c29c; }

        .stat-value { font-size: 22px; font-weight: 900; color: #1a2a3a; line-height: 1; }
        .stat-label { font-size: 11px; color: #718096; margin-top: 3px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px; }

        /* LAYOUT */
        .main-grid { display: grid; grid-template-columns: 1fr 320px; gap: 20px; }

        /* FILTRI */
        .filtri-card { background: white; border-radius: 14px; box-shadow: 0 2px 12px rgba(0,0,0,0.06); padding: 18px 20px; margin-bottom: 20px; }
        .filtri-row { display: flex; gap: 12px; align-items: flex-end; }
        .form-group-filtro { display: flex; flex-direction: column; gap: 5px; flex: 1; }
        .form-label-filtro { font-size: 11px; font-weight: 700; color: #a0aec0; text-transform: uppercase; letter-spacing: 0.5px; }
        .form-control-filtro { padding: 9px 12px; border: 2px solid #e2e8f0; border-radius: 8px; font-size: 13px; font-family: 'Nunito', sans-serif; color: #2d3748; transition: border-color 0.2s; background: white; }
        .form-control-filtro:focus { outline: none; border-color: #64a19d; }
        select.form-control-filtro { cursor: pointer; appearance: none; background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 12 12'%3E%3Cpath fill='%23718096' d='M6 8L1 3h10z'/%3E%3C/svg%3E"); background-repeat: no-repeat; background-position: right 10px center; padding-right: 28px; }
        .btn-filtro { padding: 9px 18px; background: linear-gradient(135deg, #64a19d, #4a8480); color: white; border: none; border-radius: 8px; font-size: 13px; font-weight: 700; font-family: 'Nunito', sans-serif; cursor: pointer; white-space: nowrap; display: flex; align-items: center; gap: 6px; }
        .btn-reset { padding: 9px 12px; background: #f7fafc; color: #718096; border: 1px solid #e2e8f0; border-radius: 8px; font-size: 13px; font-weight: 700; font-family: 'Nunito', sans-serif; cursor: pointer; text-decoration: none; display: flex; align-items: center; gap: 6px; }

        /* LISTA PAGAMENTI */
        .card { background: white; border-radius: 14px; box-shadow: 0 2px 12px rgba(0,0,0,0.06); }
        .card-header { padding: 16px 22px; border-bottom: 1px solid #f0f4f8; display: flex; align-items: center; justify-content: space-between; }
        .card-header h3 { font-size: 15px; font-weight: 700; color: #1a2a3a; display: flex; align-items: center; gap: 8px; }
        .card-header h3 i { color: #64a19d; }
        .card-body { padding: 8px 0; }

        .pagamento-row { display: flex; align-items: center; gap: 16px; padding: 14px 22px; border-bottom: 1px solid #f7fafc; transition: background 0.15s; }
        .pagamento-row:last-child { border-bottom: none; }
        .pagamento-row:hover { background: #f7fafc; }

        .pag-icon { width: 42px; height: 42px; border-radius: 10px; display: flex; align-items: center; justify-content: center; font-size: 16px; flex-shrink: 0; }
        .pag-icon.card { background: rgba(28,171,196,0.1); color: #1cabc4; }
        .pag-icon.cash { background: rgba(103,194,156,0.1); color: #67c29c; }

        .pag-info { flex: 1; }
        .pag-codice { font-size: 13px; font-weight: 700; color: #1a2a3a; }
        .pag-data { font-size: 12px; color: #718096; margin-top: 2px; }
        .pag-metodo-badge { display: inline-block; padding: 2px 10px; border-radius: 20px; font-size: 11px; font-weight: 700; margin-top: 3px; }
        .pag-metodo-badge.card { background: rgba(28,171,196,0.1); color: #1cabc4; }
        .pag-metodo-badge.cash { background: rgba(103,194,156,0.1); color: #3d9970; }

        .pag-somma { font-size: 18px; font-weight: 900; color: #1a2a3a; }
        .pag-somma-label { font-size: 10px; color: #a0aec0; text-align: right; }

        /* TOTALE FILTRATO */
        .totale-filtrato { padding: 14px 22px; background: rgba(100,161,157,0.05); border-top: 2px solid #f0f4f8; display: flex; justify-content: space-between; align-items: center; }
        .totale-filtrato-label { font-size: 13px; font-weight: 700; color: #718096; }
        .totale-filtrato-somma { font-size: 20px; font-weight: 900; color: #64a19d; }

        /* SIDEBAR DESTRA */
        .side-card { background: white; border-radius: 14px; box-shadow: 0 2px 12px rgba(0,0,0,0.06); margin-bottom: 16px; }
        .side-card-header { padding: 16px 20px; border-bottom: 1px solid #f0f4f8; }
        .side-card-header h3 { font-size: 14px; font-weight: 700; color: #1a2a3a; display: flex; align-items: center; gap: 8px; }
        .side-card-header h3 i { color: #64a19d; }
        .side-card-body { padding: 16px 20px; }

        /* DONUT */
        .donut-container { position: relative; max-width: 180px; margin: 0 auto 16px; }

        .metodo-row { display: flex; align-items: center; justify-content: space-between; padding: 8px 0; border-bottom: 1px solid #f7fafc; }
        .metodo-row:last-child { border-bottom: none; }
        .metodo-left { display: flex; align-items: center; gap: 10px; }
        .metodo-dot { width: 10px; height: 10px; border-radius: 50%; }
        .metodo-name { font-size: 13px; font-weight: 600; color: #2d3748; }
        .metodo-somma { font-size: 14px; font-weight: 800; color: #1a2a3a; }

        /* EMPTY */
        .empty-state { text-align: center; padding: 48px 20px; color: #a0aec0; }
        .empty-state i { font-size: 40px; margin-bottom: 12px; display: block; }
        .empty-state p { font-size: 14px; }

        canvas { max-height: 180px; }
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
        <a href="storico.php"><i class="fas fa-file-medical"></i> Storico Esami</a>
        <div class="nav-section-title">Account</div>
        <a href="modificaProfilo.php"><i class="fas fa-user-edit"></i> Modifica Profilo</a>
        <a href="pagamenti.php" class="active"><i class="fas fa-credit-card"></i> Pagamenti</a>
    </nav>
    <div class="sidebar-footer">
        <a href="../loginPage.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
    </div>
</div>

<div class="main-content">
    <div class="page-header">
        <h1>Pagamenti</h1>
        <p>Riepilogo di tutti i tuoi pagamenti effettuati</p>
    </div>

    <!-- STATS -->
    <div class="stats-grid">
        <div class="stat-card teal">
            <div class="stat-icon teal"><i class="fas fa-euro-sign"></i></div>
            <div>
                <div class="stat-value"><?= number_format($totaleSomma ?? 0, 2) ?>€</div>
                <div class="stat-label">Totale Speso</div>
            </div>
        </div>
        <div class="stat-card purple">
            <div class="stat-icon purple"><i class="fas fa-receipt"></i></div>
            <div>
                <div class="stat-value"><?= $totalePagamenti ?></div>
                <div class="stat-label">Pagamenti Totali</div>
            </div>
        </div>
        <div class="stat-card blue">
            <div class="stat-icon blue"><i class="fas fa-credit-card"></i></div>
            <div>
                <div class="stat-value"><?= number_format($sommaCard, 2) ?>€</div>
                <div class="stat-label">Con Carta</div>
            </div>
        </div>
        <div class="stat-card green">
            <div class="stat-icon green"><i class="fas fa-money-bill-wave"></i></div>
            <div>
                <div class="stat-value"><?= number_format($sommaAnno, 2) ?>€</div>
                <div class="stat-label">Quest'anno</div>
            </div>
        </div>
    </div>

    <div class="main-grid">

        <!-- LISTA + FILTRI -->
        <div>
            <!-- FILTRI -->
            <div class="filtri-card">
                <form method="GET">
                    <div class="filtri-row">
                        <div class="form-group-filtro">
                            <label class="form-label-filtro"><i class="fas fa-credit-card"></i> Metodo</label>
                            <select class="form-control-filtro" name="metodo">
                                <option value="">Tutti</option>
                                <option value="card" <?= $filtroMetodo === 'card' ? 'selected' : '' ?>>Carta</option>
                                <option value="cash" <?= $filtroMetodo === 'cash' ? 'selected' : '' ?>>Contanti</option>
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
                        <button type="submit" class="btn-filtro"><i class="fas fa-filter"></i> Filtra</button>
                        <a href="pagamenti.php" class="btn-reset"><i class="fas fa-times"></i></a>
                    </div>
                </form>
            </div>

            <!-- LISTA -->
            <div class="card">
                <div class="card-header">
                    <h3><i class="fas fa-list"></i> Lista Pagamenti</h3>
                    <span style="font-size:12px; color:#a0aec0;"><?= count($pagamenti) ?> risultati</span>
                </div>
                <div class="card-body">
                    <?php if (empty($pagamenti)): ?>
                    <div class="empty-state">
                        <i class="fas fa-receipt"></i>
                        <p>Nessun pagamento trovato.</p>
                    </div>
                    <?php else: ?>
                    <?php foreach ($pagamenti as $pag): ?>
                    <div class="pagamento-row">
                        <div class="pag-icon <?= $pag['metodo'] ?>">
                            <i class="fas <?= $pag['metodo'] === 'card' ? 'fa-credit-card' : 'fa-money-bill-wave' ?>"></i>
                        </div>
                        <div class="pag-info">
                            <div class="pag-codice">Pagamento #<?= $pag['codicePagamento'] ?></div>
                            <div class="pag-data">
                                <?= date('d/m/Y', strtotime($pag['dataPagamento'])) ?>
                                <?php if ($pag['ora'] !== null): ?>
                                — ore <?= str_pad($pag['ora'], 2, '0', STR_PAD_LEFT) ?>:<?= str_pad($pag['minuti'] ?? 0, 2, '0', STR_PAD_LEFT) ?>
                                <?php endif; ?>
                            </div>
                            <span class="pag-metodo-badge <?= $pag['metodo'] ?>">
                                <?= $pag['metodo'] === 'card' ? 'Carta' : 'Contanti' ?>
                            </span>
                        </div>
                        <div style="text-align:right;">
                            <div class="pag-somma"><?= number_format($pag['somma'], 2) ?>€</div>
                            <div class="pag-somma-label">importo</div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                    <div class="totale-filtrato">
                        <span class="totale-filtrato-label">
                            <?= ($filtroMetodo || $filtroDal || $filtroAl) ? 'Totale filtrato' : 'Totale' ?>
                        </span>
                        <span class="totale-filtrato-somma"><?= number_format($sommaFiltrata, 2) ?>€</span>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- SIDEBAR DESTRA -->
        <div>
            <!-- GRAFICO METODI -->
            <div class="side-card">
                <div class="side-card-header">
                    <h3><i class="fas fa-chart-pie"></i> Metodi di Pagamento</h3>
                </div>
                <div class="side-card-body">
                    <?php if ($totalePagamenti > 0): ?>
                    <div class="donut-container">
                        <canvas id="donutChart"></canvas>
                    </div>
                    <div class="metodo-row">
                        <div class="metodo-left">
                            <div class="metodo-dot" style="background:#1cabc4;"></div>
                            <span class="metodo-name">Carta</span>
                        </div>
                        <span class="metodo-somma"><?= number_format($sommaCard, 2) ?>€</span>
                    </div>
                    <div class="metodo-row">
                        <div class="metodo-left">
                            <div class="metodo-dot" style="background:#67c29c;"></div>
                            <span class="metodo-name">Contanti</span>
                        </div>
                        <span class="metodo-somma"><?= number_format($sommaCash, 2) ?>€</span>
                    </div>
                    <?php else: ?>
                    <div class="empty-state" style="padding:24px;">
                        <i class="fas fa-chart-pie" style="font-size:28px;"></i>
                        <p>Nessun dato disponibile.</p>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- RIEPILOGO ANNO -->
            <div class="side-card">
                <div class="side-card-header">
                    <h3><i class="fas fa-calendar-alt"></i> Anno <?= date('Y') ?></h3>
                </div>
                <div class="side-card-body">
                    <?php
                    $stmtMesi = $conn->prepare("
                        SELECT MONTH(dataPagamento) as mese, SUM(somma) as totale
                        FROM pagamento
                        WHERE codiceFiscale = ? AND YEAR(dataPagamento) = YEAR(CURDATE())
                        GROUP BY MONTH(dataPagamento)
                        ORDER BY mese
                    ");
                    $stmtMesi->execute([$cf]);
                    $mesiData = $stmtMesi->fetchAll(PDO::FETCH_KEY_PAIR);
                    $mesiNomi = ['Gen','Feb','Mar','Apr','Mag','Giu','Lug','Ago','Set','Ott','Nov','Dic'];
                    $mesiValori = [];
                    for ($i = 1; $i <= 12; $i++) {
                        $mesiValori[] = $mesiData[$i] ?? 0;
                    }
                    ?>
                    <?php if (array_sum($mesiValori) > 0): ?>
                    <canvas id="barMesi"></canvas>
                    <?php else: ?>
                    <div class="empty-state" style="padding:20px;">
                        <i class="fas fa-chart-bar" style="font-size:24px;"></i>
                        <p style="font-size:12px;">Nessun pagamento quest'anno.</p>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
<?php if ($totalePagamenti > 0): ?>
new Chart(document.getElementById('donutChart'), {
    type: 'doughnut',
    data: {
        labels: ['Carta', 'Contanti'],
        datasets: [{
            data: [<?= $sommaCard ?>, <?= $sommaCash ?>],
            backgroundColor: ['rgba(28,171,196,0.8)', 'rgba(103,194,156,0.8)'],
            borderWidth: 0,
            hoverOffset: 4
        }]
    },
    options: {
        responsive: true,
        cutout: '65%',
        plugins: {
            legend: { display: false },
            tooltip: {
                callbacks: {
                    label: function(c) { return c.label + ': ' + parseFloat(c.raw).toFixed(2) + '€'; }
                }
            }
        }
    }
});
<?php endif; ?>

<?php if (array_sum($mesiValori) > 0): ?>
new Chart(document.getElementById('barMesi'), {
    type: 'bar',
    data: {
        labels: <?= json_encode($mesiNomi) ?>,
        datasets: [{
            label: 'Spesa (€)',
            data: <?= json_encode($mesiValori) ?>,
            backgroundColor: 'rgba(100,161,157,0.6)',
            borderRadius: 4,
            borderSkipped: false
        }]
    },
    options: {
        responsive: true,
        plugins: { legend: { display: false } },
        scales: {
            y: { beginAtZero: true, grid: { color: '#f0f4f8' }, ticks: { font: { size: 10 } } },
            x: { grid: { display: false }, ticks: { font: { size: 10 } } }
        }
    }
});
<?php endif; ?>
</script>

</body>
</html>