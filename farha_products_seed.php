<?php
// farha_products_seed.php  —  Demo product seeder
// Usage: GET /farha_api/farha_products_seed.php?secret=farha2026products
// Safe to run multiple times: skips products that already exist for each tailor.

require_once __DIR__ . '/config.php';

if (($_GET['secret'] ?? '') !== 'farha2026products') {
    http_response_code(403);
    die(json_encode(['success' => false, 'message' => 'Forbidden.']));
}

$db = Database::connect();

// ── Fetch all tailors ──────────────────────────────────────────────────────
$tailors = $db->query('SELECT t.id AS tailor_id, u.first_name, u.last_name FROM tailors t JOIN users u ON u.id = t.user_id')->fetchAll();
if (empty($tailors)) {
    die(json_encode(['success' => false, 'message' => 'No tailors found. Run farha_seed.php first.']));
}

// ── Fetch categories ───────────────────────────────────────────────────────
$cats = $db->query('SELECT id, name_en FROM categories ORDER BY sort_order ASC')->fetchAll();
$catMap = [];
foreach ($cats as $c) $catMap[$c['name_en']] = $c['id'];

function catId(array $map, string ...$names): string {
    foreach ($names as $n) {
        if (isset($map[$n])) return $map[$n];
    }
    return array_values($map)[0];  // fallback to first category
}

