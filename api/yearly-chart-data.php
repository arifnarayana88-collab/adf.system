<?php
require_once '../config/config.php';
require_once '../config/database.php';
require_once '../includes/auth.php';

header('Content-Type: application/json');

// Check if user is logged in
$auth = new Auth();
if (!$auth->isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$db = Database::getInstance();

// Get year parameter (default to current year)
$year = isset($_GET['year']) ? (int)$_GET['year'] : date('Y');
$compareYear = $year - 1;

// Month names (short)
$monthNames = ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Ags', 'Sep', 'Okt', 'Nov', 'Des'];

// Get monthly transaction data for the selected year
$transData = $db->fetchAll(
    "SELECT 
        MONTH(transaction_date) as month,
        SUM(CASE WHEN transaction_type = 'income' AND (source_type IS NULL OR source_type NOT IN ('owner_fund','owner_project')) THEN amount ELSE 0 END) as income,
        SUM(CASE WHEN transaction_type = 'expense' AND (source_type IS NULL OR source_type != 'owner_project') THEN amount ELSE 0 END) as expense
    FROM cash_book
    WHERE YEAR(transaction_date) = :year
    GROUP BY MONTH(transaction_date)
    ORDER BY month ASC",
    ['year' => $year]
);

// Get monthly transaction data for compare year (previous year)
$compareData = $db->fetchAll(
    "SELECT 
        MONTH(transaction_date) as month,
        SUM(CASE WHEN transaction_type = 'income' AND (source_type IS NULL OR source_type NOT IN ('owner_fund','owner_project')) THEN amount ELSE 0 END) as income,
        SUM(CASE WHEN transaction_type = 'expense' AND (source_type IS NULL OR source_type != 'owner_project') THEN amount ELSE 0 END) as expense
    FROM cash_book
    WHERE YEAR(transaction_date) = :compare_year
    GROUP BY MONTH(transaction_date)
    ORDER BY month ASC",
    ['compare_year' => $compareYear]
);

// Map transaction data by month
$transMap = [];
foreach ($transData as $data) {
    $transMap[$data['month']] = $data;
}

$compareMap = [];
foreach ($compareData as $data) {
    $compareMap[$data['month']] = $data;
}

// Fill all 12 months (missing months will have 0 values)
$labels = [];
$income = [];
$expense = [];
$compareIncome = [];
$compareExpense = [];

for ($m = 1; $m <= 12; $m++) {
    $labels[] = $monthNames[$m - 1];
    $income[] = isset($transMap[$m]) ? (float)$transMap[$m]['income'] : 0;
    $expense[] = isset($transMap[$m]) ? (float)$transMap[$m]['expense'] : 0;
    $compareIncome[] = isset($compareMap[$m]) ? (float)$compareMap[$m]['income'] : 0;
    $compareExpense[] = isset($compareMap[$m]) ? (float)$compareMap[$m]['expense'] : 0;
}

// Return JSON response
echo json_encode([
    'success' => true,
    'labels' => $labels,
    'income' => $income,
    'expense' => $expense,
    'compare_income' => $compareIncome,
    'compare_expense' => $compareExpense,
    'compare_year' => $compareYear,
    'timestamp' => date('Y-m-d H:i:s'),
    'year' => $year
]);
