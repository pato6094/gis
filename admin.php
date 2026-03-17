<?php
require_once __DIR__ . '/functions.php';
bootstrapSettings();
requireLogin();
requireAdmin();

$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? 'settings';

    if ($action === 'settings') {
        $affiliateTag = trim($_POST['affiliate_tag'] ?? '');
        $defaultCategorySlug = trim($_POST['default_category_slug'] ?? 'elettronica');

        if ($affiliateTag === '') {
            $error = 'Il tag affiliato è obbligatorio.';
        } else {
            setSetting('affiliate_tag', $affiliateTag);
            setSetting('default_category_slug', $defaultCategorySlug);
            $success = 'Impostazioni salvate.';
        }
    }

    if ($action === 'categories' && isset($_POST['categories']) && is_array($_POST['categories'])) {
        $stmt = db()->prepare('UPDATE category_rules SET amazon_rate = ?, share_percent = ?, updated_at = NOW() WHERE id = ?');
        foreach ($_POST['categories'] as $id => $row) {
            $amazonRate = (float) ($row['amazon_rate'] ?? 0);
            $sharePercent = (float) ($row['share_percent'] ?? DEFAULT_SHARE_PERCENT);
            $stmt->execute([$amazonRate, $sharePercent, (int) $id]);
        }
        $success = 'Categorie aggiornate.';
    }

    if ($action === 'rewards' && isset($_POST['rewards']) && is_array($_POST['rewards'])) {
        $stmt = db()->prepare('UPDATE rewards SET points_cost = ?, updated_at = NOW() WHERE id = ?');
        foreach ($_POST['rewards'] as $id => $row) {
            $pointsCost = max(0, (int) ($row['points_cost'] ?? 0));
            $stmt->execute([$pointsCost, (int) $id]);
        }
        $success = 'Premi aggiornati.';
    }
}

$settings = [
    'affiliate_tag' => getSetting('affiliate_tag', DEFAULT_AFFILIATE_TAG),
    'default_category_slug' => getSetting('default_category_slug', 'elettronica'),
];

$categories = getCategoryRules();
$rewards = getRewards();
$users = db()->query('SELECT id, name, email, is_admin, total_points, created_at FROM users ORDER BY id DESC')->fetchAll();
$requests = db()->query('SELECT lr.*, u.name, u.email FROM link_requests lr INNER JOIN users u ON u.id = lr.user_id ORDER BY lr.id DESC LIMIT 50')->fetchAll();
?>
<!doctype html>
<html lang="it">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Admin</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
<div class="wrap">
    <div class="topbar">
        <strong>Area Admin</strong>
        <div class="actions">
            <a class="btn btn-light" href="dashboard.php">Dashboard</a>
            <a class="btn btn-light" href="logout.php">Esci</a>
        </div>
    </div>

    <div class="card">
        <h1>Impostazioni piattaforma</h1>
        <?php if ($success): ?><div class="alert success"><?php echo h($success); ?></div><?php endif; ?>
        <?php if ($error): ?><div class="alert error"><?php echo h($error); ?></div><?php endif; ?>
        <form method="post">
            <input type="hidden" name="action" value="settings">
            <label>Tag affiliato Amazon</label>
            <input type="text" name="affiliate_tag" value="<?php echo h($settings['affiliate_tag']); ?>" required>
            <label>Categoria predefinita</label>
            <select name="default_category_slug">
                <?php foreach ($categories as $category): ?>
                    <option value="<?php echo h($category['slug']); ?>" <?php echo $settings['default_category_slug'] === $category['slug'] ? 'selected' : ''; ?>><?php echo h($category['category_name']); ?></option>
                <?php endforeach; ?>
            </select>
            <button class="btn" type="submit">Salva impostazioni</button>
        </form>
        <p class="small">Formula: prezzo × commissione Amazon % = tua commissione; tua commissione × quota utente % = valore utente; valore utente × 100 = punti.</p>
    </div>

    <div class="card">
        <h2>Commissioni per categoria</h2>
        <form method="post">
            <input type="hidden" name="action" value="categories">
            <div class="table-wrap">
                <table>
                    <thead><tr><th>Categoria</th><th>Commissione Amazon %</th><th>Quota utente %</th><th>Esempio su 100€</th></tr></thead>
                    <tbody>
                    <?php foreach ($categories as $category): 
                        $example = calculate_points_from_price(100, $category);
                    ?>
                        <tr>
                            <td><?php echo h($category['category_name']); ?></td>
                            <td><input type="number" step="0.01" min="0" name="categories[<?php echo (int) $category['id']; ?>][amazon_rate]" value="<?php echo h((string) $category['amazon_rate']); ?>"></td>
                            <td><input type="number" step="0.01" min="0" name="categories[<?php echo (int) $category['id']; ?>][share_percent]" value="<?php echo h((string) $category['share_percent']); ?>"></td>
                            <td><?php echo (int) $example['estimated_points']; ?> pt</td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <button class="btn" type="submit">Salva categorie</button>
        </form>
    </div>

    <div class="card">
        <h2>Premi</h2>
        <form method="post">
            <input type="hidden" name="action" value="rewards">
            <div class="table-wrap">
                <table>
                    <thead><tr><th>Premio</th><th>Punti richiesti</th></tr></thead>
                    <tbody>
                    <?php foreach ($rewards as $reward): ?>
                        <tr>
                            <td><?php echo h($reward['reward_name']); ?></td>
                            <td><input type="number" min="0" name="rewards[<?php echo (int) $reward['id']; ?>][points_cost]" value="<?php echo (int) $reward['points_cost']; ?>"></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <button class="btn" type="submit">Salva premi</button>
        </form>
    </div>

    <div class="card">
        <h2>Utenti</h2>
        <div class="table-wrap">
            <table>
                <thead><tr><th>ID</th><th>Nome</th><th>Email</th><th>Admin</th><th>Punti</th><th>Creato</th></tr></thead>
                <tbody>
                <?php foreach ($users as $u): ?>
                    <tr>
                        <td><?php echo (int) $u['id']; ?></td>
                        <td><?php echo h($u['name']); ?></td>
                        <td><?php echo h($u['email']); ?></td>
                        <td><?php echo (int) $u['is_admin'] === 1 ? 'Sì' : 'No'; ?></td>
                        <td><?php echo (int) $u['total_points']; ?></td>
                        <td><?php echo h($u['created_at']); ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div class="card">
        <h2>Ultimi link processati</h2>
        <div class="table-wrap">
            <table>
                <thead><tr><th>Data</th><th>Utente</th><th>Categoria</th><th>Prodotto</th><th>Prezzo</th><th>Punti</th><th>Click</th><th>ASIN</th></tr></thead>
                <tbody>
                <?php foreach ($requests as $r): ?>
                    <tr>
                        <td><?php echo h($r['created_at']); ?></td>
                        <td><?php echo h($r['name'] . ' (' . $r['email'] . ')'); ?></td>
                        <td><?php echo h($r['category_name'] ?: '-'); ?></td>
                        <td><?php echo h($r['product_title']); ?></td>
                        <td><?php echo h(formatEuro($r['product_price'] !== null ? (float) $r['product_price'] : null)); ?></td>
                        <td><?php echo (int) $r['points_preview']; ?></td>
                        <td><?php echo $r['clicked_at'] ? h($r['clicked_at']) : '-'; ?></td>
                        <td><?php echo h($r['asin']); ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
</body>
</html>