// ── Beautiful product templates ────────────────────────────────────────────
// Images: stable Unsplash photos sized for mobile (400×500)
$templates = [
    [
        'name'        => 'Royal Boubou Sénégalais',
        'description' => 'Boubou traditionnel confectionné en bazin riche brodé à la main. Idéal pour les grandes cérémonies, mariages et fêtes religieuses. Disponible sur mesure.',
        'base_price'  => 45000,
        'currency'    => 'CFA',
        'stock'       => 8,
        'category'    => 'Boubou',
        'sizes'       => ['S','M','L','XL','XXL'],
        'allows_custom' => true,
        'images'      => [
            'https://images.unsplash.com/photo-1594938298603-c8148c4b4268?w=600&q=80',
            'https://images.unsplash.com/photo-1583391733956-3750e0ff4e8b?w=600&q=80',
        ],
    ],
    [
        'name'        => 'Grand Kaftan Brodé',
        'description' => 'Kaftan élégant en tissu damassé avec broderies dorées sur le col et les manches. Coupe ample et confortable, parfait pour toutes occasions.',
        'base_price'  => 38000,
        'currency'    => 'CFA',
        'stock'       => 12,
        'category'    => 'Kaftan',
        'sizes'       => ['M','L','XL','XXL','XXXL'],
        'allows_custom' => true,
        'images'      => [
            'https://images.unsplash.com/photo-1515886657613-9f3515b0c78f?w=600&q=80',
            'https://images.unsplash.com/photo-1529139574466-a303027c1d8b?w=600&q=80',
        ],
    ],
    [
        'name'        => 'Agbada Prestige 3 Pièces',
        'description' => 'Ensemble Agbada trois pièces en aso-oke tissé à la main. Comprend le grand boubou, la chemise et le pantalon. Une tenue royale pour les grandes occasions.',
        'base_price'  => 75000,
        'currency'    => 'CFA',
        'stock'       => 5,
        'category'    => 'Agbada',
        'sizes'       => ['L','XL','XXL'],
        'allows_custom' => true,
        'images'      => [
            'https://images.unsplash.com/photo-1539109136881-3be0616acf4b?w=600&q=80',
            'https://images.unsplash.com/photo-1485125639709-a60c3a500bf1?w=600&q=80',
        ],
    ],
    [
        'name'        => 'Robe de Soirée Wax Élégante',
        'description' => 'Robe longue en wax africain imprimé, coupe ajustée avec décolleté en V. Parfaite pour les soirées, cérémonies et événements formels. Entièrement faite sur mesure.',
        'base_price'  => 28000,
        'currency'    => 'CFA',
        'stock'       => 15,
        'category'    => 'Dress',
        'sizes'       => ['XS','S','M','L','XL'],
        'allows_custom' => true,
        'images'      => [
            'https://images.unsplash.com/photo-1583744946564-b52ac1c389c8?w=600&q=80',
            'https://images.unsplash.com/photo-1490481651871-ab68de25d43d?w=600&q=80',
        ],
    ],
    [
        'name'        => 'Costume Safari en Lin',
        'description' => 'Costume deux pièces en lin naturel, veste et pantalon assortis. Coupe moderne et aérée, idéale pour les événements professionnels et semi-formels en climate chaud.',
        'base_price'  => 55000,
        'currency'    => 'CFA',
        'stock'       => 6,
        'category'    => 'Suit',
        'sizes'       => ['M','L','XL','XXL'],
        'allows_custom' => true,
        'images'      => [
            'https://images.unsplash.com/photo-1507003211169-0a1dd7228f2d?w=600&q=80',
            'https://images.unsplash.com/photo-1552374196-1ab2a1c593e8?w=600&q=80',
        ],
    ],
    [
        'name'        => 'Tenue Enfant Baptême',
        'description' => 'Ensemble complet pour enfant occasion spéciale : boubou court et pantalon en bazin blanc brodé. Disponible de 2 à 12 ans. Parfait pour baptêmes et fêtes.',
        'base_price'  => 15000,
        'currency'    => 'CFA',
        'stock'       => 20,
        'category'    => "Children's Wear",
        'sizes'       => ['XS','S','M'],
        'allows_custom' => true,
        'images'      => [
            'https://images.unsplash.com/photo-1519238263530-99bdd11df2ea?w=600&q=80',
        ],
    ],
    [
        'name'        => 'Robe de Mariée Traditionnelle',
        'description' => 'Somptueuse robe de mariée inspirée des traditions africaines. Tissu damassé ivoire avec broderies argentées, traîne amovible. Entièrement sur mesure.',
        'base_price'  => 120000,
        'currency'    => 'CFA',
        'stock'       => 3,
        'category'    => 'Wedding Attire',
        'sizes'       => ['XS','S','M','L'],
        'allows_custom' => true,
        'images'      => [
            'https://images.unsplash.com/photo-1519657337289-077653f724ed?w=600&q=80',
            'https://images.unsplash.com/photo-1509631179647-0177331693ae?w=600&q=80',
        ],
    ],
    [
        'name'        => 'Ceinture Wax Artisanale',
        'description' => 'Ceinture large en tissu wax imprimé, fermoir métallique doré. Accessoire tendance qui sublime toutes vos tenues africaines ou modernes.',
        'base_price'  => 8500,
        'currency'    => 'CFA',
        'stock'       => 30,
        'category'    => 'Accessories',
        'sizes'       => ['S','M','L','XL'],
        'allows_custom' => false,
        'images'      => [
            'https://images.unsplash.com/photo-1553913861-c0fddf2619ee?w=600&q=80',
        ],
    ],
    [
        'name'        => 'Boubou Moderne Slim',
        'description' => 'Réinterprétation contemporaine du boubou traditionnel : coupe ajustée, col Mao, en coton respirant. Parfait pour un look élégant au quotidien ou en bureau.',
        'base_price'  => 22000,
        'currency'    => 'CFA',
        'stock'       => 10,
        'category'    => 'Boubou',
        'sizes'       => ['S','M','L','XL','XXL'],
        'allows_custom' => false,
        'images'      => [
            'https://images.unsplash.com/photo-1581044777550-4cfa2bf36a14?w=600&q=80',
            'https://images.unsplash.com/photo-1594938298603-c8148c4b4268?w=600&q=80',
        ],
    ],
    [
        'name'        => 'Kaftan Femme Tie & Dye',
        'description' => 'Kaftan femme en tissu tie & dye teint à la main, aux couleurs vives et uniques. Coupe fluide et ample, parfaite pour l\'été. Chaque pièce est unique.',
        'base_price'  => 19000,
        'currency'    => 'CFA',
        'stock'       => 7,
        'category'    => 'Kaftan',
        'sizes'       => ['XS','S','M','L','XL'],
        'allows_custom' => true,
        'images'      => [
            'https://images.unsplash.com/photo-1529139574466-a303027c1d8b?w=600&q=80',
        ],
    ],
    [
        'name'        => 'Ensemble Dashiki Premium',
        'description' => 'Chemise Dashiki en coton imprimé africain avec pantalon assorti. Impression à la main artisanale, couleurs vives garanties. Livraison avec finitions brodées.',
        'base_price'  => 32000,
        'currency'    => 'CFA',
        'stock'       => 9,
        'category'    => 'Suit',
        'sizes'       => ['S','M','L','XL','XXL'],
        'allows_custom' => false,
        'images'      => [
            'https://images.unsplash.com/photo-1552374196-1ab2a1c593e8?w=600&q=80',
            'https://images.unsplash.com/photo-1507003211169-0a1dd7228f2d?w=600&q=80',
        ],
    ],
    [
        'name'        => 'Robe Wax Courte Moderne',
        'description' => 'Robe courte en wax africain, coupe trapèze avec poches latérales. Design contemporain qui allie tradition et modernité. Idéale pour le quotidien.',
        'base_price'  => 14500,
        'currency'    => 'CFA',
        'stock'       => 18,
        'category'    => 'Dress',
        'sizes'       => ['XS','S','M','L'],
        'allows_custom' => false,
        'images'      => [
            'https://images.unsplash.com/photo-1583744946564-b52ac1c389c8?w=600&q=80',
        ],
    ],
];

