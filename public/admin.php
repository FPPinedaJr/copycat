<?php
require_once __DIR__ . '/includes/connect_db.php';

// Fetch all orders with their associated files
try {
    $stmt = $pdo->query("
        SELECT 
            o.id AS order_id,
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
</head>
<body class="bg-slate-50 min-h-screen font-sans text-slate-800">

    <!-- Sidebar/Header -->
    <nav class="bg-white border-b border-slate-200 sticky top-0 z-30 shadow-sm">
        <div class="container mx-auto px-6 h-20 flex items-center justify-between">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 bg-brand rounded-xl flex items-center justify-center text-white text-2xl font-black">
                    C
                </div>
                <h1 class="text-xl font-black text-slate-900 tracking-tight">Admin Console</h1>
            </div>
            <div class="flex items-center gap-4">
                <span class="text-sm font-bold text-slate-400">Puerto Princesa Branch</span>
                <div class="w-8 h-8 rounded-full bg-slate-200 border-2 border-white shadow-sm"></div>
            </div>
        </div>
    </nav>

    <main class="container mx-auto px-6 py-10">
        <!-- Stats Row -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-10">
            <div class="bg-white p-6 rounded-3xl shadow-sm border border-slate-100">
                <span class="text-slate-400 text-xs font-black uppercase tracking-widest block mb-2">Total Orders</span>
                <div class="flex items-baseline gap-2">
                    <span class="text-3xl font-black text-slate-900"><?= count($orders) ?></span>
                    <span class="text-emerald-500 text-xs font-bold">+12% this week</span>
                </div>
            </div>
            <div class="bg-white p-6 rounded-3xl shadow-sm border border-slate-100">
                <span class="text-slate-400 text-xs font-black uppercase tracking-widest block mb-2">Pending Prints</span>
                <div class="flex items-baseline gap-2">
                    <span class="text-3xl font-black text-slate-900"><?= count(array_filter($orders, fn($o) => $o['payment_status'] !== 'Paid')) ?></span>
                </div>
            </div>
            <div class="bg-white p-6 rounded-3xl shadow-sm border border-slate-100">
                <span class="text-slate-400 text-xs font-black uppercase tracking-widest block mb-2">Today's Revenue</span>
                <div class="flex items-baseline gap-2">
                    <span class="text-3xl font-black text-slate-900"><?= number_format(array_sum(array_column($orders, 'price')), 2) ?></span>
                </div>
            </div>
        </div>

        <!-- Orders Table -->
        <div class="bg-white rounded-[2.5rem] shadow-xl shadow-slate-200/50 overflow-hidden border border-slate-100">
            <div class="p-8 border-b border-slate-50 flex justify-between items-center bg-slate-50/50">
                <h2 class="text-xl font-black text-slate-900 tracking-tight">Recent Orders</h2>
                <div class="flex gap-2">
                    <button class="px-4 py-2 bg-white border border-slate-200 rounded-xl text-xs font-bold text-slate-600 hover:bg-slate-50 transition">Export CSV</button>
                    <button class="px-4 py-2 bg-slate-900 text-white rounded-xl text-xs font-bold hover:bg-slate-800 transition">Refresh</button>
                </div>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse">
                    <thead>
                        <tr class="bg-slate-50/50">
                            <th class="px-8 py-5 text-[10px] font-black text-slate-400 uppercase tracking-widest border-b border-slate-100">Order ID</th>
                            <th class="px-8 py-5 text-[10px] font-black text-slate-400 uppercase tracking-widest border-b border-slate-100">Document</th>
                            <th class="px-8 py-5 text-[10px] font-black text-slate-400 uppercase tracking-widest border-b border-slate-100">Customer Details</th>
                            <th class="px-8 py-5 text-[10px] font-black text-slate-400 uppercase tracking-widest border-b border-slate-100">Fulfillment</th>
                            <th class="px-8 py-5 text-[10px] font-black text-slate-400 uppercase tracking-widest border-b border-slate-100">Status</th>
                            <th class="px-8 py-5 text-[10px] font-black text-slate-400 uppercase tracking-widest border-b border-slate-100">Total Price</th>
                            <th class="px-8 py-5 text-[10px] font-black text-slate-400 uppercase tracking-widest border-b border-slate-100">Action</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-50">
                        <?php foreach ($orders as $order): ?>
                        <tr class="hover:bg-slate-50/50 transition group">
                            <td class="px-8 py-6">
                                <span class="font-black text-slate-400">#<?= str_pad($order['order_id'], 4, '0', STR_PAD_LEFT) ?></span>
                                <div class="text-[10px] text-slate-400 mt-1"><?= date('M d, h:i A', strtotime($order['created_at'])) ?></div>
                            </td>
                            <td class="px-8 py-6">
                                <div class="flex items-center gap-3">
                                    <div class="w-10 h-10 bg-orange-50 rounded-lg flex items-center justify-center text-brand text-lg">
                                        📄
                                    </div>
                                    <div>
                                        <div class="text-sm font-bold text-slate-900 truncate max-w-[150px]" title="<?= htmlspecialchars($order['filename']) ?>">
                                            <?= htmlspecialchars($order['filename']) ?>
                                        </div>
                                        <div class="text-[10px] text-slate-400 font-bold uppercase"><?= $order['total_pages'] ?> Pages • <?= $order['paper_size'] ?></div>
                                    </div>
                                </div>
                            </td>
                            <td class="px-8 py-6">
                                <div class="text-sm font-bold text-slate-700">Scheduled:</div>
                                <div class="text-xs text-slate-500 mb-1"><?= $order['scheduled_time'] ? date('M d, h:i A', strtotime($order['scheduled_time'])) : 'ASAP' ?></div>
                                <div class="text-[10px] text-slate-400 italic truncate max-w-[150px]"><?= $order['notes'] ?: 'No instructions' ?></div>
                            </td>
                            <td class="px-8 py-6">
                                <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-[10px] font-black uppercase tracking-tight <?= $order['fulfillment_type'] === 'Pick Up' ? 'bg-blue-50 text-blue-600' : 'bg-purple-50 text-purple-600' ?>">
                                    <?= $order['fulfillment_type'] === 'Pick Up' ? '🏪' : '📍' ?> <?= $order['fulfillment_type'] ?>
                                </span>
                            </td>
                            <td class="px-8 py-6">
                                <?php
                                $statusClass = match($order['payment_status']) {
                                    'Paid' => 'bg-emerald-50 text-emerald-600',
                                    'Unpaid' => 'bg-rose-50 text-rose-600',
                                    default => 'bg-amber-50 text-amber-600'
                                };
                                ?>
                                <span class="px-3 py-1 rounded-full text-[10px] font-black uppercase tracking-tight <?= $statusClass ?>">
                                    <?= $order['payment_status'] ?>
                                </span>
                            </td>
                            <td class="px-8 py-6 text-right">
                                <div class="text-lg font-black text-slate-900"><?= number_format($order['price'], 2) ?></div>
                            </td>
                            <td class="px-8 py-6">
                                <a href="<?= htmlspecialchars($order['file_path']) ?>" download class="inline-flex items-center justify-center w-10 h-10 bg-slate-100 rounded-xl text-slate-600 hover:bg-brand hover:text-white transition shadow-sm">
                                    ⬇️
                                </a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if (empty($orders)): ?>
                        <tr>
                            <td colspan="7" class="px-8 py-20 text-center">
                                <div class="text-4xl mb-4">📭</div>
                                <div class="text-slate-400 font-bold">No orders found yet.</div>
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
