<?php
// GET /farha_api/revenue_summary.php
require_once __DIR__ . '/config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') Response::error('Method not allowed.', 405);

$payload = Auth::requireTailor();
$db      = Database::connect();

$tailorStmt = $db->prepare('SELECT id FROM tailors WHERE user_id = ? LIMIT 1');
$tailorStmt->execute([$payload['user_id']]);
$tailor = $tailorStmt->fetch();
if (!$tailor) Response::notFound('Tailor profile not found.');

$tailorId = $tailor['id'];

// Total net earnings (after commission) — tailor_amount is already pre-deducted
$totalStmt = $db->prepare('
    SELECT COALESCE(SUM(p.tailor_amount), 0) AS total,
           COUNT(DISTINCT o.id)               AS order_count
    FROM   payments p
    JOIN   orders   o ON o.id = p.order_id
    WHERE  o.tailor_id = ? AND p.status = "completed"
');
$totalStmt->execute([$tailorId]);
$total = $totalStmt->fetch();

// This month
$monthStmt = $db->prepare('
    SELECT COALESCE(SUM(p.tailor_amount), 0) AS month_revenue
    FROM   payments p
    JOIN   orders   o ON o.id = p.order_id
    WHERE  o.tailor_id = ? AND p.status = "completed"
      AND  MONTH(p.created_at) = MONTH(NOW()) AND YEAR(p.created_at) = YEAR(NOW())
');
$monthStmt->execute([$tailorId]);
$month = $monthStmt->fetchColumn();

// Pending orders
$pendingStmt = $db->prepare("
    SELECT COUNT(*) FROM orders
    WHERE  tailor_id = ? AND status NOT IN ('delivered','cancelled')
");
$pendingStmt->execute([$tailorId]);
$pending = (int)$pendingStmt->fetchColumn();

// This week
$weekStmt = $db->prepare('
    SELECT COALESCE(SUM(p.tailor_amount), 0) AS week_revenue
    FROM   payments p
    JOIN   orders   o ON o.id = p.order_id
    WHERE  o.tailor_id = ? AND p.status = "completed"
      AND  YEARWEEK(p.created_at, 1) = YEARWEEK(NOW(), 1)
');
$weekStmt->execute([$tailorId]);
$week = $weekStmt->fetchColumn();

// This year
$yearStmt = $db->prepare('
    SELECT COALESCE(SUM(p.tailor_amount), 0) AS year_revenue
    FROM   payments p
    JOIN   orders   o ON o.id = p.order_id
    WHERE  o.tailor_id = ? AND p.status = "completed"
      AND  YEAR(p.created_at) = YEAR(NOW())
');
$yearStmt->execute([$tailorId]);
$year = $yearStmt->fetchColumn();

// Available balance for payout (total earned minus already paid out)
$paidOutStmt = $db->prepare("
    SELECT COALESCE(SUM(amount), 0) FROM payouts
    WHERE  tailor_id = ? AND status IN ('approved','processing','completed')
");
$paidOutStmt->execute([$tailorId]);
$totalPaidOut = (float)$paidOutStmt->fetchColumn();
$availableBalance = max(0, round((float)$total['total'] - $totalPaidOut, 2));

// Monthly breakdown (last 6 months, net earnings)
$monthlyStmt = $db->prepare('
    SELECT DATE_FORMAT(p.created_at, "%Y-%m") AS month,
           SUM(p.tailor_amount)               AS revenue,
           COUNT(DISTINCT o.id)               AS orders
    FROM   payments p
    JOIN   orders   o ON o.id = p.order_id
    WHERE  o.tailor_id = ? AND p.status = "completed"
      AND  p.created_at >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
    GROUP  BY DATE_FORMAT(p.created_at, "%Y-%m")
    ORDER  BY month ASC
');
$monthlyStmt->execute([$tailorId]);
$monthly = $monthlyStmt->fetchAll();

foreach ($monthly as &$m) $m['revenue'] = (float)$m['revenue'];

Response::success([
    'total_revenue'     => (float)$total['total'],
    'total_orders'      => (int)$total['order_count'],
    'month_revenue'     => (float)$month,
    'week_revenue'      => (float)$week,
    'year_revenue'      => (float)$year,
    'pending_orders'    => $pending,
    'available_balance' => $availableBalance,
    'commission_rate'   => PLATFORM_COMMISSION_RATE,
    'monthly_breakdown' => $monthly,
]);