// ── Insert products ────────────────────────────────────────────────────────
$created = 0;
$skipped = 0;

$imgStmt = $db->prepare(
    'INSERT INTO product_images (id, product_id, image_url, is_main, sort_order) VALUES (?, ?, ?, ?, ?)'
);
$prodStmt = $db->prepare('
    INSERT INTO products (id, tailor_id, category_id, name, description,
                          base_price, currency, stock_quantity, allows_custom,
                          is_available, is_draft, available_sizes, rating)
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 1, 0, ?, ?)
');

foreach ($tailors as $tailor) {
    // Check existing product count for this tailor
    $existing = $db->prepare('SELECT COUNT(*) FROM products WHERE tailor_id = ?');
    $existing->execute([$tailor['tailor_id']]);
    if ((int)$existing->fetchColumn() >= count($templates)) {
        $skipped += count($templates);
        continue;
    }

    // Assign different subsets of templates to each tailor for variety
    $tailorIndex = array_search($tailor, $tailors);
    $offset      = ($tailorIndex * 4) % count($templates);
    $subset      = array_slice($templates, $offset, 4);
    if (count($subset) < 4) {
        $subset = array_merge($subset, array_slice($templates, 0, 4 - count($subset)));
    }

    foreach ($subset as $tpl) {
        $productId  = generateUuid();
        $categoryId = catId($catMap, $tpl['category']);
        $rating     = round(3.5 + mt_rand(0, 15) / 10, 1);  // 3.5 – 5.0

        $prodStmt->execute([
            $productId,
            $tailor['tailor_id'],
            $categoryId,
            $tpl['name'],
            $tpl['description'],
            $tpl['base_price'],
            $tpl['currency'],
            $tpl['stock'],
            $tpl['allows_custom'] ? 1 : 0,
            json_encode($tpl['sizes']),
            $rating,
        ]);

        foreach (array_values($tpl['images']) as $i => $url) {
            $imgStmt->execute([generateUuid(), $productId, $url, $i === 0 ? 1 : 0, $i]);
        }

        $created++;
    }
}

Response::success([
    'created' => $created,
    'skipped' => $skipped,
    'tailors' => count($tailors),
], "Seeded $created products across " . count($tailors) . " tailors.");
