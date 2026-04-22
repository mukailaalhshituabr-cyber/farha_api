<?php
/**
 * farha_full_seed.php — Rich sample data for Farha App
 *
 * Run via browser : http://yourserver/farha_api/farha_full_seed.php
 * Run via CLI     : php farha_full_seed.php
 *
 * Creates  5 customers · 4 tailors · 16 products · 10 orders
 *          4 conversations · reviews · payments · wishlist · cart
 *
 * All demo passwords : Demo1234!
 */

require_once __DIR__ . '/config.php';

$db   = Database::connect();
$pass = password_hash('Demo1234!', PASSWORD_BCRYPT);

/** Execute silently — skip duplicate-key or missing-column errors */
function run(PDO $db, string $sql, array $p = []): void {
    try { $db->prepare($sql)->execute($p); }
    catch (\Exception $e) { /* intentionally silent */ }
}

// ════════════════════════════════════════════════════════════════════════════
// 1.  USERS
// ════════════════════════════════════════════════════════════════════════════

// ── Stable UUIDs ─────────────────────────────────────────────────────────────
$uC1 = 'usr-cust-0001-0000-000000000001'; // Aminata  Diallo     (f · Dakar)
$uC2 = 'usr-cust-0002-0000-000000000002'; // Karim    Coulibaly  (m · Dakar)
$uC3 = 'usr-cust-0003-0000-000000000003'; // Fatoumata Bah       (f · Niamey)
$uC4 = 'usr-cust-0004-0000-000000000004'; // Moussa   Maïga      (m · Niamey)
$uC5 = 'usr-cust-0005-0000-000000000005'; // Khadija  Touré      (f · Dakar)
$uT1 = 'usr-tail-0001-0000-000000000001'; // Ibrahim  Sawadogo   (m · Dakar)
$uT2 = 'usr-tail-0002-0000-000000000002'; // Fatima   Traoré     (f · Dakar)
$uT3 = 'usr-tail-0003-0000-000000000003'; // Ousmane  Diop       (m · Dakar)
$uT4 = 'usr-tail-0004-0000-000000000004'; // Mariam   Cissé      (f · Niamey)

// ── Profile photos (randomuser.me — stable, high quality) ────────────────────
$photos = [
    $uC1 => 'https://randomuser.me/api/portraits/women/44.jpg',
    $uC2 => 'https://randomuser.me/api/portraits/men/36.jpg',
    $uC3 => 'https://randomuser.me/api/portraits/women/65.jpg',
    $uC4 => 'https://randomuser.me/api/portraits/men/52.jpg',
    $uC5 => 'https://randomuser.me/api/portraits/women/31.jpg',
    $uT1 => 'https://randomuser.me/api/portraits/men/78.jpg',
    $uT2 => 'https://randomuser.me/api/portraits/women/12.jpg',
    $uT3 => 'https://randomuser.me/api/portraits/men/22.jpg',
    $uT4 => 'https://randomuser.me/api/portraits/women/55.jpg',
];

$users = [
    [$uC1,'aminata.diallo@farha.demo',   '+221771234501','customer','Aminata',   'Diallo',   'fr'],
    [$uC2,'karim.coulibaly@farha.demo',  '+221771234502','customer','Karim',     'Coulibaly','fr'],
    [$uC3,'fatoumata.bah@farha.demo',    '+22790123456', 'customer','Fatoumata', 'Bah',      'fr'],
    [$uC4,'moussa.maiga@farha.demo',     '+22791234567', 'customer','Moussa',    'Maïga',    'fr'],
    [$uC5,'khadija.toure@farha.demo',    '+221771234505','customer','Khadija',   'Touré',    'fr'],
    [$uT1,'ibrahim.sawadogo@farha.demo', '+221771234503','tailor',  'Ibrahim',   'Sawadogo', 'fr'],
    [$uT2,'fatima.traore@farha.demo',    '+221771234504','tailor',  'Fatima',    'Traoré',   'fr'],
    [$uT3,'ousmane.diop@farha.demo',     '+221771234506','tailor',  'Ousmane',   'Diop',     'fr'],
    [$uT4,'mariam.cisse@farha.demo',     '+22792345678', 'tailor',  'Mariam',    'Cissé',    'fr'],
];

foreach ($users as [$id,$email,$phone,$type,$fn,$ln,$lang]) {
    $chk = $db->prepare('SELECT id FROM users WHERE email = ? LIMIT 1');
    $chk->execute([$email]);
    if ($chk->fetch()) {
        // Patch photo if the row already exists
        run($db,'UPDATE users SET photo_url=? WHERE id=? AND (photo_url IS NULL OR photo_url="")',
            [$photos[$id],$id]);
        continue;
    }
    run($db,'
        INSERT INTO users(id,email,phone,password_hash,user_type,first_name,last_name,
                          language,photo_url,is_verified,is_active)
        VALUES(?,?,?,?,?,?,?,?,?,1,1)',
        [$id,$email,$phone,$pass,$type,$fn,$ln,$lang,$photos[$id]]);
}

// ── Customer profiles ─────────────────────────────────────────────────────────
$cust1 = 'cust-0001-0000-0000-000000000001';
$cust2 = 'cust-0002-0000-0000-000000000002';
$cust3 = 'cust-0003-0000-0000-000000000003';
$cust4 = 'cust-0004-0000-0000-000000000004';
$cust5 = 'cust-0005-0000-0000-000000000005';

