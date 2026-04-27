<?php
// process_upload.php
require_once __DIR__ . '/connect_db.php';
require_once __DIR__ . '/copy_scanner.php';


header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_FILES['pdf_file'])) {
    echo json_encode(['status' => 'error', 'message' => 'No file uploaded.']);
    exit;
}

$uploadTmp = $_FILES['pdf_file']['tmp_name'];

// 1. Fetch your dynamic ink prices directly from the database
try {
    $stmt = $pdo->query("SELECT black_cost, cyan_cost, magenta_cost, yellow_cost FROM ink_pricing ORDER BY id DESC LIMIT 1");
    $inkPrices = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$inkPrices) {
        throw new Exception("Ink pricing not configured in database.");
    }
} catch (Exception $e) {
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    exit;
}

// 2. Set your profit markup (e.g., 2.5 means charging 250% of the raw ink cost)
$profitMultiplier = 2.5;

// 3. Run the Smart Scanner
$analysis = CopyCatScanner::analyze($uploadTmp, $inkPrices, $profitMultiplier);

// 4. Send the breakdown straight back to the Tailwind frontend
echo json_encode($analysis);
?>