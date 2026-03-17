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
    migrateDatabase();
    seedCategoryRules();
    seedRewards();
}

function migrateDatabase(): void {
    try {
        $cols = db()->query("SHOW COLUMNS FROM link_requests")->fetchAll(PDO::FETCH_COLUMN);
        if (!in_array('category_slug', $cols, true)) {
            db()->exec("ALTER TABLE link_requests ADD COLUMN category_slug VARCHAR(80) DEFAULT NULL");
        }
        if (!in_array('category_name', $cols, true)) {
            db()->exec("ALTER TABLE link_requests ADD COLUMN category_name VARCHAR(120) DEFAULT NULL");
        }
    } catch (Throwable $e) {
    }
}

function setSettingIfMissing(string $key, string $value): void {
    $current = getSetting($key, null);
    if ($current === null) {
        setSetting($key, $value);
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

    if (!is_numeric($value)) {
        return null;
    }

    $f = (float) $value;

    if ($f > 10000 && !str_contains((string) $f, '.')) {
        $cents = (int) round($f) % 100;
        $euros = ((int) round($f) - $cents) / 100;
        return round($euros + $cents / 100, 2);
    }

    return $f;
}

function price_log(string $message): void {
    if (!isset($GLOBALS['__price_debug_log'])) {
        $GLOBALS['__price_debug_log'] = [];
    }
    $GLOBALS['__price_debug_log'][] = $message;
}

function get_price_debug_log(): array {
    return $GLOBALS['__price_debug_log'] ?? [];
}

function reset_price_debug_log(): void {
    $GLOBALS['__price_debug_log'] = [];
}

function extract_price_from_single_aprice(DOMNode $priceSpan): ?float {
    $xpath = new DOMXPath($priceSpan->ownerDocument);

    $wholeNodes = $xpath->query('.//span[contains(@class,"a-price-whole")]', $priceSpan);
    $fracNodes  = $xpath->query('.//span[contains(@class,"a-price-fraction")]', $priceSpan);

    if ($wholeNodes->length > 0 && $fracNodes->length > 0) {
        $whole    = preg_replace('/[^0-9]/', '', $wholeNodes->item(0)->textContent);
        $fraction = preg_replace('/[^0-9]/', '', $fracNodes->item(0)->textContent);
        if ($whole !== '' && $fraction !== '') {
            return (float) ($whole . '.' . $fraction);
        }
    }

    return null;
}

function extract_price_from_node(DOMNode $node): ?float {
    $xpath = new DOMXPath($node->ownerDocument);

    $priceToPaySelectors = [
        'priceToPay' => './/span[contains(@class,"priceToPay")]',
        'apexPriceToPay' => './/span[contains(@class,"apexPriceToPay")]',
        'a-price (no strike)' => './/span[contains(@class,"a-price") and not(contains(@class,"a-text-price")) and not(@data-a-strike)]',
    ];

    foreach ($priceToPaySelectors as $label => $selector) {
        $nodes = $xpath->query($selector, $node);
        $count = ($nodes instanceof DOMNodeList) ? $nodes->length : 0;
        price_log("[extract_price_from_node] Selector '{$label}': trovati {$count} nodi");
        if ($count > 0) {
            for ($i = 0; $i < $nodes->length; $i++) {
                $el = $nodes->item($i);
                $cls = $el->getAttribute('class') ?? '';
                $raw = trim($el->textContent);
                $price = extract_price_from_single_aprice($el);
                price_log("  -> nodo #{$i} class=\"{$cls}\" raw=\"{$raw}\" => prezzo=" . ($price !== null ? $price : 'null'));
                if ($price !== null && $price > 0) {
                    price_log("  ** SELEZIONATO: {$price} (via {$label})");
                    return $price;
                }
            }
        }
    }

    $allPriceNodes = $xpath->query('.//span[contains(@class,"a-price")]', $node);
    $totalAll = ($allPriceNodes instanceof DOMNodeList) ? $allPriceNodes->length : 0;
    price_log("[extract_price_from_node] Fallback: tutti a-price spans = {$totalAll}");

    if ($allPriceNodes instanceof DOMNodeList && $allPriceNodes->length > 0) {
        for ($i = 0; $i < $allPriceNodes->length; $i++) {
            $priceNode = $allPriceNodes->item($i);
            $classes = $priceNode->getAttribute('class') ?? '';
            $strike = $priceNode->getAttribute('data-a-strike') ?? '';
            $raw = trim($priceNode->textContent);
            $isStrike = str_contains($classes, 'a-text-price') || $strike === 'true';
            $price = extract_price_from_single_aprice($priceNode);
            price_log("  -> a-price #{$i} class=\"{$classes}\" strike={$strike} raw=\"{$raw}\" => prezzo=" . ($price !== null ? $price : 'null') . ($isStrike ? ' [BARRATO - skip]' : ''));
            if ($isStrike) {
                continue;
            }
            if ($price !== null && $price > 0) {
                price_log("  ** SELEZIONATO: {$price} (via fallback non-barrato)");
                return $price;
            }
        }

        $bestPrice = null;
        for ($i = 0; $i < $allPriceNodes->length; $i++) {
            $price = extract_price_from_single_aprice($allPriceNodes->item($i));
            if ($price !== null && $price > 0) {
                if ($bestPrice === null || $price > $bestPrice) {
                    $bestPrice = $price;
                }
            }
        }
        if ($bestPrice !== null) {
            price_log("  ** SELEZIONATO: {$bestPrice} (via best-price tra tutti)");
            return $bestPrice;
        }
    }

    $wholeNodes = $xpath->query('.//span[@aria-hidden="true"]//span[contains(@class,"a-price-whole")]', $node);
    $fracNodes  = $xpath->query('.//span[@aria-hidden="true"]//span[contains(@class,"a-price-fraction")]', $node);

    if ($wholeNodes->length === 0 || $fracNodes->length === 0) {
        $wholeNodes = $xpath->query('.//span[contains(@class,"a-price-whole")]', $node);
        $fracNodes  = $xpath->query('.//span[contains(@class,"a-price-fraction")]', $node);
    }

    if ($wholeNodes->length > 0 && $fracNodes->length > 0) {
        $whole    = preg_replace('/[^0-9]/', '', $wholeNodes->item(0)->textContent);
        $fraction = preg_replace('/[^0-9]/', '', $fracNodes->item(0)->textContent);
        if ($whole !== '' && $fraction !== '') {
            $p = (float) ($whole . '.' . $fraction);
            price_log("  ** SELEZIONATO: {$p} (via legacy whole+fraction)");
            return $p;
        }
    }

    price_log("[extract_price_from_node] Nessun prezzo trovato nel nodo");
    return null;
}

function extract_price_from_jsonld(string $html): ?float {
    if (preg_match_all('~<script[^>]*type=["\']application/ld\+json["\'][^>]*>(.*?)</script>~si', $html, $matches)) {
        foreach ($matches[1] as $jsonStr) {
            $data = @json_decode($jsonStr, true);
            if (!is_array($data)) {
                continue;
            }
            $offers = $data['offers'] ?? ($data['Offers'] ?? null);
            if (is_array($offers)) {
                if (isset($offers['@type'])) {
                    $offers = [$offers];
                }
                foreach ($offers as $offer) {
                    $p = $offer['price'] ?? null;
                    if ($p !== null && is_numeric($p) && (float) $p > 0) {
                        return (float) $p;
                    }
                }
            }
        }
    }
    return null;
}

function extract_amazon_price_from_html(string $html): ?float {
    reset_price_debug_log();
    libxml_use_internal_errors(true);

    price_log("=== INIZIO ESTRAZIONE PREZZO ===");
    price_log("HTML length: " . strlen($html) . " bytes");

    $dom = new DOMDocument();
    if (!$dom->loadHTML($html)) {
        price_log("ERRORE: impossibile parsare HTML");
        return null;
    }

    $xpath = new DOMXPath($dom);

    $mainContainerXPaths = [
        'corePriceDisplay_desktop_feature_div' => '//div[@id="corePriceDisplay_desktop_feature_div"]',
        'corePrice_feature_div' => '//div[@id="corePrice_feature_div"]',
        'apex_desktop_newAccordionRow' => '//div[@id="apex_desktop_newAccordionRow"]//div[contains(@class,"a-section")]',
        'apex_desktop' => '//div[@id="apex_desktop"]',
    ];

    foreach ($mainContainerXPaths as $label => $expr) {
        $containers = $xpath->query($expr);
        $found = ($containers instanceof DOMNodeList) ? $containers->length : 0;
        price_log("[Container] '{$label}': " . ($found > 0 ? "TROVATO ({$found})" : "non trovato"));
        if ($found === 0) {
            continue;
        }
        $price = extract_price_from_node($containers->item(0));
        if ($price !== null && $price > 0) {
            price_log("=> PREZZO FINALE: {$price} (da container '{$label}')");
            return $price;
        }
        price_log("[Container] '{$label}': nessun prezzo valido estratto dal nodo");
    }

    $legacyXPaths = [
        'priceblock_ourprice' => '//*[@id="priceblock_ourprice"]',
        'priceblock_dealprice' => '//*[@id="priceblock_dealprice"]',
        'priceblock_saleprice' => '//*[@id="priceblock_saleprice"]',
    ];

    foreach ($legacyXPaths as $label => $expr) {
        $nodes = $xpath->query($expr);
        $found = ($nodes instanceof DOMNodeList) ? $nodes->length : 0;
        price_log("[Legacy] '{$label}': " . ($found > 0 ? "TROVATO" : "non trovato"));
        if ($found === 0) {
            continue;
        }
        $raw = trim($nodes->item(0)->textContent);
        $price = normalize_price_string($raw);
        price_log("[Legacy] '{$label}' raw=\"{$raw}\" => prezzo=" . ($price !== null ? $price : 'null'));
        if ($price !== null && $price > 0) {
            price_log("=> PREZZO FINALE: {$price} (da legacy '{$label}')");
            return $price;
        }
    }

    price_log("[JSON-LD] Cerco prezzo in structured data...");
    $jsonLdPrice = extract_price_from_jsonld($html);
    price_log("[JSON-LD] Risultato: " . ($jsonLdPrice !== null ? $jsonLdPrice : 'non trovato'));
    if ($jsonLdPrice !== null) {
        price_log("=> PREZZO FINALE: {$jsonLdPrice} (da JSON-LD)");
        return $jsonLdPrice;
    }

    if (preg_match('~"buyingPrice"\s*:\s*([\d]+\.[\d]{2})~', $html, $m)) {
        $price = normalize_price_string($m[1]);
        price_log("[Regex] buyingPrice raw=\"{$m[1]}\" => prezzo=" . ($price !== null ? $price : 'null'));
        if ($price !== null && $price > 0) {
            price_log("=> PREZZO FINALE: {$price} (da buyingPrice regex)");
            return $price;
        }
    } else {
        price_log("[Regex] buyingPrice: non trovato");
    }

    if (preg_match('~"displayPrice"\s*:\s*"([\d]+[,\.]\d{2})\s*€"~', $html, $m)) {
        $price = normalize_price_string($m[1]);
        price_log("[Regex] displayPrice raw=\"{$m[1]}\" => prezzo=" . ($price !== null ? $price : 'null'));
        if ($price !== null && $price > 0) {
            price_log("=> PREZZO FINALE: {$price} (da displayPrice regex)");
            return $price;
        }
    } else {
        price_log("[Regex] displayPrice: non trovato");
    }

    price_log("=== NESSUN PREZZO TROVATO ===");
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
