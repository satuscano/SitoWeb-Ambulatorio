<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require '../src/PHPMailer.php';
require '../src/SMTP.php';
require '../src/Exception.php';
?>

<html>
    <head>
        <title>Registrazione</title>
        <?php include ('../inc/header.inc'); ?>
    </head>
<body>

<?php include ('../inc/start.inc'); ?>

<div class="container">
    <div class="row justify-content-center">
        <div class="col-md-6">
            <h2 class="text-center mt-5" style="color: #7464a1;">Registrazione</h2>

            <form method="POST">
                <div class="mb-3">
                    <label class="form-label">Nome</label>
                    <input type="text" class="form-control" name="nome" required>
                </div>

                <div class="mb-3">
                    <label class="form-label">Cognome</label>
                    <input type="text" class="form-control" name="cognome" required>
                </div>

                <div class="mb-3">
                    <label class="form-label">Data di nascita</label>
                    <input type="date" class="form-control" name="dataNascita" required>
                </div>

                <div class="mb-3">
                    <label class="form-label">Codice Fiscale</label>
                    <input type="text" class="form-control" name="cf" required>
                </div>

                <div class="mb-3">
                    <label class="form-label">Email</label>
                    <input type="email" class="form-control" name="email" required>
                </div>

                <br>
                <hr>

                <div class="mb-3">
                    <label class="form-label">CAP</label>
                    <input type="text" class="form-control" name="cap" required>
                </div>

                <div class="mb-3">
                    <label class="form-label">Città</label>
                    <input type="text" class="form-control" name="citta" required>
                </div>

                <div class="mb-3">
                    <label class="form-label">Via</label>
                    <input type="text" class="form-control" name="via" required>
                </div>

                <div class="mb-3">
                    <label class="form-label">Numero civico</label>
                    <input type="text" class="form-control" name="civico" required>
                </div>

                <button type="submit" class="btn btn-secondary w-100">Registrati</button>
            </form>

            <a href="loginPage.php">
                <button class="btn btn-primary w-100">Hai già un account? Accedi</button>
            </a>
            <br><br>

            <?php
            $success = false;
            if($_SERVER["REQUEST_METHOD"] == "POST"){

                $nome = $_POST["nome"];
                $cognome = $_POST["cognome"];
                $dataNascita = $_POST["dataNascita"];
                $cf = $_POST["cf"];
                $email = $_POST["email"];

                $cap = $_POST["cap"];
                $citta = $_POST["citta"];
                $via = $_POST["via"];
                $civico = $_POST["civico"];

                if(!filter_var($email, FILTER_VALIDATE_EMAIL)){
                    echo '<div class="alert alert-danger mt-3">Email non valida</div>';
                    exit;
                } else {
                    $password = bin2hex(random_bytes(4)); // generazione password
                    $username = $cf;

                    $mail = new PHPMailer(true);

                    try {
                        $mail->isSMTP();
                        $mail->Host = 'smtp.gmail.com';
                        $mail->SMTPAuth = true;

                        $mail->Username = 'alessio.tuscano07@gmail.com';
                        $mail->Password = 'oouxruxyblpdvtce';

                        $mail->SMTPSecure = 'tls';
                        $mail->Port = 587;
                        $mail->CharSet = 'UTF-8';

                        $mail->setFrom('alessio.tuscano07@gmail.com', 'Sistema di Registrazione');
                        $mail->addAddress($email);

                        $mail->Subject = 'Le tue credenziali di accesso';

                        $mail->isHTML(true);

                        $mail->Body = "
                        <!DOCTYPE html>
                        <html>
                        <head>
                            <style>
                                body {
                                    font-family: Arial, sans-serif;
                                    background-color: #f4f6f9;
                                    margin: 0;
                                    padding: 0;
                                }
                                .container {
                                    max-width: 600px;
                                    margin: 30px auto;
                                    background: #ffffff;
                                    border-radius: 10px;
                                    overflow: hidden;
                                    box-shadow: 0 4px 12px rgba(0,0,0,0.1);
                                }
                                .header {
                                    background: #0d6efd;
                                    color: white;
                                    padding: 20px;
                                    text-align: center;
                                }
                                .content {
                                    padding: 25px;
                                    color: #333;
                                }
                                .box {
                                    background: #f1f3f5;
                                    padding: 15px;
                                    border-radius: 8px;
                                    margin-top: 10px;
                                }
                                .footer {
                                    text-align: center;
                                    font-size: 12px;
                                    color: #888;
                                    padding: 15px;
                                }
                                .title {
                                    margin: 0;
                                }
                            </style>
                        </head>

                        <body>

                        <div class='container'>

                            <div class='header'>
                                <h2 class='title'>Sistema di Registrazione</h2>
                            </div>

                            <div class='content'>
                                <h3>Ciao, $nome $cognome</h3>
                                <p>La tua registrazione è stata completata con successo.</p>

                                <div class='box'>
                                    <p><strong>Username:</strong> $username</p>
                                    <p><strong>Password:</strong> $password</p>
                                </div>

                                <p style='margin-top:15px;'>
                                    Conserva queste credenziali in un luogo sicuro.
                                </p>

                                <p style='margin-top:15px;, align:center;'>
                                    <a href='http://localhost/AMBULATORIO/root/loginPage.php' style='color: #0d6efd; text-decoration: none;'>Accedi alla dashboard</a>
                                </p>
                            </div>

                            <div class='footer'>
                                Email generata automaticamente - non rispondere
                            </div>

                        </div>

                        </body>
                        </html>
                        ";


                        // verificare se codice fiscale già esiste
                        $stmt = $conn->prepare("SELECT COUNT(*) FROM users WHERE codiceFiscale = ?");
                        $stmt->execute([$cf]);
                        $n = 0;
                        $n = $stmt->fetchColumn();
                        
                        if($n > 0){
                            header("Location: registerFailed.php");
                            echo '<div class="alert alert-danger mt-3">Codice fiscale già registrato</div>';
                            exit;
                        }

                        // altrimenti, salva utente e paziente
                        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

                        $stmt = $conn->prepare("INSERT INTO users (codiceFiscale, email, ruolo, password) VALUES (?, ?, ?, ?)");
                        $stmt->execute([$cf, $email, 'paziente', $hashedPassword]);

                        $stmt = $conn->prepare("INSERT INTO paziente (codiceFiscale, nome, cognome, dataNascita, anamnesi, ind_via, ind_civico, ind_cap, ind_citta) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
                        $stmt->execute([$cf, $nome, $cognome, $dataNascita, '', $via, $civico, $cap, $citta]);
                        
                        if($mail->send()){
                            $success = true;
                        }

                        if($success)
                            header("Location: registerConfirm.php");
                        else
                            header("Location: registerFailed.php");
                        exit;

                    } catch (Exception $e) {
                        echo '<div class="alert alert-danger mt-3">Errore invio mail: ' . $mail->ErrorInfo . '</div>';
                    }
                }
            }
            ?>

        </div>
    </div>
</div>

</body>
</html>