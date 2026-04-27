<?php
require_once __DIR__ . '/includes/connect_db.php';

// Fetch prices from database
try {
    // Ordering by size importance/custom order
    $stmt = $pdo->query("SELECT paper_size, bw_price, colored_price FROM printing_prices ORDER BY 
        CASE 
            WHEN paper_size = 'Short' THEN 1 
            WHEN paper_size = 'A4' THEN 2 
            WHEN paper_size = 'Long' THEN 3 
            ELSE 4 
        END");
    $prices = $stmt->fetchAll();
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
            
            <!-- Left Side: Hero and Upload -->
            <div class="w-full lg:w-2/3 text-center lg:text-left">
                <h1 class="text-5xl md:text-6xl font-black text-gray-900 mb-6 leading-tight">
                    The Purr-fect Print, <br>
                    <span class="text-brand">Exactly When You Need It.</span>
                </h1>

                <p class="text-lg md:text-xl text-gray-600 mb-10 max-w-2xl">
                    Upload your document, get your <strong>Precision Smart-Price</strong>, and schedule your meet-up or pick-up.
                    No hidden fees!
                </p>


                <div class="w-full max-w-xl bg-white p-8 rounded-3xl shadow-xl border border-orange-100 mx-auto lg:mx-0">
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

                    <div class="mt-6 flex items-center justify-center lg:justify-start gap-4 text-sm text-gray-400 font-medium">
                        <span class="flex items-center gap-1">Secure Upload</span>
                        <span>•</span>
                        <span class="flex items-center gap-1">Instant Pricing</span>
                    </div>
                </div>
            </div>

            <!-- Right Side: Meow-nimum Charge (Price List) -->
            <div class="w-full lg:w-1/3">
                <div class="bg-white p-8 rounded-3xl shadow-xl border border-orange-100 sticky top-12">
                    <h2 class="text-2xl font-black text-gray-900 mb-6 flex items-center gap-2">
                        Meow-nimum Charge
                    </h2>
                    
                    <div class="space-y-6">
                        <!-- Black & White -->
                        <div>
                            <h3 class="text-sm font-bold text-brand uppercase tracking-wider mb-3">Black & White</h3>
                            <div class="space-y-2">
                                <?php if (empty($prices)): ?>
                                    <p class="text-xs text-gray-400 italic">No prices available.</p>
                                <?php else: ?>
                                    <?php foreach ($prices as $row): ?>
                                    <div class="flex justify-between items-center pb-2 border-b border-orange-50 last:border-0">
                                        <span class="text-gray-600 font-medium"><?= htmlspecialchars($row['paper_size']) ?></span>
                                        <span class="text-gray-900 font-bold">₱<?= number_format((float)$row['bw_price'], 2) ?></span>
                                    </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- Colored -->
                        <div>
                            <h3 class="text-sm font-bold text-blue-500 uppercase tracking-wider mb-3">Colored</h3>
                            <div class="space-y-2">
                                <?php if (empty($prices)): ?>
                                    <p class="text-xs text-gray-400 italic">No prices available.</p>
                                <?php else: ?>
                                    <?php foreach ($prices as $row): ?>
                                    <div class="flex justify-between items-center pb-2 border-b border-orange-50 last:border-0">
                                        <span class="text-gray-600 font-medium"><?= htmlspecialchars($row['paper_size']) ?></span>
                                        <span class="text-gray-900 font-bold">₱<?= number_format((float)$row['colored_price'], 2) ?></span>
                                    </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>


                    <div class="mt-8 p-4 bg-orange-50 rounded-xl">
                        <p class="text-xs text-orange-700 leading-relaxed font-medium">
                            * Prices are per page. Bulk discounts apply for orders over 50 pages. Instant pricing will calculate exact ink usage.
                        </p>
                    </div>
                </div>
            </div>

        </div>
    </main>

    <footer class="text-center py-8 text-gray-400 text-sm">
        <p>&copy; 2026 Copy Cat Printing. Built in Puerto Princesa.</p>
    </footer>

    <!-- Modal Backdrop -->
    <div id="modal-backdrop" class="fixed inset-0 bg-slate-900/80 backdrop-blur-sm z-50 flex items-center justify-center hidden opacity-0 transition-opacity duration-300 p-6">
        <!-- Modal Card -->
        <div id="modal-card" class="bg-white w-full max-w-md rounded-3xl shadow-2xl overflow-hidden transform scale-95 transition-transform duration-300">
            <!-- Progress State -->
            <div id="modal-loading" class="p-12 text-center">
                <div class="relative w-24 h-24 mx-auto mb-6">
                    <div class="absolute inset-0 border-4 border-orange-100 rounded-full opacity-50"></div>
                    <div class="absolute inset-0 border-4 border-brand rounded-full border-t-transparent animate-spin"></div>
                    <div class="absolute inset-0 flex items-center justify-center">
                        <span class="text-4xl animate-bounce">🔍</span>
                    </div>
                </div>
                <h3 class="text-2xl font-black text-gray-900 mb-2 tracking-tight">Scanning...</h3>
                <p class="text-gray-500">Checking ink coverage and paper size.</p>
            </div>

            <!-- Result State -->
            <div id="modal-result" class="hidden">
                <div class="h-2 bg-brand"></div>
                <div class="p-8">
                    <div class="text-center mb-6">
                        <div class="inline-flex items-center justify-center w-16 h-16 bg-brand/10 rounded-full text-brand text-3xl mb-4">
                            📄
                        </div>
                        <h3 class="text-2xl font-black text-gray-900 tracking-tight">Scan Complete!</h3>
                    </div>

                    <div class="space-y-4 mb-8">
                        <div class="flex justify-between items-center p-4 bg-orange-50 rounded-2xl border border-orange-100">
                            <span class="text-gray-600 font-medium">Total Pages</span>
                            <span id="res-pages" class="text-gray-900 font-black text-xl">0</span>
                        </div>
                        <div class="flex justify-between items-center p-4 bg-brand text-white rounded-2xl shadow-lg shadow-brand/20">
                            <span class="font-medium opacity-90">Total Price</span>
                            <span id="res-price" class="font-black text-2xl">₱0.00</span>
                        </div>
                    </div>

                    <!-- Meet-up / Pick-up Choice -->
                    <div class="mb-8">
                        <label class="block text-xs font-black text-gray-400 uppercase tracking-widest mb-4">How do you want it?</label>
                        <div class="grid grid-cols-2 gap-4">
                            <label class="cursor-pointer group">
                                <input type="radio" name="delivery" value="pickup" class="hidden peer" checked>
                                <div class="p-4 border-2 border-gray-100 rounded-2xl text-center group-hover:border-brand-light peer-checked:border-brand peer-checked:bg-orange-50 transition-all">
                                    <span class="block text-2xl mb-1">🏪</span>
                                    <span class="block font-bold text-gray-900">Pick-up</span>
                                </div>
                            </label>
                            <label class="cursor-pointer group">
                                <input type="radio" name="delivery" value="meetup" class="hidden peer">
                                <div class="p-4 border-2 border-gray-100 rounded-2xl text-center group-hover:border-brand-light peer-checked:border-brand peer-checked:bg-orange-50 transition-all">
                                    <span class="block text-2xl mb-1">📍</span>
                                    <span class="block font-bold text-gray-900">Meet-up</span>
                                </div>
                            </label>
                        </div>
                    </div>

                    <button id="confirm-order" class="w-full py-4 bg-gray-900 text-white rounded-2xl font-black text-lg hover:bg-gray-800 transition transform active:scale-95 shadow-xl mb-3">
                        Confirm Order
                    </button>
                    <button onclick="closeModal()" class="w-full py-2 text-gray-400 font-bold hover:text-gray-600 transition">
                        Cancel
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script>
        const dropzone = document.getElementById('dropzone');
        const fileInput = document.getElementById('file-upload');
        const backdrop = document.getElementById('modal-backdrop');
        const card = document.getElementById('modal-card');
        const loading = document.getElementById('modal-loading');
        const result = document.getElementById('modal-result');

        function openModal() {
            backdrop.classList.remove('hidden');
            setTimeout(() => {
                backdrop.classList.add('opacity-100');
                card.classList.remove('scale-95');
                card.classList.add('scale-100');
            }, 10);
        }

        function closeModal() {
            backdrop.classList.remove('opacity-100');
            card.classList.remove('scale-100');
            card.classList.add('scale-95');
            setTimeout(() => {
                backdrop.classList.add('hidden');
                loading.classList.remove('hidden');
                result.classList.add('hidden');
                fileInput.value = ''; // Reset file input
            }, 300);
        }

        fileInput.addEventListener('change', (e) => {
            if (e.target.files.length > 0) {
                handleUpload(e.target.files[0]);
            }
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

            openModal();

            const formData = new FormData();
            formData.append('pdf_file', file);

            try {
                const response = await fetch('includes/process_upload.php', {
                    method: 'POST',
                    body: formData
                });

                const data = await response.json();

                if (data.status === 'success' || data.document_summary) {
                    const summary = data.document_summary;
                    document.getElementById('res-pages').textContent = summary.total_pages;
                    document.getElementById('res-price').textContent = '₱' + parseFloat(summary.total_retail_price).toFixed(2);
                    
                    loading.classList.add('hidden');
                    result.classList.remove('hidden');
                } else {
                    alert('Error: ' + (data.message || 'Failed to scan document.'));
                    closeModal();
                }
            } catch (error) {
                console.error('Upload failed:', error);
                alert('Connection error. Please try again.');
                closeModal();
            }
        }
    </script>
</body>

</html>