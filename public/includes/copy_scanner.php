<?php

class CopyCatScanner
{
    public static function analyze($filePath, $inkPrices, $profitMultiplier = 2.0)
    {
        if (!file_exists($filePath)) {
            return ['status' => 'error', 'message' => 'File not found.'];
        }

        // --- THE UPGRADE: Auto-detect Windows vs Linux ---
        $gs = (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') ? 'gswin64c' : 'gs';

        // --- STEP 1: Detect Paper Sizes ---
        $bboxCmd = $gs . ' -q -dNODISPLAY -sDEVICE=bbox -dNOPAUSE -dBATCH ' . escapeshellarg($filePath) . ' 2>&1';
        $bboxOutput = shell_exec($bboxCmd);

        $pageSizes = [];
        if ($bboxOutput !== null && preg_match_all('/%%BoundingBox:\s+\d+\s+\d+\s+(\d+)\s+(\d+)/', $bboxOutput, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $longestSide = max((int) $match[1], (int) $match[2]);
                if ($longestSide >= 950) {
                    $pageSizes[] = 'Long';
                } elseif ($longestSide >= 820 && $longestSide < 950) {
                    $pageSizes[] = 'A4';
                } else {
                    $pageSizes[] = 'Short';
                }
            }
        }

        // --- STEP 2: Detect Ink Coverage ---
        // We added '2>&1' to capture system errors instead of letting them fail silently
        $inkCmd = $gs . ' -q -o - -sDEVICE=inkcov ' . escapeshellarg($filePath) . ' 2>&1';
        $inkOutput = shell_exec($inkCmd);

        // --- THE UPGRADE: Catch Silent Failures ---
        if (empty($inkOutput) || stripos($inkOutput, 'not recognized') !== false || stripos($inkOutput, 'not found') !== false) {
            return [
                'status' => 'error',
                'message' => "Ghostscript ($gs) is not responding. Ensure it is installed and added to your System PATH, and restart XAMPP."
            ];
        }

        $lines = explode("\n", trim($inkOutput));
        $pageResults = [];
        $totalRawCost = 0;
        $totalRetailPrice = 0;
        $pageNumber = 1;

        $yieldBlack = 6000.0;
        $yieldColor = 5000.0;

        foreach ($lines as $line) {
            $line = trim($line);
            if (preg_match('/^([0-9\.]+)\s+([0-9\.]+)\s+([0-9\.]+)\s+([0-9\.]+)\s+CMYK OK$/', $line, $matches)) {

                $cyanPct = (float) $matches[1] * 100;
                $magentaPct = (float) $matches[2] * 100;
                $yellowPct = (float) $matches[3] * 100;
                $blackPct = (float) $matches[4] * 100;

                $k_cost = ($blackPct / 5.0) * ($inkPrices['black_cost'] / $yieldBlack);
                $c_cost = ($cyanPct / 5.0) * ($inkPrices['cyan_cost'] / $yieldColor);
                $m_cost = ($magentaPct / 5.0) * ($inkPrices['magenta_cost'] / $yieldColor);
                $y_cost = ($yellowPct / 5.0) * ($inkPrices['yellow_cost'] / $yieldColor);

                $rawPageCost = $k_cost + $c_cost + $m_cost + $y_cost;
                $rawPageCost = max(0.10, $rawPageCost);

                $retailPageCost = $rawPageCost * $profitMultiplier;
                $detectedSize = $pageSizes[$pageNumber - 1] ?? 'A4';

                $pageResults[] = [
                    'page' => $pageNumber,
                    'size' => $detectedSize,
                    'raw_ink_expense' => round($rawPageCost, 2),
                    'customer_price' => round($retailPageCost, 2)
                ];

                $totalRawCost += $rawPageCost;
                $totalRetailPrice += $retailPageCost;
                $pageNumber++;
            }
        }

        // If it ran but found 0 pages (meaning the PDF was corrupt or unreadable)
        if (count($pageResults) === 0) {
            return ['status' => 'error', 'message' => 'Ghostscript ran, but could not read any pages from this PDF.'];
        }

        return [
            'status' => 'success',
            'document_summary' => [
                'total_pages' => count($pageResults),
                'total_raw_expense' => round($totalRawCost, 2),
                'total_retail_price' => round($totalRetailPrice, 2),
                'profit_margin' => round($totalRetailPrice - $totalRawCost, 2)
            ],
            'pages' => $pageResults
        ];
    }
}