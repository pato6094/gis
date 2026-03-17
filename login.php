<?php
require_once __DIR__ . '/functions.php';
bootstrapSettings();

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = (string) ($_POST['password'] ?? '');

    $stmt = db()->prepare('SELECT * FROM users WHERE email = ? LIMIT 1');
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    if (!$user || !password_verify($password, $user['password_hash'])) {
        $error = 'Credenziali non valide.';
    } else {
        $_SESSION['user_id'] = (int) $user['id'];
        header('Location: dashboard.php');
        exit;
    }
}
?>
<!doctype html>
<html lang="it">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Accedi</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
<div class="wrap small-wrap">
    <div class="card">
        <h1>Accedi</h1>
        <?php if ($error): ?><div class="alert error"><?php echo h($error); ?></div><?php endif; ?>
        <form method="post">
            <label>Email</label>
            <input type="email" name="email" required>
            <label>Password</label>
            <input type="password" name="password" required>
            <button class="btn" type="submit">Entra</button>
        </form>
        <p class="small">Non hai un account? <a href="register.php">Registrati</a></p>
    </div>
</div>
</body>
</html>
