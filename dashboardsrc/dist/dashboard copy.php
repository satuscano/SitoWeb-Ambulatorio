<?php
include("../../inc/auth.inc");
$cf = $_SESSION['codiceFiscale'];
include("../../inc/start.inc");

$stmt = $conn->prepare("SELECT COUNT(*) as totaleEsami FROM storico WHERE codiceFiscale = ?");
$stmt->execute([$cf]);
$totaleEsami = $stmt->fetch(PDO::FETCH_ASSOC)['totaleEsami'];

$stmt2 = $conn->prepare("SELECT diagnosi, data FROM storico WHERE codiceFiscale = ? ORDER BY data DESC LIMIT 1");
$stmt2->execute([$cf]);
$ultimoEsame = $stmt2->fetch(PDO::FETCH_ASSOC);

$stmt = $conn->query("SELECT nomeReparto FROM reparto");
$reparti = $stmt->fetchAll(PDO::FETCH_ASSOC);
$reparti = array_column($reparti, 'nomeReparto');

$conteggi = [];
foreach($reparti as $rep){
    $stmt3 = $conn->prepare("
        SELECT COUNT(*) as tot
        FROM storico s 
        JOIN esame e ON s.codiceEsame = e.codiceEsame
        JOIN ambulatorio a ON e.codiceAmbulatorio = a.codiceAmbulatorio
        JOIN reparto r ON a.codiceReparto = r.codiceReparto
        WHERE s.codiceFiscale = ? AND r.nomeReparto = ?
    ");
    $stmt3->execute([$cf, $rep]);
    $res = $stmt3->fetch(PDO::FETCH_ASSOC);
    $conteggi[] = $res['tot'];
}

$stmtInfo = $conn->prepare("
    SELECT nome, cognome, dataNascita, ind_citta, ind_via, ind_civico, ind_cap 
    FROM paziente 
    WHERE codiceFiscale = ?
");
$stmtInfo->execute([$cf]);
$paziente = $stmtInfo->fetch(PDO::FETCH_ASSOC);

$stmtPag = $conn->prepare("
    SELECT codicePagamento, dataPagamento, somma, metodo 
    FROM pagamento 
    WHERE codiceFiscale = ? 
    ORDER BY dataPagamento DESC
");
$stmtPag->execute([$cf]);
$pagamenti = $stmtPag->fetchAll(PDO::FETCH_ASSOC);
?>

<html lang="it">
  <head>
    <?php include ("../../inc/header.inc"); ?>
    <meta charset="utf-8">
    <title>Dashboard Paziente</title>

    <link rel="stylesheet" href="assets/css/style.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
  </head>

  <body>
    <div class="container-scroller">

    <div class="container-fluid page-body-wrapper">

    <!-- SIDEBAR -->
      <style>
        .userPicture {
          width: 20px;
          height: 20px;
          border-radius: 10%;
          object-fit: cover;
        }
      </style>

    <nav class="sidebar">
      <ul class="nav">
        <li class="nav-item">
          <a href="dashboard.php">
            
            <img class="userPicture" src="../../img/user.png" alt="Avatar">
            <span class="nav-link" style="font-weight: bold;"><?= $paziente['nome'] . " " . $paziente['cognome'] ?></span>
          </a>
        </li>
        <li class="nav-item">
          <a href="prenotaEsame.php">
            <span class="nav-link">Prenota un nuovo esame</span>
          </a>
        </li>
      </ul>
    </nav>

    <!-- MAIN -->
    <div class="main-panel">
    <div class="content-wrapper">

    <h3>Benvenuto, <?= $paziente['nome'] ?></h3>

    <!-- CARDS -->
    <div class="row">

    <div class="col-md-4 grid-margin">
    <div class="card bg-gradient-primary text-white">
    <div class="card-body">
    <h4>Esami Totali</h4>
    <h2><?= $totaleEsami ?></h2>
    </div>
    </div>
    </div>

    <div class="col-md-4 grid-margin">
    <div class="card bg-gradient-success text-white">
    <div class="card-body">
    <h4>Ultima Diagnosi</h4>
    <p><?= $ultimoEsame['diagnosi'] ?? 'Nessuna' ?></p>
    </div>
    </div>
    </div>

    <div class="col-md-4 grid-margin">
    <div class="card bg-gradient-info text-white">
    <div class="card-body">
    <h4>Ultima Visita</h4>
    <p><?= $ultimoEsame['data'] ?? '-' ?></p>
    </div>
    </div>
    </div>

    </div>

    <!-- GRAFICO -->
    <div class="row">
    <div class="col-md-6">
    <div class="card">
    <div class="card-body">
    <h4>Esami per reparto</h4>
    <canvas id="grafico"></canvas>
    </div>
    </div>
    </div>

    <!-- PROFILO -->
    <div class="col-md-6">
    <div class="card">
    <div class="card-body">
    <h4>Profilo</h4>
    <p><?= $paziente['nome'] ?> <?= $paziente['cognome'] ?></p>
    <p><?= $paziente['dataNascita'] ?></p>
    <p><?= $paziente['ind_via'] ?> <?= $paziente['ind_civico'] ?></p>
    <p><?= $paziente['ind_cap'] ?> <?= $paziente['ind_citta'] ?></p>
    </div>
    </div>
    </div>
    </div>

    <!-- PAGAMENTI -->
    <br>
    <div class="row">
    <div class="col-12">
    <div class="card">
    <div class="card-body">
    <h4>Pagamenti</h4>

    <table class="table">
    <thead>
    <tr>
    <th>Data</th>
    <th>Importo</th>
    <th>Metodo</th>
    <th>Fattura</th>
    </tr>
    </thead>
    <tbody>

    <?php foreach($pagamenti as $row): ?>
    <tr>
    <td><?= $row['dataPagamento'] ?></td>
    <td><?= $row['somma'] ?>€</td>
    <td><?= $row['metodo'] ?></td>
    <td><a href="fattura.php?codice=<?= $row['codicePagamento'] ?>" target="_blank">Visualizza</a></td>
    </tr>
    <?php endforeach; ?>
    </tbody>
    </table>

    </div>
    </div>
    </div>
    </div>

    </div>
    </div>
    </div>
    </div>

    <!-- GRAFICO JS -->
    <script>
    new Chart(document.getElementById('grafico'), {
    type: 'bar',
    data: {
    labels: <?= json_encode($reparti) ?>,
    datasets: [{
    label: 'Esami',
    data: <?= json_encode($conteggi) ?>,
    backgroundColor: 'rgba(54,162,235,0.6)'
    }]
    }
    });
    </script>
  </body>
</html>