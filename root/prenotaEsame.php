<?php
include("../inc/auth.inc");
$cf = $_SESSION['codiceFiscale'];
include("../inc/start.inc");

$stmtInfo = $conn->prepare("SELECT nome, cognome FROM paziente WHERE codiceFiscale = ?");
$stmtInfo->execute([$cf]);
$paziente = $stmtInfo->fetch(PDO::FETCH_ASSOC);

// Carica reparti
$stmtRep = $conn->query("SELECT codiceReparto, nomeReparto FROM reparto ORDER BY nomeReparto");
$reparti = $stmtRep->fetchAll(PDO::FETCH_ASSOC);

$error = '';
$step = 1;

// Gestione selezione reparto → carica medici via AJAX o POST
$medicoSelezionato = null;
$orariDisponibili = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['azione']) && $_POST['azione'] === 'prenota') {
    $codiceReparto = $_POST['codiceReparto'] ?? '';
    $codiceMedico  = $_POST['codiceMedico'] ?? '';
    $data          = $_POST['data'] ?? '';
    $oraInizio     = $_POST['oraInizio'] ?? '';

    if (!$codiceReparto || !$codiceMedico || !$data || $oraInizio === '') {
        $error = 'Compila tutti i campi.';
    } elseif (strtotime($data) < strtotime('today')) {
        $error = 'La data deve essere futura.';
    } else {
        // Verifica che l'orario non sia già occupato per quel medico
        $stmtCheck = $conn->prepare("
            SELECT COUNT(*) FROM storico s
            JOIN esame e ON s.codiceEsame = e.codiceEsame
            WHERE e.codiceMedico = ? AND s.data = ? AND s.oraInizio = ? AND s.stato = 'prenotato'
        ");
        $stmtCheck->execute([$codiceMedico, $data, $oraInizio]);
        if ($stmtCheck->fetchColumn() > 0) {
            $error = 'Questo orario è già occupato per il medico selezionato. Scegli un altro orario.';
        } else {
            // Trova ambulatorio del medico
            $stmtAmb = $conn->prepare("
                SELECT a.codiceAmbulatorio FROM ambulatorio a
                JOIN medico m ON a.codiceReparto = m.codiceReparto
                WHERE m.codiceMedico = ? LIMIT 1
            ");
            $stmtAmb->execute([$codiceMedico]);
            $codiceAmbulatorio = $stmtAmb->fetchColumn();

            // Inserisci esame
            $stmtEsame = $conn->prepare("INSERT INTO esame (codiceAmbulatorio, codiceMedico, codiceFiscale) VALUES (?, ?, ?)");
            $stmtEsame->execute([$codiceAmbulatorio, $codiceMedico, $cf]);
            $codiceEsame = $conn->lastInsertId();

            // Calcola oraFine (1 ora dopo)
            $oraFine = (int)$oraInizio + 1;

            // Inserisci storico
            $stmtStor = $conn->prepare("INSERT INTO storico (codiceEsame, data, oraInizio, oraFine, codiceFiscale, stato) VALUES (?, ?, ?, ?, ?, 'prenotato')");
            $stmtStor->execute([$codiceEsame, $data, $oraInizio, $oraFine, $cf]);

            header("Location: dashboard.php?success=prenotato");
            exit;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <?php include("../inc/header.inc"); ?>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Prenota Esame</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: 'Nunito', sans-serif; background: #f0f4f8; color: #2d3748; }

        .sidebar {
            position: fixed; top: 0; left: 0;
            width: 240px; height: 100vh;
            background: linear-gradient(180deg, #1a2a3a 0%, #0f1e2d 100%);
            display: flex; flex-direction: column;
            z-index: 100; box-shadow: 4px 0 20px rgba(0,0,0,0.15);
        }
        .sidebar-header { padding: 28px 24px 20px; border-bottom: 1px solid rgba(255,255,255,0.08); }
        .sidebar-logo { font-size: 13px; font-weight: 800; color: #64a19d; text-transform: uppercase; letter-spacing: 2px; }
        .sidebar-subtitle { font-size: 11px; color: rgba(255,255,255,0.35); margin-top: 2px; letter-spacing: 1px; }
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

        .form-card {
            background: white;
            border-radius: 16px;
            box-shadow: 0 2px 12px rgba(0,0,0,0.06);
            max-width: 680px;
        }

        .form-card-header {
            padding: 24px 28px;
            border-bottom: 1px solid #f0f4f8;
        }

        .form-card-header h3 {
            font-size: 17px;
            font-weight: 800;
            color: #1a2a3a;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .form-card-header h3 i { color: #64a19d; }

        .form-card-body { padding: 28px; }

        .form-group { margin-bottom: 22px; }

        .form-label {
            display: block;
            font-size: 12px;
            font-weight: 700;
            color: #718096;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 8px;
        }

        .form-control {
            width: 100%;
            padding: 12px 16px;
            border: 2px solid #e2e8f0;
            border-radius: 10px;
            font-size: 14px;
            font-family: 'Nunito', sans-serif;
            color: #2d3748;
            transition: border-color 0.2s;
            background: white;
        }

        .form-control:focus {
            outline: none;
            border-color: #64a19d;
            box-shadow: 0 0 0 3px rgba(100,161,157,0.15);
        }

        select.form-control {
            cursor: pointer;
            appearance: none;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 12 12'%3E%3Cpath fill='%23718096' d='M6 8L1 3h10z'/%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: right 14px center;
            padding-right: 36px;
        }

        .orari-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(70px, 1fr));
            gap: 8px;
            margin-top: 8px;
        }

        .orario-btn {
            padding: 10px 6px;
            border: 2px solid #e2e8f0;
            border-radius: 8px;
            text-align: center;
            font-size: 13px;
            font-weight: 700;
            color: #718096;
            cursor: pointer;
            transition: all 0.2s;
            background: white;
        }

        .orario-btn:hover {
            border-color: #64a19d;
            color: #64a19d;
            background: rgba(100,161,157,0.05);
        }

        .orario-btn.selected {
            background: #64a19d;
            border-color: #64a19d;
            color: white;
        }

        .orario-btn.occupato {
            background: #f7fafc;
            border-color: #e2e8f0;
            color: #cbd5e0;
            cursor: not-allowed;
            text-decoration: line-through;
        }

        .medico-card {
            border: 2px solid #e2e8f0;
            border-radius: 10px;
            padding: 14px 16px;
            margin-bottom: 10px;
            cursor: pointer;
            transition: all 0.2s;
            display: flex;
            align-items: center;
            gap: 14px;
        }

        .medico-card:hover { border-color: #64a19d; background: rgba(100,161,157,0.03); }
        .medico-card.selected { border-color: #64a19d; background: rgba(100,161,157,0.08); }

        .medico-avatar {
            width: 42px;
            height: 42px;
            border-radius: 50%;
            background: linear-gradient(135deg, #64a19d, #7464a1);
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 800;
            color: white;
            font-size: 15px;
            flex-shrink: 0;
        }

        .medico-nome { font-size: 14px; font-weight: 700; color: #1a2a3a; }
        .medico-spec { font-size: 12px; color: #718096; }
        .medico-orario { font-size: 11px; color: #64a19d; font-weight: 700; margin-top: 2px; }

        .btn-submit {
            width: 100%;
            padding: 14px;
            background: linear-gradient(135deg, #64a19d, #4a8480);
            color: white;
            border: none;
            border-radius: 10px;
            font-size: 15px;
            font-weight: 700;
            font-family: 'Nunito', sans-serif;
            cursor: pointer;
            transition: all 0.2s;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
        }

        .btn-submit:hover { transform: translateY(-1px); box-shadow: 0 6px 20px rgba(100,161,157,0.35); }

        .alert-error {
            background: rgba(161,100,104,0.1);
            border: 1px solid rgba(161,100,104,0.25);
            border-radius: 10px;
            padding: 12px 16px;
            color: #a16468;
            font-size: 13px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .section-title {
            font-size: 13px;
            font-weight: 700;
            color: #718096;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 10px;
        }

        #medicoSection, #orariSection { display: none; }
        #orariSection.visible, #medicoSection.visible { display: block; }

        .loading {
            text-align: center;
            padding: 20px;
            color: #a0aec0;
            font-size: 13px;
        }

        input[type="hidden"] { display: none; }
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
        <a href="prenotaEsame.php" class="active"><i class="fas fa-calendar-plus"></i> Prenota Esame</a>
        <a href="appuntamenti.php"><i class="fas fa-calendar-alt"></i> Appuntamenti</a>
        <a href="storico.php"><i class="fas fa-file-medical"></i> Storico Esami</a>
        <div class="nav-section-title">Account</div>
        <a href="profilo.php"><i class="fas fa-user-edit"></i> Modifica Profilo</a>
        <a href="pagamenti.php"><i class="fas fa-credit-card"></i> Pagamenti</a>
    </nav>
    <div class="sidebar-footer">
        <a href="../../root/loginPage.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
    </div>
</div>

<div class="main-content">
    <div class="page-header">
        <h1>Prenota un Esame</h1>
        <p>Scegli reparto, medico, data e orario per il tuo appuntamento</p>
    </div>

    <div class="form-card">
        <div class="form-card-header">
            <h3><i class="fas fa-calendar-plus"></i> Nuova Prenotazione</h3>
        </div>
        <div class="form-card-body">

            <?php if ($error): ?>
            <div class="alert-error">
                <i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($error) ?>
            </div>
            <?php endif; ?>

            <form method="POST" id="prenotaForm">
                <input type="hidden" name="azione" value="prenota">
                <input type="hidden" name="codiceMedico" id="codiceMedicoHidden">
                <input type="hidden" name="oraInizio" id="oraInizioHidden">

                <!-- STEP 1: REPARTO -->
                <div class="form-group">
                    <label class="form-label">1. Scegli il Reparto</label>
                    <select class="form-control" name="codiceReparto" id="repartoSelect" onchange="loadMedici()">
                        <option value="">— Seleziona reparto —</option>
                        <?php foreach ($reparti as $r): ?>
                        <option value="<?= $r['codiceReparto'] ?>" <?= (isset($_POST['codiceReparto']) && $_POST['codiceReparto'] == $r['codiceReparto']) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($r['nomeReparto']) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- STEP 2: MEDICO -->
                <div class="form-group" id="medicoSection">
                    <label class="form-label">2. Scegli il Medico</label>
                    <div id="medicoList"></div>
                </div>

                <!-- STEP 3: DATA -->
                <div class="form-group" id="dataSection" style="display:none;">
                    <label class="form-label">3. Scegli la Data</label>
                    <input type="date" class="form-control" name="data" id="dataInput"
                           min="<?= date('Y-m-d', strtotime('+1 day')) ?>"
                           value="<?= isset($_POST['data']) ? htmlspecialchars($_POST['data']) : '' ?>"
                           onchange="loadOrari()">
                </div>

                <!-- STEP 4: ORARIO -->
                <div class="form-group" id="orariSection">
                    <label class="form-label">4. Scegli l'Orario</label>
                    <div class="orari-grid" id="orariGrid"></div>
                </div>

                <button type="submit" class="btn-submit" id="btnSubmit" style="display:none;">
                    <i class="fas fa-check"></i> Conferma Prenotazione
                </button>
            </form>
        </div>
    </div>
</div>

<script>
let medicoSelezionato = null;

function loadMedici() {
    const reparto = document.getElementById('repartoSelect').value;
    const medicoSection = document.getElementById('medicoSection');
    const dataSection = document.getElementById('dataSection');
    const orariSection = document.getElementById('orariSection');
    const btnSubmit = document.getElementById('btnSubmit');

    // Reset
    medicoSelezionato = null;
    document.getElementById('codiceMedicoHidden').value = '';
    document.getElementById('oraInizioHidden').value = '';
    dataSection.style.display = 'none';
    orariSection.style.display = 'none';
    btnSubmit.style.display = 'none';

    if (!reparto) { medicoSection.style.display = 'none'; return; }

    medicoSection.style.display = 'block';
    document.getElementById('medicoList').innerHTML = '<div class="loading"><i class="fas fa-spinner fa-spin"></i> Caricamento medici...</div>';

    fetch('getMedici.php?codiceReparto=' + reparto)
        .then(r => r.json())
        .then(data => {
            let html = '';
            if (data.length === 0) {
                html = '<p style="color:#a0aec0; font-size:13px;">Nessun medico disponibile per questo reparto.</p>';
            } else {
                data.forEach(m => {
                    const initials = m.nome.charAt(0) + m.cognome.charAt(0);
                    html += `
                    <div class="medico-card" onclick="selectMedico('${m.codiceMedico}', this)" data-id="${m.codiceMedico}">
                        <div class="medico-avatar">${initials.toUpperCase()}</div>
                        <div>
                            <div class="medico-nome">Dr. ${m.nome} ${m.cognome}</div>
                            <div class="medico-spec">${m.specializzazione || 'Specialista'}</div>
                            <div class="medico-orario"><i class="fas fa-clock"></i> ${m.orario || 'Orari variabili'}</div>
                        </div>
                    </div>`;
                });
            }
            document.getElementById('medicoList').innerHTML = html;
        });
}

function selectMedico(id, el) {
    document.querySelectorAll('.medico-card').forEach(c => c.classList.remove('selected'));
    el.classList.add('selected');
    medicoSelezionato = id;
    document.getElementById('codiceMedicoHidden').value = id;
    document.getElementById('dataSection').style.display = 'block';
    document.getElementById('dataInput').value = '';
    document.getElementById('orariSection').style.display = 'none';
    document.getElementById('btnSubmit').style.display = 'none';
    document.getElementById('oraInizioHidden').value = '';
}

function loadOrari() {
    const data = document.getElementById('dataInput').value;
    const medico = medicoSelezionato;
    if (!data || !medico) return;

    document.getElementById('orariSection').style.display = 'block';
    document.getElementById('oraInizioHidden').value = '';
    document.getElementById('btnSubmit').style.display = 'none';
    document.getElementById('orariGrid').innerHTML = '<div class="loading"><i class="fas fa-spinner fa-spin"></i> Caricamento orari...</div>';

    fetch(`getOrari.php?codiceMedico=${medico}&data=${data}`)
        .then(r => r.json())
        .then(data => {
            let html = '';
            if (data.disponibili.length === 0 && data.occupati.length === 0) {
                html = '<p style="color:#a0aec0;font-size:13px;">Nessun orario disponibile per questo giorno.</p>';
            } else {
                data.disponibili.forEach(ora => {
                    html += `<div class="orario-btn" onclick="selectOrario(${ora}, this)">${ora}:00</div>`;
                });
                data.occupati.forEach(ora => {
                    html += `<div class="orario-btn occupato">${ora}:00</div>`;
                });
            }
            document.getElementById('orariGrid').innerHTML = html;
        });
}

function selectOrario(ora, el) {
    document.querySelectorAll('.orario-btn:not(.occupato)').forEach(b => b.classList.remove('selected'));
    el.classList.add('selected');
    document.getElementById('oraInizioHidden').value = ora;
    document.getElementById('btnSubmit').style.display = 'flex';
}
</script>

</body>
</html>