<?php
/**
 * REFACTORED PROCESS UPLOAD (STANDARD-BASE THRESHOLD MODEL)
 */

require_once __DIR__ . '/connect_db.php';
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_FILES['pdf_file']) || !isset($_POST['scan_data'])) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid request.']);
    exit;
}

$scanData = json_decode($_POST['scan_data'], true);
$totalPages = count($scanData);

// 1. Load the specific Price Matrix (Based on your DB)
$priceMatrix = [
    'Short' => ['bw' => 1.00, 'color' => 2.00],
    'A4' => ['bw' => 1.00, 'color' => 2.00],
    'Long' => ['bw' => 1.50, 'color' => 3.00]
];

// 2. Define the Absolute Clustering Tiers (Where ~6% is the 100% Baseline)
function getTierPricing($coverage)
{
    // Surcharge is the exact Peso amount ADDED to or SUBTRACTED from the Base Price
    if ($coverage >= 20.0)
        return ['tier' => '1 Premium (Heavy)', 'surcharge' => 2.00];  // Add ₱2.00
    if ($coverage >= 10.0)
        return ['tier' => '2 Premium (Dense)', 'surcharge' => 1.00];  // Add ₱1.00
    if ($coverage >= 4.5)
        return ['tier' => '3 Standard (Text)', 'surcharge' => 0.00];  // Base Price
    if ($coverage >= 1.5)
        return ['tier' => '4 Light (Sparse)', 'surcharge' => -0.25]; // Discount ₱0.25
    return ['tier' => '5 Minimal', 'surcharge' => -0.50]; // Discount ₱0.50
}

$debug = ['pages' => []];
$pageBreakdown = [];
$grandTotalRetail = 0;

// --- THE CORE SCAN LOOP ---
foreach ($scanData as $page) {
    $size = $page['size'] ?? 'Short';
    $kCov = (float) $page['black_pct'];
    $colorCov = (float) $page['color_pct'];
    $totalCoverage = $kCov + $colorCov;

    // A. Strict Color Logic
    $isColor = $colorCov > 0;
    $basePrice = $isColor ? $priceMatrix[$size]['color'] : $priceMatrix[$size]['bw'];

    // B. Get Tier and Flat Surcharge
    $cluster = getTierPricing($totalCoverage);

    // C. Calculate Final Retail Price (Base + Surcharge)
    $retailPrice = $basePrice + $cluster['surcharge'];

    // D. Safety Floor: Never let discounts push the price below the absolute minimum
    $minimumAllowed = $isColor ? 1.00 : 0.50;
    $retailPrice = max($minimumAllowed, $retailPrice);

    // Accumulate total
    $grandTotalRetail += $retailPrice;

    // E. Build the Debug Table Data
    $debug['pages'][] = [
        'page' => $page['page'],
        'size' => $size,
        'mode' => $isColor ? 'Color' : 'B&W',
        'k_coverage' => number_format($kCov, 4) . '%',
        'color_coverage' => number_format($colorCov, 4) . '%',
        'base_price' => '₱' . number_format($basePrice, 2),
        'cluster_tier' => $cluster['tier'],
        'surcharge' => ($cluster['surcharge'] > 0 ? '+' : '') . '₱' . number_format($cluster['surcharge'], 2),
        'retail_price' => '₱' . number_format($retailPrice, 2)
    ];

    $pageBreakdown[] = [
        'page' => $page['page'],
        'size' => $size,
        'price' => round($retailPrice, 2)
    ];
}

// ... [FILE SYSTEM PERSISTENCE GOES HERE] ...

echo json_encode([
    'status' => 'success',
    'total_pages' => $totalPages,
    'total_price' => round($grandTotalRetail, 2),
    'pages' => $pageBreakdown,
    'debug' => $debug
]);