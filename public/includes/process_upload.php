<?php
/**
 * REFACTORED PROCESS UPLOAD (DYNAMIC BASE & TIER THRESHOLD MODEL)
 */

require_once __DIR__ . '/connect_db.php';
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_FILES['pdf_file']) || !isset($_POST['scan_data'])) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid request.']);
    exit;
}

$scanData = json_decode($_POST['scan_data'], true);
$totalPages = count($scanData);

// 1. Load the Base Price Matrix & Dynamic Tiers
$priceMatrix = [];
$tiersMatrix = [];

try {
    // --- A. Fetch Base Prices ---
    $stmtPrices = $pdo->query("SELECT paper_size, bw_price, colored_price FROM printing_prices");
    $prices = $stmtPrices->fetchAll(PDO::FETCH_ASSOC);

    if ($prices) {
        foreach ($prices as $row) {
            $priceMatrix[$row['paper_size']] = [
                'bw' => (float) $row['bw_price'],
                'color' => (float) $row['colored_price']
            ];
        }
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Printing prices table is empty.']);
        exit;
    }

    // --- B. Fetch Dynamic Tiers ---
    // IMPORTANT: We ORDER BY min_coverage DESC so our evaluation function reads from highest to lowest threshold
    $stmtTiers = $pdo->query("SELECT paper_size, color_mode, tier_name, min_coverage, surcharge 
                              FROM printing_tiers 
                              ORDER BY paper_size, color_mode, min_coverage DESC");
    $tiers = $stmtTiers->fetchAll(PDO::FETCH_ASSOC);

    if ($tiers) {
        foreach ($tiers as $row) {
            $size = $row['paper_size'];
            $mode = $row['color_mode']; // Expected: 'BW' or 'Color'

            $tiersMatrix[$size][$mode][] = [
                'tier' => $row['tier_name'],
                'min_coverage' => (float) $row['min_coverage'],
                'surcharge' => (float) $row['surcharge']
            ];
        }
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Pricing tiers table is empty.']);
        exit;
    }

} catch (PDOException $e) {
    echo json_encode(['status' => 'error', 'message' => 'Database error: ' . $e->getMessage()]);
    exit;
}

// 2. The Dynamic Tier Evaluation Function
function getDynamicTierPricing($coverage, $size, $isColor, $tiersMatrix)
{
    $mode = $isColor ? 'Color' : 'BW';

    // Ensure we have loaded tiers for this specific paper size and mode combination
    if (isset($tiersMatrix[$size][$mode])) {
        // Because our SQL query ordered by min_coverage DESC, we can just loop 
        // and return the first one where coverage >= min_coverage
        foreach ($tiersMatrix[$size][$mode] as $tierDef) {
            if ($coverage >= $tierDef['min_coverage']) {
                return [
                    'tier' => $tierDef['tier'],
                    'surcharge' => $tierDef['surcharge']
                ];
            }
        }
    }

    // Absolute fallback just in case a scanned coverage somehow falls below the lowest configured threshold
    return ['tier' => 'Default (No Match)', 'surcharge' => 0.00];
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

    // Safety fallback if paper size isn't mapped
    if (!isset($priceMatrix[$size])) {
        $size = 'Short';
    }

    // A. Strict Color Logic
    $isColor = $colorCov > 0;
    $basePrice = $isColor ? $priceMatrix[$size]['color'] : $priceMatrix[$size]['bw'];

    // B. Get Tier and Flat Surcharge Dynamically!
    $cluster = getDynamicTierPricing($totalCoverage, $size, $isColor, $tiersMatrix);

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
        'base_price' => number_format($basePrice, 2),
        'cluster_tier' => $cluster['tier'],
        'surcharge' => ($cluster['surcharge'] > 0 ? '+' : '') . number_format($cluster['surcharge'], 2),
        'retail_price' => number_format($retailPrice, 2)
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