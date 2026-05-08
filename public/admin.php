<?php
session_start();
require_once __DIR__ . '/includes/connect_db.php';

// --- CONFIGURATION ---
$adminPassword = '@ndFErn0'; // Change this to your preferred password

// --- AUTHENTICATION LOGIC ---
if (isset($_GET['logout'])) {
    session_destroy();
    header("Location: index.php");
    exit;
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login'])) {
    if ($_POST['password'] === $adminPassword) {
        $_SESSION['admin_logged_in'] = true;
        header("Location: " . $_SERVER['PHP_SELF']);
        exit;
    } else {
        $error = 'Incorrect password. Please try again.';
    }
}

$isLoggedIn = isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true;

// --- DATA FETCHING (Only if logged in) ---
$orders = [];
$filesByOrder = [];
$stats = [
    'total_orders' => 0,
    'pending_prints' => 0,
    'today_revenue' => 0
];

if ($isLoggedIn) {
    try {
        // 1. Fetch all orders and calculate their total price
        $stmtOrders = $pdo->query("
            SELECT 
                o.id AS order_id, o.customer_name, o.payment_status, 
                o.fulfillment_type, o.location_pin AS notes, 
                o.scheduled_time, o.created_at,
                COALESCE(SUM(f.price), 0) as total_price
            FROM orders o
            LEFT JOIN files f ON o.id = f.order_id
            GROUP BY o.id
            ORDER BY o.created_at DESC
        ");
        $orders = $stmtOrders->fetchAll(PDO::FETCH_ASSOC);

        // 2. Fetch all files to display nested under their orders
        $stmtFiles = $pdo->query("SELECT * FROM files");
        $allFiles = $stmtFiles->fetchAll(PDO::FETCH_ASSOC);

        foreach ($allFiles as $file) {
            $filesByOrder[$file['order_id']][] = $file;
        }

        // 3. Calculate Stats
        $today = date('Y-m-d');
        foreach ($orders as $order) {
            $stats['total_orders']++;
            if ($order['payment_status'] !== 'Paid') {
                $stats['pending_prints']++;
            }
            if (strpos($order['created_at'], $today) === 0) {
                $stats['today_revenue'] += $order['total_price'];
            }
        }

    } catch (PDOException $e) {
        die("Error fetching data: " . $e->getMessage());
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard | Copy Cat</title>
    <link href="assets/css/style.css" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        brand: {
                            DEFAULT: '#f97316', // orange-500
                            light: '#fdba74',
                            dark: '#ea580c',
                        }
                    }
                }
            }
        }
    </script>
</head>

