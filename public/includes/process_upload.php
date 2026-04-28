<?php
/**
 * REFACTORED PROCESS UPLOAD (HYBRID: FIXED B&W + DYNAMIC COLOR TIERS)
 */

require_once __DIR__ . '/connect_db.php';
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_FILES['pdf_file']) || !isset($_POST['scan_data'])) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid request.']);
    exit;
}

$scanData = json_decode($_POST['scan_data'], true);
$totalPages = count($scanData);

$priceMatrix = [];
$colorTiersMatrix = [];

try {
    // 1. Fetch Base Prices
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

    // 2. Fetch ONLY Color Tiers (Ordered highest to lowest coverage)
    // We assume your table still has 'min_coverage' and 'surcharge'
    $stmtTiers = $pdo->query("SELECT paper_size, tier_name, min_coverage, surcharge 
                              FROM printing_tiers 
                              WHERE color_mode = 'Color' 
                              ORDER BY paper_size, min_coverage DESC");
    $tiers = $stmtTiers->fetchAll(PDO::FETCH_ASSOC);

    if ($tiers) {
        foreach ($tiers as $row) {
            $size = $row['paper_size'];
            $colorTiersMatrix[$size][] = [
                'tier' => $row['tier_name'],
                'min_coverage' => (float) $row['min_coverage'],
                'surcharge' => (float) $row['surcharge']
            ];
        }
    }
} catch (PDOException $e) {
    echo json_encode(['status' => 'error', 'message' => 'Database error: ' . $e->getMessage()]);
    exit;
}

// 3. Helper Function: Evaluate Color Coverage against Dynamic Tiers
function getDynamicColorTier($coverage, $size, $colorTiersMatrix)
{
    if (isset($colorTiersMatrix[$size])) {
        foreach ($colorTiersMatrix[$size] as $tierDef) {
            if ($coverage >= $tierDef['min_coverage']) {
                return [
                    'tier' => $tierDef['tier'],
                    'surcharge' => $tierDef['surcharge']
                ];
            }
        }
    }
    // Fallback if coverage is below the lowest configured tier
    return ['tier' => 'Standard Color', 'surcharge' => 0.00];
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

    // A. STRICT COLOR LOGIC (Using the 0.02% dark noise buffer from earlier)
    $isColor = $colorCov > 0.02;

    $appliedSurcharge = 0.00;
    $tierName = '';

    // B. The Hybrid Split
    if (!$isColor) {
        // FIXED B&W: Ignores tiers completely
        $basePrice = $priceMatrix[$size]['bw'];
        $appliedSurcharge = 0.00;
        $tierName = 'Fixed B&W';
    } else {
        // DYNAMIC COLOR: Evaluates against printing_tiers table
        $basePrice = $priceMatrix[$size]['color'];
        $tierResult = getDynamicColorTier($totalCoverage, $size, $colorTiersMatrix);
        $appliedSurcharge = $tierResult['surcharge'];
        $tierName = $tierResult['tier'];
    }

    // C. Calculate Final Retail Price
    $retailPrice = $basePrice + $appliedSurcharge;

    // Accumulate total
    $grandTotalRetail += $retailPrice;

    // D. Build the Debug Table Data
    $debug['pages'][] = [
        'page' => $page['page'],
        'size' => $size,
        'mode' => $isColor ? 'Color' : 'B&W',
        'total_coverage' => number_format($totalCoverage, 4) . '%',
        'base_price' => number_format($basePrice, 2),
        'cluster_tier' => $tierName,
        'surcharge' => '+' . number_format($appliedSurcharge, 2),
        'retail_price' => number_format($retailPrice, 2)
    ];

    $pageBreakdown[] = [
        'page' => $page['page'],
        'size' => $size,
        'price' => round($retailPrice, 2)
    ];
}

// --- FILE SYSTEM PERSISTENCE ---
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
    'total_price' => round($grandTotalRetail, 2),
    'file_path' => 'uploads/' . $newFileName,
    'pages' => $pageBreakdown,
    'debug' => $debug
]);