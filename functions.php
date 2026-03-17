<?php
define('DEFAULT_AMAZON_RATE', 3);      // default fallback
define('DEFAULT_SHARE_PERCENT', 20);   // percentuale utente (consigliata)
define('BONUS_SHARE_PERCENT', 5);      // bonus opzionale
require_once __DIR__ . '/db.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function h(string $value): string {
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

function isLoggedIn(): bool {
    return !empty($_SESSION['user_id']);
}

function requireLogin(): void {
    if (!isLoggedIn()) {
        header('Location: login.php');
        exit;
    }
}

function currentUser(): ?array {
    if (!isLoggedIn()) {
        return null;
    }

    $stmt = db()->prepare('SELECT id, name, email, is_admin, total_points, created_at FROM users WHERE id = ? LIMIT 1');
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch();

    return $user ?: null;
}

function requireAdmin(): void {
    $user = currentUser();
    if (!$user || (int) $user['is_admin'] !== 1) {
        http_response_code(403);
        exit('Accesso negato');
    }
}

function getSetting(string $key, ?string $default = null): ?string {
    $stmt = db()->prepare('SELECT setting_value FROM settings WHERE setting_key = ? LIMIT 1');
    $stmt->execute([$key]);
    $row = $stmt->fetch();

    return $row ? $row['setting_value'] : $default;
}

function setSetting(string $key, string $value): void {
    $stmt = db()->prepare('INSERT INTO settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)');
    $stmt->execute([$key, $value]);
}

function bootstrapSettings(): void {
    setSettingIfMissing('affiliate_tag', DEFAULT_AFFILIATE_TAG);
    setSettingIfMissing('default_category_slug', 'elettronica');
    seedCategoryRules();
    seedRewards();
}

function setSettingIfMissing(string $key, string $value): void {
    $current = getSetting($key, null);
    if ($current === null) {
        setSetting($key, $value);
    }
}

function ensureAdminExists(int $userId): void {
    $count = (int) db()->query('SELECT COUNT(*) FROM users WHERE is_admin = 1')->fetchColumn();
    if ($count === 0) {
        $stmt = db()->prepare('UPDATE users SET is_admin = 1 WHERE id = ?');
        $stmt->execute([$userId]);
    }
}

function normalizeAmazonUrl(string $input): string {
    $input = trim($input);
    if (!preg_match('~^https?://~i', $input)) {
        $input = 'https://' . $input;
    }
    return $input;
}

function resolveShortAmazonUrl(string $url): string {
    $parts = parse_url($url);
    $host = strtolower($parts['host'] ?? '');
    if (!in_array($host, ['amzn.eu', 'www.amzn.eu', 'amzn.to', 'www.amzn.to'], true)) {
        return $url;
    }

    $html = fetch_remote_html($url, true);
    if ($html === null) {
        return $url;
    }

    return getLastEffectiveUrl() ?: $url;
}

function extractAmazonAsin(string $url): ?string {
    $patterns = [
        '~/(?:dp|gp/product|gp/aw/d|gp/offer-listing|offer-listing)/([A-Z0-9]{10})(?:[/?]|$)~i',
        '~[?&]asin=([A-Z0-9]{10})(?:[&]|$)~i',
    ];

    foreach ($patterns as $pattern) {
        if (preg_match($pattern, $url, $matches)) {
            return strtoupper($matches[1]);
        }
    }

    return null;
}

function getAffiliateTag(): string {
    return trim((string) getSetting('affiliate_tag', DEFAULT_AFFILIATE_TAG));
}

function buildAffiliateUrl(string $asin): string {
    $tag = rawurlencode(getAffiliateTag());
    return 'https://www.amazon.it/dp/' . rawurlencode($asin) . '/?tag=' . $tag;
}