foreach ([
    [$cust1,$uC1,'female'],
    [$cust2,$uC2,'male'],
    [$cust3,$uC3,'female'],
    [$cust4,$uC4,'male'],
    [$cust5,$uC5,'female'],
] as [$id,$uid,$gender]) {
    $chk = $db->prepare('SELECT id FROM customers WHERE user_id=? LIMIT 1');
    $chk->execute([$uid]);
    if (!$chk->fetch()) {
        run($db,'INSERT INTO customers(id,user_id,gender) VALUES(?,?,?)',[$id,$uid,$gender]);
    }
}

// ── Tailor profiles ───────────────────────────────────────────────────────────
$tail1 = 'tail-0001-0000-0000-000000000001';
$tail2 = 'tail-0002-0000-0000-000000000002';
$tail3 = 'tail-0003-0000-0000-000000000003';
$tail4 = 'tail-0004-0000-0000-000000000004';

// Cover images — picsum deterministic seeds
$covers = [
    $tail1 => 'https://picsum.photos/seed/atelier-ibrahim/900/400',
    $tail2 => 'https://picsum.photos/seed/maison-fatima/900/400',
    $tail3 => 'https://picsum.photos/seed/couture-ousmane/900/400',
    $tail4 => 'https://picsum.photos/seed/mariam-niamey/900/400',
];

$tailors = [
    [$tail1,$uT1,
     "Atelier Ibrahim — Couture Prestige",
     "Spécialiste des tenues cérémonielles pour hommes depuis 15 ans. Boubou bazin, kaftan, agbada et costumes brodés à la main sur commande ou en prêt-à-porter.",
     'Médina, Rue 22, Dakar, Sénégal', 14.6937,-17.4441, 15,'master', 4.9, 87],
    [$tail2,$uT2,
     "Maison Fatima — Soie de Dakar",
     "Techniques patrimoniales rencontrent tissu de luxe. Spécialiste de la mode féminine africaine — robes de mariée, kaftan brodé doré et tenues de soirée sur mesure.",
     'Plateau, Avenue L. S. Senghor, Dakar, Sénégal', 14.6928,-17.4467, 12,'grandmaster', 4.8, 63],
    [$tail3,$uT3,
     "Couture Ousmane Diop",
     "Mode africaine contemporaine fusionnant modernité et tradition. Collections mixtes pour hommes et femmes avec des tissus wax et basin de première qualité.",
     'Grand Dakar, Rue des Artisans, Dakar, Sénégal', 14.7167,-17.4677, 8,'senior', 4.7, 41],
    [$tail4,$uT4,
     "Mariam Création — Niamey",
     "Couturière nigérienne reconnue depuis 10 ans. Spécialisée dans les tissus locaux (basin, wax, tergal). Livraison partout au Niger. Sur mesure ou prêt-à-porter.",
     'Plateau, Rue du Commerce, Niamey, Niger', 13.5137, 2.1098, 10,'master', 4.85, 55],
];

foreach ($tailors as [$id,$uid,$shop,$bio,$loc,$lat,$lng,$yrs,$lvl,$rat,$rev]) {
    $chk = $db->prepare('SELECT id FROM tailors WHERE user_id=? LIMIT 1');
    $chk->execute([$uid]);
    if (!$chk->fetch()) {
        run($db,'
            INSERT INTO tailors(id,user_id,shop_name,bio,shop_location,latitude,longitude,
                                years_experience,experience_level,is_available,is_verified_tailor,
                                rating,total_reviews,total_orders)
            VALUES(?,?,?,?,?,?,?,?,?,1,1,?,?,?)',
            [$id,$uid,$shop,$bio,$loc,$lat,$lng,$yrs,$lvl,$rat,$rev,0]);
        run($db,'UPDATE tailors SET cover_image_url=? WHERE id=?',[$covers[$id],$id]);
    }
}

// ════════════════════════════════════════════════════════════════════════════
// 2.  CATEGORIES
// ════════════════════════════════════════════════════════════════════════════

$categories = [
    ['cat00000-0000-0000-0000-000000000001','Boubou'],
    ['cat00000-0000-0000-0000-000000000002','Kaftan'],
    ['cat00000-0000-0000-0000-000000000003','Agbada'],
    ['cat00000-0000-0000-0000-000000000004','Robe / Dress'],
    ['cat00000-0000-0000-0000-000000000005','Costume / Suit'],
    ['cat00000-0000-0000-0000-000000000006','Tenue Enfant'],
    ['cat00000-0000-0000-0000-000000000007','Tenue de Mariage'],
    ['cat00000-0000-0000-0000-000000000008','Accessoires'],
];
foreach ($categories as [$cid,$cname]) {
    $chk = $db->prepare('SELECT id FROM categories WHERE id=? LIMIT 1');
    $chk->execute([$cid]);
    if (!$chk->fetch()) {
        run($db,'INSERT INTO categories(id,name,slug) VALUES(?,?,?)',
            [$cid,$cname,strtolower(str_replace([' ','/',"'",'é','è','ê'],'_',$cname))]);
    }
}

// ════════════════════════════════════════════════════════════════════════════
// 3.  PRODUCTS  (4 per tailor = 16 total)
// ════════════════════════════════════════════════════════════════════════════

