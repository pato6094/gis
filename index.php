<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
require_once __DIR__ . '/functions.php';
bootstrapSettings();

if (isLoggedIn()) {
    header('Location: dashboard.php');
    exit;
}
?>
<!doctype html>
<html lang="it">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?php echo h(SITE_NAME); ?></title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
<div class="wrap">
    <div class="card center hero">
        <h1><?php echo h(SITE_NAME); ?></h1>
        <p>Gli utenti incollano un link Amazon lungo o corto, il sistema lo converte con il tuo tag affiliato, mostra i punti previsti e poi manda l'utente su Amazon.</p>
        <div class="actions">
            <a class="btn" href="register.php">Registrati</a>
            <a class="btn btn-light" href="login.php">Accedi</a>
        </div>
        <p class="small">Il primo account registrato diventa automaticamente amministratore.</p>
    </div>
</div>
</body>
</html>
