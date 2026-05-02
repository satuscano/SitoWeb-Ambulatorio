<?php session_start(); ?>
<html>
    <head>
        <title>Login</title>
        <?php include ('../inc/header.inc'); ?>
    </head>
    <body>
    <?php include ('../inc/start.inc'); ?>    
        <div class="container">
            <div class="row justify-content-center">
                <div class="col-md-6">
                    <h2 class="text-center mt-5" style="color: #64a19d;">Login</h2>
                    <form action="" method="post">
                        <div class="mb-3">
                            <label for="username" class="form-label">Codice Fiscale</label>
                                <input type="text" class="form-control" id="username" name="codiceFiscale" required>
                        </div>
                        <div class="mb-3">
                            <label for="password" class="form-label">Password</label>
                                <input type="password" class="form-control" id="password" name="password" required>
                        </div>
                        <button type="submit" class="btn btn-primary w-100">Login</button>
                    </form>
                    <a href="registerPage.php">
                        <button class="btn btn-secondary w-100 mt-2">Non hai un account? Registrati</button>
                    </a>
                </div>
            </div>
        </div>
        
        <?php 
            if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                $username = $_POST['codiceFiscale'];
                $password = $_POST['password'];                

                $stmt = $conn->prepare("SELECT codiceFiscale, ruolo, password FROM users WHERE codiceFiscale = :codiceFiscale");
                $stmt->execute([':codiceFiscale' => $username]);
                $user = $stmt->fetch();

                if ($user && password_verify($password, $user['password'])) {
                    $_SESSION['codiceFiscale'] = $user['codiceFiscale'];
                    $_SESSION['ruolo'] = $user['ruolo'];
                    header("Location: ../dashboardsrc/dist/dashboard.php");
                    exit;
                } else
                    $error = 'Credenziali non valide. Riprova.';
            }
            if (isset($error)) {
                echo '<div class="alert alert-danger mt-3" role="alert">' . $error . '</div>';
            }
        ?> 
    </body>
</html>