// Product images — picsum with descriptive seeds (deterministic)
// format: https://picsum.photos/seed/{seed}/600/750
$pimg = [
    'p01' => ['https://picsum.photos/seed/boubou-bazin-gold/600/750',  'https://picsum.photos/seed/boubou-bazin-blue/600/750'],
    'p02' => ['https://picsum.photos/seed/kaftan-senegal-1/600/750',   'https://picsum.photos/seed/kaftan-senegal-2/600/750'],
    'p03' => ['https://picsum.photos/seed/agbada-senator-1/600/750',   'https://picsum.photos/seed/agbada-senator-2/600/750'],
    'p04' => ['https://picsum.photos/seed/costume-mariage-1/600/750',  'https://picsum.photos/seed/costume-mariage-2/600/750'],
    'p05' => ['https://picsum.photos/seed/robe-wax-soiree-1/600/750',  'https://picsum.photos/seed/robe-wax-soiree-2/600/750'],
    'p06' => ['https://picsum.photos/seed/kaftan-femme-or-1/600/750',  'https://picsum.photos/seed/kaftan-femme-or-2/600/750'],
    'p07' => ['https://picsum.photos/seed/tenue-enfant-wax-1/600/750', 'https://picsum.photos/seed/tenue-enfant-wax-2/600/750'],
    'p08' => ['https://picsum.photos/seed/robe-mariee-kente-1/600/750','https://picsum.photos/seed/robe-mariee-kente-2/600/750'],
    'p09' => ['https://picsum.photos/seed/boubou-lin-blanc-1/600/750', 'https://picsum.photos/seed/boubou-lin-blanc-2/600/750'],
    'p10' => ['https://picsum.photos/seed/dashiki-moderne-1/600/750',  'https://picsum.photos/seed/dashiki-moderne-2/600/750'],
    'p11' => ['https://picsum.photos/seed/robe-ankara-1/600/750',      'https://picsum.photos/seed/robe-ankara-2/600/750'],
    'p12' => ['https://picsum.photos/seed/tenue-enfant-bap-1/600/750', 'https://picsum.photos/seed/tenue-enfant-bap-2/600/750'],
    'p13' => ['https://picsum.photos/seed/boubou-niger-1/600/750',     'https://picsum.photos/seed/boubou-niger-2/600/750'],
    'p14' => ['https://picsum.photos/seed/robe-basin-niger-1/600/750', 'https://picsum.photos/seed/robe-basin-niger-2/600/750'],
    'p15' => ['https://picsum.photos/seed/kaftan-tergal-1/600/750',    'https://picsum.photos/seed/kaftan-tergal-2/600/750'],
    'p16' => ['https://picsum.photos/seed/ensemble-mariee-niger-1/600/750','https://picsum.photos/seed/ensemble-mariee-niger-2/600/750'],
];

