<?php

/**
 * ONE-TIME DEPLOY SCRIPT
 * Place this at top of includes/header.php temporarily
 * Access via: https://adfsystem.online/?_deploy_breakfast_2026
 * Then REMOVE THIS CODE
 */

if (isset($_GET['_deploy_breakfast_2026'])) {
    $content = <<<'EOFPHP'
<?php

/**
 * BREAKFAST ORDER - Rewritten clean version
 * Flow: Pick guest (not yet ordered today) → Pick menu → Submit → Pick next guest
 */
define('APP_ACCESS', true);
require_once '../../config/config.php';
require_once '../../config/database.php';
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';

$auth = new Auth();
$auth->requireLogin();
if (!$auth->hasPermission('frontdesk')) {
    header('Location: ' . BASE_URL . '/403.php');
    exit;
}

$db = Database::getInstance();
$pdo = $db->getConnection();
// Breakfast hotel date rolls over at 10:00, so last night's picks stay visible until 10 AM.
$today = ((int)date('H') < 10) ? date('Y-m-d', strtotime('-1 day')) : date('Y-m-d');

$guestLinkMessageTemplate = '';
try {
    $row = $db->fetchOne("SELECT setting_value FROM settings WHERE setting_key = 'breakfast_guest_link_template' LIMIT 1");
    $guestLinkMessageTemplate = trim((string)($row['setting_value'] ?? ''));
} catch (Exception $e) {
}

// Ensure table exists
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS breakfast_menus (
        id INT PRIMARY KEY AUTO_INCREMENT, menu_name VARCHAR(100) NOT NULL,
        description TEXT, category ENUM('western','indonesian','asian','drinks','beverages','extras') DEFAULT 'western',
        price DECIMAL(10,2) DEFAULT 0.00, is_free BOOLEAN DEFAULT TRUE, is_available BOOLEAN DEFAULT TRUE,
        image_url VARCHAR(255), created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    $pdo->exec("CREATE TABLE IF NOT EXISTS breakfast_orders (
        id INT PRIMARY KEY AUTO_INCREMENT, booking_id INT NULL, guest_name VARCHAR(500) NOT NULL,
        room_number TEXT, total_pax INT DEFAULT 1, breakfast_time TIME, breakfast_date DATE,
        location VARCHAR(20) DEFAULT 'restaurant', menu_items TEXT, special_requests TEXT,
        total_price DECIMAL(10,2) DEFAULT 0.00, order_status VARCHAR(20) DEFAULT 'pending',
        created_by INT, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
} catch (Exception $e) {
}

// Widen guest_name column and drop old unique constraint (combined multi-guest names can be long)
try {
    $pdo->exec("ALTER TABLE breakfast_orders MODIFY guest_name VARCHAR(500) NOT NULL");
} catch (Exception $e) {
}
try {
    $pdo->exec("ALTER TABLE breakfast_orders DROP INDEX uk_guest_date");
} catch (Exception $e) {
}

// Get menus
$freeMenus = $paidMenus = [];
try {
    $freeMenus = $pdo->query("SELECT * FROM breakfast_menus WHERE is_available=1 AND is_free=1 ORDER BY category,menu_name")->fetchAll(PDO::FETCH_ASSOC);
    $paidMenus = $pdo->query("SELECT * FROM breakfast_menus WHERE is_available=1 AND is_free=0 ORDER BY category,menu_name")->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
}

// Default child menu IDs for guest portal quota (example: pancake + waffle)
$defaultChildMenuIds = [];
foreach (array_merge($freeMenus, $paidMenus) as $mx) {
    $nameLower = strtolower(trim($mx['menu_name'] ?? ''));
    if (in_array($nameLower, ['pancake', 'waffle'], true)) {
        $defaultChildMenuIds[] = (int)$mx['id'];
    }
}

// Get in-house guests WHO HAVE NOT ORDERED TODAY
// Keep each booking as its own row so same-name guests in different rooms stay separate
$inHouseGuests = [];
$guestQuotaMap = [];
try {
    $quotaRows = $pdo->query("SELECT booking_id, adult_count, child_young_count, child_old_count, total_pax, max_main, max_drink, max_child, child_menu_ids, extra_main_price, extra_drink_price, extra_child_price, breakfast_date FROM breakfast_guest_quota")->fetchAll(PDO::FETCH_ASSOC);
    foreach ($quotaRows as $qr) {
        $guestQuotaMap[(int)$qr['booking_id']] = $qr;
    }

    $stmt = $pdo->prepare("
        SELECT b.id as booking_id, g.id as guest_id, g.guest_name, COALESCE(g.phone,'') as guest_phone,
               COALESCE(r.room_number, b.room_number) as room_number,
               EXISTS(
                   SELECT 1
                   FROM breakfast_orders bo
                   WHERE bo.breakfast_date = ?
                     AND bo.booking_id = b.id
               ) as has_order_today
        FROM bookings b
        JOIN guests g ON b.guest_id = g.id
        LEFT JOIN rooms r ON b.room_id = r.id
        WHERE b.status = 'checked_in'
        ORDER BY has_order_today ASC, COALESCE(r.room_number, b.room_number) ASC, b.id ASC
    ");
    $stmt->execute([$today]);
    $inHouseGuests = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
}
EOFPHP;

    $path = '/home/adfb2574/public_html/modules/frontdesk/breakfast.php';
    @mkdir(dirname($path), 0755, true);
    $bytes = @file_put_contents($path, $content);

    die("<pre>✓ Deploy success!\nFile: $path\nBytes: $bytes\n\nNow REMOVE the _deploy_breakfast_2026 code from production!</pre>");
}
