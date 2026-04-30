<?php
require_once __DIR__ . '/includes/connect_db.php';

$prices = [];
$priceMatrix = [];
$colorTiersMatrix = [];

try {
    // Fetch base prices and calculate min/max surcharges directly via SQL
    $query = "
        SELECT 
            p.paper_size, 
            p.bw_price, 
            p.colored_price,
            COALESCE((SELECT MIN(surcharge) FROM printing_tiers t WHERE t.paper_size = p.paper_size AND t.color_mode = 'BW'), 0) as bw_min_sur,
            COALESCE((SELECT MAX(surcharge) FROM printing_tiers t WHERE t.paper_size = p.paper_size AND t.color_mode = 'BW'), 0) as bw_max_sur,
            COALESCE((SELECT MIN(surcharge) FROM printing_tiers t WHERE t.paper_size = p.paper_size AND t.color_mode = 'Color'), 0) as color_min_sur,
            COALESCE((SELECT MAX(surcharge) FROM printing_tiers t WHERE t.paper_size = p.paper_size AND t.color_mode = 'Color'), 0) as color_max_sur
        FROM printing_prices p 
        ORDER BY 
            CASE 
                WHEN p.paper_size = 'Short' THEN 1 
                WHEN p.paper_size = 'A4' THEN 2 
                WHEN p.paper_size = 'Long' THEN 3 
                ELSE 4 
            END
    ";

    $stmt = $pdo->query($query);
    $prices = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Build matrices for the JavaScript client
    if ($prices) {
        foreach ($prices as $row) {
            $priceMatrix[$row['paper_size']] = [
                'bw' => (float) $row['bw_price'],
                'color' => (float) $row['colored_price']
            ];
        }

        // Fetch Color Tiers ordered by highest coverage first
        $stmtTiers = $pdo->query("SELECT paper_size, tier_name, min_coverage, surcharge FROM printing_tiers WHERE color_mode = 'Color' ORDER BY paper_size, min_coverage DESC");
        while ($row = $stmtTiers->fetch(PDO::FETCH_ASSOC)) {
            $colorTiersMatrix[$row['paper_size']][] = [
                'tier' => $row['tier_name'],
                'min_coverage' => (float) $row['min_coverage'],
                'surcharge' => (float) $row['surcharge']
            ];
        }
    }
} catch (PDOException $e) {
    // If the query fails, $prices stays as an empty array [] initialized at the top
    // You can log the error here if needed: error_log($e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">


<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Copy Cat | The Purr-fect Print</title>
    <link href="assets/css/style.css" rel="stylesheet">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.min.js"></script>
    <script>
        // Set the worker for PDF.js
        pdfjsLib.GlobalWorkerOptions.workerSrc = 'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.worker.min.js';
    </script>
    <style>
        /* Optional: Custom scrollbar for a cleaner look in the preview area */
        .custom-scrollbar::-webkit-scrollbar {
            width: 6px;
        }

        .custom-scrollbar::-webkit-scrollbar-track {
            background: transparent;
        }

        .custom-scrollbar::-webkit-scrollbar-thumb {
            background: #e2e8f0;
            border-radius: 10px;
        }

        .custom-scrollbar::-webkit-scrollbar-thumb:hover {
            background: #cbd5e1;
        }
    </style>
</head>


<body class="bg-orange-50 min-h-screen font-sans text-gray-800 flex flex-col">

    <nav class="container mx-auto px-6 py-4 flex justify-between items-center">
        <div class="flex items-center gap-2">
            <span class="text-2xl font-black text-brand-dark tracking-tight">Copy Cat</span>
        </div>
        <div>
            <a href="admin.php"
                class="bg-brand text-white px-5 py-2 rounded-full font-bold shadow-sm hover:bg-brand-dark transition transform hover:-translate-y-0.5 inline-block">
                Admin
            </a>
        </div>
    </nav>

    <main class="flex-grow container mx-auto px-6 py-12">
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-12 items-start justify-center">

            <!-- Hero Section (Title & Description) -->
            <div class="lg:col-span-2 order-1 text-center lg:text-left">
                <h1 class="text-4xl sm:text-5xl md:text-6xl font-black text-gray-900 mb-6 leading-tight tracking-tight">
                    The Purr-fect Print, <br>
                    <span class="text-brand">Exactly When You Need It.</span>
                </h1>

                <p class="text-base sm:text-lg md:text-xl text-gray-600 md:mb-10 max-w-2xl">
                    Upload your document, get your <strong>Precision Smart-Price</strong>, and schedule your meet-up or
                    pick-up.
                    No hidden fees!
                </p>
            </div>

            <!-- Price Guide Section (Order 2 on mobile, Merged row on desktop) -->
            <div class="w-full lg:col-span-1 lg:row-span-2 order-2">
                <div class="bg-white p-8 rounded-3xl shadow-xl border border-orange-100 sticky top-12">
                    <h2 class="text-2xl font-black text-gray-900 mb-2 flex items-center gap-2">
                        Smart-Price Guide
                    </h2>
                    <p class="text-sm text-gray-500 mb-6 font-medium">B&W is fixed, color adapts to ink usage.</p>

                    <div class="space-y-6">
                        <div>
                            <h3 class="text-sm font-bold text-brand uppercase tracking-wider mb-3">Black & White</h3>
                            <div class="space-y-2">
                                <?php if (empty($prices)): ?>
                                    <p class="text-xs text-gray-400 italic">No prices available.</p>
                                <?php else: ?>
                                    <?php foreach ($prices as $row): ?>
                                        <div
                                            class="flex justify-between items-center pb-2 border-b border-orange-50 last:border-0">
                                            <span
                                                class="text-gray-600 font-medium"><?= htmlspecialchars($row['paper_size']) ?></span>
                                            <span class="text-gray-900 font-bold text-[15px]">
                                                ₱<?= number_format((float) $row['bw_price'], 2) ?>
                                            </span>
                                        </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                        </div>

                        <div>
                            <h3 class="text-sm font-bold text-blue-500 uppercase tracking-wider mb-3">Colored</h3>
                            <div class="space-y-2">
                                <?php if (empty($prices)): ?>
                                    <p class="text-xs text-gray-400 italic">No prices available.</p>
                                <?php else: ?>
                                    <?php foreach ($prices as $row):
                                        // Calculate the absolute minimum starting price for color
                                        $colMin = max(1.00, (float) $row['colored_price'] + (float) $row['color_min_sur']);
                                        ?>
                                        <div
                                            class="flex justify-between items-center pb-2 border-b border-orange-50 last:border-0">
                                            <span
                                                class="text-gray-600 font-medium"><?= htmlspecialchars($row['paper_size']) ?></span>
                                            <span class="text-gray-900 font-bold text-[15px]">
                                                <span class="text-gray-400 font-normal text-[11px]  uppercase mr-1">Starts
                                                    at</span>₱<?= number_format($colMin, 2) ?>
                                            </span>
                                        </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <div class="mt-8 p-4 bg-orange-50 rounded-xl">
                        <p class="text-xs text-orange-700 leading-relaxed font-medium">
                            * Black & White pages have a flat rate. Colored pages use our Smart-Pricing algorithm, so
                            pages with heavy graphics will incur surcharges above the starting price.
                        </p>
                    </div>
                </div>
            </div>

            <!-- Upload Section (Order 3 on mobile, Order 2 on desktop) -->
            <div class="lg:col-span-2 order-3">
                <div
                    class="w-full max-w-xl bg-white p-8 rounded-3xl shadow-xl border border-orange-100 mx-auto lg:mx-0">
                    <div id="dropzone"
                        class="border-4 border-dashed border-brand-light rounded-2xl p-10 bg-orange-50/50 hover:bg-orange-50 transition duration-300 cursor-pointer flex flex-col items-center justify-center group">

                        <h3 class="text-xl font-bold text-gray-800 mb-2">Drop your PDF right meow!</h3>
                        <p class="text-gray-500 text-sm mb-6">or click to browse your files (Max 50MB)</p>

                        <label for="file-upload"
                            class="bg-gray-900 text-white px-6 py-3 rounded-full font-bold cursor-pointer hover:bg-gray-800 transition shadow-md">
                            Select PDF File
                        </label>
                        <input id="file-upload" type="file" accept=".pdf" multiple class="hidden" />
                    </div>

                    <div
                        class="mt-6 flex items-center justify-center lg:justify-start gap-4 text-sm text-gray-400 font-medium">
                        <span class="flex items-center gap-1">Secure Upload</span>
                        <span>•</span>
                        <span class="flex items-center gap-1">Instant Pricing</span>
                    </div>
                </div>
            </div>

        </div>
    </main>

    <footer class="text-center py-8 text-gray-400 text-sm">
        <p>&copy; 2026 Copy Cat Printing. Built in Puerto Princesa.</p>
    </footer>

    <div id="modal-backdrop"
        class="fixed inset-0 bg-slate-900/80 backdrop-blur-sm z-50 flex items-center justify-center hidden opacity-0 transition-opacity duration-300 p-6">

        <div id="card-loading"
            class="step-card bg-white w-full max-w-sm rounded-3xl shadow-2xl overflow-hidden hidden transform scale-95 transition-all duration-300 p-12 text-center">
            <div class="relative w-24 h-24 mx-auto mb-6">
                <div class="absolute inset-0 border-4 border-orange-100 rounded-full opacity-50"></div>
                <div class="absolute inset-0 border-4 border-brand rounded-full border-t-transparent animate-spin">
                </div>
                <div class="absolute inset-0 flex items-center justify-center">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5"
                        stroke="currentColor" class="w-10 h-10 text-brand animate-bounce">
                        <path stroke-linecap="round" stroke-linejoin="round"
                            d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m5.231 13.481L15 17.25m-4.5-15H5.625c-.621 0-1.125.504-1.125 1.125v16.5c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9zm3.75 11.625a2.625 2.625 0 11-5.25 0 2.625 2.625 0 015.25 0z" />
                    </svg>
                </div>
            </div>
            <h3 class="text-2xl font-black text-gray-900 mb-2 tracking-tight">Scanning...</h3>
            <p id="scan-status" class="text-gray-500 font-bold mb-6 text-lg">Page 0 / 0</p>

            <div class="w-full bg-slate-100 h-2.5 rounded-full overflow-hidden">
                <div id="scan-progress" class="bg-brand h-full w-0 transition-all duration-300 ease-out"></div>
            </div>
        </div>

        <div id="card-result"
            class="step-card bg-white w-fit rounded-[2rem] shadow-[0_20px_60px_-15px_rgba(0,0,0,0.1)] border border-gray-100 overflow-hidden hidden transform scale-95 transition-all duration-300">
            <div class="h-1.5 w-full bg-gradient-to-r from-orange-400 to-orange-600"></div>

            <div class="flex flex-col md:flex-row min-h-[550px]">

                <div
                    class="w-full md:w-[360px] p-8 lg:p-10 border-r border-gray-100 flex flex-col justify-between bg-slate-50/50">
                    <div>
                        <div class="mb-8">
                            <h3 class="text-2xl font-extrabold text-gray-900 tracking-tight">Smart-Price</h3>
                            <p class="text-gray-500 text-sm mt-1 font-medium">Real-time document analysis.</p>
                        </div>

                        <div class="space-y-4 mb-8">
                            <div
                                class="flex items-center justify-between p-4 bg-white rounded-2xl border border-gray-200 shadow-sm">
                                <span class="text-sm font-bold text-gray-400 uppercase tracking-wider">Paper Size</span>
                                <span id="res-paper-size"
                                    class="text-gray-900 font-extrabold text-xl tracking-tight">A4</span>
                            </div>

                            <div
                                class="flex items-center justify-between p-4 bg-white rounded-2xl border border-gray-200 shadow-sm">
                                <span class="text-sm font-bold text-gray-400 uppercase tracking-wider">Total
                                    Pages</span>
                                <span id="res-pages"
                                    class="text-gray-900 font-extrabold text-xl tracking-tight">0</span>
                            </div>

                            <div
                                class="p-1.5 bg-gray-100 rounded-xl flex items-center justify-between gap-1.5 mb-2 border border-gray-200/60 shadow-inner">
                                <button id="btn-mode-color" onclick="setPrintMode('Color')"
                                    class="flex-1 py-2.5 text-sm font-black bg-blue-500 text-white shadow-md rounded-lg transition-all transform scale-100">
                                    Color Print
                                </button>
                                <button id="btn-mode-bw" onclick="setPrintMode('BW')"
                                    class="flex-1 py-2.5 text-sm font-bold text-gray-500 bg-transparent hover:text-gray-900 transition-all rounded-lg">
                                    B&W Only
                                </button>
                            </div>

                            <div
                                class="p-6 bg-gradient-to-br from-orange-500 to-orange-600 text-white rounded-2xl shadow-lg shadow-orange-500/20 border border-orange-400/50 relative overflow-hidden">
                                <span
                                    class="block text-[11px] font-bold text-orange-100 uppercase tracking-widest mb-1.5">Final
                                    Total</span>
                                <div class="flex items-baseline gap-1 relative z-10">
                                    <span class="font-bold text-2xl">₱</span>
                                    <span id="res-price" class="font-black text-5xl tracking-tighter">0.00</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="space-y-3">
                        <button id="btn-toggle-breakdown" onclick="toggleBreakdown()"
                            class="md:hidden w-full py-3 text-brand font-bold text-sm hover:bg-orange-50 rounded-xl transition-colors border border-orange-100 mt-2">
                            Show Page Breakdown
                        </button>
                        <button onclick="switchStep('customer')"
                            class="w-full py-4 bg-gray-900 text-white rounded-2xl font-bold text-[15px] hover:bg-gray-800 transition-all active:scale-[0.98] shadow-md hover:shadow-xl flex justify-center items-center gap-2 group">
                            Continue to Checkout
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2.5"
                                stroke="currentColor" class="w-4 h-4 group-hover:translate-x-1 transition-transform">
                                <path stroke-linecap="round" stroke-linejoin="round"
                                    d="M13.5 4.5L21 12m0 0l-7.5 7.5M21 12H3" />
                            </svg>
                        </button>
                        <button onclick="closeModal()"
                            class="w-full py-3 text-gray-400 font-bold text-sm hover:text-gray-700 hover:bg-gray-100/50 rounded-xl transition-colors">
                            Cancel Order
                        </button>
                    </div>
                </div>

                <div id="breakdown-section" class="flex-grow p-8 lg:p-10 bg-white flex flex-col hidden md:flex">
                    <div class="flex items-center justify-between mb-6">
                        <h4 class="text-xs font-black text-gray-400 uppercase tracking-widest">Page Breakdown</h4>
                        <div
                            class="flex items-center gap-1.5 bg-orange-50 text-orange-600 border border-orange-100 px-3 py-1.5 rounded-full">
                            <span class="w-1.5 h-1.5 rounded-full bg-orange-500 animate-pulse"></span>
                            <span class="text-[10px] font-bold tracking-wide">LIVE PREVIEW</span>
                        </div>
                    </div>

                    <div
                        class="pdf-viewer-container custom-scrollbar h-full max-h-[600px] overflow-y-auto p-6 bg-slate-50/80 rounded-3xl border border-gray-100 shadow-[inset_0_2px_4px_rgba(0,0,0,0.02)]">
                    </div>
                </div>
            </div>
        </div>

        <div id="card-customer"
            class="step-card bg-white w-full max-w-lg rounded-3xl shadow-2xl overflow-hidden hidden transform scale-95 transition-all duration-300">
            <div class="h-2 bg-brand"></div>
            <div class="p-10">
                <div class="text-center mb-8">
                    <div
                        class="inline-flex items-center justify-center w-16 h-16 bg-orange-50 rounded-2xl text-orange-500 text-3xl mb-4">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2"
                            stroke="currentColor" class="w-8 h-8">
                            <path stroke-linecap="round" stroke-linejoin="round"
                                d="M15.75 6a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0zM4.501 20.118a7.5 7.5 0 0114.998 0A17.933 17.933 0 0112 21.75c-2.676 0-5.216-.584-7.499-1.632z" />
                        </svg>
                    </div>
                    <h3 class="text-2xl font-black text-gray-900 tracking-tight">Who is this for?</h3>
                    <p class="text-gray-500 text-sm mt-1">Please enter your name for the order.</p>
                </div>

                <div class="mb-10">
                    <input type="text" id="inp-customer-name" placeholder="e.g., Juan Dela Cruz"
                        class="w-full p-4 bg-gray-50 border border-gray-100 rounded-2xl focus:ring-2 focus:ring-brand focus:border-brand outline-none transition-all font-bold text-gray-700 text-center text-xl">
                </div>

                <button onclick="switchStep('schedule')"
                    class="w-full py-4 bg-gray-900 text-white rounded-2xl font-black text-lg hover:bg-gray-800 transition transform active:scale-95 shadow-xl mb-4">
                    Next Step
                </button>
                <button onclick="switchStep('result')"
                    class="w-full py-2 text-gray-400 font-bold hover:text-gray-600 transition">
                    Go Back
                </button>
            </div>
        </div>

        <div id="card-schedule"
            class="step-card bg-white w-full max-w-lg rounded-3xl shadow-2xl overflow-hidden hidden transform scale-95 transition-all duration-300">
            <div class="h-2 bg-brand"></div>
            <div class="p-10">
                <div class="text-center mb-8">
                    <div
                        class="inline-flex items-center justify-center w-16 h-16 bg-orange-50 rounded-2xl text-orange-500 text-3xl mb-4">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2"
                            stroke="currentColor" class="w-8 h-8">
                            <path stroke-linecap="round" stroke-linejoin="round"
                                d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 012.25-2.25h13.5A2.25 2.25 0 0121 7.5v11.25m-18 0A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75m-18 0v-7.5A2.25 2.25 0 015.25 9h13.5A2.25 2.25 0 0121 11.25v7.5m-9-6h.008v.008H12v-.008zM12 15h.008v.008H12V15zm0 2.25h.008v.008H12v-.008zM9.75 15h.008v.008H9.75V15zm0 2.25h.008v.008H9.75v-.008zM7.5 15h.008v.008H7.5V15zm0 2.25h.008v.008H7.5v-.008zm6.75-4.5h.008v.008h-.008v-.008zm0 2.25h.008v.008h-.008V15zm0 2.25h.008v.008h-.008v-.008zm2.25-4.5h.008v.008H16.5v-.008zm0 2.25h.008v.008H16.5V15z" />
                        </svg>
                    </div>
                    <h3 class="text-2xl font-black text-gray-900 tracking-tight">Schedule Your Print</h3>
                    <p class="text-gray-500 text-sm mt-1">When would you like to get your copies?</p>
                </div>

                <div class="space-y-6 mb-10">
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label
                                class="block text-xs font-black text-gray-400 uppercase tracking-widest mb-2">Date</label>
                            <input type="date" id="inp-date"
                                class="w-full p-4 bg-gray-50 border border-gray-100 rounded-2xl focus:ring-2 focus:ring-brand focus:border-brand outline-none transition-all font-bold text-gray-700">
                        </div>
                        <div>
                            <label
                                class="block text-xs font-black text-gray-400 uppercase tracking-widest mb-2">Time</label>
                            <input type="time" id="inp-time"
                                class="w-full p-4 bg-gray-50 border border-gray-100 rounded-2xl focus:ring-2 focus:ring-brand focus:border-brand outline-none transition-all font-bold text-gray-700">
                        </div>
                    </div>

                    <div>
                        <label class="block text-xs font-black text-gray-400 uppercase tracking-widest mb-2">Special
                            Instructions</label>
                        <textarea id="inp-notes" rows="3" placeholder="e.g. Please bind them together..."
                            class="w-full p-4 bg-gray-50 border border-gray-100 rounded-2xl focus:ring-2 focus:ring-brand focus:border-brand outline-none transition-all text-sm"></textarea>
                    </div>
                </div>

                <button onclick="switchStep('delivery')"
                    class="w-full py-4 bg-gray-900 text-white rounded-2xl font-black text-lg hover:bg-gray-800 transition transform active:scale-95 shadow-xl mb-4">
                    Next Step
                </button>
                <button onclick="switchStep('customer')"
                    class="w-full py-2 text-gray-400 font-bold hover:text-gray-600 transition">
                    Go Back
                </button>
            </div>
        </div>

        <div id="card-delivery"
            class="step-card bg-white w-full max-w-lg rounded-3xl shadow-2xl overflow-hidden hidden transform scale-95 transition-all duration-300">
            <div class="h-2 bg-brand"></div>
            <div class="p-10">
                <div class="text-center mb-8">
                    <div
                        class="inline-flex items-center justify-center w-16 h-16 bg-blue-50 rounded-2xl text-blue-500 text-3xl mb-4">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2"
                            stroke="currentColor" class="w-8 h-8">
                            <path stroke-linecap="round" stroke-linejoin="round"
                                d="M8.25 18.75a1.5 1.5 0 01-3 0m3 0a1.5 1.5 0 00-3 0m3 0h6m-9 0H3.375a1.125 1.125 0 01-1.125-1.125V14.25m17.25 4.5a1.5 1.5 0 01-3 0m3 0a1.5 1.5 0 00-3 0m3 0h1.125c.621 0 1.129-.504 1.09-1.124a17.902 17.902 0 00-3.213-9.193 2.056 2.056 0 00-1.58-.86H14.25M16.5 18.75h-2.25m0-11.177v-.958c0-.568-.422-1.048-.987-1.106a48.554 48.554 0 00-10.026 0 1.106 1.106 0 00-.987 1.106v7.635m12-6.677v6.677m0 4.5v-4.5m0 0h-12" />
                        </svg>
                    </div>
                    <h3 class="text-2xl font-black text-gray-900 tracking-tight">Checkout</h3>
                    <p class="text-gray-500 text-sm mt-1">Select your preferred receiving method.</p>
                </div>

                <div class="grid grid-cols-2 gap-4 mb-10">
                    <label class="cursor-pointer group flex">
                        <input type="radio" name="delivery" value="pickup" class="hidden peer" checked>
                        <div
                            class="flex-1 p-6 border-2 border-gray-100 rounded-[2rem] text-center group-hover:border-brand-light peer-checked:border-brand peer-checked:bg-orange-50 transition-all flex flex-col items-center justify-center min-h-[180px]">
                            <div class="mb-4">
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
                                    stroke-width="1.5" stroke="currentColor"
                                    class="w-10 h-10 text-gray-700 group-hover:text-brand peer-checked:text-brand transition-colors">
                                    <path stroke-linecap="round" stroke-linejoin="round"
                                        d="M13.5 21v-7.5a.75.75 0 01.75-.75h3a.75.75 0 01.75.75V21m-4.5 0H2.36m11.14 0H18m0 0h3.64m-1.39 0V9.809c0-.626-.31-1.227-.836-1.594l-2.18-1.516M18 21v-5.25a.75.75 0 00-.75-.75h-2.5a.75.75 0 00-.75.75V21m-4.5 0h2.25m0 0V12a2.25 2.25 0 00-2.25-2.25H5.25A2.25 2.25 0 003 12v9m15 0h2.25M3 21h2.25m12-16.5l-3-2.25a.75.75 0 00-.9 0l-3 2.25m6 0v2.25m-6-2.25v2.25" />
                                </svg>
                            </div>
                            <span class="block font-bold text-gray-900 text-lg">Pick-up</span>
                            <span class="block text-[10px] text-gray-400 mt-2 uppercase tracking-wide">At my
                                house</span>
                            <span class="block text-[10px] text-gray-500 font-bold uppercase">(FREE)</span>
                        </div>
                    </label>
                    <label class="cursor-pointer group flex">
                        <input type="radio" name="delivery" value="meetup" class="hidden peer">
                        <div
                            class="flex-1 p-6 border-2 border-gray-100 rounded-[2rem] text-center group-hover:border-brand-light peer-checked:border-brand peer-checked:bg-orange-50 transition-all flex flex-col items-center justify-center min-h-[180px]">
                            <div class="mb-4">
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
                                    stroke-width="1.5" stroke="currentColor"
                                    class="w-10 h-10 text-gray-700 group-hover:text-brand peer-checked:text-brand transition-colors">
                                    <path stroke-linecap="round" stroke-linejoin="round"
                                        d="M15 10.5a3 3 0 11-6 0 3 3 0 016 0z" />
                                    <path stroke-linecap="round" stroke-linejoin="round"
                                        d="M19.5 10.5c0 7.142-7.5 11.25-7.5 11.25S4.5 17.642 4.5 10.5a7.5 7.5 0 1115 0z" />
                                </svg>
                            </div>
                            <span class="block font-bold text-gray-900 text-lg">Meet-up</span>
                            <span class="block text-[10px] text-gray-400 mt-2 uppercase tracking-wide">At School</span>
                            <span class="block text-[10px] text-brand-dark font-black uppercase">(+₱20 fee)</span>
                        </div>
                    </label>
                </div>

                <button id="confirm-order" onclick="saveOrder()"
                    class="w-full py-5 bg-brand text-white rounded-2xl font-black text-xl hover:bg-brand-dark transition transform active:scale-95 shadow-xl shadow-brand/20 mb-4 flex items-center justify-center gap-3">
                    <span id="btn-text">Confirm Order</span>
                    <svg id="btn-spinner" class="hidden animate-spin h-6 w-6 text-white"
                        xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4">
                        </circle>
                        <path class="opacity-75" fill="currentColor"
                            d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z">
                        </path>
                    </svg>
                </button>

                <button onclick="switchStep('schedule')"
                    class="w-full py-2 text-gray-400 font-bold hover:text-gray-600 transition">
                    Go Back
                </button>
            </div>
        </div>


        <div id="card-success"
            class="step-card bg-white w-full max-w-sm rounded-3xl shadow-2xl overflow-hidden hidden transform scale-95 transition-all duration-300 p-12 text-center">
            <div
                class="w-20 h-20 bg-emerald-50 text-emerald-500 rounded-full flex items-center justify-center text-4xl mx-auto mb-6 animate-bounce">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2.5"
                    stroke="currentColor" class="w-10 h-10">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5" />
                </svg>
            </div>
            <h3 class="text-3xl font-black text-gray-900 mb-2 tracking-tight">Order Received!</h3>
            <p class="text-gray-500 mb-8">We've received your documents. Our team will start processing them shortly.
            </p>
            <button onclick="location.reload()"
                class="w-full py-4 bg-emerald-500 text-white rounded-2xl font-black text-lg hover:bg-emerald-600 transition transform active:scale-95 shadow-xl shadow-emerald-200">
                Back to Home
            </button>
        </div>

        <!-- Mobile Bottom Sheet for Page Breakdown -->
        <div id="breakdown-sheet" class="md:hidden fixed inset-0 z-[70] hidden">
            <div id="breakdown-sheet-backdrop" onclick="toggleBreakdown()"
                class="absolute inset-0 bg-slate-900/40 backdrop-blur-[2px] opacity-0 transition-opacity duration-300">
            </div>
            <div id="breakdown-sheet-content"
                class="absolute inset-x-0 bottom-0 bg-white rounded-t-[2.5rem] shadow-2xl p-6 transform translate-y-full transition-transform duration-300 flex flex-col max-h-[90vh]">
                <div class="w-12 h-1.5 bg-gray-200 rounded-full mx-auto mb-6"></div>
                <div class="flex items-center justify-between mb-6">
                    <h4 class="text-lg font-black text-gray-900 uppercase tracking-widest">Page Breakdown</h4>
                    <button onclick="toggleBreakdown()" class="p-2 text-gray-400 hover:text-gray-600 transition">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2.5"
                            stroke="currentColor" class="w-6 h-6">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>
                <div
                    class="pdf-viewer-container custom-scrollbar h-full max-h-[600px] overflow-y-auto p-6 bg-slate-50/80 rounded-3xl border border-gray-100 shadow-[inset_0_2px_4px_rgba(0,0,0,0.02)]">
                </div>
            </div>
        </div>

    </div>

    <script>
        const priceMatrix = <?= json_encode($priceMatrix) ?>;
        const colorTiersMatrix = <?= json_encode($colorTiersMatrix) ?>;

        const dropzone = document.getElementById('dropzone');
        const fileInput = document.getElementById('file-upload');
        const backdrop = document.getElementById('modal-backdrop');

        const cards = {
            loading: document.getElementById('card-loading'),
            result: document.getElementById('card-result'),
            customer: document.getElementById('card-customer'),
            schedule: document.getElementById('card-schedule'),
            delivery: document.getElementById('card-delivery'),
            success: document.getElementById('card-success')
        };

        let scannedFiles = [];
        let currentPrintMode = 'Color';

        function openModal() {
            backdrop.classList.remove('hidden');
            document.getElementById('scan-progress').style.width = '0%';
            document.getElementById('scan-status').textContent = 'Preparing documents...';
            switchStep('loading');
            setTimeout(() => { backdrop.classList.add('opacity-100'); }, 10);
        }

        function switchStep(stepName) {
            Object.values(cards).forEach(card => {
                card.classList.add('hidden');
                card.classList.add('scale-95');
                card.classList.remove('scale-100');
            });
            const activeCard = cards[stepName];
            activeCard.classList.remove('hidden');
            setTimeout(() => {
                activeCard.classList.remove('scale-95');
                activeCard.classList.add('scale-100');
            }, 50);
        }

        function closeModal() {
            backdrop.classList.remove('opacity-100');
            const sheet = document.getElementById('breakdown-sheet');
            if (sheet && !sheet.classList.contains('hidden')) toggleBreakdown();

            Object.values(cards).forEach(card => {
                card.classList.remove('scale-100');
                card.classList.add('scale-95');
            });

            setTimeout(() => {
                backdrop.classList.add('hidden');
                fileInput.value = '';
                scannedFiles = [];
            }, 300);
        }

        fileInput.addEventListener('change', (e) => {
            if (e.target.files.length > 0) handleUpload(e.target.files);
        });

        ['dragenter', 'dragover'].forEach(eventName => {
            dropzone.addEventListener(eventName, (e) => {
                e.preventDefault();
                dropzone.classList.add('border-brand', 'bg-orange-100');
            });
        });

        ['dragleave', 'drop'].forEach(eventName => {
            dropzone.addEventListener(eventName, (e) => {
                e.preventDefault();
                dropzone.classList.remove('border-brand', 'bg-orange-100');
                if (eventName === 'drop' && e.dataTransfer.files.length > 0) {
                    handleUpload(e.dataTransfer.files);
                }
            });
        });

        async function handleUpload(files) {
            openModal();
            scannedFiles = [];

            try {
                let totalOverallPages = 0;
                let pdfRefs = [];

                // First pass: Pre-load to get total pages for the progress bar
                for (let i = 0; i < files.length; i++) {
                    if (files[i].type !== 'application/pdf') continue;
                    const arrayBuffer = await files[i].arrayBuffer();
                    const pdf = await pdfjsLib.getDocument({ data: arrayBuffer }).promise;
                    pdfRefs.push({ file: files[i], pdf: pdf });
                    totalOverallPages += pdf.numPages;
                }

                if (pdfRefs.length === 0) {
                    alert('No valid PDF files found.');
                    closeModal();
                    return;
                }

                let currentPageCount = 0;
                const canvas = document.createElement('canvas');
                const ctx = canvas.getContext('2d', { willReadFrequently: true });

                // Second pass: Render and calculate ink usage
                for (let fileIndex = 0; fileIndex < pdfRefs.length; fileIndex++) {
                    const { file, pdf } = pdfRefs[fileIndex];
                    const totalPages = pdf.numPages;
                    let pagesData = [];
                    let thumbnails = [];

                    for (let pageNum = 1; pageNum <= totalPages; pageNum++) {
                        currentPageCount++;
                        const progress = Math.round((currentPageCount / totalOverallPages) * 100);
                        document.getElementById('scan-status').textContent = `Scanning Page ${currentPageCount} / ${totalOverallPages}`;
                        document.getElementById('scan-progress').style.width = `${progress}%`;

                        if (pageNum % 5 === 0) await new Promise(r => setTimeout(r, 10));

                        const page = await pdf.getPage(pageNum);
                        const viewport = page.getViewport({ scale: 0.3 });
                        canvas.width = viewport.width;
                        canvas.height = viewport.height;

                        const pdfWidthInches = page.view[2] / 72;
                        const pdfHeightInches = page.view[3] / 72;
                        const w = Math.min(pdfWidthInches, pdfHeightInches);
                        const h = Math.max(pdfWidthInches, pdfHeightInches);

                        const supportedSizes = [
                            { name: 'A4', w: 8.3, h: 11.7 }, { name: 'Short', w: 8.5, h: 11.0 },
                            { name: 'Legal', w: 8.5, h: 14.0 }, { name: 'Long', w: 8.5, h: 13.0 }
                        ];

                        let paperSize = 'Short';
                        let minDistance = Infinity;
                        for (const size of supportedSizes) {
                            const distance = Math.sqrt(Math.pow(w - size.w, 2) + Math.pow(h - size.h, 2));
                            if (distance < minDistance) { minDistance = distance; paperSize = size.name; }
                        }

                        await page.render({ canvasContext: ctx, viewport: viewport }).promise;
                        thumbnails.push(canvas.toDataURL('image/jpeg', 0.5));

                        const imgData = ctx.getImageData(0, 0, canvas.width, canvas.height).data;
                        let pixelCount = 0, kWeightSum = 0, colorWeightSum = 0;

                        for (let i = 0; i < imgData.length; i += 16) {
                            pixelCount++;
                            let r = imgData[i], g = imgData[i + 1], b = imgData[i + 2];
                            if (r > 245 && g > 245 && b > 245) continue;
                            if (Math.abs(r - g) < 15 && Math.abs(g - b) < 15) {
                                kWeightSum += (255 - ((r + g + b) / 3)) / 255;
                            } else {
                                colorWeightSum += (255 - ((r + g + b) / 3)) / 255;
                            }
                        }

                        pagesData.push({
                            page: pageNum,
                            size: paperSize,
                            black_pct: ((kWeightSum / pixelCount) * 100).toFixed(4),
                            color_pct: ((colorWeightSum / pixelCount) * 100).toFixed(4),
                            price: 0,
                            selected: true // New property for page deselection
                        });
                    }

                    scannedFiles.push({
                        raw_file: file,
                        original_name: file.name,
                        copies: 1, // Default copies
                        pages: pagesData,
                        thumbnails: thumbnails,
                        total_price: 0
                    });
                }

                setPrintMode('Color');
                switchStep('result');

            } catch (error) {
                console.error('FULL SCAN ERROR:', error);
                alert('Scan failed:\n' + (error?.message || error?.toString() || 'Unknown error'));
                closeModal();
            }
        }

        function setPrintMode(mode) {
            currentPrintMode = mode;
            const btnColor = document.getElementById('btn-mode-color');
            const btnBw = document.getElementById('btn-mode-bw');

            if (mode === 'Color') {
                btnColor.className = 'flex-1 py-2.5 text-sm font-black bg-blue-500 text-white shadow-md rounded-lg transition-all transform scale-100 ring-2 ring-blue-500 ring-offset-1';
                btnBw.className = 'flex-1 py-2.5 text-sm font-bold text-gray-500 bg-transparent hover:text-gray-900 transition-all rounded-lg transform scale-95 opacity-80 hover:scale-100 hover:opacity-100';
            } else {
                btnBw.className = 'flex-1 py-2.5 text-sm font-black bg-gray-900 text-white shadow-md rounded-lg transition-all transform scale-100 ring-2 ring-gray-900 ring-offset-1';
                btnColor.className = 'flex-1 py-2.5 text-sm font-bold text-gray-500 bg-transparent hover:text-gray-900 transition-all rounded-lg transform scale-95 opacity-80 hover:scale-100 hover:opacity-100';
            }
            recalculatePrices();
        }

        function togglePageSelection(fileIndex, pageIndex) {
            const page = scannedFiles[fileIndex].pages[pageIndex];
            page.selected = !page.selected;
            recalculatePrices();
        }

        function updateCopies(fileIndex, value) {
            let copies = parseInt(value);
            if (isNaN(copies) || copies < 1) copies = 1;
            scannedFiles[fileIndex].copies = copies;
            recalculatePrices();
        }

        function recalculatePrices() {
            if (scannedFiles.length === 0) return;

            let grandTotalRetail = 0;
            let totalActivePagesOverall = 0;
            let paperSizes = new Set();

            scannedFiles.forEach((file) => {
                let fileTotal = 0;
                let activePages = 0;

                file.pages.forEach(page => {
                    if (!page.selected) return; // Skip excluded pages

                    activePages++;
                    let paperSize = page.size;
                    paperSizes.add(paperSize);
                    let totalCoverage = parseFloat(page.black_pct) + parseFloat(page.color_pct);
                    let isColor = (currentPrintMode === 'Color') ? (parseFloat(page.color_pct) > 0.02) : false;

                    if (!priceMatrix[paperSize]) paperSize = 'Short';

                    let basePrice = isColor ? priceMatrix[paperSize].color : priceMatrix[paperSize].bw;
                    let surcharge = 0;

                    if (isColor && colorTiersMatrix[paperSize]) {
                        for (let tier of colorTiersMatrix[paperSize]) {
                            if (totalCoverage >= tier.min_coverage) {
                                surcharge = tier.surcharge;
                                break;
                            }
                        }
                    }

                    page.price = basePrice + surcharge;
                    fileTotal += page.price;
                });

                file.total_price = fileTotal * file.copies;
                grandTotalRetail += file.total_price;
                totalActivePagesOverall += (activePages * file.copies);
            });

            document.getElementById('res-price').textContent = grandTotalRetail.toFixed(2);
            document.getElementById('res-pages').textContent = totalActivePagesOverall;

            // Update sidebar paper size text
            const sizeElem = document.getElementById('res-paper-size');
            sizeElem.textContent = paperSizes.size > 1 ? 'Multiple' : (Array.from(paperSizes)[0] || 'N/A');

            renderViewer();
        }

        function renderViewer() {
            const viewers = document.querySelectorAll('.pdf-viewer-container');

            viewers.forEach(viewer => {
                viewer.innerHTML = '';

                scannedFiles.forEach((file, fileIndex) => {
                    // Create Header for each file
                    const fileHeader = document.createElement('div');
                    fileHeader.className = 'col-span-full flex justify-between items-center bg-white p-4 rounded-xl shadow-sm mb-4 border border-gray-100';
                    fileHeader.innerHTML = `
                <h5 class="font-bold text-gray-800 truncate pr-4">${file.original_name}</h5>
                <div class="flex items-center gap-3 shrink-0">
                    <label class="text-xs font-bold text-gray-400 uppercase tracking-wider">Copies</label>
                    <input type="number" min="1" value="${file.copies}" 
                        class="w-16 p-2 bg-gray-50 border border-gray-200 rounded-lg text-center font-bold text-gray-700 outline-none focus:border-brand transition-colors"
                        onchange="updateCopies(${fileIndex}, this.value)">
                </div>
            `;
                    viewer.appendChild(fileHeader);

                    // Create Grid for Thumbnails
                    const grid = document.createElement('div');
                    grid.className = 'grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-6 mb-8';

                    file.pages.forEach((pageInfo, pageIndex) => {
                        const img = document.createElement('img');
                        img.src = file.thumbnails[pageIndex];

                        // Styling based on selection state
                        if (pageInfo.selected) {
                            img.className = 'w-full h-auto object-cover rounded shadow-sm border-2 border-brand/50 hover:border-brand transition-all cursor-pointer hover:-translate-y-1';
                        } else {
                            img.className = 'w-full h-auto object-cover rounded border border-gray-200 opacity-40 grayscale cursor-pointer hover:opacity-70 transition-all';
                        }
                        img.onclick = () => togglePageSelection(fileIndex, pageIndex);

                        const container = document.createElement('div');
                        container.className = 'relative flex flex-col items-center group';
                        container.title = `Click to ${pageInfo.selected ? 'exclude' : 'include'} page`;

                        // Only show price badge if selected
                        if (pageInfo.selected) {
                            const priceBadge = document.createElement('div');
                            priceBadge.className = 'absolute -top-2 -right-2 bg-gray-900 text-white text-[10px] font-black px-2 py-1 rounded-full shadow-md z-10 pointer-events-none';
                            priceBadge.textContent = '₱' + parseFloat(pageInfo.price).toFixed(2);
                            container.appendChild(priceBadge);
                        } else {
                            const excludedBadge = document.createElement('div');
                            excludedBadge.className = 'absolute top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 bg-red-500/90 text-white text-xs font-bold px-3 py-1 rounded-full pointer-events-none backdrop-blur-sm shadow-sm';
                            excludedBadge.textContent = 'EXCLUDED';
                            container.appendChild(excludedBadge);
                        }

                        const pageLabel = document.createElement('span');
                        pageLabel.className = `text-[10px] font-bold mt-2 uppercase tracking-wider ${pageInfo.selected ? 'text-gray-500' : 'text-gray-300 line-through'}`;
                        pageLabel.textContent = `Page ${pageInfo.page}`;

                        container.appendChild(img);
                        container.appendChild(pageLabel);
                        grid.appendChild(container);
                    });

                    viewer.appendChild(grid);
                });
            });
        }

        async function saveOrder() {
            if (scannedFiles.length === 0) return;

            const customerName = document.getElementById('inp-customer-name').value;
            if (!customerName.trim()) {
                alert('Please enter a name for the order.');
                switchStep('customer');
                return;
            }

            const btn = document.getElementById('confirm-order');
            const btnText = document.getElementById('btn-text');
            const btnSpinner = document.getElementById('btn-spinner');

            btn.disabled = true;
            btn.classList.add('opacity-80', 'cursor-not-allowed');
            btnText.textContent = 'Processing...';
            btnSpinner.classList.remove('hidden');

            const delivery = document.querySelector('input[name="delivery"]:checked').value;
            const scheduledDate = document.getElementById('inp-date').value;
            const scheduledTime = document.getElementById('inp-time').value;
            const notes = document.getElementById('inp-notes').value;

            let finalSchedule = null;
            if (scheduledDate && scheduledTime) finalSchedule = `${scheduledDate} ${scheduledTime}:00`;

            const formData = new FormData();
            let filesMeta = [];
            let grandTotalPrice = 0;

            scannedFiles.forEach(file => {
                formData.append('pdf_files[]', file.raw_file);

                const excludedPages = file.pages.filter(p => !p.selected).map(p => p.page).join(',');
                const activePagesCount = file.pages.filter(p => p.selected).length;

                filesMeta.push({
                    filename: file.original_name,
                    paper_size: file.pages[0]?.size || 'Short',
                    total_pages: activePagesCount,
                    price: file.total_price,
                    copies: file.copies,
                    excluded_pages: excludedPages
                });

                grandTotalPrice += file.total_price;
            });

            if (delivery === 'meetup') grandTotalPrice += 20;

            formData.append('files_meta', JSON.stringify(filesMeta));
            formData.append('price', grandTotalPrice);
            formData.append('customer_name', customerName);
            formData.append('print_mode', currentPrintMode);
            formData.append('delivery', delivery);
            if (finalSchedule) formData.append('scheduled_time', finalSchedule);
            formData.append('notes', notes);

            try {
                const response = await fetch('includes/finalize_order.php', {
                    method: 'POST',
                    body: formData
                });

                const res = await response.json();
                if (res.status === 'success') {
                    switchStep('success');
                } else {
                    alert('Error: ' + res.message);
                    resetBtn();
                }
            } catch (e) {
                alert('Failed to save order.');
                resetBtn();
            }

            function resetBtn() {
                btn.disabled = false;
                btn.classList.remove('opacity-80', 'cursor-not-allowed');
                btnText.textContent = 'Confirm Order';
                btnSpinner.classList.add('hidden');
            }
        }

        function toggleBreakdown() {
            const sheet = document.getElementById('breakdown-sheet');
            const backdrop = document.getElementById('breakdown-sheet-backdrop');
            const content = document.getElementById('breakdown-sheet-content');

            if (sheet.classList.contains('hidden')) {
                sheet.classList.remove('hidden');
                setTimeout(() => {
                    backdrop.classList.remove('opacity-0');
                    backdrop.classList.add('opacity-100');
                    content.classList.remove('translate-y-full');
                }, 10);
            } else {
                backdrop.classList.remove('opacity-100');
                backdrop.classList.add('opacity-0');
                content.classList.add('translate-y-full');
                setTimeout(() => {
                    sheet.classList.add('hidden');
                }, 300);
            }
        }
    </script>
</body>

</html>