<body class="bg-slate-50 min-h-screen font-sans text-slate-800 flex flex-col relative">

    <?php if (!$isLoggedIn): ?>
        <!-- LOGIN SCREEN -->
        <div class="flex-1 flex items-center justify-center p-6">
            <div
                class="w-full max-w-md bg-white rounded-[2rem] shadow-2xl shadow-slate-200/50 p-10 border border-slate-100 text-center">
                <div
                    class="w-20 h-20 bg-brand rounded-2xl flex items-center justify-center text-white text-4xl font-black shadow-lg shadow-brand/30 mx-auto mb-6">
                    C
                </div>
                <h1 class="text-3xl font-black text-slate-900 tracking-tight mb-2">Admin Access</h1>
                <p class="text-slate-500 text-sm font-medium mb-8">Enter your password to manage orders.</p>

                <?php if ($error): ?>
                    <div class="bg-red-50 text-red-500 text-sm font-bold p-3 rounded-xl mb-6 border border-red-100">
                        <?= htmlspecialchars($error) ?>
                    </div>
                <?php endif; ?>

                <form method="POST" action="">
                    <input type="password" name="password" placeholder="Password" required autofocus
                        class="w-full p-4 bg-slate-50 border border-slate-200 rounded-2xl mb-6 focus:ring-2 focus:ring-brand focus:border-brand outline-none transition-all text-center font-bold text-lg tracking-widest text-slate-700">

                    <button type="submit" name="login"
                        class="w-full py-4 bg-slate-900 text-white rounded-2xl font-black text-lg hover:bg-slate-800 transition transform active:scale-95 shadow-xl">
                        Unlock Dashboard
                    </button>
                </form>
            </div>
        </div>
    <?php else: ?>
        <!-- ADMIN DASHBOARD -->
        <nav class="bg-white border-b border-slate-200 sticky top-0 z-30 shadow-sm">
            <div class="container mx-auto px-4 md:px-6 h-16 md:h-20 flex items-center justify-between">
                <div class="flex items-center gap-3">
                    <div
                        class="w-8 h-8 md:w-10 md:h-10 bg-brand rounded-lg md:rounded-xl flex items-center justify-center text-white text-xl md:text-2xl font-black shadow-md shadow-brand/20">
                        C
                    </div>
                    <h1 class="text-lg md:text-xl font-black text-slate-900 tracking-tight">Admin Console</h1>
                </div>
                <div class="flex items-center gap-4 md:gap-6">
                    <span class="text-sm font-bold text-slate-400 hidden sm:block">Puerto Princesa Branch</span>
                    <div class="w-px h-6 bg-slate-200 hidden sm:block"></div>
                    <a href="?logout=1"
                        class="text-sm font-bold text-rose-500 hover:text-rose-600 transition flex items-center gap-1.5">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2"
                            stroke="currentColor" class="w-4 h-4 md:w-5 md:h-5">
                            <path stroke-linecap="round" stroke-linejoin="round"
                                d="M15.75 9V5.25A2.25 2.25 0 0013.5 3h-6a2.25 2.25 0 00-2.25 2.25v13.5A2.25 2.25 0 007.5 21h6a2.25 2.25 0 002.25-2.25V15M12 9l-3 3m0 0l3 3m-3-3h12.75" />
                        </svg>
                        <span class="hidden md:inline">Logout</span>
                    </a>
                </div>
            </div>
        </nav>

        <main class="container mx-auto px-4 md:px-6 py-6 md:py-10 flex-1">
            <!-- Stats Row -->
            <div class="grid grid-cols-2 md:grid-cols-3 gap-4 md:gap-6 mb-8 md:mb-10">
                <div
                    class="bg-white p-5 md:p-6 rounded-2xl md:rounded-3xl shadow-sm border border-slate-100 flex flex-col justify-center">
                    <span
                        class="text-slate-400 text-[10px] md:text-xs font-black uppercase tracking-widest block mb-1 md:mb-2">Orders</span>
                    <div class="flex items-baseline gap-2">
                        <span class="text-3xl md:text-4xl font-black text-slate-900"><?= $stats['total_orders'] ?></span>
                    </div>
                </div>
                <div
                    class="bg-white p-5 md:p-6 rounded-2xl md:rounded-3xl shadow-sm border border-slate-100 flex flex-col justify-center relative overflow-hidden">
                    <span
                        class="text-slate-400 text-[10px] md:text-xs font-black uppercase tracking-widest block mb-1 md:mb-2">Pending</span>
                    <div class="flex items-baseline gap-2">
                        <span class="text-3xl md:text-4xl font-black text-slate-900"><?= $stats['pending_prints'] ?></span>
                        <?php if ($stats['pending_prints'] > 0): ?>
                            <span class="flex h-2 w-2 md:h-3 md:w-3 relative ml-1 md:ml-2">
                                <span
                                    class="animate-ping absolute inline-flex h-full w-full rounded-full bg-brand opacity-75"></span>
                                <span class="relative inline-flex rounded-full h-2 w-2 md:h-3 md:w-3 bg-brand"></span>
                            </span>
                        <?php endif; ?>
                    </div>
                </div>
                <div
                    class="col-span-2 md:col-span-1 bg-white p-5 md:p-6 rounded-2xl md:rounded-3xl shadow-sm border border-slate-100 flex flex-col justify-center">
                    <span
                        class="text-slate-400 text-[10px] md:text-xs font-black uppercase tracking-widest block mb-1 md:mb-2">Today's
                        Revenue</span>
                    <div class="flex items-baseline gap-1">
                        <span class="text-slate-400 font-bold text-lg md:text-xl">₱</span>
                        <span
                            class="text-3xl md:text-4xl font-black text-slate-900"><?= number_format($stats['today_revenue'], 2) ?></span>
                    </div>
                </div>
            </div>

            <!-- List Header -->
            <div class="flex flex-row justify-between items-center mb-6">
                <h2 class="text-lg md:text-xl font-black text-slate-900 tracking-tight">Recent Orders</h2>
                <button onclick="location.reload()"
                    class="px-4 py-2 md:px-5 md:py-2.5 bg-slate-900 text-white rounded-lg md:rounded-xl text-xs font-bold hover:bg-slate-800 transition shadow-md flex items-center gap-2">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2"
                        stroke="currentColor" class="w-4 h-4">
                        <path stroke-linecap="round" stroke-linejoin="round"
                            d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0l3.181 3.183a8.25 8.25 0 0013.803-3.7M4.031 9.865a8.25 8.25 0 0113.803-3.7l3.181 3.182m0-4.991v4.99" />
                    </svg>
                    <span class="hidden sm:inline">Refresh List</span>
                </button>
            </div>

            <?php if (empty($orders)): ?>
                <div class="bg-white rounded-3xl p-10 text-center border border-slate-100 shadow-sm">
                    <div
                        class="w-16 h-16 bg-slate-100 rounded-full flex items-center justify-center text-slate-300 mx-auto mb-4">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5"
                            stroke="currentColor" class="w-8 h-8">
                            <path stroke-linecap="round" stroke-linejoin="round"
                                d="M20.25 7.5l-.625 10.632a2.25 2.25 0 01-2.247 2.118H6.622a2.25 2.25 0 01-2.247-2.118L3.75 7.5M10 11.25h4M3.375 7.5h17.25c.621 0 1.125-.504 1.125-1.125v-1.5c0-.621-.504-1.125-1.125-1.125H3.375c-.621 0-1.125.504-1.125 1.125v1.5c0 .621.504 1.125 1.125 1.125z" />
                        </svg>
                    </div>
                    <h3 class="text-lg font-black text-slate-900 mb-1">No orders yet</h3>
                    <p class="text-sm text-slate-500 font-medium">When customers place orders, they will appear here.</p>
                </div>
            <?php else: ?>

                <!-- DESKTOP VIEW -->
                <div
                    class="hidden lg:block bg-white rounded-[2.5rem] shadow-xl shadow-slate-200/50 overflow-hidden border border-slate-100">
                    <div class="overflow-x-auto">
                        <table class="w-full text-left border-collapse min-w-[1000px]">
                            <thead>
                                <tr class="bg-slate-50/50">
                                    <th
                                        class="px-8 py-5 text-[10px] font-black text-slate-400 uppercase tracking-widest border-b border-slate-100 w-1/5">
                                        Order & Customer</th>
                                    <th
                                        class="px-8 py-5 text-[10px] font-black text-slate-400 uppercase tracking-widest border-b border-slate-100 w-2/5">
                                        Files to Print</th>
                                    <th
                                        class="px-8 py-5 text-[10px] font-black text-slate-400 uppercase tracking-widest border-b border-slate-100">
                                        Schedule & Details</th>
                                    <th
                                        class="px-8 py-5 text-[10px] font-black text-slate-400 uppercase tracking-widest border-b border-slate-100">
                                        Fulfillment</th>
                                    <th
                                        class="px-8 py-5 text-[10px] font-black text-slate-400 uppercase tracking-widest border-b border-slate-100 text-right">
                                        Status & Total</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-50">
                                <?php foreach ($orders as $order): ?>
                                    <tr class="hover:bg-slate-50/40 transition group">
                                        <td class="px-8 py-6 align-top">
                                            <div class="font-black text-slate-900 text-sm mb-1">
                                                <?= htmlspecialchars($order['customer_name'] ?? 'Unknown') ?>
                                            </div>
                                            <div class="flex items-center gap-2">
                                                <span
                                                    class="font-bold text-slate-400 text-xs">#<?= str_pad($order['order_id'], 4, '0', STR_PAD_LEFT) ?></span>
                                                <span class="text-slate-300">•</span>
                                                <span
                                                    class="text-[10px] font-medium text-slate-500 uppercase tracking-wide"><?= date('M d, h:i A', strtotime($order['created_at'])) ?></span>
                                            </div>
                                        </td>
                                        <td class="px-8 py-6 align-top">
                                            <div class="space-y-3">
                                                <?php
                                                $orderFiles = $filesByOrder[$order['order_id']] ?? [];
                                                if (empty($orderFiles)):
                                                    ?>
                                                    <span class="text-xs text-slate-400 italic">No files attached.</span>
                                                <?php else:
                                                    foreach ($orderFiles as $file): ?>
                                                        <div
                                                            class="flex items-center justify-between p-3 bg-white border border-slate-200 rounded-xl shadow-sm hover:border-brand/30 transition-colors">
                                                            <div class="flex items-center gap-3 overflow-hidden pr-3">
                                                                <div
                                                                    class="w-8 h-8 rounded-lg bg-orange-50 text-brand flex items-center justify-center shrink-0">
                                                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
                                                                        stroke-width="2" stroke="currentColor" class="w-4 h-4">
                                                                        <path stroke-linecap="round" stroke-linejoin="round"
                                                                            d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m5.231 13.481L15 17.25m-4.5-15H5.625c-.621 0-1.125.504-1.125 1.125v16.5c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9zm3.75 11.625a2.625 2.625 0 11-5.25 0 2.625 2.625 0 015.25 0z" />
                                                                    </svg>
                                                                </div>
                                                                <div class="min-w-0">
                                                                    <div class="text-sm font-bold text-slate-800 truncate"
                                                                        title="<?= htmlspecialchars($file['filename']) ?>">
                                                                        <?= htmlspecialchars($file['filename']) ?>
                                                                    </div>
                                                                    <div
                                                                        class="text-[10px] text-slate-500 font-bold uppercase tracking-wide mt-0.5 flex flex-wrap gap-x-2 gap-y-1">
                                                                        <span><?= $file['total_pages'] ?> Pages</span>
                                                                        <span class="text-slate-300">•</span>
                                                                        <span class="text-brand"><?= $file['copies'] ?>x Copies</span>
                                                                        <span class="text-slate-300">•</span>
                                                                        <span><?= $file['paper_size'] ?></span>
                                                                        <?php if (!empty($file['excluded_pages'])): ?>
                                                                            <span class="text-slate-300">•</span>
                                                                            <span class="text-rose-500"
                                                                                title="Excluded Pages: <?= $file['excluded_pages'] ?>">Skip:
                                                                                <?= $file['excluded_pages'] ?></span>
                                                                        <?php endif; ?>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                            <!-- Print Settings Button (Download removed from here) -->
                                                            <button
                                                                onclick="openPrintModal('<?= htmlspecialchars($file['file_path']) ?>', '<?= $file['copies'] ?>', '<?= htmlspecialchars($file['paper_size']) ?>', '<?= htmlspecialchars($file['excluded_pages']) ?>', <?= (int) $file['total_pages'] ?>)"
                                                                title="Open Print Settings"
                                                                class="shrink-0 px-3 py-2 text-[10px] font-black uppercase tracking-widest flex items-center gap-1.5 bg-slate-900 text-white rounded-lg hover:bg-slate-800 transition-colors shadow-sm">
                                                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
                                                                    stroke-width="2" stroke="currentColor" class="w-3.5 h-3.5">
                                                                    <path stroke-linecap="round" stroke-linejoin="round"
                                                                        d="M6.72 13.829c-.24.03-.48.062-.72.096m.72-.096a42.415 42.415 0 0 1 10.56 0m-10.56 0L6.34 18m10.94-4.171c.24.03.48.062.72.096m-.72-.096L17.66 18m0 0 .229 2.523a1.125 1.125 0 0 1-1.12 1.227H7.231c-.662 0-1.18-.568-1.12-1.227L6.34 18m11.318 0h1.091A2.25 2.25 0 0 0 21 15.75V9.456c0-1.081-.768-2.015-1.837-2.175a48.055 48.055 0 0 0-1.913-.247M6.34 18H5.25A2.25 2.25 0 0 1 3 15.75V9.456c0-1.081.768-2.015 1.837-2.175a48.041 48.041 0 0 1 1.913-.247m10.5 0a48.536 48.536 0 0 0-10.5 0v-2.94a2.25 2.25 0 0 1 2.25-2.25h6a2.25 2.25 0 0 1 2.25 2.25v2.94Z" />
                                                                </svg>
                                                                Print
                                                            </button>
                                                        </div>
                                                    <?php endforeach; endif; ?>
                                            </div>
                                        </td>
                                        <td class="px-8 py-6 align-top">
                                            <div class="text-sm font-bold text-slate-700 mb-1">
                                                <?= $order['scheduled_time'] ? date('M d, h:i A', strtotime($order['scheduled_time'])) : 'ASAP' ?>
                                            </div>
                                            <div class="text-xs text-slate-500 italic max-w-[200px] leading-relaxed">
                                                <?= htmlspecialchars($order['notes']) ?: 'No special instructions' ?>
                                            </div>
                                        </td>
                                        <td class="px-8 py-6 align-top">
                                            <?php if ($order['fulfillment_type'] === 'Pick Up'): ?>
                                                <span
                                                    class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full text-[10px] font-black uppercase tracking-widest bg-blue-50 text-blue-600 border border-blue-100/50">
                                                    Pick-Up
                                                </span>
                                            <?php else: ?>
                                                <span
                                                    class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full text-[10px] font-black uppercase tracking-widest bg-purple-50 text-purple-600 border border-purple-100/50">
                                                    Meet-Up
                                                </span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="px-8 py-6 align-top text-right">
                                            <div class="text-base font-black text-slate-900 mb-2">
                                                ₱<?= number_format($order['total_price'], 2) ?></div>
                                            <?php
                                            $statusClass = match ($order['payment_status']) {
                                                'Paid' => 'bg-emerald-50 text-emerald-600 border-emerald-100',
                                                'Unpaid' => 'bg-rose-50 text-rose-600 border-rose-100',
                                                default => 'bg-amber-50 text-amber-600 border-amber-100'
                                            };
                                            ?>
                                            <span
                                                class="px-3 py-1 rounded-full text-[10px] font-black uppercase tracking-widest border <?= $statusClass ?>">
                                                <?= $order['payment_status'] ?>
                                            </span>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- MOBILE VIEW -->
                <div class="block lg:hidden space-y-4">
                    <?php foreach ($orders as $order): ?>
                        <div
                            class="bg-white p-5 rounded-2xl shadow-sm border border-slate-200 flex flex-col gap-4 relative overflow-hidden">
                            <div class="flex justify-between items-start border-b border-slate-100 pb-4">
                                <div>
                                    <div class="font-black text-slate-900 text-base mb-1 truncate max-w-[160px]">
                                        <?= htmlspecialchars($order['customer_name'] ?? 'Unknown') ?>
                                    </div>
                                    <div class="flex items-center gap-1.5 text-xs">
                                        <span
                                            class="font-bold text-slate-400">#<?= str_pad($order['order_id'], 4, '0', STR_PAD_LEFT) ?></span>
                                        <span class="text-slate-300">•</span>
                                        <span
                                            class="font-medium text-slate-500 uppercase tracking-tight"><?= date('M d, h:i A', strtotime($order['created_at'])) ?></span>
                                    </div>
                                </div>
                                <div class="text-right shrink-0">
                                    <div class="font-black text-slate-900 text-base mb-1">
                                        ₱<?= number_format($order['total_price'], 2) ?></div>
                                    <?php
                                    $statusClass = match ($order['payment_status']) {
                                        'Paid' => 'bg-emerald-50 text-emerald-600 border-emerald-100',
                                        'Unpaid' => 'bg-rose-50 text-rose-600 border-rose-100',
                                        default => 'bg-amber-50 text-amber-600 border-amber-100'
                                    };
                                    ?>
                                    <span
                                        class="px-2 py-0.5 rounded-full text-[9px] font-black uppercase tracking-widest border <?= $statusClass ?>">
                                        <?= $order['payment_status'] ?>
                                    </span>
                                </div>
                            </div>
                            <div class="space-y-2">
                                <span class="text-[10px] font-black text-slate-400 uppercase tracking-widest">Files</span>
                                <?php
                                $orderFiles = $filesByOrder[$order['order_id']] ?? [];
                                if (empty($orderFiles)):
                                    ?>
                                    <div class="text-xs text-slate-400 italic">No files attached.</div>
                                <?php else:
                                    foreach ($orderFiles as $file): ?>
                                        <div class="flex items-center justify-between p-3 bg-slate-50 rounded-xl border border-slate-100">
                                            <div class="flex flex-col min-w-0 pr-2">
                                                <div class="text-sm font-bold text-slate-800 truncate"
                                                    title="<?= htmlspecialchars($file['filename']) ?>">
                                                    <?= htmlspecialchars($file['filename']) ?>
                                                </div>
                                                <div
                                                    class="text-[10px] text-slate-500 font-bold uppercase tracking-wide mt-1 flex flex-wrap gap-x-2 gap-y-1">
                                                    <span><?= $file['total_pages'] ?> Pgs</span>
                                                    <span class="text-slate-300">•</span>
                                                    <span class="text-brand"><?= $file['copies'] ?>x</span>
                                                    <span class="text-slate-300">•</span>
                                                    <span><?= $file['paper_size'] ?></span>
                                                    <?php if (!empty($file['excluded_pages'])): ?>
                                                        <span class="text-slate-300">•</span>
                                                        <span class="text-rose-500">Skip: <?= $file['excluded_pages'] ?></span>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                            <!-- Print Settings Button -->
                                            <button
                                                onclick="openPrintModal('<?= htmlspecialchars($file['file_path']) ?>', '<?= $file['copies'] ?>', '<?= htmlspecialchars($file['paper_size']) ?>', '<?= htmlspecialchars($file['excluded_pages']) ?>', <?= (int) $file['total_pages'] ?>)"
                                                title="Open Print Settings"
                                                class="shrink-0 w-8 h-8 flex items-center justify-center bg-slate-900 text-white rounded-lg shadow-sm hover:bg-slate-800 transition-colors">
                                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2"
                                                    stroke="currentColor" class="w-4 h-4">
                                                    <path stroke-linecap="round" stroke-linejoin="round"
                                                        d="M6.72 13.829c-.24.03-.48.062-.72.096m.72-.096a42.415 42.415 0 0 1 10.56 0m-10.56 0L6.34 18m10.94-4.171c.24.03.48.062.72.096m-.72-.096L17.66 18m0 0 .229 2.523a1.125 1.125 0 0 1-1.12 1.227H7.231c-.662 0-1.18-.568-1.12-1.227L6.34 18m11.318 0h1.091A2.25 2.25 0 0 0 21 15.75V9.456c0-1.081-.768-2.015-1.837-2.175a48.055 48.055 0 0 0-1.913-.247M6.34 18H5.25A2.25 2.25 0 0 1 3 15.75V9.456c0-1.081.768-2.015 1.837-2.175a48.041 48.041 0 0 1 1.913-.247m10.5 0a48.536 48.536 0 0 0-10.5 0v-2.94a2.25 2.25 0 0 1 2.25-2.25h6a2.25 2.25 0 0 1 2.25 2.25v2.94Z" />
                                                </svg>
                                            </button>
                                        </div>
                                    <?php endforeach; endif; ?>
                            </div>
                            <div class="grid grid-cols-2 gap-4 pt-2">
                                <div>
                                    <span
                                        class="text-[10px] font-black text-slate-400 uppercase tracking-widest block mb-1">Schedule</span>
                                    <div class="text-xs font-bold text-slate-700">
                                        <?= $order['scheduled_time'] ? date('M d, h:i A', strtotime($order['scheduled_time'])) : 'ASAP' ?>
                                    </div>
                                </div>
                                <div class="text-right">
                                    <span
                                        class="text-[10px] font-black text-slate-400 uppercase tracking-widest block mb-1">Fulfillment</span>
                                    <span
                                        class="inline-flex items-center gap-1 px-2.5 py-1 rounded-md text-[9px] font-black uppercase tracking-widest bg-slate-100 text-slate-600">
                                        <?= $order['fulfillment_type'] ?>
                                    </span>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </main>

        <!-- PRINT DETAILS MODAL -->
        <div id="printModal"
            class="fixed inset-0 z-50 hidden items-center justify-center bg-slate-900/40 backdrop-blur-sm transition-opacity">
            <div class="bg-white rounded-3xl p-6 md:p-8 max-w-sm w-full mx-4 shadow-2xl border border-slate-100">
                <div class="flex items-center justify-between mb-2">
                    <h3 class="text-xl font-black text-slate-900 tracking-tight">Print Settings</h3>
                    <div class="w-10 h-10 bg-brand/10 rounded-full flex items-center justify-center text-brand">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2"
                            stroke="currentColor" class="w-5 h-5">
                            <path stroke-linecap="round" stroke-linejoin="round"
                                d="M6.72 13.829c-.24.03-.48.062-.72.096m.72-.096a42.415 42.415 0 0 1 10.56 0m-10.56 0L6.34 18m10.94-4.171c.24.03.48.062.72.096m-.72-.096L17.66 18m0 0 .229 2.523a1.125 1.125 0 0 1-1.12 1.227H7.231c-.662 0-1.18-.568-1.12-1.227L6.34 18m11.318 0h1.091A2.25 2.25 0 0 0 21 15.75V9.456c0-1.081-.768-2.015-1.837-2.175a48.055 48.055 0 0 0-1.913-.247M6.34 18H5.25A2.25 2.25 0 0 1 3 15.75V9.456c0-1.081.768-2.015 1.837-2.175a48.041 48.041 0 0 1 1.913-.247m10.5 0a48.536 48.536 0 0 0-10.5 0v-2.94a2.25 2.25 0 0 1 2.25-2.25h6a2.25 2.25 0 0 1 2.25 2.25v2.94Z" />
                        </svg>
                    </div>
                </div>
                <p class="text-sm text-slate-500 mb-6 font-medium">Verify and apply these settings in your print dialog.</p>

                <div class="space-y-3 mb-8">
                    <div class="flex justify-between items-center p-3.5 bg-slate-50 border border-slate-100 rounded-xl">
                        <span class="text-[10px] font-black text-slate-400 uppercase tracking-widest">Copies Needed</span>
                        <span class="text-lg font-black text-brand" id="modalCopies"></span>
                    </div>

                    <div class="flex justify-between items-center p-3.5 bg-slate-50 border border-slate-100 rounded-xl">
                        <span class="text-[10px] font-black text-slate-400 uppercase tracking-widest">Paper Size</span>
                        <span class="text-sm font-bold text-slate-800" id="modalPaperSize"></span>
                    </div>

                    <!-- Calculated Pages to Print -->
                    <div id="modalPagesToPrintContainer"
                        class="p-3.5 bg-emerald-50 border border-emerald-100 rounded-xl hidden flex-col gap-2">
                        <div class="flex justify-between items-center">
                            <span class="text-[10px] font-black text-emerald-600 uppercase tracking-widest">Pages to
                                Print</span>
                        </div>
                        <div class="flex gap-2 relative">
                            <input type="text" id="modalPagesToPrint" readonly
                                class="w-full bg-white border border-emerald-200 rounded-lg pl-3 pr-10 py-2.5 text-sm font-black text-emerald-700 outline-none selection:bg-emerald-200">

                            <button onclick="copyPagesToPrint()" id="copyBtn"
                                class="absolute right-1.5 top-1.5 bottom-1.5 px-2.5 bg-emerald-100 hover:bg-emerald-500 text-emerald-600 hover:text-white rounded-md transition-colors flex items-center justify-center"
                                title="Copy to clipboard">
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2.5"
                                    stroke="currentColor" class="w-4 h-4">
                                    <path stroke-linecap="round" stroke-linejoin="round"
                                        d="M15.666 3.888A2.25 2.25 0 0 0 13.5 2.25h-3c-1.03 0-1.9.693-2.166 1.638m7.332 0c.055.194.084.4.084.612v0a.75.75 0 0 1-.75.75H9a.75.75 0 0 1-.75-.75v0c0-.212.03-.418.084-.612m7.332 0c.646.049 1.288.11 1.927.184 1.1.128 1.907 1.077 1.907 2.185V19.5a2.25 2.25 0 0 1-2.25 2.25H6.75A2.25 2.25 0 0 1 4.5 19.5V6.257c0-1.108.806-2.057 1.907-2.185a48.208 48.208 0 0 1 1.927-.184" />
                                </svg>
                            </button>
                        </div>
                    </div>
                </div>

                <div class="flex gap-3">
                    <button onclick="closePrintModal()"
                        class="w-1/4 py-3 bg-slate-100 text-slate-500 rounded-xl font-bold text-sm hover:bg-slate-200 transition">Cancel</button>
                    <!-- Download Button Moved Here -->
                    <a id="modalDownloadBtn" href="#" download
                        class="w-1/4 py-3 flex items-center justify-center bg-brand/10 text-brand border border-brand/20 rounded-xl font-bold text-sm hover:bg-brand hover:text-white transition">DL</a>
                    <button id="proceedPrintBtn"
                        class="flex-1 py-3 bg-slate-900 text-white rounded-xl font-black text-sm hover:bg-slate-800 transition shadow-lg shadow-slate-900/20 active:scale-95 transform">Open
                        Printer</button>
                </div>
            </div>
        </div>

        <script>
            let currentPrintUrl = '';

            // Helper function: Converts "total pages" and "excluded string" into an "included string"
            function calculatePagesToPrint(totalPages, excludedStr) {
                if (!excludedStr || excludedStr.trim() === '') return '';

                let excluded = new Set();
                let parts = excludedStr.split(',');

                parts.forEach(part => {
                    part = part.trim();
                    if (part.includes('-')) {
                        let [start, end] = part.split('-').map(Number);
                        for (let i = start; i <= end; i++) excluded.add(i);
                    } else {
                        excluded.add(Number(part));
                    }
                });

                let included = [];
                for (let i = 1; i <= totalPages; i++) {
                    if (!excluded.has(i)) included.push(i);
                }

                if (included.length === 0) return 'None';

                // Compact into ranges (e.g. 1, 2, 3, 5 -> "1-3, 5")
                let ranges = [];
                let start = included[0];
                let prev = start;

                for (let i = 1; i < included.length; i++) {
                    if (included[i] === prev + 1) {
                        prev = included[i];
                    } else {
                        ranges.push(start === prev ? `${start}` : `${start}-${prev}`);
                        start = included[i];
                        prev = start;
                    }
                }
                ranges.push(start === prev ? `${start}` : `${start}-${prev}`);

                return ranges.join(', ');
            }

            function openPrintModal(fileUrl, copies, paperSize, excludedPages, totalPages) {
                currentPrintUrl = fileUrl;

                document.getElementById('modalCopies').textContent = copies + 'x';
                document.getElementById('modalPaperSize').textContent = paperSize;
                document.getElementById('modalDownloadBtn').href = fileUrl;

                const printPagesContainer = document.getElementById('modalPagesToPrintContainer');
                const printPagesInput = document.getElementById('modalPagesToPrint');

                if (excludedPages && excludedPages.trim() !== '') {
                    printPagesContainer.classList.remove('hidden');
                    printPagesContainer.classList.add('flex');

                    // Calculate the inverse pages
                    printPagesInput.value = calculatePagesToPrint(totalPages, excludedPages);

                    // Reset copy button visual state
                    const copyBtn = document.getElementById('copyBtn');
                    copyBtn.innerHTML = `<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor" class="w-4 h-4"><path stroke-linecap="round" stroke-linejoin="round" d="M15.666 3.888A2.25 2.25 0 0 0 13.5 2.25h-3c-1.03 0-1.9.693-2.166 1.638m7.332 0c.055.194.084.4.084.612v0a.75.75 0 0 1-.75.75H9a.75.75 0 0 1-.75-.75v0c0-.212.03-.418.084-.612m7.332 0c.646.049 1.288.11 1.927.184 1.1.128 1.907 1.077 1.907 2.185V19.5a2.25 2.25 0 0 1-2.25 2.25H6.75A2.25 2.25 0 0 1 4.5 19.5V6.257c0-1.108.806-2.057 1.907-2.185a48.208 48.208 0 0 1 1.927-.184" /></svg>`;
                    copyBtn.classList.replace('bg-brand', 'hover:bg-emerald-500');
                    copyBtn.classList.replace('text-white', 'text-emerald-600');
                } else {
                    printPagesContainer.classList.add('hidden');
                    printPagesContainer.classList.remove('flex');
                }

                const modal = document.getElementById('printModal');
                modal.classList.remove('hidden');
                modal.classList.add('flex');
            }

            function closePrintModal() {
                const modal = document.getElementById('printModal');
                modal.classList.add('hidden');
                modal.classList.remove('flex');
            }

            function copyPagesToPrint() {
                const input = document.getElementById('modalPagesToPrint');
                input.select();
                input.setSelectionRange(0, 99999);

                navigator.clipboard.writeText(input.value).then(() => {
                    const copyBtn = document.getElementById('copyBtn');
                    copyBtn.innerHTML = `<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="3" stroke="currentColor" class="w-4 h-4"><path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" /></svg>`;
                    copyBtn.classList.replace('hover:bg-emerald-500', 'bg-brand');
                    copyBtn.classList.replace('text-emerald-600', 'text-white');
                    copyBtn.classList.replace('hover:text-white', 'text-white');
                });
            }

            document.getElementById('proceedPrintBtn').addEventListener('click', function () {
                const iframe = document.createElement('iframe');
                iframe.style.display = 'none';
                iframe.src = currentPrintUrl;
                document.body.appendChild(iframe);

                iframe.onload = function () {
                    setTimeout(() => {
                        iframe.contentWindow.focus();
                        iframe.contentWindow.print();
                        setTimeout(() => { document.body.removeChild(iframe); }, 60000);
                    }, 500);
                };

                closePrintModal();
            });
        </script>
    <?php endif; ?>

</body>

</html>