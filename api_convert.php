<?php
require_once __DIR__ . '/functions.php';
bootstrapSettings();
requireLogin();
header('Content-Type: application/json; charset=utf-8');

try {
    $inputUrl = trim($_POST['url'] ?? '');
    $categorySlug = trim($_POST['category_slug'] ?? '');

    if ($inputUrl === '') {
        throw new RuntimeException('Inserisci un link Amazon.');
    }

    $normalizedUrl = normalizeAmazonUrl($inputUrl);
    $resolvedUrl = resolveShortAmazonUrl($normalizedUrl);
    $asin = extractAmazonAsin($resolvedUrl);

    if (!$asin) {
        $asin = extractAmazonAsin($normalizedUrl);
    }

    if (!$asin) {
        throw new RuntimeException('ASIN non trovato nel link inserito.');
    }

    $categoryRule = getCategoryRuleBySlug($categorySlug);
    $product = fetchProductDataByAsin($asin, $categoryRule['slug'] ?? null);
    $affiliateUrl = buildAffiliateUrl($asin);
    $calculation = $product['calculation'] ?? null;
    $points = (int) (($calculation['estimated_points'] ?? 0));
    $user = currentUser();

    $requestId = recordLinkRequest(
        (int) $user['id'],
        $normalizedUrl,
        $resolvedUrl,
        $asin,
        $affiliateUrl,
        $product['title'],
        $product['price'],
        $points,
        $categoryRule['slug'] ?? null,
        $categoryRule['category_name'] ?? null
    );

    echo json_encode([
        'success' => true,
        'request_id' => $requestId,
        'asin' => $asin,
        'title' => $product['title'],
        'price' => $product['price'],
        'price_label' => formatEuro($product['price']),
        'points' => $points,
        'affiliate_url' => $affiliateUrl,
        'go_url' => 'go.php?id=' . $requestId,
        'tag' => getAffiliateTag(),
        'resolved_url' => $resolvedUrl,
        'calculation' => $calculation,
        'category_rule' => $categoryRule,
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
} catch (Throwable $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage(),
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}
