<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
header('Content-Type: text/html; charset=utf-8');

define('APP_ACCESS', true);
require_once 'config/config.php';
require_once 'config/database.php';

$db = Database::getInstance();
$pdo = $db->getConnection();

$today = ((int)date('H') < 10) ? date('Y-m-d', strtotime('-1 day')) : date('Y-m-d');

echo "<h2>Debug Breakfast Guests - Date: $today</h2>";

// 1. Check bookings table
echo "<h3>1. Total bookings in database:</h3>";
$result = $pdo->query("SELECT COUNT(*) as cnt FROM bookings")->fetch();
echo "Total: " . $result['cnt'] . "\n<br>";

// 2. Check checked-in bookings
echo "<h3>2. Checked-in bookings:</h3>";
$result = $pdo->query("SELECT COUNT(*) as cnt FROM bookings WHERE status = 'checked_in'")->fetch();
echo "Checked-in: " . $result['cnt'] . "\n<br>";

// 3. List all checked-in guests
echo "<h3>3. All checked-in guests (raw):</h3>";
$stmt = $pdo->query("
    SELECT b.id, b.guest_id, b.room_id, b.status, 
           g.guest_name, r.room_number
    FROM bookings b
    JOIN guests g ON b.guest_id = g.id
    LEFT JOIN rooms r ON b.room_id = r.id
    WHERE b.status = 'checked_in'
    LIMIT 10
");
echo "<table border='1' cellpadding='5'>";
echo "<tr><th>Booking ID</th><th>Guest Name</th><th>Room Number</th><th>Status</th></tr>";
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    echo "<tr>";
    echo "<td>" . $row['id'] . "</td>";
    echo "<td>" . htmlspecialchars($row['guest_name']) . "</td>";
    echo "<td>" . htmlspecialchars($row['room_number']) . "</td>";
    echo "<td>" . $row['status'] . "</td>";
    echo "</tr>";
}
echo "</table>";

// 4. Test the EXACT query from breakfast.php
echo "<h3>4. Query from breakfast.php (the one with EXISTS subquery):</h3>";
try {
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
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo "Query returned " . count($results) . " rows<br><br>";

    if (count($results) > 0) {
        echo "<table border='1' cellpadding='5'>";
        echo "<tr><th>Booking ID</th><th>Guest Name</th><th>Room</th><th>Has Order</th></tr>";
        foreach ($results as $row) {
            echo "<tr>";
            echo "<td>" . $row['booking_id'] . "</td>";
            echo "<td>" . htmlspecialchars($row['guest_name']) . "</td>";
            echo "<td>" . htmlspecialchars($row['room_number']) . "</td>";
            echo "<td>" . ($row['has_order_today'] ? 'YES' : 'NO') . "</td>";
            echo "</tr>";
        }
        echo "</table>";
    } else {
        echo "<p style='color: red;'><strong>NO RESULTS!</strong></p>";
    }
} catch (Exception $e) {
    echo "<p style='color: red;'><strong>ERROR: " . htmlspecialchars($e->getMessage()) . "</strong></p>";
    echo "<pre>" . htmlspecialchars($e->getTraceAsString()) . "</pre>";
}

// 5. Check breakfast_orders for today
echo "<h3>5. Breakfast orders for today ($today):</h3>";
$stmt = $pdo->query("SELECT * FROM breakfast_orders WHERE breakfast_date = '$today' LIMIT 5");
$rows = $stmt->fetchAll();
echo "Found " . count($rows) . " orders<br>";
if (count($rows) > 0) {
    echo "<pre>";
    print_r($rows[0]);
    echo "</pre>";
}