$products = [
    // ── Ibrahim (tail1) ──
    ['prod-0001-0000-0000-000000000001',$tail1,'cat00000-0000-0000-0000-000000000001','p01',
     'Boubou Bazin Prestige', 85000,
     "Grand Boubou en bazin riche brodé à la main, idéal pour mariages et cérémonies. Disponible en bleu roi, bordeaux et vert forêt.",
     ['L','XL','XXL','XXXL'], 12, 0],
    ['prod-0002-0000-0000-000000000001',$tail1,'cat00000-0000-0000-0000-000000000002','p02',
     'Kaftan Sénégalais Moderne', 55000,
     "Kaftan élégant avec broderie sénégalaise sur le col et les manches. Coupe moderne adaptée à toutes les occasions.",
     ['M','L','XL','XXL'], 8, 0],
    ['prod-0003-0000-0000-000000000001',$tail1,'cat00000-0000-0000-0000-000000000003','p03',
     'Agbada Senator Premium', 120000,
     "Agbada trois pièces en tissu de luxe. Broderies dorées sur le grand boubou, pantalon et bonnet assortis inclus.",
     ['L','XL','XXL'], 5, 0],
    ['prod-0004-0000-0000-000000000001',$tail1,'cat00000-0000-0000-0000-000000000007','p04',
     'Costume de Mariage Brodé', 180000,
     "Ensemble complet pour mariages : boubou, pantalon et bonnet brodés à la main. Sur mesure disponible sous 10 jours.",
     ['M','L','XL','XXL','XXXL'], 3, 0],

    // ── Fatima (tail2) ──
    ['prod-0005-0000-0000-000000000002',$tail2,'cat00000-0000-0000-0000-000000000004','p05',
     'Robe Wax Soirée Chic', 65000,
     "Magnifique robe de soirée en tissu wax imprimé, coupe sirène ajustée. Entièrement cousue à la main avec finitions impeccables.",
     ['XS','S','M','L'], 10, 0],
    ['prod-0006-0000-0000-000000000002',$tail2,'cat00000-0000-0000-0000-000000000002','p06',
     'Kaftan Femme Brodé Or', 75000,
     "Kaftan féminin somptueux avec broderie dorée sur toute la longueur. Tissu Bazin supérieur, livré avec foulard assorti.",
     ['S','M','L','XL'], 6, 0],
    ['prod-0007-0000-0000-000000000002',$tail2,'cat00000-0000-0000-0000-000000000006','p07',
     'Tenue Enfant Baptême Wax', 25000,
     "Ensemble complet pour enfants (2–10 ans) en wax coloré festif. Idéal pour baptêmes, anniversaires et fêtes.",
     ['2-3ans','4-5ans','6-8ans','9-10ans'], 15, 0],
    ['prod-0008-0000-0000-000000000002',$tail2,'cat00000-0000-0000-0000-000000000007','p08',
     'Robe de Mariée Traditionnelle', 250000,
     "Robe de mariée en tissu Kente et soie avec broderie perlée. Sur mesure disponible. Voile et accessoires assortis sur demande.",
     ['XS','S','M','L','XL'], 2, 0],

    // ── Ousmane (tail3) ──
    ['prod-0009-0000-0000-000000000003',$tail3,'cat00000-0000-0000-0000-000000000001','p09',
     'Boubou Lin Blanc Cérémonie', 70000,
     "Boubou en lin blanc épuré, broderie discrète sur les épaules. Légèreté parfaite pour les grandes chaleurs.",
     ['M','L','XL','XXL'], 7, 0],
    ['prod-0010-0000-0000-000000000003',$tail3,'cat00000-0000-0000-0000-000000000005','p10',
     'Dashiki Modern Gentleman', 42000,
     "Chemise Dashiki revisitée avec coupe slim moderne. Tissu wax premium, manches courtes ou longues selon choix.",
     ['S','M','L','XL'], 14, 0],
    ['prod-0011-0000-0000-000000000003',$tail3,'cat00000-0000-0000-0000-000000000004','p11',
     'Robe Ankara Géométrique', 58000,
     "Robe mi-longue en ankara aux motifs géométriques vifs. Coupe droite avec ceinture tissu, versatile du bureau à la soirée.",
     ['XS','S','M','L','XL'], 9, 0],
    ['prod-0012-0000-0000-000000000003',$tail3,'cat00000-0000-0000-0000-000000000006','p12',
     'Ensemble Enfant Fête Complet', 32000,
     "Ensemble boubou + pantalon pour garçon, ou robe + foulard pour fille. Tissu wax doux, disponible en 10 coloris.",
     ['2-3ans','4-5ans','6-8ans','9-10ans'], 20, 0],

    // ── Mariam (tail4 — Niger) ──
    ['prod-0013-0000-0000-000000000004',$tail4,'cat00000-0000-0000-0000-000000000001','p13',
     'Boubou Basin Nigérien Classique', 60000,
     "Grand Boubou traditionnel nigérien en basin riche. Broderie locale authentique, teinture naturelle. Confort et noblesse.",
     ['L','XL','XXL','XXXL'], 10, 0],
    ['prod-0014-0000-0000-000000000004',$tail4,'cat00000-0000-0000-0000-000000000004','p14',
     'Robe Basin Soirée Niamey', 68000,
     "Robe longue en basin de haute qualité, broderies argentées sur le col et les manches. Coupe ample et élégante.",
     ['S','M','L','XL'], 8, 0],
    ['prod-0015-0000-0000-000000000004',$tail4,'cat00000-0000-0000-0000-000000000002','p15',
     'Kaftan Tergal Homme Niger', 50000,
     "Kaftan en tergal de qualité supérieure, confectionné selon les traditions nigériennes. Léger et facile à entretenir.",
     ['M','L','XL','XXL'], 11, 0],
    ['prod-0016-0000-0000-000000000004',$tail4,'cat00000-0000-0000-0000-000000000007','p16',
     'Ensemble Mariée Nigérienne', 220000,
     "Tenue de mariée complète selon la tradition nigérienne : grand boubou brodé, foulard coordonné et ceinture ornée.",
     ['S','M','L','XL'], 2, 0],
];

foreach ($products as [$pid,$tid,$cat,$imgKey,$name,$price,$desc,$sizes,$stock,$draft]) {
    $chk = $db->prepare('SELECT id FROM products WHERE id=? LIMIT 1');
    $chk->execute([$pid]);
    if ($chk->fetch()) continue;

    run($db,'
        INSERT INTO products(id,tailor_id,category_id,name,description,base_price,currency,
                             stock_quantity,is_available,is_draft,allows_custom)
        VALUES(?,?,?,?,?,?,?,?,1,?,1)',
        [$pid,$tid,$cat,$name,$desc,$price,'CFA',$stock,$draft]);

    // Two images per product
    run($db,'INSERT INTO product_images(id,product_id,image_url,is_main) VALUES(?,?,?,1)',
        [generateUuid(),$pid,$pimg[$imgKey][0]]);
    run($db,'INSERT INTO product_images(id,product_id,image_url,is_main) VALUES(?,?,?,0)',
        [generateUuid(),$pid,$pimg[$imgKey][1]]);

    // Sizes
    foreach ($sizes as $sz) {
        run($db,'INSERT IGNORE INTO product_sizes(product_id,size) VALUES(?,?)',[$pid,$sz]);
    }
}

// ════════════════════════════════════════════════════════════════════════════
// 4.  ORDERS  (10 orders — varied statuses and customers)
// ════════════════════════════════════════════════════════════════════════════

