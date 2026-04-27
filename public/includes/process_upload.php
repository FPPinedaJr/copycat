<?php
/**
 * PROCESS UPLOAD & CAT-ALYSIS ALGORITHM
 * 
 * 1. Fetch registry values from `ink_pricing` table (Black, Colors, Paper, Multiplier).
 * 2. Calculate Base Price per 100% coverage: (unit_cost / total_impressions).
 * 3. Scan PDF Data: For each page, calculate ink cost based on pixel coverage percentage.
 * 4. Add Paper Base: (paper_cost / paper_yield).
 * 5. Apply Profit Multiplier: (Ink + Paper) * Multiplier.
 * 6. Safety Floor: Enforce meow-nimum charge of 2.00 PHP per page.
 * 7. Sum up all pages for the Grand Total Retail Price.
 */

require_once __DIR__ . '/connect_db.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_FILES['pdf_file']) || !isset($_POST['scan_data'])) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid request.']);
    exit;
}

$scanData = json_decode($_POST['scan_data'], true);
$totalPages = count($scanData);

try {
    // 1. Fetch all registry data into a key-value map
    $stmt = $pdo->query("SELECT ink_key, unit_cost, total_impressions FROM ink_pricing");
    $registry = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $registry[$row['ink_key']] = [
            'cost' => (float) $row['unit_cost'],
            'yield' => (float) $row['total_impressions']
        ];
    }

    if (!isset($registry['multiplier'])) {
        throw new Exception("Pricing logic missing 'multiplier' key in ink_pricing table.");
    }

} catch (Exception $e) {
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    exit;
}


// --- RE-ENGINEERED CALIBRATION (CYBER-CAT) ---

// 1. Calculate Base Units (Cost per 100% coverage)
$baseK = $registry['black']['cost'] / $registry['black']['yield'];
$baseC = $registry['cyan']['cost'] / $registry['cyan']['yield'];
$baseM = $registry['magenta']['cost'] / $registry['magenta']['yield'];
$baseY = $registry['yellow']['cost'] / $registry['yellow']['yield'];
$baseColorAvg = ($baseC + $baseM + $baseY) / 3;

// 2. Load Logistics Overhead
$basePaper = $registry['paper']['cost'] / $registry['paper']['yield'];
$profitMultiplier = $registry['multiplier']['cost'];
$colorOverhead = $registry['color_premium']['cost'] ?? 1.00; // Base fee for color head activation

$debug = [
    'base_costs' => [
        'black' => $baseK,
        'color_avg' => $baseColorAvg,
        'paper' => $basePaper,
        'multiplier' => $profitMultiplier,
        'color_premium' => $colorOverhead
    ],
    'pages' => []
];

$totalRawInkExpense = 0;
$pageBreakdown = [];

// --- THE CORE SCAN LOOP ---
foreach ($scanData as $page) {
    // A. Calculate Raw Ink Consumption
    $inkK = ($page['black_pct'] / 100) * $baseK;

    // We use the intensity to drive the price
    $colorIntensity = ($page['color_pct'] > 0) ? ($page['color_pct'] / 100) : 0;
    $inkColor = $colorIntensity * $baseColorAvg;

    // B. Apply "Chromacity Logic-Overhead"
    // If color coverage is > 0.1%, it's treated as a color page.
    $activationOverhead = ($page['color_pct'] > 0.1) ? $colorOverhead : 0;

    // C. The New Master Formula:
    // Retail = ((Ink + Paper + ColorOverhead) * Multiplier)
    $totalPageRaw = $inkK + $inkColor + $basePaper + $activationOverhead;
    $retailPrice = $totalPageRaw * $profitMultiplier;

    // D. Static Floors (Safety protocols)
    // If it's color, it MUST be at least 1.50. If B&W, min is 0.50.
    if ($page['color_pct'] > 0.1) {
        $retailPrice = max(1.50, $retailPrice);
    } else {
        $retailPrice = max(0.50, $retailPrice);
    }

    $totalRawInkExpense += $totalPageRaw;

    $debug['pages'][] = [
        'page' => $page['page'],
        'k_coverage' => $page['black_pct'],
        'color_coverage' => $page['color_pct'],
        'k_ink_cost' => $inkK,
        'color_ink_cost' => $inkColor,
        'overhead' => $activationOverhead,
        'raw_total' => $totalPageRaw,
        'retail' => $retailPrice
    ];

    $pageBreakdown[] = [
        'page' => $page['page'],
        'size' => $page['size'],
        'price' => round($retailPrice, 2)
    ];
}


$grandTotalRetail = array_sum(array_column($pageBreakdown, 'price'));

// --- FILE SYSTEM PERSISTENCE ---
$uploadDir = __DIR__ . '/../uploads/';
if (!is_dir($uploadDir))
    mkdir($uploadDir, 0777, true);

$cleanFileName = preg_replace('/[^a-zA-Z0-9.-]/', '_', $_FILES['pdf_file']['name']);
$newFileName = time() . '_' . $cleanFileName;
$destination = $uploadDir . $newFileName;

move_uploaded_file($_FILES['pdf_file']['tmp_name'], $destination);

// --- RETURN CAT-ALYSIS RESULTS ---
echo json_encode([
    'status' => 'success',
    'total_pages' => $totalPages,
    'total_price' => round($grandTotalRetail, 2),
    'raw_cost' => round($totalRawInkExpense, 2),
    'file_path' => 'uploads/' . $newFileName,
    'pages' => $pageBreakdown,
    'debug' => $debug
]);