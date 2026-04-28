<?php
require_once __DIR__ . '/includes/connect_db.php';

// Fetch all orders with their associated files, now including customer_name
try {
    $stmt = $pdo->query("
        SELECT 
            o.id AS order_id,
            o.customer_name,
            o.payment_status,
            o.fulfillment_type,
            o.location_pin AS notes,
            o.scheduled_time,
            o.created_at,
            f.filename,
            f.file_path,
            f.paper_size,
            f.total_pages,
            f.price
        FROM orders o
        LEFT JOIN files f ON o.id = f.order_id
        ORDER BY o.created_at DESC
    ");
    $orders = $stmt->fetchAll();
} catch (PDOException $e) {
    die("Error fetching orders: " . $e->getMessage());
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
</head>

<body class="bg-slate-50 min-h-screen font-sans text-slate-800">

    <nav class="bg-white border-b border-slate-200 sticky top-0 z-30 shadow-sm">
        <div class="container mx-auto px-6 h-20 flex items-center justify-between">
            <div class="flex items-center gap-3">
                <div
                    class="w-10 h-10 bg-brand rounded-xl flex items-center justify-center text-white text-2xl font-black shadow-md shadow-orange-500/20">
                    C
                </div>
                <h1 class="text-xl font-black text-slate-900 tracking-tight">Admin Console</h1>
            </div>
            <div class="flex items-center gap-4">
                <span class="text-sm font-bold text-slate-400">Puerto Princesa Branch</span>
                <div
                    class="w-8 h-8 rounded-full bg-slate-200 border-2 border-white shadow-sm flex items-center justify-center overflow-hidden">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5"
                        stroke="currentColor" class="w-5 h-5 text-slate-400">
                        <path stroke-linecap="round" stroke-linejoin="round"
                            d="M15.75 6a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0zM4.501 20.118a7.5 7.5 0 0114.998 0A17.933 17.933 0 0112 21.75c-2.676 0-5.216-.584-7.499-1.632z" />
                    </svg>
                </div>
            </div>
        </div>
    </nav>

    <main class="container mx-auto px-6 py-10">
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-10">
            <div class="bg-white p-6 rounded-3xl shadow-sm border border-slate-100">
                <span class="text-slate-400 text-xs font-black uppercase tracking-widest block mb-2">Total Orders</span>
                <div class="flex items-baseline gap-2">
                    <span class="text-3xl font-black text-slate-900"><?= count($orders) ?></span>
                    <span class="text-emerald-500 text-xs font-bold">+12% this week</span>
                </div>
            </div>
            <div class="bg-white p-6 rounded-3xl shadow-sm border border-slate-100">
                <span class="text-slate-400 text-xs font-black uppercase tracking-widest block mb-2">Pending
                    Prints</span>
                <div class="flex items-baseline gap-2">
                    <span
                        class="text-3xl font-black text-slate-900"><?= count(array_filter($orders, fn($o) => $o['payment_status'] !== 'Paid')) ?></span>
                </div>
            </div>
            <div class="bg-white p-6 rounded-3xl shadow-sm border border-slate-100">
                <span class="text-slate-400 text-xs font-black uppercase tracking-widest block mb-2">Today's
                    Revenue</span>
                <div class="flex items-baseline gap-2">
                    <span class="text-slate-400 font-bold text-lg mr-1">₱</span>
                    <span
                        class="text-3xl font-black text-slate-900"><?= number_format(array_sum(array_column($orders, 'price')), 2) ?></span>
                </div>
            </div>
        </div>

        <div class="bg-white rounded-[2.5rem] shadow-xl shadow-slate-200/50 overflow-hidden border border-slate-100">
            <div class="p-8 border-b border-slate-50 flex justify-between items-center bg-slate-50/50">
                <h2 class="text-xl font-black text-slate-900 tracking-tight">Recent Orders</h2>
                <div class="flex gap-2">
                    <button
                        class="px-4 py-2 bg-white border border-slate-200 rounded-xl text-xs font-bold text-slate-600 hover:bg-slate-50 transition shadow-sm flex items-center gap-2">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2"
                            stroke="currentColor" class="w-4 h-4">
                            <path stroke-linecap="round" stroke-linejoin="round"
                                d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5M16.5 12L12 16.5m0 0L7.5 12m4.5 4.5V3" />
                        </svg>
                        Export CSV
                    </button>
                    <button onclick="location.reload()"
                        class="px-4 py-2 bg-slate-900 text-white rounded-xl text-xs font-bold hover:bg-slate-800 transition shadow-md flex items-center gap-2">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2"
                            stroke="currentColor" class="w-4 h-4">
                            <path stroke-linecap="round" stroke-linejoin="round"
                                d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0l3.181 3.183a8.25 8.25 0 0013.803-3.7M4.031 9.865a8.25 8.25 0 0113.803-3.7l3.181 3.182m0-4.991v4.99" />
                        </svg>
                        Refresh
                    </button>
                </div>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse">
                    <thead>
                        <tr class="bg-slate-50/50">
                            <th
                                class="px-8 py-5 text-[10px] font-black text-slate-400 uppercase tracking-widest border-b border-slate-100">
                                Order & Customer</th>
                            <th
                                class="px-8 py-5 text-[10px] font-black text-slate-400 uppercase tracking-widest border-b border-slate-100">
                                Document</th>
                            <th
                                class="px-8 py-5 text-[10px] font-black text-slate-400 uppercase tracking-widest border-b border-slate-100">
                                Schedule & Notes</th>
                            <th
                                class="px-8 py-5 text-[10px] font-black text-slate-400 uppercase tracking-widest border-b border-slate-100">
                                Fulfillment</th>
                            <th
                                class="px-8 py-5 text-[10px] font-black text-slate-400 uppercase tracking-widest border-b border-slate-100">
                                Status</th>
                            <th
                                class="px-8 py-5 text-[10px] font-black text-slate-400 uppercase tracking-widest border-b border-slate-100 text-right">
                                Total Price</th>
                            <th
                                class="px-8 py-5 text-[10px] font-black text-slate-400 uppercase tracking-widest border-b border-slate-100 text-center">
                                Action</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-50">
                        <?php foreach ($orders as $order): ?>
                            <tr class="hover:bg-slate-50/80 transition group">

                                <td class="px-8 py-6">
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

                                <td class="px-8 py-6">
                                    <div class="flex items-center gap-4">
                                        <div
                                            class="w-10 h-10 bg-orange-50 rounded-xl flex items-center justify-center text-brand shadow-sm border border-orange-100/50">
                                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
                                                stroke-width="1.5" stroke="currentColor" class="w-5 h-5">
                                                <path stroke-linecap="round" stroke-linejoin="round"
                                                    d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m5.231 13.481L15 17.25m-4.5-15H5.625c-.621 0-1.125.504-1.125 1.125v16.5c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9zm3.75 11.625a2.625 2.625 0 11-5.25 0 2.625 2.625 0 015.25 0z" />
                                            </svg>
                                        </div>
                                        <div>
                                            <div class="text-sm font-bold text-slate-900 truncate max-w-[150px]"
                                                title="<?= htmlspecialchars($order['filename']) ?>">
                                                <?= htmlspecialchars($order['filename']) ?>
                                            </div>
                                            <div
                                                class="text-[10px] text-slate-500 font-bold uppercase mt-0.5 tracking-wide">
                                                <?= $order['total_pages'] ?> Pages <span
                                                    class="text-slate-300 mx-1">•</span> <?= $order['paper_size'] ?>
                                            </div>
                                        </div>
                                    </div>
                                </td>

                                <td class="px-8 py-6">
                                    <div class="text-sm font-bold text-slate-700">
                                        <?= $order['scheduled_time'] ? date('M d, h:i A', strtotime($order['scheduled_time'])) : 'ASAP' ?>
                                    </div>
                                    <div class="text-[11px] text-slate-500 italic truncate max-w-[180px] mt-0.5"
                                        title="<?= htmlspecialchars($order['notes']) ?>">
                                        <?= htmlspecialchars($order['notes']) ?: 'No special instructions' ?>
                                    </div>
                                </td>

                                <td class="px-8 py-6">
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

                                <td class="px-8 py-6">
                                    <?php
                                    $statusClass = match ($order['payment_status']) {
                                        'Paid' => 'bg-emerald-50 text-emerald-600 border-emerald-100',
                                        'Unpaid' => 'bg-rose-50 text-rose-600 border-rose-100',
                                        default => 'bg-amber-50 text-amber-600 border-amber-100'
                                    };
                                    ?>
                                    <span
                                        class="px-3 py-1.5 rounded-full text-[10px] font-black uppercase tracking-widest border <?= $statusClass ?>">
                                        <?= $order['payment_status'] ?>
                                    </span>
                                </td>

                                <td class="px-8 py-6 text-right">
                                    <div class="text-sm font-black text-slate-900">₱<?= number_format($order['price'], 2) ?>
                                    </div>
                                </td>

                                <td class="px-8 py-6 text-center">
                                    <a href="<?= htmlspecialchars($order['file_path']) ?>" download
                                        class="inline-flex items-center justify-center w-10 h-10 bg-white border border-slate-200 rounded-xl text-slate-500 hover:bg-brand hover:text-white hover:border-brand transition shadow-sm group-hover:shadow-md">
                                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
                                            stroke-width="2" stroke="currentColor" class="w-4 h-4">
                                            <path stroke-linecap="round" stroke-linejoin="round"
                                                d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5M16.5 12L12 16.5m0 0L7.5 12m4.5 4.5V3" />
                                        </svg>
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>

                        <?php if (empty($orders)): ?>
                            <tr>
                                <td colspan="7" class="px-8 py-24 text-center">
                                    <div class="flex justify-center mb-4">
                                        <div
                                            class="w-20 h-20 bg-slate-100 rounded-full flex items-center justify-center text-slate-300">
                                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
                                                stroke-width="1.5" stroke="currentColor" class="w-10 h-10">
                                                <path stroke-linecap="round" stroke-linejoin="round"
                                                    d="M20.25 7.5l-.625 10.632a2.25 2.25 0 01-2.247 2.118H6.622a2.25 2.25 0 01-2.247-2.118L3.75 7.5M10 11.25h4M3.375 7.5h17.25c.621 0 1.125-.504 1.125-1.125v-1.5c0-.621-.504-1.125-1.125-1.125H3.375c-.621 0-1.125.504-1.125 1.125v1.5c0 .621.504 1.125 1.125 1.125z" />
                                            </svg>
                                        </div>
                                    </div>
                                    <h3 class="text-lg font-black text-slate-900 mb-1">No orders yet</h3>
                                    <p class="text-sm text-slate-500 font-medium">When customers place orders, they will
                                        appear here.</p>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </main>

</body>

</html>