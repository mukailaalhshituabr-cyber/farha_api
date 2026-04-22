<?php
// ══════════════════════════════════════════════════════════════════════════════
// farha_seed.php  —  Demo data seeder
// Visit once in browser: http://169.239.251.102:280/~mukaila.shittu/farha_api/farha_seed.php
// DELETE THIS FILE from the server after seeding!
// ══════════════════════════════════════════════════════════════════════════════


// ── Simple secret guard — add ?secret=farha2026 to the URL ───────────────────
if (($_GET['secret'] ?? '') !== 'farha2026') {
    http_response_code(403);
    die('<h2 style="color:red;font-family:sans-serif">403 — Add ?secret=farha2026 to the URL to run this seeder.</h2>');
}

set_time_limit(120);
ini_set('display_errors', '1');
error_reporting(E_ALL);

// ── DB connection (inline — no config.php to avoid CORS headers etc.) ─────────
$host = 'localhost';
$name = 'mobileapps_2026B_mukaila_shittu';
$user = 'mukaila.shittu';
$pass = 'Adf=Tdd3&Wt';

try {
    $db = new PDO("mysql:host=$host;dbname=$name;charset=utf8mb4", $user, $pass, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
} catch (PDOException $e) {
    die('<pre style="color:red">DB connection failed: ' . $e->getMessage() . '</pre>');
}

function uuid(): string {
    $data = random_bytes(16);
    $data[6] = chr(ord($data[6]) & 0x0f | 0x40);
    $data[8] = chr(ord($data[8]) & 0x3f | 0x80);
    return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
}

$log   = [];
$creds = [];

function note(string $msg): void { global $log; $log[] = $msg; }

// ── Demo password (same for all demo accounts) ───────────────────────────────
$demoPass     = 'Demo@1234';
$demoPassHash = password_hash($demoPass, PASSWORD_BCRYPT, ['cost' => 12]);

// ── Category UUIDs (must match farha_database.sql) ───────────────────────────
$CAT_BOUBOU   = 'cat00000-0000-0000-0000-000000000001';
$CAT_KAFTAN   = 'cat00000-0000-0000-0000-000000000002';
$CAT_AGBADA   = 'cat00000-0000-0000-0000-000000000003';
$CAT_DRESS    = 'cat00000-0000-0000-0000-000000000004';
$CAT_SUIT     = 'cat00000-0000-0000-0000-000000000005';
$CAT_CHILDREN = 'cat00000-0000-0000-0000-000000000006';
$CAT_WEDDING  = 'cat00000-0000-0000-0000-000000000007';
$CAT_ACCESS   = 'cat00000-0000-0000-0000-000000000008';

// ══════════════════════════════════════════════════════════════════════════════
// CLEAN UP existing demo data (idempotent re-run)
// ══════════════════════════════════════════════════════════════════════════════
$demoEmails = [
    'amadou.diallo@farhademo.com',
    'fatou.ndiaye@farhademo.com',
    'ibrahim.sow@farhademo.com',
    'aisha.customer@farhademo.com',
    'kofi.customer@farhademo.com',
];
$placeholders = implode(',', array_fill(0, count($demoEmails), '?'));
$existingUsers = $db->prepare("SELECT id FROM users WHERE email IN ($placeholders)");
$existingUsers->execute($demoEmails);
$existingIds = $existingUsers->fetchAll(PDO::FETCH_COLUMN);

if ($existingIds) {
    $p = implode(',', array_fill(0, count($existingIds), '?'));
    $db->prepare("DELETE FROM users WHERE id IN ($p)")->execute($existingIds);
    note('Cleaned up ' . count($existingIds) . ' existing demo user(s).');
}

// ══════════════════════════════════════════════════════════════════════════════
// TAILOR 1 — Amadou Diallo
// ══════════════════════════════════════════════════════════════════════════════
$t1UserId   = uuid();
$t1TailorId = uuid();
$t1Email    = 'amadou.diallo@farhademo.com';

$db->prepare('INSERT INTO users (id,email,phone,password_hash,user_type,first_name,last_name,language,is_verified,is_active,last_login) VALUES (?,?,?,?,"tailor",?,?,?,1,1,NOW())')
   ->execute([$t1UserId, $t1Email, '+221771234501', $demoPassHash, 'Amadou', 'Diallo', 'fr']);

$db->prepare('INSERT INTO tailors (id,user_id,shop_name,gender,bio,shop_location,latitude,longitude,years_experience,experience_level,is_available,is_verified_tailor,rating,total_reviews,total_orders) VALUES (?,?,?,?,?,?,?,?,?,?,1,1,4.80,24,47)')
   ->execute([
       $t1TailorId, $t1UserId,
       'Atelier Diallo Couture',
       'male',
       'Maître tailleur avec plus de 15 ans d\'expérience dans la confection de boubous et de tenues traditionnelles ouest-africaines. Spécialisé dans les broderies à la main et les tissus bazin riche.',
       'Médina, Dakar, Sénégal',
       14.7167, -17.4677,
       15, 'grandmaster',
   ]);

$creds[] = ['name' => 'Amadou Diallo (Tailor)', 'email' => $t1Email, 'password' => $demoPass];
note("Created tailor: Amadou Diallo ($t1Email)");

// ══════════════════════════════════════════════════════════════════════════════
// TAILOR 2 — Fatou Ndiaye
// ══════════════════════════════════════════════════════════════════════════════
$t2UserId   = uuid();
$t2TailorId = uuid();
$t2Email    = 'fatou.ndiaye@farhademo.com';

$db->prepare('INSERT INTO users (id,email,phone,password_hash,user_type,first_name,last_name,language,is_verified,is_active,last_login) VALUES (?,?,?,?,"tailor",?,?,?,1,1,NOW())')
   ->execute([$t2UserId, $t2Email, '+221771234502', $demoPassHash, 'Fatou', 'Ndiaye', 'fr']);

$db->prepare('INSERT INTO tailors (id,user_id,shop_name,gender,bio,shop_location,latitude,longitude,years_experience,experience_level,is_available,is_verified_tailor,rating,total_reviews,total_orders) VALUES (?,?,?,?,?,?,?,?,?,?,1,1,4.65,18,33)')
   ->execute([
       $t2TailorId, $t2UserId,
       'Fatou Fashion Studio',
       'female',
       'Créatrice de mode spécialisée dans les robes de soirée et tenues de mariage. Mon atelier marie élégamment les tissus wax africains aux coupes modernes pour une femme contemporaine.',
       'Plateau, Abidjan, Côte d\'Ivoire',
       5.3364, -4.0267,
       10, 'master',
   ]);

$creds[] = ['name' => 'Fatou Ndiaye (Tailor)', 'email' => $t2Email, 'password' => $demoPass];
note("Created tailor: Fatou Ndiaye ($t2Email)");

// ══════════════════════════════════════════════════════════════════════════════
// TAILOR 3 — Ibrahim Sow
// ══════════════════════════════════════════════════════════════════════════════
$t3UserId   = uuid();
$t3TailorId = uuid();
$t3Email    = 'ibrahim.sow@farhademo.com';

$db->prepare('INSERT INTO users (id,email,phone,password_hash,user_type,first_name,last_name,language,is_verified,is_active,last_login) VALUES (?,?,?,?,"tailor",?,?,?,1,1,NOW())')
   ->execute([$t3UserId, $t3Email, '+22370012345', $demoPassHash, 'Ibrahim', 'Sow', 'en']);

$db->prepare('INSERT INTO tailors (id,user_id,shop_name,gender,bio,shop_location,latitude,longitude,years_experience,experience_level,is_available,is_verified_tailor,rating,total_reviews,total_orders) VALUES (?,?,?,?,?,?,?,?,?,?,1,1,4.90,41,89)')
   ->execute([
       $t3TailorId, $t3UserId,
       'Royal Agbada House',
       'male',
       'Award-winning master tailor with 20+ years crafting premium Agbada and Kaftan for royalty and dignitaries across West Africa. Every stitch tells a story of heritage and excellence.',
       'Victoria Island, Lagos, Nigeria',
       6.4281, 3.4219,
       20, 'grandmaster',
   ]);

$creds[] = ['name' => 'Ibrahim Sow (Tailor)', 'email' => $t3Email, 'password' => $demoPass];
note("Created tailor: Ibrahim Sow ($t3Email)");

// ══════════════════════════════════════════════════════════════════════════════
// CUSTOMERS
// ══════════════════════════════════════════════════════════════════════════════
$c1UserId     = uuid();
$c1CustomerId = uuid();
$c1Email      = 'aisha.customer@farhademo.com';

$db->prepare('INSERT INTO users (id,email,phone,password_hash,user_type,first_name,last_name,language,is_verified,is_active,last_login) VALUES (?,?,?,?,"customer",?,?,?,1,1,NOW())')
   ->execute([$c1UserId, $c1Email, '+221771234510', $demoPassHash, 'Aisha', 'Coulibaly', 'fr']);
$db->prepare('INSERT INTO customers (id,user_id,gender) VALUES (?,?,?)')
   ->execute([$c1CustomerId, $c1UserId, 'female']);

$c2UserId     = uuid();
$c2CustomerId = uuid();
$c2Email      = 'kofi.customer@farhademo.com';

$db->prepare('INSERT INTO users (id,email,phone,password_hash,user_type,first_name,last_name,language,is_verified,is_active,last_login) VALUES (?,?,?,?,"customer",?,?,?,1,1,NOW())')
   ->execute([$c2UserId, $c2Email, '+23324012345', $demoPassHash, 'Kofi', 'Mensah', 'en']);
$db->prepare('INSERT INTO customers (id,user_id,gender) VALUES (?,?,?)')
   ->execute([$c2CustomerId, $c2UserId, 'male']);

$creds[] = ['name' => 'Aisha Coulibaly (Customer)', 'email' => $c1Email, 'password' => $demoPass];
$creds[] = ['name' => 'Kofi Mensah (Customer)',     'email' => $c2Email, 'password' => $demoPass];
note("Created 2 demo customers.");

// ══════════════════════════════════════════════════════════════════════════════
// PRODUCTS
// ══════════════════════════════════════════════════════════════════════════════

// Helper: insert product + images, return product id
function insertProduct(PDO $db, string $tailorId, string $catId, string $name, string $desc,
    float $price, array $sizes, array $images, int $stock = 10,
    bool $allowsCustom = false, float $rating = 0.0, int $reviews = 0, int $sales = 0
): string {
    $id = uuid();
    $db->prepare('INSERT INTO products (id,tailor_id,category_id,name,description,base_price,currency,stock_quantity,allows_custom,is_available,is_draft,available_sizes,rating,total_reviews,total_sales) VALUES (?,?,?,?,?,?,?,?,?,1,0,?,?,?,?)')
       ->execute([
           $id, $tailorId, $catId, $name, $desc,
           $price, 'CFA', $stock,
           $allowsCustom ? 1 : 0,
           $sizes ? json_encode($sizes) : null,
           $rating, $reviews, $sales,
       ]);
    foreach ($images as $i => $url) {
        $db->prepare('INSERT INTO product_images (id,product_id,image_url,is_main,sort_order) VALUES (?,?,?,?,?)')
           ->execute([uuid(), $id, $url, $i === 0 ? 1 : 0, $i]);
    }
    return $id;
}

// ── Tailor 1 (Amadou Diallo) — Boubous & Kaftans ─────────────────────────────
$p1 = insertProduct($db, $t1TailorId, $CAT_BOUBOU,
    'Grand Boubou Brodé Premium',
    'Boubou trois pièces en bazin riche blanc cassé avec broderies dorées faites à la main autour du col et des manches. Tissu importé du Mali, coupe ample et confortable pour les grandes occasions.',
    85000, ['S','M','L','XL','XXL'],
    [
        'https://images.unsplash.com/photo-1589156280159-27698a70f29e?w=800&q=80',
        'https://images.unsplash.com/photo-1583391733956-6c78276477e2?w=800&q=80',
        'https://images.unsplash.com/photo-1578662996442-48f60103fc96?w=800&q=80',
    ], 8, true, 4.9, 12, 28
);

$p2 = insertProduct($db, $t1TailorId, $CAT_BOUBOU,
    'Boubou Bazin Riche Bleu Royal',
    'Boubou deux pièces en bazin riche bleu royal avec broderies blanches au col. Légèreté et élégance pour vos cérémonies et fêtes religieuses. Disponible sur mesure.',
    62000, ['M','L','XL','XXL'],
    [
        'https://images.unsplash.com/photo-1560243563-062bfc001d68?w=800&q=80',
        'https://images.unsplash.com/photo-1594938298603-c8148c4b4357?w=800&q=80',
    ], 12, true, 4.8, 8, 19
);

$p3 = insertProduct($db, $t1TailorId, $CAT_KAFTAN,
    'Kaftan Senegalais Prestige',
    'Kaftan en tissu wax de haute qualité, coupe droite avec broderies sur le devant. Idéal pour les mariages, baptêmes et événements formels. Personnalisation des couleurs possible.',
    45000, ['S','M','L','XL'],
    [
        'https://images.unsplash.com/photo-1617038260897-41a1f14a8ca0?w=800&q=80',
        'https://images.unsplash.com/photo-1594938298603-c8148c4b4357?w=800&q=80',
    ], 15, true, 4.7, 6, 15
);

$p4 = insertProduct($db, $t1TailorId, $CAT_ACCESS,
    'Chéchia Traditionnelle Brodée',
    'Chéchia artisanale en feutre rouge avec broderies dorées. Accompagnement parfait pour votre boubou ou kaftan. Fabriquée à la main selon les traditions sénégalaises.',
    12000, ['Unique'],
    [
        'https://images.unsplash.com/photo-1521369909029-2afed882baaa?w=800&q=80',
    ], 20, false, 4.6, 4, 11
);

note("Created 4 products for Amadou Diallo.");

// ── Tailor 2 (Fatou Ndiaye) — Dresses & Wedding ──────────────────────────────
$p5 = insertProduct($db, $t2TailorId, $CAT_DRESS,
    'Robe Soirée Wax Moderne',
    'Robe longue en wax africain imprimé, coupe sirène avec fente latérale. Dos décolleté et manches kimono courtes. La fusion parfaite du style africain et de la mode contemporaine.',
    55000, ['XS','S','M','L','XL'],
    [
        'https://images.unsplash.com/photo-1515886657613-9f3515b0c78f?w=800&q=80',
        'https://images.unsplash.com/photo-1539109136881-3be0616acf4b?w=800&q=80',
        'https://images.unsplash.com/photo-1496747611176-843222e1e57c?w=800&q=80',
    ], 6, true, 4.8, 9, 14
);

$p6 = insertProduct($db, $t2TailorId, $CAT_WEDDING,
    'Robe Mariée Africaine Luxe',
    'Splendide robe de mariée alliant dentelle ivoire et tissu brodé africain. Corsage structuré, jupe volumineuse et traîne de 1,5 mètre. Création unique, entièrement sur mesure.',
    320000, ['XS','S','M','L'],
    [
        'https://images.unsplash.com/photo-1594552072238-b8a33785b7c0?w=800&q=80',
        'https://images.unsplash.com/photo-1583939003579-730e3918a45a?w=800&q=80',
        'https://images.unsplash.com/photo-1519741497674-611481863552?w=800&q=80',
    ], 3, true, 5.0, 7, 7
);

$p7 = insertProduct($db, $t2TailorId, $CAT_DRESS,
    'Ensemble Pagne 2 Pièces',
    'Ensemble élégant composé d\'un top bustier et d\'une jupe midi en pagne wax. Broderies raffinées sur le bustier. Parfait pour les cérémonies de mariage et baptêmes.',
    38000, ['XS','S','M','L','XL'],
    [
        'https://images.unsplash.com/photo-1490481651871-ab68de25d43d?w=800&q=80',
        'https://images.unsplash.com/photo-1485968579580-b6d095142e6e?w=800&q=80',
    ], 10, true, 4.5, 5, 12
);

$p8 = insertProduct($db, $t2TailorId, $CAT_CHILDREN,
    'Tenue Enfant Baptême Wax',
    'Adorable ensemble bébé en wax africain coloré. Comprend une chemise, un pantalon et un chapeau assorti. Disponible de 3 mois à 5 ans. Lavable en machine.',
    18000, ['3M','6M','12M','2ans','3ans','4ans','5ans'],
    [
        'https://images.unsplash.com/photo-1503944583220-79d8926ad5e2?w=800&q=80',
        'https://images.unsplash.com/photo-1522771930-78848d9293e8?w=800&q=80',
    ], 18, true, 4.7, 11, 22
);

$p9 = insertProduct($db, $t2TailorId, $CAT_WEDDING,
    'Tenue Marié Grand Boubou Blanc',
    'Magnifique grand boubou blanc pour le marié avec broderies argent sur le col et les poignets. Tissu bazin superfin importé. Fait pour marquer les esprits le jour J.',
    145000, ['M','L','XL','XXL','3XL'],
    [
        'https://images.unsplash.com/photo-1594938374182-a57369b8ee62?w=800&q=80',
        'https://images.unsplash.com/photo-1507003211169-0a1dd7228f2d?w=800&q=80',
    ], 4, true, 4.9, 6, 9
);

note("Created 5 products for Fatou Ndiaye.");

// ── Tailor 3 (Ibrahim Sow) — Agbada & Suits ──────────────────────────────────
$p10 = insertProduct($db, $t3TailorId, $CAT_AGBADA,
    'Agbada Royal 3 Pièces Or et Noir',
    'Majestueux Agbada trois pièces en tissu damassé noir avec broderies dorées élaborées. Comprend le grand Agbada, la chemise Buba et le pantalon Sokoto. Pour les occasions les plus solennelles.',
    195000, ['M','L','XL','XXL','3XL'],
    [
        'https://images.unsplash.com/photo-1574180045827-681f8a1a9622?w=800&q=80',
        'https://images.unsplash.com/photo-1606107557195-0e29a4b5b4aa?w=800&q=80',
        'https://images.unsplash.com/photo-1598300042247-d088f8ab3a91?w=800&q=80',
    ], 5, true, 4.95, 18, 32
);

$p11 = insertProduct($db, $t3TailorId, $CAT_AGBADA,
    'Agbada Blanc Cérémonie Prestige',
    'Agbada blanc pur en bazin avec broderies bleues royales. Symbolise l\'élégance et la dignité. Confectionné à la main avec soin par nos artisans expérimentés. Livré avec le Fila assorti.',
    165000, ['L','XL','XXL','3XL'],
    [
        'https://images.unsplash.com/photo-1489987707025-afc232f7ea0f?w=800&q=80',
        'https://images.unsplash.com/photo-1542060748-10c28b62716f?w=800&q=80',
    ], 6, true, 4.85, 14, 25
);

$p12 = insertProduct($db, $t3TailorId, $CAT_KAFTAN,
    'Kaftan Homme Damassé Premium',
    'Kaftan homme en tissu damassé bordeaux avec broderies crème. Coupe droite moderne légèrement ajustée. Parfait pour les dîners d\'affaires, cérémonies et événements culturels.',
    75000, ['S','M','L','XL','XXL'],
    [
        'https://images.unsplash.com/photo-1617038260897-41a1f14a8ca0?w=800&q=80',
        'https://images.unsplash.com/photo-1603252109303-2751441dd157?w=800&q=80',
    ], 9, true, 4.7, 10, 18
);

$p13 = insertProduct($db, $t3TailorId, $CAT_SUIT,
    'Costume Africain 3 Pièces Wax',
    'Costume trois pièces (veste, gilet, pantalon) entièrement réalisé en wax africain imprimé. Coupe slim moderne, doublure en soie. Le mariage réussi entre élégance occidentale et identité africaine.',
    125000, ['S','M','L','XL'],
    [
        'https://images.unsplash.com/photo-1507679799987-c73779587ccf?w=800&q=80',
        'https://images.unsplash.com/photo-1617137984095-74e4e5e3613f?w=800&q=80',
        'https://images.unsplash.com/photo-1594938374182-a57369b8ee62?w=800&q=80',
    ], 7, true, 4.8, 9, 15
);

$p14 = insertProduct($db, $t3TailorId, $CAT_SUIT,
    'Blazer Kente Africain',
    'Blazer élégant en tissu Kente tissé à la main au Ghana. Doublure imprimée africaine. Une pièce statement qui affirme votre identité culturelle en toute circonstance professionnelle.',
    89000, ['S','M','L','XL','XXL'],
    [
        'https://images.unsplash.com/photo-1617137984095-74e4e5e3613f?w=800&q=80',
        'https://images.unsplash.com/photo-1490578474895-699cd4e2cf59?w=800&q=80',
    ], 11, true, 4.6, 7, 13
);

$p15 = insertProduct($db, $t3TailorId, $CAT_ACCESS,
    'Ceinture & Pochette Cuir Artisanal',
    'Set accessoires homme: ceinture en cuir véritable tanné à la main avec boucle dorée + pochette de costume assortie. Fabrication artisanale nigériane de qualité supérieure.',
    22000, ['Unique'],
    [
        'https://images.unsplash.com/photo-1553062407-98eeb64c6a62?w=800&q=80',
        'https://images.unsplash.com/photo-1548036328-c9fa89d128fa?w=800&q=80',
    ], 25, false, 4.5, 6, 16
);

note("Created 6 products for Ibrahim Sow.");

// ══════════════════════════════════════════════════════════════════════════════
// ORDERS
// ══════════════════════════════════════════════════════════════════════════════

function insertOrder(PDO $db, string $ref, string $custId, string $tailorId,
    ?string $productId, string $type, string $status,
    float $total, float $paid, string $size = 'L', int $qty = 1,
    ?string $completed = null, int $daysAgo = 7
): string {
    $id = uuid();
    $createdAt = date('Y-m-d H:i:s', strtotime("-{$daysAgo} days"));
    $db->prepare('INSERT INTO orders (id,reference_number,customer_id,tailor_id,product_id,order_type,status,size,quantity,total_amount,deposit_amount,paid_amount,currency,estimated_completion,created_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)')
       ->execute([$id, $ref, $custId, $tailorId, $productId, $type, $status, $size, $qty, $total, $total * 0.3, $paid, 'CFA', $completed, $createdAt]);
    return $id;
}

$o1 = insertOrder($db, 'FRH-2026-001', $c1CustomerId, $t1TailorId, $p1, 'ready_made', 'delivered', 85000, 85000, 'XL', 1, date('Y-m-d', strtotime('-3 days')), 14);
$o2 = insertOrder($db, 'FRH-2026-002', $c1CustomerId, $t2TailorId, $p5, 'ready_made', 'delivered', 55000, 55000, 'M', 1, date('Y-m-d', strtotime('-1 day')), 10);
$o3 = insertOrder($db, 'FRH-2026-003', $c2CustomerId, $t3TailorId, $p10,'ready_made', 'sewing',    195000, 58500,'XXL',1, date('Y-m-d', strtotime('+5 days')), 3);
$o4 = insertOrder($db, 'FRH-2026-004', $c2CustomerId, $t1TailorId, $p3, 'ready_made', 'confirmed', 45000, 13500,'L',  1, date('Y-m-d', strtotime('+7 days')), 1);
$o5 = insertOrder($db, 'FRH-2026-005', $c1CustomerId, $t3TailorId, $p13,'custom',     'cutting',   125000,37500,'M',  1, date('Y-m-d', strtotime('+10 days')),5);
$o6 = insertOrder($db, 'FRH-2026-006', $c2CustomerId, $t2TailorId, $p6, 'custom',     'delivered', 320000,320000,'S', 1, date('Y-m-d', strtotime('-2 days')), 30);

// Payments for delivered orders
foreach ([[$o1, 85000], [$o2, 55000], [$o6, 320000]] as [$oid, $amt]) {
    $db->prepare('INSERT INTO payments (id,order_id,amount,currency,payment_method,transaction_id,status) VALUES (?,?,?,?,?,?,?)')
       ->execute([uuid(), $oid, $amt, 'CFA', 'wave', 'TXN-' . strtoupper(substr(md5($oid), 0, 10)), 'completed']);
}

// Update tailor order counts
$db->prepare('UPDATE tailors SET total_orders = total_orders + 2 WHERE id = ?')->execute([$t1TailorId]);
$db->prepare('UPDATE tailors SET total_orders = total_orders + 2 WHERE id = ?')->execute([$t2TailorId]);
$db->prepare('UPDATE tailors SET total_orders = total_orders + 2 WHERE id = ?')->execute([$t3TailorId]);

note("Created 6 demo orders.");

// ══════════════════════════════════════════════════════════════════════════════
// REVIEWS
// ══════════════════════════════════════════════════════════════════════════════

function insertReview(PDO $db, string $orderId, string $productId, string $tailorId,
    string $customerId, int $stars, string $comment): void {
    $db->prepare('INSERT INTO reviews (id,order_id,product_id,tailor_id,customer_id,rating,comment) VALUES (?,?,?,?,?,?,?)')
       ->execute([uuid(), $orderId, $productId, $tailorId, $customerId, $stars, $comment]);
    // Update product rating
    $db->prepare('UPDATE products SET total_reviews = total_reviews + 1, rating = ((rating * (total_reviews - 1) + ?) / total_reviews) WHERE id = ?')
       ->execute([$stars, $productId]);
}

insertReview($db, $o1, $p1,  $t1TailorId, $c1CustomerId, 5,
    'Qualité exceptionnelle! Le boubou est magnifique, les broderies sont parfaites. Amadou est très professionnel et respecte les délais. Je recommande vivement cet atelier!');

insertReview($db, $o2, $p5,  $t2TailorId, $c1CustomerId, 5,
    'Robe absolument sublime! Fatou a su capturer exactement ce que je voulais. Les finitions sont impeccables et le wax est de très bonne qualité. Je reviendrai certainement!');

insertReview($db, $o6, $p6,  $t2TailorId, $c2CustomerId, 5,
    'Ibrahim is a true master of his craft. The Agbada is absolutely regal — the embroidery details are breathtaking. Received so many compliments at the ceremony. Worth every franc!');

note("Created 3 reviews.");

// ══════════════════════════════════════════════════════════════════════════════
// WISHLIST & CART SAMPLES
// ══════════════════════════════════════════════════════════════════════════════

// Aisha wishlist
foreach ([$p10, $p11, $p13] as $pid) {
    $db->prepare('INSERT IGNORE INTO wishlist (id,customer_id,product_id) VALUES (?,?,?)')->execute([uuid(), $c1CustomerId, $pid]);
}
// Kofi wishlist
foreach ([$p6, $p7, $p9] as $pid) {
    $db->prepare('INSERT IGNORE INTO wishlist (id,customer_id,product_id) VALUES (?,?,?)')->execute([uuid(), $c2CustomerId, $pid]);
}
// Aisha cart
$db->prepare('INSERT IGNORE INTO cart_items (id,customer_id,product_id,quantity,size) VALUES (?,?,?,?,?)')->execute([uuid(), $c1CustomerId, $p12, 1, 'M']);
$db->prepare('INSERT IGNORE INTO cart_items (id,customer_id,product_id,quantity,size) VALUES (?,?,?,?,?)')->execute([uuid(), $c1CustomerId, $p15, 2, 'Unique']);

note("Created wishlist and cart items.");

// ══════════════════════════════════════════════════════════════════════════════
// MEASUREMENTS for customers
// ══════════════════════════════════════════════════════════════════════════════

$db->prepare('INSERT INTO measurements (id,customer_id,label,chest,waist,hips,shoulder_width,arm_length,inseam,height,weight,unit) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)')
   ->execute([uuid(), $c1CustomerId, 'Mes mensurations', 88, 70, 95, 38, 60, 75, 165, 60, 'cm']);

$db->prepare('INSERT INTO measurements (id,customer_id,label,chest,waist,hips,shoulder_width,arm_length,inseam,height,weight,unit) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)')
   ->execute([uuid(), $c2CustomerId, 'My measurements', 102, 86, 98, 46, 65, 80, 178, 82, 'cm']);

note("Created measurements for both customers.");

// ══════════════════════════════════════════════════════════════════════════════
// NOTIFICATIONS
// ══════════════════════════════════════════════════════════════════════════════

$notifications = [
    [$c1UserId,  'order_confirmed', 'Order Confirmed!', 'Your Grand Boubou Brodé Premium order #FRH-2026-001 is being prepared.', $o1],
    [$c1UserId,  'order_delivered', 'Order Delivered!', 'Your order #FRH-2026-001 has been delivered. Enjoy your beautiful Boubou!', $o1],
    [$c2UserId,  'order_update',    'Order In Progress', 'Your Royal Agbada #FRH-2026-003 is now being sewn. Est. delivery in 5 days.', $o3],
    [$t1UserId,  'new_order',       'New Order Received', 'Aisha Coulibaly placed a new order for Grand Boubou Brodé Premium.', $o1],
    [$t2UserId,  'new_order',       'New Order Received', 'Kofi Mensah placed a custom order for Robe Mariée Africaine Luxe.', $o6],
];

$nStmt = $db->prepare('INSERT INTO notifications (id,user_id,type,title,body,reference_id,is_read) VALUES (?,?,?,?,?,?,0)');
foreach ($notifications as [$uid, $type, $title, $body, $relId]) {
    $nStmt->execute([uuid(), $uid, $type, $title, $body, $relId]);
}

note("Created 5 notifications.");

// ══════════════════════════════════════════════════════════════════════════════
// OUTPUT
// ══════════════════════════════════════════════════════════════════════════════
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Farha Seed — Done</title>
<style>
  body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif; max-width: 780px; margin: 40px auto; padding: 0 20px; background: #f8f4f0; color: #333; }
  h1   { color: #c4820a; }
  h2   { color: #555; margin-top: 32px; }
  .ok  { background: #d4edda; border-left: 4px solid #28a745; padding: 8px 14px; margin: 6px 0; border-radius: 4px; }
  table { width: 100%; border-collapse: collapse; margin-top: 10px; }
  th, td { padding: 10px 14px; border: 1px solid #ddd; text-align: left; }
  th { background: #c4820a; color: #fff; }
  tr:nth-child(even) td { background: #fdf7f0; }
  .warn { background: #fff3cd; border-left: 4px solid #ffc107; padding: 12px 14px; border-radius: 4px; margin-top: 24px; font-weight: bold; }
  code { background: #eee; padding: 2px 6px; border-radius: 3px; font-size: 0.9em; }
</style>
</head>
<body>
<h1>Farha — Demo Seed Complete</h1>

<h2>What was created</h2>
<?php foreach ($log as $line): ?>
<div class="ok">✓ <?= htmlspecialchars($line) ?></div>
<?php endforeach; ?>

<h2>Demo Login Credentials</h2>
<p>All accounts use the same password: <code><?= $demoPass ?></code></p>
<table>
<tr><th>Name</th><th>Email</th><th>Password</th><th>Type</th></tr>
<?php foreach ($creds as $c): ?>
<tr>
  <td><?= htmlspecialchars($c['name']) ?></td>
  <td><?= htmlspecialchars($c['email']) ?></td>
  <td><code><?= htmlspecialchars($c['password']) ?></code></td>
  <td><?= str_contains($c['name'], 'Tailor') ? 'Tailor' : 'Customer' ?></td>
</tr>
<?php endforeach; ?>
</table>

<div class="warn">
  ⚠️ DELETE this file from the server now!<br>
  It exposes database credentials. Run:<br>
  <code>rm ~/public_html/farha_api/farha_seed.php</code>
</div>
</body>
</html>
