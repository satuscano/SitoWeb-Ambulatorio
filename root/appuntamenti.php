<?php
include("../inc/auth.inc");
$cf = $_SESSION['codiceFiscale'];
include("../inc/start.inc");

$stmtInfo = $conn->prepare("SELECT nome, cognome FROM paziente WHERE codiceFiscale = ?");
$stmtInfo->execute([$cf]);
$paziente = $stmtInfo->fetch(PDO::FETCH_ASSOC);

// Filtro stato
$filtroStato = $_GET['stato'] ?? 'prenotato';
$stati = ['prenotato', 'completato', 'cancellato'];
if (!in_array($filtroStato, $stati)) $filtroStato = 'prenotato';

$stmtApps = $conn->prepare("
    SELECT s.codiceEsame, s.data, s.oraInizio, s.oraFine, s.stato, s.diagnosi, s.prescrizione,
           m.nome AS nomeMedico, m.cognome AS cognomeMedico,
           r.nomeReparto, a.piano
    FROM storico s
    JOIN esame e ON s.codiceEsame = e.codiceEsame
    JOIN medico m ON e.codiceMedico = m.codiceMedico
    JOIN ambulatorio a ON e.codiceAmbulatorio = a.codiceAmbulatorio
    JOIN reparto r ON a.codiceReparto = r.codiceReparto
    WHERE s.codiceFiscale = ? AND s.stato = ?
    ORDER BY s.data DESC, s.oraInizio ASC
");
$stmtApps->execute([$cf, $filtroStato]);
$appuntamenti = $stmtApps->fetchAll(PDO::FETCH_ASSOC);

// Conteggi per tab
$conteggi = [];
foreach ($stati as $s) {
    $stmtC = $conn->prepare("SELECT COUNT(*) FROM storico WHERE codiceFiscale = ? AND stato = ?");
    $stmtC->execute([$cf, $s]);
    $conteggi[$s] = $stmtC->fetchColumn();
}
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <?php include("../inc/header.inc"); ?>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Appuntamenti</title>
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

        .page-header { margin-bottom: 28px; display: flex; align-items: center; justify-content: space-between; }
        .page-header h1 { font-size: 26px; font-weight: 800; color: #1a2a3a; }
        .page-header p { font-size: 14px; color: #718096; margin-top: 4px; }

        .btn-prenota { padding: 11px 22px; background: linear-gradient(135deg, #64a19d, #4a8480); color: white; border: none; border-radius: 10px; font-size: 13px; font-weight: 700; font-family: 'Nunito', sans-serif; cursor: pointer; text-decoration: none; display: inline-flex; align-items: center; gap: 8px; transition: all 0.2s; }
        .btn-prenota:hover { transform: translateY(-1px); box-shadow: 0 6px 20px rgba(100,161,157,0.35); color: white; }

        /* TABS */
        .tabs { display: flex; gap: 4px; background: white; border-radius: 12px; padding: 6px; box-shadow: 0 2px 12px rgba(0,0,0,0.06); margin-bottom: 24px; width: fit-content; }
        .tab-btn { padding: 9px 20px; border-radius: 8px; border: none; font-size: 13px; font-weight: 700; font-family: 'Nunito', sans-serif; cursor: pointer; transition: all 0.2s; color: #718096; background: transparent; display: flex; align-items: center; gap: 8px; }
        .tab-btn:hover { color: #1a2a3a; }
        .tab-btn.active.prenotato { background: rgba(100,161,157,0.12); color: #64a19d; }
        .tab-btn.active.completato { background: rgba(103,194,156,0.12); color: #3d9970; }
        .tab-btn.active.cancellato { background: rgba(161,100,104,0.12); color: #a16468; }
        .tab-badge { display: inline-flex; align-items: center; justify-content: center; width: 20px; height: 20px; border-radius: 50%; font-size: 11px; font-weight: 800; }
        .tab-badge.prenotato { background: rgba(100,161,157,0.15); color: #64a19d; }
        .tab-badge.completato { background: rgba(103,194,156,0.15); color: #3d9970; }
        .tab-badge.cancellato { background: rgba(161,100,104,0.15); color: #a16468; }

        /* CARDS APPUNTAMENTI */
        .apt-card {
            background: white;
            border-radius: 14px;
            box-shadow: 0 2px 12px rgba(0,0,0,0.05);
            margin-bottom: 14px;
            overflow: hidden;
            transition: box-shadow 0.2s, transform 0.2s;
            border-left: 4px solid #64a19d;
        }
        .apt-card:hover { box-shadow: 0 6px 24px rgba(0,0,0,0.1); transform: translateY(-1px); }
        .apt-card.completato { border-left-color: #67c29c; }
        .apt-card.cancellato { border-left-color: #a16468; opacity: 0.75; }

        .apt-card-inner { padding: 20px 24px; display: flex; align-items: center; gap: 20px; }

        .apt-date-block {
            width: 64px;
            height: 64px;
            border-radius: 12px;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }
        .apt-date-block.prenotato { background: rgba(100,161,157,0.1); }
        .apt-date-block.completato { background: rgba(103,194,156,0.1); }
        .apt-date-block.cancellato { background: rgba(161,100,104,0.1); }

        .apt-day { font-size: 22px; font-weight: 900; line-height: 1; }
        .apt-day.prenotato { color: #64a19d; }
        .apt-day.completato { color: #67c29c; }
        .apt-day.cancellato { color: #a16468; }

        .apt-month { font-size: 11px; font-weight: 700; text-transform: uppercase; }
        .apt-month.prenotato { color: #64a19d; }
        .apt-month.completato { color: #67c29c; }
        .apt-month.cancellato { color: #a16468; }

        .apt-main { flex: 1; }
        .apt-reparto { font-size: 16px; font-weight: 800; color: #1a2a3a; }
        .apt-details { display: flex; gap: 16px; margin-top: 6px; flex-wrap: wrap; }
        .apt-detail-item { display: flex; align-items: center; gap: 5px; font-size: 12px; color: #718096; }
        .apt-detail-item i { color: #a0aec0; font-size: 11px; }

        .apt-ora-badge { padding: 4px 12px; border-radius: 20px; font-size: 12px; font-weight: 800; }
        .apt-ora-badge.prenotato { background: rgba(100,161,157,0.1); color: #64a19d; }
        .apt-ora-badge.completato { background: rgba(103,194,156,0.1); color: #3d9970; }
        .apt-ora-badge.cancellato { background: rgba(161,100,104,0.1); color: #a16468; }

        .apt-actions { display: flex; flex-direction: column; gap: 8px; align-items: flex-end; }

        .btn-action { padding: 7px 14px; border-radius: 8px; font-size: 12px; font-weight: 700; border: none; cursor: pointer; font-family: 'Nunito', sans-serif; transition: all 0.2s; text-decoration: none; display: inline-flex; align-items: center; gap: 6px; white-space: nowrap; }
        .btn-modifica { background: rgba(100,161,157,0.1); color: #64a19d; }
        .btn-modifica:hover { background: #64a19d; color: white; }
        .btn-elimina { background: rgba(161,100,104,0.1); color: #a16468; }
        .btn-elimina:hover { background: #a16468; color: white; }
        .btn-dettagli { background: rgba(116,100,161,0.1); color: #7464a1; }
        .btn-dettagli:hover { background: #7464a1; color: white; }

        /* DIAGNOSI/PRESCRIZIONE COLLAPSIBLE */
        .apt-extra { padding: 0 24px 16px; display: none; }
        .apt-extra.show { display: block; }
        .apt-extra-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
        .apt-extra-item { background: #f7fafc; border-radius: 10px; padding: 12px 14px; }
        .apt-extra-label { font-size: 10px; font-weight: 800; text-transform: uppercase; letter-spacing: 0.5px; color: #a0aec0; margin-bottom: 4px; }
        .apt-extra-value { font-size: 13px; color: #2d3748; }

        /* MODAL */
        .modal-overlay { display: none; position: fixed; top:0; left:0; width:100%; height:100%; background: rgba(0,0,0,0.5); z-index: 1000; align-items: center; justify-content: center; }
        .modal-overlay.show { display: flex; }
        .modal-box { background: white; border-radius: 16px; padding: 28px; max-width: 440px; width: 90%; box-shadow: 0 20px 60px rgba(0,0,0,0.2); }
        .modal-box h4 { font-size: 18px; font-weight: 800; margin-bottom: 8px; color: #1a2a3a; }
        .modal-box p { color: #718096; font-size: 14px; margin-bottom: 24px; }
        .modal-actions { display: flex; gap: 12px; justify-content: flex-end; }
        .btn-cancel-modal { padding: 10px 20px; border-radius: 8px; border: 1px solid #e2e8f0; background: white; color: #718096; font-weight: 600; cursor: pointer; font-size: 13px; }
        .btn-confirm-modal { padding: 10px 20px; border-radius: 8px; border: none; background: #a16468; color: white; font-weight: 700; cursor: pointer; font-size: 13px; }

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
        <a href="appuntamenti.php" class="active"><i class="fas fa-calendar-alt"></i> Appuntamenti</a>
        <a href="storico.php"><i class="fas fa-file-medical"></i> Storico Esami</a>
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
            <h1>I miei Appuntamenti</h1>
            <p>Gestisci le tue prenotazioni e visualizza lo storico</p>
        </div>
        <a href="prenotaEsame.php" class="btn-prenota">
            <i class="fas fa-plus"></i> Nuovo Esame
        </a>
    </div>

    <!-- TABS -->
    <div class="tabs">
        <?php
        $tabLabels = ['prenotato' => 'In programma', 'completato' => 'Completati', 'cancellato' => 'Cancellati'];
        foreach ($stati as $s):
        ?>
        <a href="?stato=<?= $s ?>" class="tab-btn <?= $filtroStato === $s ? 'active ' . $s : '' ?>">
            <?= $tabLabels[$s] ?>
            <span class="tab-badge <?= $s ?>"><?= $conteggi[$s] ?></span>
        </a>
        <?php endforeach; ?>
    </div>

    <!-- LISTA APPUNTAMENTI -->
    <?php if (empty($appuntamenti)): ?>
    <div class="empty-state">
        <i class="fas fa-<?= $filtroStato === 'prenotato' ? 'calendar-plus' : ($filtroStato === 'completato' ? 'check-circle' : 'ban') ?>"></i>
        <h3>Nessun appuntamento <?= $tabLabels[$filtroStato] === 'In programma' ? 'in programma' : strtolower($tabLabels[$filtroStato]) ?></h3>
        <?php if ($filtroStato === 'prenotato'): ?>
        <p>Non hai ancora prenotato nessun esame.<br><a href="prenotaEsame.php" style="color:#64a19d; font-weight:700;">Prenota il tuo primo esame →</a></p>
        <?php else: ?>
        <p>Non ci sono appuntamenti in questa categoria.</p>
        <?php endif; ?>
    </div>
    <?php else: ?>

    <?php foreach ($appuntamenti as $apt): ?>
    <div class="apt-card <?= $apt['stato'] ?>">
        <div class="apt-card-inner">
            <div class="apt-date-block <?= $apt['stato'] ?>">
                <div class="apt-day <?= $apt['stato'] ?>"><?= date('d', strtotime($apt['data'])) ?></div>
                <div class="apt-month <?= $apt['stato'] ?>"><?= date('M', strtotime($apt['data'])) ?></div>
            </div>

            <div class="apt-main">
                <div class="apt-reparto"><?= htmlspecialchars($apt['nomeReparto']) ?></div>
                <div class="apt-details">
                    <span class="apt-detail-item"><i class="fas fa-user-md"></i> Dr. <?= htmlspecialchars($apt['nomeMedico'] . ' ' . $apt['cognomeMedico']) ?></span>
                    <span class="apt-detail-item"><i class="fas fa-building"></i> Piano <?= $apt['piano'] ?></span>
                    <span class="apt-detail-item"><i class="fas fa-calendar"></i> <?= date('d/m/Y', strtotime($apt['data'])) ?></span>
                </div>
            </div>

            <div class="apt-ora-badge <?= $apt['stato'] ?>">
                <?= $apt['oraInizio'] ?>:00 – <?= $apt['oraFine'] ?? ($apt['oraInizio'] + 1) ?>:00
            </div>

            <div class="apt-actions">
                <?php if ($apt['stato'] === 'prenotato'): ?>
                <a href="modificaEsame.php?id=<?= $apt['codiceEsame'] ?>" class="btn-action btn-modifica">
                    <i class="fas fa-pen"></i> Modifica
                </a>
                <button class="btn-action btn-elimina" onclick="openModal(<?= $apt['codiceEsame'] ?>)">
                    <i class="fas fa-times"></i> Cancella
                </button>
                <?php elseif ($apt['stato'] === 'completato'): ?>
                <button class="btn-action btn-dettagli" onclick="toggleExtra(<?= $apt['codiceEsame'] ?>)">
                    <i class="fas fa-eye"></i> Dettagli
                </button>
                <?php else: ?>
                <span style="font-size:12px; color:#a16468; font-weight:700;"><i class="fas fa-ban"></i> Cancellato</span>
                <?php endif; ?>
            </div>
        </div>

        <?php if ($apt['stato'] === 'completato' && ($apt['diagnosi'] || $apt['prescrizione'])): ?>
        <div class="apt-extra" id="extra-<?= $apt['codiceEsame'] ?>">
            <div class="apt-extra-grid">
                <?php if ($apt['diagnosi']): ?>
                <div class="apt-extra-item">
                    <div class="apt-extra-label"><i class="fas fa-stethoscope"></i> Diagnosi</div>
                    <div class="apt-extra-value"><?= htmlspecialchars($apt['diagnosi']) ?></div>
                </div>
                <?php endif; ?>
                <?php if ($apt['prescrizione']): ?>
                <div class="apt-extra-item">
                    <div class="apt-extra-label"><i class="fas fa-pills"></i> Prescrizione</div>
                    <div class="apt-extra-value"><?= htmlspecialchars($apt['prescrizione']) ?></div>
                </div>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>
    </div>
    <?php endforeach; ?>

    <?php endif; ?>
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
function toggleExtra(id) {
    const el = document.getElementById('extra-' + id);
    el.classList.toggle('show');
}
</script>

</body>
</html>