<?php
require_once __DIR__ . '/includes/connect_db.php';

// Fetch base prices and calculate min/max surcharges directly via SQL
try {
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
} catch (PDOException $e) {
    // If table doesn't exist yet or query fails, we'll use an empty array
    $prices = [];
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
            <button class="text-brand-dark font-semibold hover:text-brand transition mr-4">Log In</button>
            <button
                class="bg-brand text-white px-5 py-2 rounded-full font-bold shadow-sm hover:bg-brand-dark transition transform hover:-translate-y-0.5">
                Sign In
            </button>
        </div>
    </nav>

    <main class="flex-grow container mx-auto px-6 py-12">
        <div class="flex flex-col lg:flex-row gap-12 items-center lg:items-start justify-center">

            <div class="w-full lg:w-2/3 text-center lg:text-left">
                <h1 class="text-5xl md:text-6xl font-black text-gray-900 mb-6 leading-tight">
                    The Purr-fect Print, <br>
                    <span class="text-brand">Exactly When You Need It.</span>
                </h1>

                <p class="text-lg md:text-xl text-gray-600 mb-10 max-w-2xl">
                    Upload your document, get your <strong>Precision Smart-Price</strong>, and schedule your meet-up or
                    pick-up.
                    No hidden fees!
                </p>


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
                        <input id="file-upload" type="file" accept=".pdf" class="hidden" />
                    </div>

                    <div
                        class="mt-6 flex items-center justify-center lg:justify-start gap-4 text-sm text-gray-400 font-medium">
                        <span class="flex items-center gap-1">Secure Upload</span>
                        <span>•</span>
                        <span class="flex items-center gap-1">Instant Pricing</span>
                    </div>
                </div>
            </div>

            <div class="w-full lg:w-1/3">
                <div class="bg-white p-8 rounded-3xl shadow-xl border border-orange-100 sticky top-12">
                    <h2 class="text-2xl font-black text-gray-900 mb-2 flex items-center gap-2">
                        Meow-nimum to Max-imum
                    </h2>
                    <p class="text-sm text-gray-500 mb-6 font-medium">Estimated price ranges per page.</p>

                    <div class="space-y-6">
                        <div>
                            <h3 class="text-sm font-bold text-brand uppercase tracking-wider mb-3">Black & White</h3>
                            <div class="space-y-2">
                                <?php if (empty($prices)): ?>
                                    <p class="text-xs text-gray-400 italic">No prices available.</p>
                                <?php else: ?>
                                    <?php foreach ($prices as $row):
                                        // Calculate exact min and max with safety floor
                                        $bwMin = max(0.50, (float) $row['bw_price'] + (float) $row['bw_min_sur']);
                                        $bwMax = (float) $row['bw_price'] + (float) $row['bw_max_sur'];
                                        ?>
                                        <div
                                            class="flex justify-between items-center pb-2 border-b border-orange-50 last:border-0">
                                            <span
                                                class="text-gray-600 font-medium"><?= htmlspecialchars($row['paper_size']) ?></span>
                                            <span class="text-gray-900 font-bold text-[15px]">
                                                ₱<?= number_format($bwMin, 2) ?> <span
                                                    class="text-gray-400 font-normal mx-1">-</span>
                                                ₱<?= number_format($bwMax, 2) ?>
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
                                        // Calculate exact min and max with safety floor
                                        $colMin = max(1.00, (float) $row['colored_price'] + (float) $row['color_min_sur']);
                                        $colMax = (float) $row['colored_price'] + (float) $row['color_max_sur'];
                                        ?>
                                        <div
                                            class="flex justify-between items-center pb-2 border-b border-orange-50 last:border-0">
                                            <span
                                                class="text-gray-600 font-medium"><?= htmlspecialchars($row['paper_size']) ?></span>
                                            <span class="text-gray-900 font-bold text-[15px]">
                                                ₱<?= number_format($colMin, 2) ?> <span
                                                    class="text-gray-400 font-normal mx-1">-</span>
                                                ₱<?= number_format($colMax, 2) ?>
                                            </span>
                                        </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <div class="mt-8 p-4 bg-orange-50 rounded-xl">
                        <p class="text-xs text-orange-700 leading-relaxed font-medium">
                            * Final price per page depends on exact ink coverage. Light text gets discounts
                            (Meow-nimum), while heavy graphics incur surcharges (Max-imum).
                        </p>
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
                    <span class="text-4xl animate-bounce">🔍</span>
                </div>
            </div>
            <h3 class="text-2xl font-black text-gray-900 mb-2 tracking-tight">Scanning...</h3>
            <p class="text-gray-500">Checking ink coverage and paper size.</p>
        </div>

        <div id="card-result"
            class="step-card bg-white w-full max-w-5xl rounded-[2rem] shadow-[0_20px_60px_-15px_rgba(0,0,0,0.1)] border border-gray-100 overflow-hidden hidden transform scale-95 transition-all duration-300">
            <div class="h-1.5 w-full bg-gradient-to-r from-orange-400 to-orange-600"></div>

            <div class="flex flex-col md:flex-row min-h-[550px]">

                <div
                    class="w-full md:w-[360px] p-8 lg:p-10 border-r border-gray-100 flex flex-col justify-between bg-slate-50/50">
                    <div>
                        <div class="mb-8">
                            <div
                                class="inline-flex items-center justify-center w-14 h-14 bg-orange-100 text-orange-600 rounded-2xl mb-5 shadow-sm border border-orange-200/50">
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2"
                                    stroke="currentColor" class="w-7 h-7">
                                    <path stroke-linecap="round" stroke-linejoin="round"
                                        d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m5.231 13.481L15 17.25m-4.5-15H5.625c-.621 0-1.125.504-1.125 1.125v16.5c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9zm3.75 11.625a2.625 2.625 0 11-5.25 0 2.625 2.625 0 015.25 0z" />
                                </svg>
                            </div>
                            <h3 class="text-2xl font-extrabold text-gray-900 tracking-tight">Smart-Price</h3>
                            <p class="text-gray-500 text-sm mt-1 font-medium">Real-time document analysis.</p>
                        </div>

                        <div class="space-y-4 mb-8">
                            <div
                                class="flex items-center justify-between p-4 bg-white rounded-2xl border border-gray-200 shadow-sm">
                                <span class="text-sm font-bold text-gray-400 uppercase tracking-wider">Total
                                    Pages</span>
                                <span id="res-pages"
                                    class="text-gray-900 font-extrabold text-2xl tracking-tight">0</span>
                            </div>

                            <div
                                class="p-6 bg-gradient-to-br from-orange-500 to-orange-600 text-white rounded-2xl shadow-lg shadow-orange-500/20 border border-orange-400/50 relative overflow-hidden">
                                <div
                                    class="absolute -right-6 -top-6 w-24 h-24 bg-white opacity-10 rounded-full blur-2xl">
                                </div>

                                <span
                                    class="block text-[11px] font-bold text-orange-100 uppercase tracking-widest mb-1.5">Final
                                    Total</span>
                                <div class="flex items-baseline gap-1 relative z-10">
                                    <span id="res-price" class="font-black text-5xl tracking-tighter">0.00</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="space-y-3">
                        <button onclick="switchStep('delivery')"
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

                <div class="flex-grow p-8 lg:p-10 bg-white flex flex-col">
                    <div class="flex items-center justify-between mb-6">
                        <h4 class="text-xs font-black text-gray-400 uppercase tracking-widest">Page Breakdown</h4>

                        <div
                            class="flex items-center gap-1.5 bg-orange-50 text-orange-600 border border-orange-100 px-3 py-1.5 rounded-full">
                            <span class="w-1.5 h-1.5 rounded-full bg-orange-500 animate-pulse"></span>
                            <span class="text-[10px] font-bold tracking-wide">LIVE PREVIEW</span>
                        </div>
                    </div>

                    <div id="pdf-viewer-grid"
                        class="custom-scrollbar grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-6 h-full max-h-[600px] overflow-y-auto p-6 bg-slate-50/80 rounded-3xl border border-gray-100 shadow-[inset_0_2px_4px_rgba(0,0,0,0.02)]">
                    </div>
                </div>
            </div>
        </div>


        <div id="card-schedule"
            class="step-card bg-white w-full max-w-lg rounded-3xl shadow-2xl overflow-hidden hidden transform scale-95 transition-all duration-300">
            <div class="h-2 bg-brand"></div>
            <div class="p-10">
                <div class="text-center mb-8">
                    <div
                        class="inline-flex items-center justify-center w-16 h-16 bg-orange-50 rounded-2xl text-orange-500 text-3xl mb-4">
                        📅
                    </div>
                    <h3 class="text-2xl font-black text-gray-900 tracking-tight">Schedule Your Print</h3>
                    <p class="text-gray-500 text-sm mt-1">When would you like to get your copies?</p>
                </div>

                <div class="space-y-6 mb-10">
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-black text-gray-400 uppercase tracking-widest mb-2">Pick a
                                Date</label>
                            <input type="date" id="inp-date"
                                class="w-full p-4 bg-gray-50 border border-gray-100 rounded-2xl focus:ring-2 focus:ring-brand focus:border-brand outline-none transition-all font-bold text-gray-700">
                        </div>
                        <div>
                            <label class="block text-xs font-black text-gray-400 uppercase tracking-widest mb-2">Pick a
                                Time</label>
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
                <button onclick="switchStep('delivery')"
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
                        🚚
                    </div>
                    <h3 class="text-2xl font-black text-gray-900 tracking-tight">Checkout</h3>
                    <p class="text-gray-500 text-sm mt-1">Select your preferred pick-up point.</p>
                </div>

                <div class="grid grid-cols-2 gap-4 mb-10">
                    <label class="cursor-pointer group">
                        <input type="radio" name="delivery" value="pickup" class="hidden peer" checked>
                        <div
                            class="p-8 border-2 border-gray-100 rounded-[2rem] text-center group-hover:border-brand-light peer-checked:border-brand peer-checked:bg-orange-50 transition-all">
                            <span class="block text-4xl mb-3">🏪</span>
                            <span class="block font-bold text-gray-900">Pick-up</span>
                            <span class="block text-[10px] text-gray-400 mt-1 uppercase">At School</span>
                        </div>
                    </label>
                    <label class="cursor-pointer group">
                        <input type="radio" name="delivery" value="meetup" class="hidden peer">
                        <div
                            class="p-8 border-2 border-gray-100 rounded-[2rem] text-center group-hover:border-brand-light peer-checked:border-brand peer-checked:bg-orange-50 transition-all">
                            <span class="block text-4xl mb-3">📍</span>
                            <span class="block font-bold text-gray-900">Meet-up</span>
                            <span class="block text-[10px] text-gray-400 mt-1 uppercase">At my house</span>
                        </div>
                    </label>
                </div>

                <button onclick="switchStep('schedule')"
                    class="w-full py-4 bg-gray-900 text-white rounded-2xl font-black text-lg hover:bg-gray-800 transition transform active:scale-95 shadow-xl mb-4">
                    Confirm Checkout
                </button>

                <button onclick="switchStep('result')"
                    class="w-full py-2 text-gray-400 font-bold hover:text-gray-600 transition">
                    Go Back
                </button>
            </div>
        </div>


        <div id="card-success"
            class="step-card bg-white w-full max-w-sm rounded-3xl shadow-2xl overflow-hidden hidden transform scale-95 transition-all duration-300 p-12 text-center">
            <div
                class="w-20 h-20 bg-emerald-50 text-emerald-500 rounded-full flex items-center justify-center text-4xl mx-auto mb-6 animate-bounce">
                ✅
            </div>
            <h3 class="text-3xl font-black text-gray-900 mb-2 tracking-tight">Order Received!</h3>
            <p class="text-gray-500 mb-8">We've received your documents. Our team will start processing them
                shortly.</p>

            <button onclick="location.reload()"
                class="w-full py-4 bg-emerald-500 text-white rounded-2xl font-black text-lg hover:bg-emerald-600 transition transform active:scale-95 shadow-xl shadow-emerald-200">
                Back to Home
            </button>
        </div>


    </div>

    <script>
        const DEBUG_MODE = false; // Set to true to enable CSV export of pricing logic

        const dropzone = document.getElementById('dropzone');
        const fileInput = document.getElementById('file-upload');
        const backdrop = document.getElementById('modal-backdrop');

        const cards = {
            loading: document.getElementById('card-loading'),
            result: document.getElementById('card-result'),
            schedule: document.getElementById('card-schedule'),
            delivery: document.getElementById('card-delivery'),
            success: document.getElementById('card-success')
        };

        /**
         * Exports an array of objects to a CSV file and triggers download
         */
        function downloadCSV(data, filename = 'debug_export.csv') {
            if (!data || !data.length) return;

            // Extract headers from the first object
            const headers = Object.keys(data[0]).join(',');

            // Convert each object to a CSV row
            const rows = data.map(row =>
                Object.values(row).map(value => {
                    // Escape quotes and wrap in quotes to handle commas within values
                    const str = String(value).replace(/"/g, '""');
                    return `"${str}"`;
                }).join(',')
            );

            const csvContent = [headers, ...rows].join('\n');
            const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
            const link = document.createElement('a');

            if (link.download !== undefined) {
                const url = URL.createObjectURL(blob);
                link.setAttribute('href', url);
                link.setAttribute('download', filename);
                link.style.visibility = 'hidden';
                document.body.appendChild(link);
                link.click();
                document.body.removeChild(link);
            }
        }



        let scanData = null;
        let scanFinished = false;

        function openModal() {
            backdrop.classList.remove('hidden');
            switchStep('loading');
            setTimeout(() => {
                backdrop.classList.add('opacity-100');
            }, 10);
        }

        function switchStep(stepName) {
            // Hide all cards
            Object.values(cards).forEach(card => {
                card.classList.add('hidden');
                card.classList.add('scale-95');
                card.classList.remove('scale-100');
            });

            // Show active card
            const activeCard = cards[stepName];
            activeCard.classList.remove('hidden');
            setTimeout(() => {
                activeCard.classList.remove('scale-95');
                activeCard.classList.add('scale-100');
            }, 50);
        }

        function closeModal() {
            backdrop.classList.remove('opacity-100');
            Object.values(cards).forEach(card => {
                card.classList.remove('scale-100');
                card.classList.add('scale-95');
            });

            setTimeout(() => {
                backdrop.classList.add('hidden');
                fileInput.value = '';
                scanData = null;
                scanFinished = false;
            }, 300);
        }

        fileInput.addEventListener('change', (e) => {

            if (e.target.files.length > 0) handleUpload(e.target.files[0]);
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
                    handleUpload(e.dataTransfer.files[0]);
                }
            });
        });

        async function handleUpload(file) {
            if (file.type !== 'application/pdf') {
                alert('Please upload a PDF file.');
                return;
            }

            openModal(); // Show your scanning animation

            try {
                // 1. Read the PDF directly in the user's browser
                const arrayBuffer = await file.arrayBuffer();
                const pdf = await pdfjsLib.getDocument({ data: arrayBuffer }).promise;

                let pagesData = [];
                let totalBlackCoverage = 0;
                let totalColorCoverage = 0;

                // Create an invisible canvas to analyze the pixels
                const canvas = document.createElement('canvas');
                const ctx = canvas.getContext('2d', { willReadFrequently: true });

                // 2. Loop through every page
                for (let pageNum = 1; pageNum <= pdf.numPages; pageNum++) {
                    const page = await pdf.getPage(pageNum);

                    // Scale 0.5 is a great balance between speed and pixel accuracy
                    const viewport = page.getViewport({ scale: 0.5 });
                    canvas.width = viewport.width;
                    canvas.height = viewport.height;

                    // Determine Paper Size based on standard points
                    const longestSide = Math.max(page.view[2], page.view[3]);
                    let paperSize = 'Short';
                    if (longestSide >= 950) paperSize = 'Long';
                    else if (longestSide >= 820) paperSize = 'A4';

                    await page.render({ canvasContext: ctx, viewport: viewport }).promise;

                    const imgData = ctx.getImageData(0, 0, canvas.width, canvas.height).data;
                    let pixelCount = canvas.width * canvas.height;

                    let kWeightSum = 0;
                    let colorWeightSum = 0;

                    // Analyze every pixel using the "Hybrid Intensity Logic"
                    for (let i = 0; i < imgData.length; i += 4) {
                        let r = imgData[i];
                        let g = imgData[i + 1];
                        let b = imgData[i + 2];
                        // imgData[i+3] is Alpha, which we ignore assuming a white page background

                        // A. Skip absolute white (Paper) immediately to save massive CPU cycles
                        if (r > 245 && g > 245 && b > 245) continue;

                        // B. Grayscale vs Color Detection
                        // A threshold of 15 effectively ignores anti-aliasing artifacts on black text
                        if (Math.abs(r - g) < 15 && Math.abs(g - b) < 15) {
                            // It's a Black/Gray pixel. Calculate density (0.0 to 1.0)
                            kWeightSum += (255 - ((r + g + b) / 3)) / 255;
                        } else {
                            // It's a Color pixel. Calculate density (0.0 to 1.0)
                            colorWeightSum += (255 - ((r + g + b) / 3)) / 255;
                        }
                    }

                    // Calculate the percentage of the page physically covered by solid ink
                    let pageBlackPct = (kWeightSum / pixelCount) * 100;
                    let pageColorPct = (colorWeightSum / pixelCount) * 100;

                    pagesData.push({
                        page: pageNum,
                        size: paperSize,
                        black_pct: pageBlackPct.toFixed(4), // Keeping 4 decimals for precise backend math
                        color_pct: pageColorPct.toFixed(4)
                    });
                }

                // 3. Send the calculated coverage + the PDF file to your Hostinger server
                const formData = new FormData();
                formData.append('pdf_file', file);
                formData.append('scan_data', JSON.stringify(pagesData)); // Send the math!

                const response = await fetch('includes/process_upload.php', {
                    method: 'POST',
                    body: formData
                });

                const data = await response.json();

                // --- PRICING ALGO DEBUG ---
                if (typeof DEBUG_MODE !== 'undefined' && DEBUG_MODE) {
                    console.log('%c CAT-ALYSIS DEBUG: Exporting to CSV... ', 'background: #f97316; color: #fff; font-weight: bold; padding: 4px; border-radius: 4px;');
                    if (data.debug && data.debug.pages) {
                        downloadCSV(data.debug.pages, `pricing_breakdown_${file.name.replace('.pdf', '')}.csv`);
                    }
                } else {
                    console.log('%c CAT-ALYSIS PRICING DEBUG ', 'background: #f97316; color: #fff; font-weight: bold; padding: 4px; border-radius: 4px;');
                    console.table(data.debug.pages);
                }

                if (data.status === 'success') {

                    // Populate global scanData variable so saveOrder() works
                    scanData = {
                        original_name: file.name,
                        temp_file_path: data.file_path,
                        pages: data.pages, // The array of page prices
                        document_summary: {
                            total_pages: data.total_pages,
                            total_retail_price: data.total_price
                        }
                    };

                    document.getElementById('res-pages').textContent = data.total_pages;
                    document.getElementById('res-price').textContent = parseFloat(data.total_price).toFixed(2);

                    // Trigger the visual thumbnail renderer
                    await renderViewer(pdf, data.pages);

                    switchStep('result');
                } else {
                    alert('Error: ' + data.message);
                    closeModal();
                }

            } catch (error) {
                console.error('FULL SCAN ERROR:', error);

                alert(
                    'Scan failed:\n' +
                    (error?.message || error?.toString() || 'Unknown error')
                );

                closeModal();
            }
        }

        async function renderViewer(pdf, pagesData) {
            const viewer = document.getElementById('pdf-viewer-grid');
            viewer.innerHTML = ''; // Clear out any previous scans

            for (let i = 0; i < pagesData.length; i++) {
                const pageInfo = pagesData[i];
                const pageNum = pageInfo.page;

                const page = await pdf.getPage(pageNum);
                const viewport = page.getViewport({ scale: 0.25 }); // Low scale for tiny thumbnails

                // Create the canvas for the PDF page
                const canvas = document.createElement('canvas');
                const ctx = canvas.getContext('2d');
                canvas.width = viewport.width;
                canvas.height = viewport.height;
                canvas.className = 'w-full h-auto object-cover rounded shadow-sm border border-gray-200 bg-white';

                // Paint the PDF page onto the canvas
                await page.render({ canvasContext: ctx, viewport: viewport }).promise;

                // Create the wrapper and the price tag badge
                const container = document.createElement('div');
                container.className = 'relative flex flex-col items-center group cursor-help';
                container.title = `Page ${pageNum} - ${pageInfo.size}`;

                const priceBadge = document.createElement('div');
                priceBadge.className = 'absolute -top-2 -right-2 bg-gray-900 text-white text-[10px] font-black px-2 py-1 rounded-full shadow-md z-10 scale-90 group-hover:scale-110 transition-transform';
                priceBadge.textContent = parseFloat(pageInfo.price).toFixed(2);

                const pageLabel = document.createElement('span');
                pageLabel.className = 'text-[10px] text-gray-400 font-bold mt-1 uppercase tracking-wider';
                pageLabel.textContent = `Pg ${pageNum}`;

                container.appendChild(canvas);
                container.appendChild(priceBadge);
                container.appendChild(pageLabel);
                viewer.appendChild(container);
            }
        }

        async function saveOrder() {
            if (!scanData) return;

            const btn = document.getElementById('confirm-order');
            const btnText = document.getElementById('btn-text');
            const btnSpinner = document.getElementById('btn-spinner');

            // Set loading state
            btn.disabled = true;
            btn.classList.add('opacity-80', 'cursor-not-allowed');
            btnText.textContent = 'Processing...';
            btnSpinner.classList.remove('hidden');

            const delivery = document.querySelector('input[name="delivery"]:checked').value;
            const scheduledDate = document.getElementById('inp-date').value;
            const scheduledTime = document.getElementById('inp-time').value;
            const notes = document.getElementById('inp-notes').value;

            // Combine date and time
            let finalSchedule = null;
            if (scheduledDate && scheduledTime) {
                finalSchedule = `${scheduledDate} ${scheduledTime}:00`;
            }

            const finalData = {
                filename: scanData.original_name,
                file_path: scanData.temp_file_path,
                paper_size: scanData.pages[0].size, // Primary size
                is_duplex: 0, // Hardcoded for now as per user request to remove duplex
                total_pages: scanData.document_summary.total_pages,
                price: scanData.document_summary.total_retail_price,
                ink_data: JSON.stringify(scanData.pages),
                delivery: delivery,
                scheduled_time: finalSchedule,
                notes: notes
            };


            try {
                const response = await fetch('includes/finalize_order.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(finalData)
                });

                const res = await response.json();
                if (res.status === 'success') {
                    switchStep('success');
                } else {
                    alert('Error: ' + res.message);
                    // Reset button state
                    btn.disabled = false;
                    btn.classList.remove('opacity-80', 'cursor-not-allowed');
                    btnText.textContent = 'Confirm Order';
                    btnSpinner.classList.add('hidden');
                }

            } catch (e) {
                alert('Failed to save order.');
                // Reset button state
                btn.disabled = false;
                btn.classList.remove('opacity-80', 'cursor-not-allowed');
                btnText.textContent = 'Confirm Order';
                btnSpinner.classList.add('hidden');
            }
        }
    </script>
</body>

</html>