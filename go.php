<?php
require_once __DIR__ . '/functions.php';
bootstrapSettings();
requireLogin();

$id = (int) ($_GET['id'] ?? 0);
$stmt = db()->prepare('SELECT * FROM link_requests WHERE id = ? AND user_id = ? LIMIT 1');
$stmt->execute([$id, $_SESSION['user_id']]);
$row = $stmt->fetch();

if (!$row) {
    exit('Link non trovato.');
}

$stmt = db()->prepare('UPDATE link_requests SET clicked_at = NOW() WHERE id = ?');
$stmt->execute([$id]);

header('Location: ' . $row['affiliate_url']);
exit;
