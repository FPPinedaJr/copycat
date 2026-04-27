<?php
// process_upload.php
require_once __DIR__ . '/connect_db.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_FILES['pdf_file']) || !isset($_POST['scan_data'])) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid request.']);
    exit;
}

$scanData = json_decode($_POST['scan_data'], true);
$totalPages = count($scanData);

try {
    $stmt = $pdo->query("SELECT black_cost, cyan_cost, magenta_cost, yellow_cost FROM ink_pricing ORDER BY id DESC LIMIT 1");
    $pricing = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$pricing)
        throw new Exception("Pricing not set in database.");
} catch (Exception $e) {
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    exit;
}

// --- THE MATH FIX ---
$profitMultiplier = 2.5;
$yieldBlack = 6000.0;
$yieldColor = 5000.0;
$paperCost = 1.50; // The actual physical cost of 1 blank sheet of bond paper

$totalPrice = 0;
$totalRawCost = 0;
$pageBreakdown = []; // Array to hold the price tag for every single page

foreach ($scanData as $page) {
    $k_cost = ($page['black_pct'] / 5.0) * ($pricing['black_cost'] / $yieldBlack);

    $avgColorBottleCost = ($pricing['cyan_cost'] + $pricing['magenta_cost'] + $pricing['yellow_cost']) / 3;
    $c_cost = ($page['color_pct'] / 5.0) * ($avgColorBottleCost / $yieldColor);

    $rawInkCost = $k_cost + $c_cost;

    // Total raw expense is the ink PLUS the physical paper
    $totalPageExpense = $rawInkCost + $paperCost;

    // Customer price is the ink markup PLUS the base paper cost
    $retailPageCost = ($rawInkCost * $profitMultiplier) + $paperCost;

    // Ensure you never charge less than 2.00 PHP for a single page, even if it's mostly blank
    $retailPageCost = max(2.00, $retailPageCost);

    $totalRawCost += $totalPageExpense;
    $totalPrice += $retailPageCost;

    // Save this page's price for the UI viewer
    $pageBreakdown[] = [
        'page' => $page['page'],
        'size' => $page['size'],
        'price' => round($retailPageCost, 2)
    ];
}

$uploadDir = __DIR__ . '/../uploads/';
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0777, true);
}

$cleanFileName = preg_replace('/[^a-zA-Z0-9.-]/', '_', $_FILES['pdf_file']['name']);
$newFileName = time() . '_' . $cleanFileName;
$destination = $uploadDir . $newFileName;

move_uploaded_file($_FILES['pdf_file']['tmp_name'], $destination);

echo json_encode([
    'status' => 'success',
    'total_pages' => $totalPages,
    'total_price' => round($totalPrice, 2),
    'raw_cost' => round($totalRawCost, 2),
    'file_path' => 'uploads/' . $newFileName,
    'pages' => $pageBreakdown // Send the breakdown array back to the JavaScript
]);
?>