<?php
// GET /farha_api/admin_stats.php  —  platform-wide dashboard metrics
require_once __DIR__ . '/config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') Response::error('Method not allowed.', 405);

Auth::requireAdmin();
$db = Database::connect();

// User counts
$users = $db->query("
    SELECT
        COUNT(*)                                        AS total,
        SUM(user_type = 'customer')                    AS customers,
        SUM(user_type = 'tailor')                      AS tailors,
        SUM(DATE(created_at) = CURDATE())              AS new_today
    FROM users WHERE is_active = 1
")->fetch();

// Tailor approval queue
$pendingTailors = (int)$db->query("SELECT COUNT(*) FROM tailors WHERE status = 'pending'")->fetchColumn();

// Orders
$orders = $db->query("
    SELECT
        COUNT(*)                                                     AS total,
        SUM(DATE(created_at) = CURDATE())                           AS today,
        SUM(YEARWEEK(created_at,1) = YEARWEEK(NOW(),1))             AS this_week,
        SUM(MONTH(created_at) = MONTH(NOW()) AND YEAR(created_at) = YEAR(NOW())) AS this_month,
        SUM(status = 'pending')                                      AS pending,
        SUM(status = 'cancelled')                                    AS cancelled
    FROM orders
")->fetch();

// Revenue (platform fees collected)
$revenue = $db->query("
    SELECT
        COALESCE(SUM(platform_fee), 0)                                                AS total_platform_revenue,
        COALESCE(SUM(amount), 0)                                                      AS total_gmv,
        COALESCE(SUM(CASE WHEN MONTH(created_at)=MONTH(NOW()) AND YEAR(created_at)=YEAR(NOW()) THEN platform_fee END), 0) AS month_platform_revenue,
        COALESCE(SUM(CASE WHEN MONTH(created_at)=MONTH(NOW()) AND YEAR(created_at)=YEAR(NOW()) THEN amount END), 0)       AS month_gmv,
        SUM(status = 'failed')                                                         AS failed_payments
    FROM payments WHERE status IN ('completed','failed')
")->fetch();

// Pending payouts
$pendingPayouts = $db->query("
    SELECT COUNT(*) AS count, COALESCE(SUM(amount),0) AS total_amount
    FROM payouts WHERE status = 'pending'
")->fetch();

// Recent 10 activity events
$recentStmt = $db->query("
    (SELECT 'new_order'    AS type, o.reference_number AS ref,
            CONCAT(u.first_name,' ',u.last_name) AS actor, o.created_at AS at
     FROM orders o JOIN customers c ON c.id=o.customer_id JOIN users u ON u.id=c.user_id
     ORDER BY o.created_at DESC LIMIT 5)
    UNION ALL
    (SELECT 'new_user'     AS type, u.email AS ref,
            CONCAT(u.first_name,' ',u.last_name) AS actor, u.created_at AS at
     FROM users u ORDER BY u.created_at DESC LIMIT 5)
    ORDER BY at DESC LIMIT 10
");
$recent = $recentStmt->fetchAll();

// Monthly GMV breakdown (last 6 months)
$monthlyStmt = $db->query("
    SELECT DATE_FORMAT(created_at,'%Y-%m') AS month,
           SUM(amount)                     AS gmv,
           SUM(platform_fee)               AS commission,
           COUNT(*)                        AS payments
    FROM payments WHERE status='completed'
      AND created_at >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
    GROUP BY DATE_FORMAT(created_at,'%Y-%m')
    ORDER BY month ASC
");
$monthly = $monthlyStmt->fetchAll();
foreach ($monthly as &$m) {
    $m['gmv']        = (float)$m['gmv'];
    $m['commission'] = (float)$m['commission'];
    $m['payments']   = (int)$m['payments'];
}

Response::success([
    'users' => [
        'total'     => (int)$users['total'],
        'customers' => (int)$users['customers'],
        'tailors'   => (int)$users['tailors'],
        'new_today' => (int)$users['new_today'],
    ],
    'pending_tailor_approvals' => $pendingTailors,
    'orders' => [
        'total'      => (int)$orders['total'],
        'today'      => (int)$orders['today'],
        'this_week'  => (int)$orders['this_week'],
        'this_month' => (int)$orders['this_month'],
        'pending'    => (int)$orders['pending'],
        'cancelled'  => (int)$orders['cancelled'],
    ],
    'revenue' => [
        'total_gmv'              => (float)$revenue['total_gmv'],
        'total_platform_revenue' => (float)$revenue['total_platform_revenue'],
        'month_gmv'              => (float)$revenue['month_gmv'],
        'month_platform_revenue' => (float)$revenue['month_platform_revenue'],
        'failed_payments'        => (int)$revenue['failed_payments'],
        'commission_rate'        => PLATFORM_COMMISSION_RATE,
    ],
    'payouts' => [
        'pending_count'  => (int)$pendingPayouts['count'],
        'pending_amount' => (float)$pendingPayouts['total_amount'],
    ],
    'recent_activity'   => $recent,
    'monthly_breakdown' => $monthly,
]);
