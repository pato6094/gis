<?php
require_once __DIR__ . '/functions.php';
bootstrapSettings();

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = (string) ($_POST['password'] ?? '');

    if ($name === '' || $email === '' || $password === '') {
        $error = 'Compila tutti i campi.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Email non valida.';
    } elseif (strlen($password) < 6) {
        $error = 'La password deve avere almeno 6 caratteri.';
    } else {
        $stmt = db()->prepare('SELECT id FROM users WHERE email = ? LIMIT 1');
        $stmt->execute([$email]);
        if ($stmt->fetch()) {
            $error = 'Email già registrata.';
        } else {
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $stmt = db()->prepare('INSERT INTO users (name, email, password_hash, is_admin, total_points, created_at) VALUES (?, ?, ?, 0, 0, NOW())');
            $stmt->execute([$name, $email, $hash]);
            $userId = (int) db()->lastInsertId();
            ensureAdminExists($userId);
            $_SESSION['user_id'] = $userId;
            header('Location: dashboard.php');
            exit;
        }
    }
}
?>
<!doctype html>
<html lang="it">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Registrati</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
<div class="wrap small-wrap">
    <div class="card">
        <h1>Registrati</h1>
        <?php if ($error): ?><div class="alert error"><?php echo h($error); ?></div><?php endif; ?>
        <form method="post">
            <label>Nome</label>
            <input type="text" name="name" required>
            <label>Email</label>
            <input type="email" name="email" required>
            <label>Password</label>
            <input type="password" name="password" required>
            <button class="btn" type="submit">Crea account</button>
        </form>
        <p class="small">Hai già un account? <a href="login.php">Accedi</a></p>
    </div>
</div>
</body>
</html>
