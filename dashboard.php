<?php
require_once __DIR__ . '/functions.php';
bootstrapSettings();
requireLogin();
$user = currentUser();
$categories = getCategoryRules();
$rewards = getRewards();
$defaultCategorySlug = getSetting('default_category_slug', 'elettronica');

$stmt = db()->prepare('SELECT * FROM link_requests WHERE user_id = ? ORDER BY id DESC LIMIT 20');
$stmt->execute([$user['id']]);
$requests = $stmt->fetchAll();
?>
<!doctype html>
<html lang="it">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Dashboard</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
<div class="wrap">
    <div class="topbar">
        <div>
            <strong><?php echo h(SITE_NAME); ?></strong>
            <div class="small">Ciao <?php echo h($user['name']); ?> · Punti totali: <strong><?php echo (int) $user['total_points']; ?></strong></div>
        </div>
        <div class="actions">
            <?php if ((int) $user['is_admin'] === 1): ?><a class="btn btn-light" href="admin.php">Admin</a><?php endif; ?>
            <a class="btn btn-light" href="logout.php">Esci</a>
        </div>
    </div>

    <div class="card">
        <h1>Inserisci link Amazon</h1>
        <p>Seleziona la categoria commissionale, incolla il link Amazon e il sistema calcola i punti sulla tua commissione reale, non sul prezzo intero.</p>

        <label for="categorySlug">Categoria prodotto</label>
        <select id="categorySlug">
            <?php foreach ($categories as $category): ?>
                <option value="<?php echo h($category['slug']); ?>" <?php echo $defaultCategorySlug === $category['slug'] ? 'selected' : ''; ?>>
                    <?php echo h($category['category_name']); ?> (<?php echo number_format((float) $category['amazon_rate'], 2, ',', '.'); ?>%)
                </option>
            <?php endforeach; ?>
        </select>

        <div class="form-inline">
            <input type="text" id="amazonUrl" placeholder="https://amzn.eu/... oppure https://www.amazon.it/dp/...">
            <button class="btn" id="analyzeBtn" type="button">Calcola punti</button>
        </div>

        <div id="resultBox" class="result hidden"></div>
    </div>

    <div class="card">
        <h2>Premi riscattabili</h2>
        <div class="reward-grid">
            <?php foreach ($rewards as $reward): ?>
                <div class="reward-item">
                    <strong><?php echo h($reward['reward_name']); ?></strong>
                    <div class="points"><?php echo (int) $reward['points_cost']; ?> pt</div>
                </div>
            <?php endforeach; ?>
        </div>
        <p class="small">Conversione consigliata: 100 punti = 1€ di premio.</p>
    </div>

    <div class="card">
        <h2>Ultimi link generati</h2>
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Data</th>
                        <th>Categoria</th>
                        <th>Prodotto</th>
                        <th>Prezzo</th>
                        <th>Punti</th>
                        <th>ASIN</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($requests as $row): ?>
                    <tr>
                        <td><?php echo h($row['created_at']); ?></td>
                        <td><?php echo h($row['category_name'] ?: '-'); ?></td>
                        <td><?php echo h($row['product_title'] ?: 'Prodotto Amazon'); ?></td>
                        <td><?php echo h(formatEuro($row['product_price'] !== null ? (float) $row['product_price'] : null)); ?></td>
                        <td><?php echo (int) $row['points_preview']; ?></td>
                        <td><?php echo h($row['asin']); ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<script src="app.js"></script>
</body>
</html>
