<?php
require_once __DIR__ . '/config.php';

$baseUrl = 'https://digiprox24.com';

$urls = [
    [
        'loc' => $baseUrl . '/',
        'priority' => '1.0',
        'changefreq' => 'daily'
    ],
    [
        'loc' => $baseUrl . '/products.php',
        'priority' => '0.9',
        'changefreq' => 'daily'
    ],
    [
        'loc' => $baseUrl . '/categories.php',
        'priority' => '0.8',
        'changefreq' => 'weekly'
    ],
    [
        'loc' => $baseUrl . '/services.php',
        'priority' => '0.8',
        'changefreq' => 'weekly'
    ],
    [
        'loc' => $baseUrl . '/about.php',
        'priority' => '0.7',
        'changefreq' => 'monthly'
    ],
    [
        'loc' => $baseUrl . '/contact.php',
        'priority' => '0.7',
        'changefreq' => 'monthly'
    ],
    [
        'loc' => $baseUrl . '/privacy.php',
        'priority' => '0.5',
        'changefreq' => 'yearly'
    ],
    [
        'loc' => $baseUrl . '/terms.php',
        'priority' => '0.5',
        'changefreq' => 'yearly'
    ],
    [
        'loc' => $baseUrl . '/return-policy.php',
        'priority' => '0.5',
        'changefreq' => 'yearly'
    ],
    [
        'loc' => $baseUrl . '/warranty.php',
        'priority' => '0.5',
        'changefreq' => 'yearly'
    ],
];

// Fetch active categories
try {
    $catStmt = $pdo->query("SELECT id FROM categories ORDER BY id ASC");
    while ($row = $catStmt->fetch(PDO::FETCH_ASSOC)) {
        $urls[] = [
            'loc' => $baseUrl . '/products.php?category=' . $row['id'],
            'priority' => '0.7',
            'changefreq' => 'weekly'
        ];
    }
} catch (Exception $e) {
    // Log or handle gracefully
}

// Fetch active products
try {
    $prodStmt = $pdo->query("SELECT id FROM products WHERE is_disabled = 0 ORDER BY id DESC");
    while ($row = $prodStmt->fetch(PDO::FETCH_ASSOC)) {
        $urls[] = [
            'loc' => $baseUrl . '/product_detail.php?id=' . $row['id'],
            'priority' => '0.8',
            'changefreq' => 'weekly'
        ];
    }
} catch (Exception $e) {
    // Log or handle gracefully
}

$xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
$xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";

foreach ($urls as $u) {
    $xml .= "  <url>\n";
    $xml .= "    <loc>" . htmlspecialchars($u['loc']) . "</loc>\n";
    $xml .= "    <changefreq>" . $u['changefreq'] . "</changefreq>\n";
    $xml .= "    <priority>" . $u['priority'] . "</priority>\n";
    $xml .= "  </url>\n";
}

$xml .= '</urlset>' . "\n";

file_put_contents(__DIR__ . '/sitemap.xml', $xml);
echo "sitemap.xml generated successfully with " . count($urls) . " URLs.\n";
