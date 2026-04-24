<?php
/**
 * Breakfast Guest Self-Pick Portal API
 * - create_link: authenticated frontdesk action
 * - get_link: public read by token
 * - submit_link: public submit by token with quota enforcement
 */
define('APP_ACCESS', true);
require_once '../config/config.php';
require_once '../config/database.php';

header('Content-Type: application/json');

$db = Database::getInstance();
$pdo = $db->getConnection();

function hotel_date()
{
    return (int)date('H') < 12 ? date('Y-m-d', strtotime('-1 day')) : date('Y-m-d');
}

function ensure_breakfast_orders_table($pdo)
{
    $pdo->exec("CREATE TABLE IF NOT EXISTS breakfast_orders (
        id INT PRIMARY KEY AUTO_INCREMENT,
        booking_id INT NULL,
        guest_name VARCHAR(500) NOT NULL,
        room_number TEXT,
        total_pax INT DEFAULT 1,
        breakfast_time TIME,
        breakfast_date DATE,
        location VARCHAR(20) DEFAULT 'restaurant',
        menu_items TEXT,
        special_requests TEXT,
        total_price DECIMAL(10,2) DEFAULT 0.00,
        order_status VARCHAR(20) DEFAULT 'pending',
        created_by INT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
}

function ensure_portal_links_table($pdo)
{
    $pdo->exec("CREATE TABLE IF NOT EXISTS breakfast_guest_links (
        id INT PRIMARY KEY AUTO_INCREMENT,
        token VARCHAR(80) NOT NULL,
        booking_id INT NULL,
        guest_id INT NULL,
        guest_name VARCHAR(500) NOT NULL,
        guest_phone VARCHAR(60) NULL,
        room_number TEXT,
        breakfast_date DATE NOT NULL,
        max_main INT NOT NULL DEFAULT 1,
        max_child INT NOT NULL DEFAULT 0,
        child_menu_ids TEXT,
        link_status VARCHAR(20) NOT NULL DEFAULT 'open',
        selected_menu_ids TEXT,
        selected_child_ids TEXT,
        special_requests TEXT,
        expires_at DATETIME NULL,
        submitted_at DATETIME NULL,
        created_by INT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY uk_token (token),
        INDEX idx_guest_date (guest_name(191), breakfast_date),
        INDEX idx_status (link_status),
        INDEX idx_exp (expires_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
}

function parse_json_body()
{
    $raw = file_get_contents('php://input');
    if (!$raw) return [];
    $decoded = json_decode($raw, true);
    return is_array($decoded) ? $decoded : [];
}

function get_setting($db, $key)
{
    $row = $db->fetchOne("SELECT setting_value FROM settings WHERE setting_key = ?", [$key]);
    return $row['setting_value'] ?? '';
}

$action = $_GET['action'] ?? $_POST['action'] ?? '';
$body = parse_json_body();
if (!$action && !empty($body['action'])) {
    $action = $body['action'];
}

try {
    ensure_breakfast_orders_table($pdo);
    ensure_portal_links_table($pdo);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Gagal inisialisasi tabel: ' . $e->getMessage()]);
    exit;
}

if ($action === 'create_link') {
    require_once '../includes/auth.php';
    $auth = new Auth();
    $auth->requireLogin();
    if (!$auth->hasPermission('frontdesk')) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Forbidden']);
        exit;
    }

    $guestName = trim((string)($body['guest_name'] ?? ''));
    $guestPhone = trim((string)($body['guest_phone'] ?? ''));
    $guestId = !empty($body['guest_id']) ? (int)$body['guest_id'] : null;
    $bookingId = !empty($body['booking_id']) ? (int)$body['booking_id'] : null;
    $rooms = $body['room_number'] ?? [];
    if (!is_array($rooms)) $rooms = [$rooms];
    $rooms = array_values(array_unique(array_filter(array_map('trim', $rooms))));

    $breakfastDate = !empty($body['breakfast_date']) ? $body['breakfast_date'] : hotel_date();
    $maxMain = max(0, (int)($body['max_main'] ?? 1));
    $maxChild = max(0, (int)($body['max_child'] ?? 0));
    $expireHours = max(1, min(72, (int)($body['expire_hours'] ?? 24)));

    $childMenuIds = $body['child_menu_ids'] ?? [];
    if (!is_array($childMenuIds)) $childMenuIds = [];
    $childMenuIds = array_values(array_unique(array_map('intval', $childMenuIds)));
    $childMenuIds = array_values(array_filter($childMenuIds, function ($v) {
        return $v > 0;
    }));

    if ($guestName === '') {
        echo json_encode(['success' => false, 'message' => 'Nama tamu wajib diisi']);
        exit;
    }
    if ($maxMain + $maxChild <= 0) {
        echo json_encode(['success' => false, 'message' => 'Jatah menu minimal 1']);
        exit;
    }

    $token = bin2hex(random_bytes(24));
    $userId = !empty($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null;
    $roomJson = json_encode($rooms);
    $childJson = json_encode($childMenuIds);
    $expiresAt = date('Y-m-d H:i:s', strtotime('+' . $expireHours . ' hours'));

    try {
        $pdo->prepare("UPDATE breakfast_guest_links
            SET link_status = 'expired'
            WHERE breakfast_date = ? AND LOWER(TRIM(guest_name)) = LOWER(TRIM(?)) AND link_status = 'open'")
            ->execute([$breakfastDate, $guestName]);

        $pdo->prepare("INSERT INTO breakfast_guest_links
            (token, booking_id, guest_id, guest_name, guest_phone, room_number, breakfast_date,
             max_main, max_child, child_menu_ids, expires_at, created_by)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)")
            ->execute([
                $token,
                $bookingId,
                $guestId,
                $guestName,
                $guestPhone,
                $roomJson,
                $breakfastDate,
                $maxMain,
                $maxChild,
                $childJson,
                $expiresAt,
                $userId
            ]);

        $linkUrl = rtrim(BASE_URL, '/') . '/modules/frontdesk/breakfast-guest.php?t=' . urlencode($token);
        echo json_encode([
            'success' => true,
            'message' => 'Link berhasil dibuat',
            'data' => [
                'token' => $token,
                'link_url' => $linkUrl,
                'expires_at' => $expiresAt
            ]
        ]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Gagal membuat link: ' . $e->getMessage()]);
    }
    exit;
}

if ($action === 'get_link') {
    $token = trim((string)($_GET['token'] ?? $body['token'] ?? ''));
    if ($token === '') {
        echo json_encode(['success' => false, 'message' => 'Token wajib']);
        exit;
    }

    $link = $db->fetchOne("SELECT * FROM breakfast_guest_links WHERE token = ? LIMIT 1", [$token]);
    if (!$link) {
        echo json_encode(['success' => false, 'message' => 'Link tidak ditemukan']);
        exit;
    }

    if ($link['link_status'] !== 'open') {
        echo json_encode(['success' => false, 'message' => 'Link sudah tidak aktif']);
        exit;
    }

    if (!empty($link['expires_at']) && strtotime($link['expires_at']) < time()) {
        $pdo->prepare("UPDATE breakfast_guest_links SET link_status = 'expired' WHERE id = ?")->execute([(int)$link['id']]);
        echo json_encode(['success' => false, 'message' => 'Link sudah kedaluwarsa']);
        exit;
    }

    $menus = $db->fetchAll("SELECT id, menu_name, category, is_free, price FROM breakfast_menus WHERE is_available = 1 ORDER BY category, menu_name") ?: [];
    $menuMap = [];
    foreach ($menus as $m) {
        $menuMap[(int)$m['id']] = $m;
    }

    $childIds = json_decode($link['child_menu_ids'] ?? '[]', true);
    if (!is_array($childIds)) $childIds = [];
    $childIds = array_values(array_unique(array_map('intval', $childIds)));

    $childMenus = [];
    foreach ($childIds as $id) {
        if (isset($menuMap[$id])) $childMenus[] = $menuMap[$id];
    }

    $mainMenus = [];
    foreach ($menus as $m) {
        if (in_array((int)$m['id'], $childIds, true)) continue;
        $mainMenus[] = $m;
    }

    $rooms = json_decode($link['room_number'] ?? '[]', true);
    if (!is_array($rooms)) {
        $rooms = !empty($link['room_number']) ? [$link['room_number']] : [];
    }

    $waInfo = get_setting($db, 'breakfast_wa_info_text');
    $waMediaPath = get_setting($db, 'breakfast_wa_media_path');
    $waMediaUrl = '';
    if ($waMediaPath) {
        $waMediaUrl = (strpos($waMediaPath, 'http') === 0)
            ? $waMediaPath
            : rtrim(BASE_URL, '/') . '/' . ltrim($waMediaPath, '/');
    }

    echo json_encode([
        'success' => true,
        'data' => [
            'token' => $token,
            'guest_name' => $link['guest_name'],
            'room_number' => $rooms,
            'breakfast_date' => $link['breakfast_date'],
            'max_main' => (int)$link['max_main'],
            'max_child' => (int)$link['max_child'],
            'main_menus' => $mainMenus,
            'child_menus' => $childMenus,
            'wa_info_text' => $waInfo,
            'wa_media_url' => $waMediaUrl,
            'expires_at' => $link['expires_at']
        ]
    ]);
    exit;
}

if ($action === 'submit_link') {
    $token = trim((string)($body['token'] ?? ''));
    if ($token === '') {
        echo json_encode(['success' => false, 'message' => 'Token wajib']);
        exit;
    }

    $selectedMain = $body['selected_main'] ?? [];
    $selectedChild = $body['selected_child'] ?? [];
    if (!is_array($selectedMain)) $selectedMain = [];
    if (!is_array($selectedChild)) $selectedChild = [];
    $selectedMain = array_values(array_unique(array_map('intval', $selectedMain)));
    $selectedChild = array_values(array_unique(array_map('intval', $selectedChild)));
    $selectedMain = array_values(array_filter($selectedMain, function ($v) { return $v > 0; }));
    $selectedChild = array_values(array_filter($selectedChild, function ($v) { return $v > 0; }));

    $specialRequests = trim((string)($body['special_requests'] ?? ''));
    $location = trim((string)($body['location'] ?? 'restaurant'));
    if (!in_array($location, ['restaurant', 'room_service', 'take_away'], true)) {
        $location = 'restaurant';
    }

    $pdo->beginTransaction();
    try {
        $link = $db->fetchOne("SELECT * FROM breakfast_guest_links WHERE token = ? LIMIT 1", [$token]);
        if (!$link) {
            throw new Exception('Link tidak ditemukan');
        }
        if ($link['link_status'] !== 'open') {
            throw new Exception('Link sudah tidak aktif');
        }
        if (!empty($link['expires_at']) && strtotime($link['expires_at']) < time()) {
            $pdo->prepare("UPDATE breakfast_guest_links SET link_status = 'expired' WHERE id = ?")->execute([(int)$link['id']]);
            throw new Exception('Link sudah kedaluwarsa');
        }

        $maxMain = max(0, (int)$link['max_main']);
        $maxChild = max(0, (int)$link['max_child']);

        if (count($selectedMain) > $maxMain) {
            throw new Exception('Pilihan menu utama melebihi jatah (' . $maxMain . ')');
        }
        if (count($selectedChild) > $maxChild) {
            throw new Exception('Pilihan menu anak melebihi jatah (' . $maxChild . ')');
        }

        $allowedChild = json_decode($link['child_menu_ids'] ?? '[]', true);
        if (!is_array($allowedChild)) $allowedChild = [];
        $allowedChild = array_values(array_unique(array_map('intval', $allowedChild)));

        foreach ($selectedChild as $id) {
            if (!in_array($id, $allowedChild, true)) {
                throw new Exception('Ada menu anak yang tidak diizinkan');
            }
        }

        foreach ($selectedMain as $id) {
            if (in_array($id, $allowedChild, true)) {
                throw new Exception('Menu anak tidak boleh dipilih di menu utama');
            }
        }

        $allSelected = array_values(array_unique(array_merge($selectedMain, $selectedChild)));
        if (count($allSelected) === 0) {
            throw new Exception('Pilih minimal 1 menu');
        }

        $placeholders = implode(',', array_fill(0, count($allSelected), '?'));
        $menus = $db->fetchAll("SELECT id, menu_name, price, is_free FROM breakfast_menus WHERE is_available = 1 AND id IN ($placeholders)", $allSelected) ?: [];
        $menuMap = [];
        foreach ($menus as $m) {
            $menuMap[(int)$m['id']] = $m;
        }

        $menuItems = [];
        $totalPrice = 0;
        foreach ($selectedMain as $id) {
            if (empty($menuMap[$id])) continue;
            $m = $menuMap[$id];
            $item = [
                'menu_id' => (int)$m['id'],
                'menu_name' => $m['menu_name'],
                'quantity' => 1,
                'price' => (float)$m['price'],
                'is_free' => (int)$m['is_free'],
                'group' => 'main'
            ];
            $menuItems[] = $item;
            if (!(int)$m['is_free']) $totalPrice += (float)$m['price'];
        }
        foreach ($selectedChild as $id) {
            if (empty($menuMap[$id])) continue;
            $m = $menuMap[$id];
            $item = [
                'menu_id' => (int)$m['id'],
                'menu_name' => $m['menu_name'],
                'quantity' => 1,
                'price' => (float)$m['price'],
                'is_free' => (int)$m['is_free'],
                'group' => 'child'
            ];
            $menuItems[] = $item;
            if (!(int)$m['is_free']) $totalPrice += (float)$m['price'];
        }

        if (count($menuItems) === 0) {
            throw new Exception('Menu tidak valid');
        }

        $guestName = $link['guest_name'];
        $breakfastDate = $link['breakfast_date'];
        $bookingId = !empty($link['booking_id']) ? (int)$link['booking_id'] : null;
        $roomJson = $link['room_number'] ?: json_encode([]);
        $menuJson = json_encode($menuItems);
        $totalPax = count($menuItems);
        $breakfastTime = '07:00:00';

        $portalNote = '[Guest Portal]';
        if ($specialRequests !== '') {
            $portalNote .= ' ' . $specialRequests;
        }

        $existing = $db->fetchOne(
            "SELECT id FROM breakfast_orders WHERE breakfast_date = ? AND FIND_IN_SET(?, REPLACE(guest_name, ', ', ',')) > 0 LIMIT 1",
            [$breakfastDate, $guestName]
        );

        if ($existing) {
            $pdo->prepare("UPDATE breakfast_orders SET
                booking_id = ?, guest_name = ?, room_number = ?, total_pax = ?, breakfast_time = ?,
                breakfast_date = ?, location = ?, menu_items = ?, special_requests = ?, total_price = ?,
                order_status = 'pending'
                WHERE id = ?")
                ->execute([
                    $bookingId,
                    $guestName,
                    $roomJson,
                    $totalPax,
                    $breakfastTime,
                    $breakfastDate,
                    $location,
                    $menuJson,
                    $portalNote,
                    $totalPrice,
                    (int)$existing['id']
                ]);
            $orderId = (int)$existing['id'];
        } else {
            $pdo->prepare("INSERT INTO breakfast_orders
                (booking_id, guest_name, room_number, total_pax, breakfast_time, breakfast_date,
                 location, menu_items, special_requests, total_price, order_status, created_by)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending', NULL)")
                ->execute([
                    $bookingId,
                    $guestName,
                    $roomJson,
                    $totalPax,
                    $breakfastTime,
                    $breakfastDate,
                    $location,
                    $menuJson,
                    $portalNote,
                    $totalPrice
                ]);
            $orderId = (int)$pdo->lastInsertId();
        }

        $pdo->prepare("UPDATE breakfast_guest_links
            SET link_status = 'submitted', selected_menu_ids = ?, selected_child_ids = ?,
                special_requests = ?, submitted_at = NOW()
            WHERE id = ?")
            ->execute([
                json_encode($selectedMain),
                json_encode($selectedChild),
                $specialRequests,
                (int)$link['id']
            ]);

        $pdo->commit();
        echo json_encode([
            'success' => true,
            'message' => 'Pilihan sarapan berhasil dikirim',
            'data' => ['order_id' => $orderId]
        ]);
    } catch (Exception $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit;
}

echo json_encode(['success' => false, 'message' => 'Action tidak dikenal']);
