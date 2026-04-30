<?php
session_start();
require_once __DIR__ . '/includes/connect_db.php';

// --- CONFIGURATION ---
$adminPassword = 'admin123'; // Change this to your preferred password

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

<body class="bg-slate-50 min-h-screen font-sans text-slate-800 flex flex-col">

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

                <!-- DESKTOP VIEW (Hidden on Mobile) -->
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
                                        <!-- Desktop: Order Info -->
                                        <td class="px-8 py-6 align-top">
                                            <div class="font-black text-slate-900 text-sm mb-1">
                                                <?= htmlspecialchars($order['customer_name'] ?? 'Unknown') ?></div>
                                            <div class="flex items-center gap-2">
                                                <span
                                                    class="font-bold text-slate-400 text-xs">#<?= str_pad($order['order_id'], 4, '0', STR_PAD_LEFT) ?></span>
                                                <span class="text-slate-300">•</span>
                                                <span
                                                    class="text-[10px] font-medium text-slate-500 uppercase tracking-wide"><?= date('M d, h:i A', strtotime($order['created_at'])) ?></span>
                                            </div>
                                        </td>

                                        <!-- Desktop: Files List -->
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
                                                            <a href="<?= htmlspecialchars($file['file_path']) ?>" download
                                                                title="Download PDF"
                                                                class="shrink-0 w-8 h-8 flex items-center justify-center bg-slate-50 text-slate-400 rounded-lg hover:bg-slate-900 hover:text-white transition-colors">
                                                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
                                                                    stroke-width="2.5" stroke="currentColor" class="w-4 h-4">
                                                                    <path stroke-linecap="round" stroke-linejoin="round"
                                                                        d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5M16.5 12L12 16.5m0 0L7.5 12m4.5 4.5V3" />
                                                                </svg>
                                                            </a>
                                                        </div>
                                                    <?php endforeach; endif; ?>
                                            </div>
                                        </td>

                                        <!-- Desktop: Schedule & Details -->
                                        <td class="px-8 py-6 align-top">
                                            <div class="text-sm font-bold text-slate-700 mb-1">
                                                <?= $order['scheduled_time'] ? date('M d, h:i A', strtotime($order['scheduled_time'])) : 'ASAP' ?>
                                            </div>
                                            <div class="text-xs text-slate-500 italic max-w-[200px] leading-relaxed">
                                                <?= htmlspecialchars($order['notes']) ?: 'No special instructions' ?>
                                            </div>
                                        </td>

                                        <!-- Desktop: Fulfillment -->
                                        <td class="px-8 py-6 align-top">
                                            <?php if ($order['fulfillment_type'] === 'Pick Up'): ?>
                                                <span
                                                    class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full text-[10px] font-black uppercase tracking-widest bg-blue-50 text-blue-600 border border-blue-100/50">
                                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
                                                        stroke-width="2" stroke="currentColor" class="w-3.5 h-3.5">
                                                        <path stroke-linecap="round" stroke-linejoin="round"
                                                            d="M13.5 21v-7.5a.75.75 0 01.75-.75h3a.75.75 0 01.75.75V21m-4.5 0H2.36m11.14 0H18m0 0h3.64m-1.39 0V9.809c0-.626-.31-1.227-.836-1.594l-2.18-1.516M18 21v-5.25a.75.75 0 00-.75-.75h-2.5a.75.75 0 00-.75.75V21m-4.5 0h2.25m0 0V12a2.25 2.25 0 00-2.25-2.25H5.25A2.25 2.25 0 003 12v9m15 0h2.25M3 21h2.25m12-16.5l-3-2.25a.75.75 0 00-.9 0l-3 2.25m6 0v2.25m-6-2.25v2.25" />
                                                    </svg>
                                                    Pick-Up
                                                </span>
                                            <?php else: ?>
                                                <span
                                                    class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full text-[10px] font-black uppercase tracking-widest bg-purple-50 text-purple-600 border border-purple-100/50">
                                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
                                                        stroke-width="2" stroke="currentColor" class="w-3.5 h-3.5">
                                                        <path stroke-linecap="round" stroke-linejoin="round"
                                                            d="M15 10.5a3 3 0 11-6 0 3 3 0 016 0z" />
                                                        <path stroke-linecap="round" stroke-linejoin="round"
                                                            d="M19.5 10.5c0 7.142-7.5 11.25-7.5 11.25S4.5 17.642 4.5 10.5a7.5 7.5 0 1115 0z" />
                                                    </svg>
                                                    Meet-Up
                                                </span>
                                            <?php endif; ?>
                                        </td>

                                        <!-- Desktop: Status & Price -->
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

                <!-- MOBILE VIEW (Hidden on Desktop) -->
                <div class="block lg:hidden space-y-4">
                    <?php foreach ($orders as $order): ?>
                        <div
                            class="bg-white p-5 rounded-2xl shadow-sm border border-slate-200 flex flex-col gap-4 relative overflow-hidden">
                            <!-- Mobile: Header (Customer, ID, Date, Price, Status) -->
                            <div class="flex justify-between items-start border-b border-slate-100 pb-4">
                                <div>
                                    <div class="font-black text-slate-900 text-base mb-1 truncate max-w-[160px]">
                                        <?= htmlspecialchars($order['customer_name'] ?? 'Unknown') ?></div>
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

                            <!-- Mobile: Files List -->
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
                                            <a href="<?= htmlspecialchars($file['file_path']) ?>" download title="Download PDF"
                                                class="shrink-0 w-8 h-8 flex items-center justify-center bg-white border border-slate-200 text-slate-600 rounded-lg shadow-sm hover:bg-slate-900 hover:text-white hover:border-slate-900 transition-colors">
                                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2.5"
                                                    stroke="currentColor" class="w-4 h-4">
                                                    <path stroke-linecap="round" stroke-linejoin="round"
                                                        d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5M16.5 12L12 16.5m0 0L7.5 12m4.5 4.5V3" />
                                                </svg>
                                            </a>
                                        </div>
                                    <?php endforeach; endif; ?>
                            </div>

                            <!-- Mobile: Schedule & Fulfillment -->
                            <div class="grid grid-cols-2 gap-4 pt-2">
                                <div>
                                    <span
                                        class="text-[10px] font-black text-slate-400 uppercase tracking-widest block mb-1">Schedule</span>
                                    <div class="text-xs font-bold text-slate-700">
                                        <?= $order['scheduled_time'] ? date('M d, h:i A', strtotime($order['scheduled_time'])) : 'ASAP' ?>
                                    </div>
                                    <?php if ($order['notes']): ?>
                                        <div class="text-[10px] text-slate-500 italic truncate mt-0.5"
                                            title="<?= htmlspecialchars($order['notes']) ?>">
                                            <?= htmlspecialchars($order['notes']) ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                <div class="text-right">
                                    <span
                                        class="text-[10px] font-black text-slate-400 uppercase tracking-widest block mb-1">Fulfillment</span>
                                    <?php if ($order['fulfillment_type'] === 'Pick Up'): ?>
                                        <span
                                            class="inline-flex items-center gap-1 px-2.5 py-1 rounded-md text-[9px] font-black uppercase tracking-widest bg-blue-50 text-blue-600 border border-blue-100/50">
                                            Pick-Up
                                        </span>
                                    <?php else: ?>
                                        <span
                                            class="inline-flex items-center gap-1 px-2.5 py-1 rounded-md text-[9px] font-black uppercase tracking-widest bg-purple-50 text-purple-600 border border-purple-100/50">
                                            Meet-Up
                                        </span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

            <?php endif; ?>
        </main>
    <?php endif; ?>

</body>

</html>