// [id, ref, customer_id, tailor_id, product_id, type, status, qty, size,
//  total, deposit, paid, currency, special_instructions, days_ago]
$orders = [
    ['ord-0001-0000-0000-000000000001','FAR-2025-0001',$cust1,$tail1,
     'prod-0001-0000-0000-000000000001','ready_made','sewing',
     1,'XXL', 85000, 25500, 25500,'CFA',null,3],
    ['ord-0002-0000-0000-000000000001','FAR-2025-0002',$cust1,$tail2,
     'prod-0005-0000-0000-000000000002','ready_made','delivered',
     1,'M',   65000, 19500, 65000,'CFA',null,15],
    ['ord-0003-0000-0000-000000000001','FAR-2025-0003',$cust2,$tail1,
     'prod-0003-0000-0000-000000000001','ready_made','pending',
     1,'XL', 120000, 36000, 36000,'CFA','Préférence couleur bleue roi',1],
    ['ord-0004-0000-0000-000000000001','FAR-2025-0004',$cust1,$tail1,
     null,'custom','cutting',
     1, null, 95000, 28500, 28500,'CFA','Kaftan avec col mandarin, manches 3/4, broderie épaules',7],
    ['ord-0005-0000-0000-000000000001','FAR-2025-0005',$cust2,$tail2,
     'prod-0006-0000-0000-000000000002','ready_made','delivered',
     1,'L',   75000, 22500, 75000,'CFA',null,21],
    ['ord-0006-0000-0000-000000000001','FAR-2025-0006',$cust1,$tail2,
     'prod-0008-0000-0000-000000000002','ready_made','confirmed',
     1,'S',  250000, 75000, 75000,'CFA','Robe de mariée sur mesure avec voile assorti',2],
    ['ord-0007-0000-0000-000000000001','FAR-2025-0007',$cust3,$tail4,
     'prod-0013-0000-0000-000000000004','ready_made','ready',
     1,'XL',  60000, 18000, 18000,'CFA',null,5],
    ['ord-0008-0000-0000-000000000001','FAR-2025-0008',$cust4,$tail4,
     'prod-0015-0000-0000-000000000004','ready_made','delivered',
     1,'L',   50000, 15000, 50000,'CFA',null,12],
    ['ord-0009-0000-0000-000000000001','FAR-2025-0009',$cust5,$tail3,
     'prod-0010-0000-0000-000000000003','ready_made','confirmed',
     2,'M',   84000, 25200, 25200,'CFA','Une chemise blanche et une orange',2],
    ['ord-0010-0000-0000-000000000001','FAR-2025-0010',$cust3,$tail3,
     'prod-0011-0000-0000-000000000003','ready_made','sewing',
     1,'S',   58000, 17400, 17400,'CFA',null,4],
];

foreach ($orders as [$oid,$ref,$custId,$tailId,$prodId,$type,$status,$qty,$size,
                     $total,$dep,$paid,$cur,$instr,$daysAgo]) {
    $chk = $db->prepare('SELECT id FROM orders WHERE id=? LIMIT 1');
    $chk->execute([$oid]);
    if ($chk->fetch()) continue;

    $dt = (new DateTime())->modify("-{$daysAgo} days")->format('Y-m-d H:i:s');
    run($db,'
        INSERT INTO orders(id,reference_number,customer_id,tailor_id,product_id,order_type,
                           status,quantity,size,total_amount,deposit_amount,paid_amount,
                           currency,special_instructions,created_at,updated_at)
        VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)',
        [$oid,$ref,$custId,$tailId,$prodId,$type,$status,$qty,$size,
         $total,$dep,$paid,$cur,$instr,$dt,$dt]);
}

// ════════════════════════════════════════════════════════════════════════════
// 5.  PAYMENTS  (for orders with paid_amount > 0)
// ════════════════════════════════════════════════════════════════════════════

$payments = [
    // orderId,  amount,  method,           daysAgo
    ['ord-0001-0000-0000-000000000001', 25500, 'mtn_momo',    3],
    ['ord-0002-0000-0000-000000000001', 19500, 'orange_money',15],
    ['ord-0002-0000-0000-000000000001', 45500, 'orange_money',10], // balance
    ['ord-0003-0000-0000-000000000001', 36000, 'telecel',      1],
    ['ord-0004-0000-0000-000000000001', 28500, 'mtn_momo',     7],
    ['ord-0005-0000-0000-000000000001', 22500, 'orange_money',21],
    ['ord-0005-0000-0000-000000000001', 52500, 'orange_money',16], // balance
    ['ord-0006-0000-0000-000000000001', 75000, 'amana',        2],
    ['ord-0007-0000-0000-000000000001', 18000, 'mynita',       5],
    ['ord-0008-0000-0000-000000000001', 15000, 'mtn_momo',    12],
    ['ord-0008-0000-0000-000000000001', 35000, 'mtn_momo',     7], // balance
    ['ord-0009-0000-0000-000000000001', 25200, 'telecel',      2],
    ['ord-0010-0000-0000-000000000001', 17400, 'orange_money', 4],
];

foreach ($payments as [$ordId,$amount,$method,$daysAgo]) {
    $chk = $db->prepare('SELECT id FROM orders WHERE id=? LIMIT 1');
    $chk->execute([$ordId]);
    $order = $chk->fetch();
    if (!$order) continue;

    $dt  = (new DateTime())->modify("-{$daysAgo} days")->format('Y-m-d H:i:s');
    $pid = generateUuid();
    run($db,'
        INSERT INTO payments(id,order_id,amount,currency,payment_method,status,
                             transaction_id,created_at)
        VALUES(?,?,?,"CFA",?,"completed",?,?)',
        [$pid,$ordId,$amount,$method,'FARHA-'.strtoupper(bin2hex(random_bytes(4))),$dt]);
}

// ════════════════════════════════════════════════════════════════════════════
// 6.  CONVERSATIONS + MESSAGES
// ════════════════════════════════════════════════════════════════════════════

$conv1 = 'conv-0001-0000-0000-000000000001'; // Aminata   ↔ Ibrahim
$conv2 = 'conv-0002-0000-0000-000000000002'; // Karim     ↔ Fatima
$conv3 = 'conv-0003-0000-0000-000000000003'; // Fatoumata ↔ Mariam
$conv4 = 'conv-0004-0000-0000-000000000004'; // Khadija   ↔ Ousmane

