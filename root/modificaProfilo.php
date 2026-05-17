<?php
include("../inc/auth.inc");
$cf = $_SESSION['codiceFiscale'];
include("../inc/start.inc");

$stmtInfo = $conn->prepare("SELECT nome, cognome, dataNascita, ind_citta, ind_via, ind_civico, ind_cap, anamnesi FROM paziente WHERE codiceFiscale = ?");
$stmtInfo->execute([$cf]);
$paziente = $stmtInfo->fetch(PDO::FETCH_ASSOC);

$stmtEmail = $conn->prepare("SELECT email FROM users WHERE codiceFiscale = ?");
$stmtEmail->execute([$cf]);
$user = $stmtEmail->fetch(PDO::FETCH_ASSOC);

$error = '';
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $via    = trim($_POST['via'] ?? '');
    $civico = trim($_POST['civico'] ?? '');
    $cap    = trim($_POST['cap'] ?? '');
    $citta  = trim($_POST['citta'] ?? '');
    $email  = trim($_POST['email'] ?? '');
    $anamnesi = trim($_POST['anamnesi'] ?? '');

    $nuovaPassword = $_POST['nuovaPassword'] ?? '';
    $confermaPassword = $_POST['confermaPassword'] ?? '';

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Email non valida.';
    } elseif ($nuovaPassword && $nuovaPassword !== $confermaPassword) {
        $error = 'Le password non coincidono.';
    } elseif ($nuovaPassword && strlen($nuovaPassword) < 6) {
        $error = 'La password deve essere di almeno 6 caratteri.';
    } else {
        // Aggiorna paziente
        $stmtUpd = $conn->prepare("UPDATE paziente SET ind_via=?, ind_civico=?, ind_cap=?, ind_citta=?, anamnesi=? WHERE codiceFiscale=?");
        $stmtUpd->execute([$via, $civico, $cap, $citta, $anamnesi, $cf]);

        // Aggiorna email
        $stmtEmail2 = $conn->prepare("UPDATE users SET email=? WHERE codiceFiscale=?");
        $stmtEmail2->execute([$email, $cf]);

        // Aggiorna password se fornita
        if ($nuovaPassword) {
            $hash = password_hash($nuovaPassword, PASSWORD_DEFAULT);
            $stmtPwd = $conn->prepare("UPDATE users SET password=? WHERE codiceFiscale=?");
            $stmtPwd->execute([$hash, $cf]);
        }

        header("Location: dashboard.php?success=profilo");
        exit;
    }

    // Ricarica dati
    $stmtInfo->execute([$cf]);
    $paziente = $stmtInfo->fetch(PDO::FETCH_ASSOC);
}
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <?php include("../inc/header.inc"); ?>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Modifica Profilo</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: 'Nunito', sans-serif; background: #f0f4f8; color: #2d3748; }

        .sidebar { position: fixed; top: 0; left: 0; width: 240px; height: 100vh; background: linear-gradient(180deg, #1a2a3a 0%, #0f1e2d 100%); display: flex; flex-direction: column; z-index: 100; box-shadow: 4px 0 20px rgba(0,0,0,0.15); }
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

        .profile-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; max-width: 900px; }

        .card { background: white; border-radius: 14px; box-shadow: 0 2px 12px rgba(0,0,0,0.06); }
        .card-header { padding: 18px 24px; border-bottom: 1px solid #f0f4f8; }
        .card-header h3 { font-size: 15px; font-weight: 700; color: #1a2a3a; display: flex; align-items: center; gap: 8px; }
        .card-header h3 i { color: #64a19d; }
        .card-body { padding: 24px; }

        .info-readonly {
            background: #f7fafc;
            border-radius: 10px;
            padding: 16px;
            margin-bottom: 16px;
        }

        .info-row { display: flex; justify-content: space-between; margin-bottom: 10px; }
        .info-row:last-child { margin-bottom: 0; }
        .info-label { font-size: 12px; color: #a0aec0; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; }
        .info-value { font-size: 14px; font-weight: 600; color: #2d3748; }

        .form-group { margin-bottom: 18px; }
        .form-label { display: block; font-size: 12px; font-weight: 700; color: #718096; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 8px; }
        .form-control { width: 100%; padding: 11px 14px; border: 2px solid #e2e8f0; border-radius: 10px; font-size: 14px; font-family: 'Nunito', sans-serif; color: #2d3748; transition: border-color 0.2s; background: white; }
        .form-control:focus { outline: none; border-color: #64a19d; box-shadow: 0 0 0 3px rgba(100,161,157,0.15); }
        textarea.form-control { resize: vertical; min-height: 80px; }

        .form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }

        .btn-submit { padding: 12px 28px; background: linear-gradient(135deg, #64a19d, #4a8480); color: white; border: none; border-radius: 10px; font-size: 14px; font-weight: 700; font-family: 'Nunito', sans-serif; cursor: pointer; transition: all 0.2s; display: inline-flex; align-items: center; gap: 8px; }
        .btn-submit:hover { transform: translateY(-1px); box-shadow: 0 6px 20px rgba(100,161,157,0.35); }

        .alert-error { background: rgba(161,100,104,0.1); border: 1px solid rgba(161,100,104,0.25); border-radius: 10px; padding: 12px 16px; color: #a16468; font-size: 13px; margin-bottom: 20px; display: flex; align-items: center; gap: 10px; }

        .cf-badge { display: inline-block; background: rgba(116,100,161,0.1); color: #7464a1; padding: 4px 12px; border-radius: 20px; font-size: 12px; font-weight: 700; font-family: monospace; letter-spacing: 1px; }

        .full-width { grid-column: 1 / -1; }
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
        <a href="profilo.php" class="active"><i class="fas fa-user-edit"></i> Modifica Profilo</a>
        <a href="pagamenti.php"><i class="fas fa-credit-card"></i> Pagamenti</a>
    </nav>
    <div class="sidebar-footer">
        <a href="../../root/loginPage.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
    </div>
</div>

<div class="main-content">
    <div class="page-header">
        <h1>Modifica Profilo</h1>
        <p>Aggiorna le tue informazioni personali e le credenziali di accesso</p>
    </div>

    <?php if ($error): ?>
    <div class="alert-error" style="max-width:900px;"><i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <form method="POST">
    <div class="profile-grid">

        <!-- INFO NON MODIFICABILI -->
        <div class="card">
            <div class="card-header"><h3><i class="fas fa-id-card"></i> Dati Anagrafici</h3></div>
            <div class="card-body">
                <div class="info-readonly">
                    <div class="info-row">
                        <span class="info-label">Nome</span>
                        <span class="info-value"><?= htmlspecialchars($paziente['nome']) ?></span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Cognome</span>
                        <span class="info-value"><?= htmlspecialchars($paziente['cognome']) ?></span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Data di Nascita</span>
                        <span class="info-value"><?= date('d/m/Y', strtotime($paziente['dataNascita'])) ?></span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Codice Fiscale</span>
                        <span class="cf-badge"><?= htmlspecialchars($cf) ?></span>
                    </div>
                </div>
                <p style="font-size:12px; color:#a0aec0;"><i class="fas fa-info-circle"></i> I dati anagrafici non possono essere modificati. Contatta l'amministrazione per eventuali correzioni.</p>
            </div>
        </div>

        <!-- INDIRIZZO -->
        <div class="card">
            <div class="card-header"><h3><i class="fas fa-map-marker-alt"></i> Indirizzo</h3></div>
            <div class="card-body">
                <div class="form-group">
                    <label class="form-label">Via / Strada</label>
                    <input type="text" class="form-control" name="via" value="<?= htmlspecialchars($paziente['ind_via'] ?? '') ?>" required>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Civico</label>
                        <input type="text" class="form-control" name="civico" value="<?= htmlspecialchars($paziente['ind_civico'] ?? '') ?>" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">CAP</label>
                        <input type="text" class="form-control" name="cap" value="<?= htmlspecialchars($paziente['ind_cap'] ?? '') ?>" required>
                    </div>
                </div>
                <div class="form-group">
                    <label class="form-label">Città</label>
                    <input type="text" class="form-control" name="citta" value="<?= htmlspecialchars($paziente['ind_citta'] ?? '') ?>" required>
                </div>
            </div>
        </div>

        <!-- CREDENZIALI -->
        <div class="card">
            <div class="card-header"><h3><i class="fas fa-lock"></i> Credenziali di Accesso</h3></div>
            <div class="card-body">
                <div class="form-group">
                    <label class="form-label">Email</label>
                    <input type="email" class="form-control" name="email" value="<?= htmlspecialchars($user['email'] ?? '') ?>" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Nuova Password <span style="color:#a0aec0; font-weight:400;">(lascia vuoto per non cambiarla)</span></label>
                    <input type="password" class="form-control" name="nuovaPassword" placeholder="Minimo 6 caratteri">
                </div>
                <div class="form-group">
                    <label class="form-label">Conferma Password</label>
                    <input type="password" class="form-control" name="confermaPassword">
                </div>
            </div>
        </div>

        <!-- ANAMNESI -->
        <div class="card">
            <div class="card-header"><h3><i class="fas fa-notes-medical"></i> Anamnesi</h3></div>
            <div class="card-body">
                <div class="form-group">
                    <label class="form-label">Note mediche e patologie note</label>
                    <textarea class="form-control" name="anamnesi" rows="6"><?= htmlspecialchars($paziente['anamnesi'] ?? '') ?></textarea>
                </div>
                <p style="font-size:12px; color:#a0aec0;"><i class="fas fa-info-circle"></i> Queste informazioni sono visibili ai medici che ti prendono in carico.</p>
            </div>
        </div>

        <!-- SALVA -->
        <div style="grid-column: 1/-1; display:flex; justify-content: flex-end; padding-bottom: 10px;">
            <button type="submit" class="btn-submit">
                <i class="fas fa-save"></i> Salva Modifiche
            </button>
        </div>

    </div>
    </form>
</div>

</body>
</html>