function fetch_remote_html(string $url, bool $followLocation = true): ?string {
    static $lastEffectiveUrl = null;
    $lastEffectiveUrl = $url;

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => $followLocation,
        CURLOPT_MAXREDIRS => 10,
        CURLOPT_TIMEOUT => 25,
        CURLOPT_CONNECTTIMEOUT => 15,
        CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 Chrome/122.0 Safari/537.36',
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => false,
        CURLOPT_ENCODING => '',
        CURLOPT_HTTPHEADER => [
            'Accept-Language: it-IT,it;q=0.9,en-US;q=0.8,en;q=0.7',
            'Cache-Control: no-cache',
            'Pragma: no-cache',
        ],
    ]);

    $html = curl_exec($ch);
    $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $lastEffectiveUrl = curl_getinfo($ch, CURLINFO_EFFECTIVE_URL) ?: $url;
    curl_close($ch);

    $GLOBALS['__last_effective_url'] = $lastEffectiveUrl;

    if (!is_string($html) || $html === '' || $httpCode >= 400) {
        return null;
    }

    return $html;
}

function getLastEffectiveUrl(): ?string {
    return $GLOBALS['__last_effective_url'] ?? null;
}

function normalize_price_string(string $raw): ?float {
    $value = html_entity_decode(trim($raw), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $value = preg_replace('~[^0-9,\.]~', '', $value);
    if ($value === '') {
        return null;
    }

    if (substr_count($value, ',') > 0 && substr_count($value, '.') > 0) {
        $value = str_replace('.', '', $value);
        $value = str_replace(',', '.', $value);
    } elseif (substr_count($value, ',') > 0) {
        $value = str_replace(',', '.', $value);
    }

    return is_numeric($value) ? (float) $value : null;
}

function extract_amazon_price_from_html(string $html): ?float {
    libxml_use_internal_errors(true);

    $dom = new DOMDocument();
    if (!$dom->loadHTML($html)) {
        return null;
    }

    $xpath = new DOMXPath($dom);

    $primaryXPath = '/html/body/div[1]/div[2]/div[2]/div[5]/div[4]/div[17]/div/div/div[6]/div[1]/span[2]/span[2]';
    $nodes = $xpath->query($primaryXPath);
    if ($nodes instanceof DOMNodeList && $nodes->length > 0) {
        $price = normalize_price_string(trim($nodes->item(0)->textContent));
        if ($price !== null) {
            return $price;
        }
    }

    $fallbackXPaths = [
        '//*[@id="priceblock_ourprice"]',
        '//*[@id="priceblock_dealprice"]',
        '//*[@id="priceblock_saleprice"]',
        '//*[@id="corePrice_feature_div"]//span[contains(@class,"a-offscreen")]',
        '//*[@id="corePriceDisplay_desktop_feature_div"]//span[contains(@class,"a-offscreen")]',
        '//*[contains(@class,"a-price")]//span[contains(@class,"a-offscreen")]',
    ];

    foreach ($fallbackXPaths as $expr) {
        $nodes = $xpath->query($expr);
        if (!($nodes instanceof DOMNodeList) || $nodes->length === 0) {
            continue;
        }

        foreach ($nodes as $node) {
            $price = normalize_price_string(trim($node->textContent));
            if ($price !== null) {
                return $price;
            }
        }
    }

    if (preg_match('/"priceAmount"\s*:\s*"?(\d+[\.,]\d{2})"?/i', $html, $matches)) {
        $price = normalize_price_string($matches[1]);
        if ($price !== null) {
            return $price;
        }
    }

    return null;
}

function extractAmazonTitleFromHtml(string $html): string {
    $title = 'Prodotto Amazon';

    if (preg_match('~<span[^>]*id="productTitle"[^>]*>(.*?)</span>~is', $html, $m)) {
        $title = trim(html_entity_decode(strip_tags($m[1]), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
    } elseif (preg_match('~<meta[^>]*property="og:title"[^>]*content="([^"]+)"~i', $html, $m)) {
        $title = trim(html_entity_decode($m[1], ENT_QUOTES | ENT_HTML5, 'UTF-8'));
    }

    return $title !== '' ? $title : 'Prodotto Amazon';
}

function get_amazon_product_price(string $url): ?float {
    $html = fetch_remote_html($url);
    if ($html === null) {
        return null;
    }

    return extract_amazon_price_from_html($html);
}

function defaultCategoryRulesMap(): array {
    return [
        'elettronica' => ['name' => 'Elettronica', 'amazon_rate' => 3.00],
        'videogiochi' => ['name' => 'Videogiochi', 'amazon_rate' => 1.00],
        'casa-cucina' => ['name' => 'Casa e cucina', 'amazon_rate' => 7.00],
        'beauty' => ['name' => 'Beauty', 'amazon_rate' => 10.00],
        'salute' => ['name' => 'Salute', 'amazon_rate' => 10.00],
        'sport' => ['name' => 'Sport', 'amazon_rate' => 7.00],
        'abbigliamento' => ['name' => 'Abbigliamento', 'amazon_rate' => 12.00],
        'scarpe' => ['name' => 'Scarpe', 'amazon_rate' => 12.00],
        'gioielli' => ['name' => 'Gioielli', 'amazon_rate' => 10.00],
        'libri' => ['name' => 'Libri', 'amazon_rate' => 7.00],
        'giocattoli' => ['name' => 'Giocattoli', 'amazon_rate' => 7.00],
        'auto-moto' => ['name' => 'Auto / Moto', 'amazon_rate' => 5.00],
        'pet' => ['name' => 'Pet', 'amazon_rate' => 8.00],
        'software' => ['name' => 'Software', 'amazon_rate' => 5.00],
        'alimentari' => ['name' => 'Alimentari', 'amazon_rate' => 5.00],
        'ufficio' => ['name' => 'Ufficio', 'amazon_rate' => 6.00],
        'bricolage' => ['name' => 'Bricolage', 'amazon_rate' => 6.00],
        'prima-infanzia' => ['name' => 'Prima infanzia', 'amazon_rate' => 7.00],
    ];
}

function seedCategoryRules(): void {
    $tableExists = false;
    try {
        db()->query('SELECT 1 FROM category_rules LIMIT 1');
        $tableExists = true;
    } catch (Throwable $e) {
        $tableExists = false;
    }

    if (!$tableExists) {
        return;
    }

    $count = (int) db()->query('SELECT COUNT(*) FROM category_rules')->fetchColumn();
    if ($count > 0) {
        return;
    }

    $stmt = db()->prepare('INSERT INTO category_rules (slug, category_name, amazon_rate, share_percent, is_active, created_at, updated_at) VALUES (?, ?, ?, ?, 1, NOW(), NOW())');
    foreach (defaultCategoryRulesMap() as $slug => $row) {
        $stmt->execute([$slug, $row['name'], $row['amazon_rate'], DEFAULT_SHARE_PERCENT]);
    }
}

function seedRewards(): void {
    $tableExists = false;
    try {
        db()->query('SELECT 1 FROM rewards LIMIT 1');
        $tableExists = true;
    } catch (Throwable $e) {
        $tableExists = false;
    }

    if (!$tableExists) {
        return;
    }

    $count = (int) db()->query('SELECT COUNT(*) FROM rewards')->fetchColumn();
    if ($count > 0) {
        return;
    }

    $rewards = [
        ['Gift card Amazon 5€', 500],
        ['Gift card Amazon 10€', 1000],
        ['Gift card Amazon 20€', 2000],
        ['Gift card Amazon 25€', 2500],
        ['Gift card Amazon 50€', 5000],
        ['Gift card Amazon 100€', 10000],
    ];

    $stmt = db()->prepare('INSERT INTO rewards (reward_name, points_cost, is_active, created_at, updated_at) VALUES (?, ?, 1, NOW(), NOW())');
    foreach ($rewards as $reward) {
        $stmt->execute([$reward[0], $reward[1]]);
    }
}

function getCategoryRules(): array {
    try {
        $rows = db()->query('SELECT * FROM category_rules WHERE is_active = 1 ORDER BY category_name ASC')->fetchAll();
        if ($rows) {
            return $rows;
        }
    } catch (Throwable $e) {
    }

    $rows = [];
    foreach (defaultCategoryRulesMap() as $slug => $row) {
        $rows[] = [
            'slug' => $slug,
            'category_name' => $row['name'],
            'amazon_rate' => $row['amazon_rate'],
            'share_percent' => DEFAULT_SHARE_PERCENT,
            'is_active' => 1,
        ];
    }
    return $rows;
}

function getCategoryRuleBySlug(?string $slug): array {
    $slug = trim((string) $slug);
    if ($slug !== '') {
        try {
            $stmt = db()->prepare('SELECT * FROM category_rules WHERE slug = ? AND is_active = 1 LIMIT 1');
            $stmt->execute([$slug]);
            $row = $stmt->fetch();
            if ($row) {
                return $row;
            }
        } catch (Throwable $e) {
        }
    }

    $defaultSlug = getSetting('default_category_slug', 'elettronica');
    if ($defaultSlug && $defaultSlug !== $slug) {
        return getCategoryRuleBySlug($defaultSlug);
    }

    return [
        'slug' => 'elettronica',
        'category_name' => 'Elettronica',
        'amazon_rate' => DEFAULT_AMAZON_RATE,
        'share_percent' => DEFAULT_SHARE_PERCENT,
        'is_active' => 1,
    ];
}

function getRewards(): array {
    try {
        return db()->query('SELECT * FROM rewards WHERE is_active = 1 ORDER BY points_cost ASC')->fetchAll();
    } catch (Throwable $e) {
        return [];
    }
}

function calculate_points_from_price(float $price, array $categoryRule): array {
    $amazonRate = (float) ($categoryRule['amazon_rate'] ?? 0);
    $sharePercent = (float) ($categoryRule['share_percent'] ?? DEFAULT_SHARE_PERCENT);

    $amazonCommission = $price * ($amazonRate / 100);
    $userValue = $amazonCommission * ($sharePercent / 100);
    $points = (int) round($userValue * 100);

    return [
        'product_price' => round($price, 2),
        'amazon_rate' => $amazonRate,
        'share_percent' => $sharePercent,
        'amazon_commission' => round($amazonCommission, 2),
        'user_value' => round($userValue, 2),
        'estimated_points' => max(0, $points),
    ];
}

function fetchProductDataByAsin(string $asin, ?string $categorySlug = null): array {
    $url = buildAffiliateUrl($asin);
    $html = fetch_remote_html($url);
    $effectiveUrl = getLastEffectiveUrl() ?: $url;
    $categoryRule = getCategoryRuleBySlug($categorySlug);

    if ($html === null) {
        return [
            'title' => 'Prodotto Amazon',
            'price' => null,
            'currency' => 'EUR',
            'effective_url' => $effectiveUrl,
            'calculation' => null,
            'category_rule' => $categoryRule,
        ];
    }

    $title = extractAmazonTitleFromHtml($html);
    $price = extract_amazon_price_from_html($html);
    $calculation = $price !== null ? calculate_points_from_price($price, $categoryRule) : null;

    return [
        'title' => $title,
        'price' => $price,
        'currency' => 'EUR',
        'effective_url' => $effectiveUrl,
        'calculation' => $calculation,
        'category_rule' => $categoryRule,
    ];
}

function calculatePoints(?float $price, ?string $categorySlug = null): int {
    if ($price === null) {
        return 0;
    }

    $calculation = calculate_points_from_price($price, getCategoryRuleBySlug($categorySlug));
    return (int) ($calculation['estimated_points'] ?? 0);
}

function recordLinkRequest(int $userId, string $originalUrl, string $resolvedUrl, string $asin, string $affiliateUrl, string $productTitle, ?float $price, int $points, ?string $categorySlug = null, ?string $categoryName = null): int {
    $stmt = db()->prepare('INSERT INTO link_requests (user_id, original_url, resolved_url, asin, affiliate_url, product_title, product_price, points_preview, category_slug, category_name, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())');
    $stmt->execute([$userId, $originalUrl, $resolvedUrl, $asin, $affiliateUrl, $productTitle, $price, $points, $categorySlug, $categoryName]);
    return (int) db()->lastInsertId();
}

function formatEuro(?float $value): string {
    if ($value === null) {
        return 'Prezzo non disponibile';
    }
    return '€ ' . number_format($value, 2, ',', '.');
}