foreach ([
    [$conv1,$cust1,$tail1,'ord-0001-0000-0000-000000000001'],
    [$conv2,$cust2,$tail2,'ord-0005-0000-0000-000000000001'],
    [$conv3,$cust3,$tail4,'ord-0007-0000-0000-000000000001'],
    [$conv4,$cust5,$tail3,'ord-0009-0000-0000-000000000001'],
] as [$cid,$custId,$tailId,$ordId]) {
    $chk = $db->prepare('SELECT id FROM conversations WHERE id=? LIMIT 1');
    $chk->execute([$cid]);
    if (!$chk->fetch()) {
        run($db,'INSERT INTO conversations(id,customer_id,tailor_id,order_id,created_at)
                 VALUES(?,?,?,?,NOW())',[$cid,$custId,$tailId,$ordId]);
    }
}

// [uuid, conv_id, sender_id, body, hours_ago, is_read]
$msgs = [
    // ── Conv 1 : Aminata ↔ Ibrahim ──────────────────────────────────────
    [generateUuid(),$conv1,$uC1,
     "Salam Ibrahim ! Pouvez-vous me donner des nouvelles de mon boubou bazin ?",72,1],
    [generateUuid(),$conv1,$uT1,
     "Wa alaykoum assalam Aminata ! Votre boubou est en couture. Nous terminons les broderies demain insha'Allah.",71,1],
    [generateUuid(),$conv1,$uC1,
     "Alhamdulillah ! Est-ce possible d'ajouter une broderie supplémentaire sur les manches ?",48,1],
    [generateUuid(),$conv1,$uT1,
     "Oui, c'est tout à fait possible. Je vous appelle ce soir pour voir le modèle ensemble.",47,1],
    [generateUuid(),$conv1,$uC1,
     "Parfait, je serai disponible après 18h. Jazakallah khayran !",46,0],

    // ── Conv 2 : Karim ↔ Fatima ─────────────────────────────────────────
    [generateUuid(),$conv2,$uC2,
     "Bonjour Madame Fatima, ma commande de kaftan est-elle prête ?",24,1],
    [generateUuid(),$conv2,$uT2,
     "Salam Karim ! Oui, votre kaftan doré est prêt. Vous pouvez venir le récupérer à partir de demain, 9h–18h.",23,1],
    [generateUuid(),$conv2,$uC2,
     "Excellent ! Je serai là demain matin. Merci beaucoup.",22,0],

    // ── Conv 3 : Fatoumata ↔ Mariam ─────────────────────────────────────
    [generateUuid(),$conv3,$uC3,
     "Assalam Mariam ! J'ai commandé un boubou basin XL. Quel est le délai de confection ?",120,1],
    [generateUuid(),$conv3,$uT4,
     "Wa alaykoum assalam Fatoumata ! Pour un boubou basin XL avec broderies, comptez 5 à 7 jours ouvrables.",118,1],
    [generateUuid(),$conv3,$uC3,
     "D'accord merci. C'est pour une cérémonie dans 2 semaines, donc il y a de la marge.",117,1],
    [generateUuid(),$conv3,$uT4,
     "Parfait ! Je commence dès demain. Je vous enverrai une photo quand la broderie sera terminée.",116,0],

    // ── Conv 4 : Khadija ↔ Ousmane ──────────────────────────────────────
    [generateUuid(),$conv4,$uC5,
     "Salam ! J'ai commandé deux chemises Dashiki. Puis-je choisir les couleurs exactes ?",50,1],
    [generateUuid(),$conv4,$uT3,
     "Wa alaykoum assalam ! Bien sûr. Je peux faire une orange vif et une blanche ivoire comme vous avez indiqué. Cela vous convient ?",49,1],
    [generateUuid(),$conv4,$uC5,
     "Oui, parfait ! Et est-ce que vous faites les manches longues aussi ?",48,1],
    [generateUuid(),$conv4,$uT3,
     "Absolument. Manches longues disponibles sans supplément. Insha'Allah votre commande sera prête dans 4 jours.",47,0],
];

foreach ($msgs as [$mid,$convId,$senderId,$body,$hoursAgo,$isRead]) {
    $chk = $db->prepare('SELECT id FROM messages WHERE id=? LIMIT 1');
    $chk->execute([$mid]);
    if ($chk->fetch()) continue;

    $dt = (new DateTime())->modify("-{$hoursAgo} hours")->format('Y-m-d H:i:s');
    run($db,'INSERT INTO messages(id,conversation_id,sender_id,message_text,is_read,created_at)
             VALUES(?,?,?,?,?,?)', [$mid,$convId,$senderId,$body,$isRead,$dt]);
    run($db,'UPDATE conversations SET last_message=?,last_message_at=?,updated_at=NOW() WHERE id=?',
        [$body,$dt,$convId]);
}

// ════════════════════════════════════════════════════════════════════════════
// 7.  REVIEWS
// ════════════════════════════════════════════════════════════════════════════

$reviews = [
    // [customer_id, tailor_id, product_id, rating, comment, days_ago]
    [$cust1,$tail1,'prod-0001-0000-0000-000000000001',5,
     "Ibrahim est un maître artisan. Mon boubou bazin est d'une qualité exceptionnelle — broderies parfaites, tissu sublime. Je recommande vivement !",22],
    [$cust2,$tail2,'prod-0006-0000-0000-000000000002',5,
     "Fatima est une vraie artiste ! Le kaftan doré est magnifique, exactement ce que je cherchais. La broderie est fine et les finitions sont impeccables.",18],
    [$cust1,$tail2,'prod-0005-0000-0000-000000000002',4,
     "Très belle robe wax, la coupe sirène est parfaite. Livraison légèrement en retard mais le résultat final valait l'attente.",20],
    [$cust4,$tail4,'prod-0015-0000-0000-000000000004',5,
     "Mariam a confectionné un kaftan tergal exceptionnel. La qualité de finition et la rapidité de livraison sont remarquables. Très professionnel !",14],
    [$cust3,$tail4,'prod-0013-0000-0000-000000000004',4,
     "Beau boubou basin, tissu de bonne qualité et broderies soignées. Je referai appel à Mariam pour ma prochaine commande.",8],
    [$cust5,$tail3,'prod-0010-0000-0000-000000000003',5,
     "Ousmane a réalisé deux dashikis magnifiques avec une coupe parfaitement ajustée. Rapide, professionnel et à l'écoute. Bravo !",3],
];

foreach ($reviews as [$custId,$tailId,$prodId,$rating,$comment,$daysAgo]) {
    $chk = $db->prepare('SELECT id FROM reviews WHERE customer_id=? AND product_id=? LIMIT 1');
    $chk->execute([$custId,$prodId]);
    if ($chk->fetch()) continue;

    $dt = (new DateTime())->modify("-{$daysAgo} days")->format('Y-m-d H:i:s');
    run($db,'INSERT INTO reviews(id,customer_id,tailor_id,product_id,rating,comment,created_at)
             VALUES(?,?,?,?,?,?,?)',
        [generateUuid(),$custId,$tailId,$prodId,$rating,$comment,$dt]);
}

// ════════════════════════════════════════════════════════════════════════════
// 8.  MEASUREMENTS
// ════════════════════════════════════════════════════════════════════════════

$measurements = [
    ['meas-0001-0000-0000-000000000001',$cust1,'Mes mensurations principales','general',
     92,74,98,38,60,140,36,'cm',"Boubou légèrement ample sur les hanches"],
    ['meas-0002-0000-0000-000000000002',$cust3,'Mensurations Fatoumata','general',
     88,70,96,36,58,138,35,'cm',"Préfère les robes avec plus d'aisance sur les épaules"],
    ['meas-0003-0000-0000-000000000003',$cust5,'Tenues soirée','general',
     86,68,94,35,57,136,34,'cm',"Coupe ajustée sur la taille"],
    ['meas-0004-0000-0000-000000000004',$cust2,'Mes mesures','general',
     100,86,null,44,64,null,40,'cm',"Coupe droite, légèrement large sur les épaules"],
    ['meas-0005-0000-0000-000000000005',$cust4,'Mensurations Moussa','general',
     98,84,null,43,62,null,39,'cm',"Kaftan standard, longueur traditionnelle"],
];

foreach ($measurements as [$mid,$custId,$name,$type,$chest,$waist,$hips,$shoulder,$sleeve,$length,$neck,$unit,$notes]) {
    $chk = $db->prepare('SELECT id FROM measurements WHERE id=? LIMIT 1');
    $chk->execute([$mid]);
    if ($chk->fetch()) continue;

    run($db,'
        INSERT INTO measurements(id,customer_id,profile_name,garment_type,
                                 chest,waist,hips,shoulder_width,sleeve_length,
                                 total_length,neck,unit,notes)
        VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?)',
        [$mid,$custId,$name,$type,$chest,$waist,$hips,$shoulder,$sleeve,$length,$neck,$unit,$notes]);
}

// ════════════════════════════════════════════════════════════════════════════
// 9.  NOTIFICATIONS
// ════════════════════════════════════════════════════════════════════════════

$notifs = [
    // [user_id, type, title, body, reference_id, is_read, hours_ago]
    [$uC1,'order_status','Couture en cours — Boubou Bazin',
     "Ibrahim travaille actuellement sur votre boubou. La broderie sera terminée demain insha'Allah.",
     'ord-0001-0000-0000-000000000001',0,70],
    [$uC1,'order_status','Commande confirmée — Robe de Mariée',
     "Fatima a confirmé votre commande de robe de mariée. Délai estimé : 15 jours.",
     'ord-0006-0000-0000-000000000001',0,48],
    [$uC1,'payment','Paiement reçu — Dépôt confirmé',
     "Votre dépôt de 75 000 CFA pour la robe de mariée a été reçu avec succès.",
     'ord-0006-0000-0000-000000000001',1,48],
    [$uC2,'order_status','Commande reçue — Agbada Senator',
     "Ibrahim a bien reçu votre commande d'Agbada Senator Premium. Il vous contactera prochainement.",
     'ord-0003-0000-0000-000000000001',0,24],
    [$uC3,'order_status','Commande prête à récupérer !',
     "Votre boubou basin XL chez Mariam Création est prêt. Passez le récupérer à Niamey, Plateau.",
     'ord-0007-0000-0000-000000000001',0,5],
    [$uC5,'order_status','Couture en cours — Dashiki',
     "Ousmane a commencé la confection de vos deux chemises Dashiki. Prêt dans environ 4 jours.",
     'ord-0009-0000-0000-000000000001',0,48],
    [$uC5,'message','Nouveau message de Ousmane Diop',
     "Absolument. Manches longues disponibles sans supplément. Insha'Allah votre commande sera prête dans 4 jours.",
     'ord-0009-0000-0000-000000000001',0,47],
    [$uT1,'order_status','Nouvelle commande — Agbada Senator',
     "Karim Coulibaly a passé une commande d'Agbada Senator Premium (taille XL).",
     'ord-0003-0000-0000-000000000001',0,24],
    [$uT2,'order_status','Nouvelle commande — Robe de Mariée',
     "Aminata Diallo a commandé une Robe de Mariée Traditionnelle taille S avec voile assorti.",
     'ord-0006-0000-0000-000000000001',0,48],
    [$uT4,'order_status','Commande livrée — Kaftan Tergal',
     "Moussa Maïga a confirmé la réception de sa commande de Kaftan Tergal. Merci pour votre confiance !",
     'ord-0008-0000-0000-000000000001',1,120],
];

foreach ($notifs as [$uid,$type,$title,$body,$refId,$isRead,$hoursAgo]) {
    $dt = (new DateTime())->modify("-{$hoursAgo} hours")->format('Y-m-d H:i:s');
    run($db,'
        INSERT INTO notifications(id,user_id,type,title,body,reference_id,is_read,created_at)
        VALUES(?,?,?,?,?,?,?,?)',
        [generateUuid(),$uid,$type,$title,$body,$refId,$isRead,$dt]);
}

// ════════════════════════════════════════════════════════════════════════════
// 10.  CART ITEMS
// ════════════════════════════════════════════════════════════════════════════

$cartItems = [
    // [customer_id, product_id, qty, size]
    [$cust1,'prod-0002-0000-0000-000000000001',1,'XL'],
    [$cust1,'prod-0009-0000-0000-000000000003',1,'XXL'],
    [$cust2,'prod-0013-0000-0000-000000000004',1,'XL'],
    [$cust4,'prod-0009-0000-0000-000000000003',1,'L'],
];

foreach ($cartItems as [$custId,$prodId,$qty,$sz]) {
    $chk = $db->prepare('SELECT id FROM cart_items WHERE customer_id=? AND product_id=? LIMIT 1');
    $chk->execute([$custId,$prodId]);
    if (!$chk->fetch()) {
        run($db,'INSERT INTO cart_items(id,customer_id,product_id,quantity,size) VALUES(?,?,?,?,?)',
            [generateUuid(),$custId,$prodId,$qty,$sz]);
    }
}

// ════════════════════════════════════════════════════════════════════════════
// 11.  WISHLIST ITEMS
// ════════════════════════════════════════════════════════════════════════════

$wishlist = [
    [$cust1,'prod-0008-0000-0000-000000000002'],
    [$cust1,'prod-0006-0000-0000-000000000002'],
    [$cust3,'prod-0016-0000-0000-000000000004'],
    [$cust5,'prod-0005-0000-0000-000000000002'],
    [$cust5,'prod-0011-0000-0000-000000000003'],
    [$cust2,'prod-0003-0000-0000-000000000001'],
    [$cust4,'prod-0001-0000-0000-000000000001'],
];

foreach ($wishlist as [$custId,$prodId]) {
    $chk = $db->prepare('SELECT id FROM wishlist_items WHERE customer_id=? AND product_id=? LIMIT 1');
    $chk->execute([$custId,$prodId]);
    if (!$chk->fetch()) {
        run($db,'INSERT INTO wishlist_items(id,customer_id,product_id,added_at) VALUES(?,?,?,NOW())',
            [generateUuid(),$custId,$prodId]);
    }
}

// ════════════════════════════════════════════════════════════════════════════
// Done
// ════════════════════════════════════════════════════════════════════════════
echo json_encode([
    'success' => true,
    'message' => 'Seed complete!',
    'summary' => [
        'customers'     => 5,
        'tailors'       => 4,
        'products'      => 16,
        'orders'        => 10,
        'payments'      => 13,
        'conversations' => 4,
        'reviews'       => 6,
        'measurements'  => 5,
        'notifications' => 10,
        'cart_items'    => 4,
        'wishlist'      => 7,
    ],
    'demo_accounts' => [
        ['role'=>'customer','email'=>'aminata.diallo@farha.demo',   'password'=>'Demo1234!','name'=>'Aminata Diallo'],
        ['role'=>'customer','email'=>'karim.coulibaly@farha.demo',  'password'=>'Demo1234!','name'=>'Karim Coulibaly'],
        ['role'=>'customer','email'=>'fatoumata.bah@farha.demo',    'password'=>'Demo1234!','name'=>'Fatoumata Bah'],
        ['role'=>'customer','email'=>'moussa.maiga@farha.demo',     'password'=>'Demo1234!','name'=>'Moussa Maïga'],
        ['role'=>'customer','email'=>'khadija.toure@farha.demo',    'password'=>'Demo1234!','name'=>'Khadija Touré'],
        ['role'=>'tailor',  'email'=>'ibrahim.sawadogo@farha.demo', 'password'=>'Demo1234!','name'=>'Ibrahim Sawadogo'],
        ['role'=>'tailor',  'email'=>'fatima.traore@farha.demo',    'password'=>'Demo1234!','name'=>'Fatima Traoré'],
        ['role'=>'tailor',  'email'=>'ousmane.diop@farha.demo',     'password'=>'Demo1234!','name'=>'Ousmane Diop'],
        ['role'=>'tailor',  'email'=>'mariam.cisse@farha.demo',     'password'=>'Demo1234!','name'=>'Mariam Cissé'],
    ],
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
