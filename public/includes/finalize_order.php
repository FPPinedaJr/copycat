<?php
// finalize_order.php
require_once __DIR__ . '/connect_db.php';

header('Content-Type: application/json');

$json = file_get_contents('php://input');
$data = json_decode($json, true);

if (!$data) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid data provided.']);
    exit;
}

try {
    // Start a database transaction
    $pdo->beginTransaction();

    // 1. Insert the Order FIRST
    $fulfillment = ($data['delivery'] === 'pickup') ? 'Pick Up' : 'Meet Up';
    $scheduledTime = !empty($data['scheduled_time']) ? $data['scheduled_time'] : null;
    $locationPin = !empty($data['notes']) ? $data['notes'] : null; // Using notes as location_pin for now

    $stmtOrder = $pdo->prepare("INSERT INTO orders (user_id, status_id, payment_status, fulfillment_type, location_pin, scheduled_time, created_at) 
                                VALUES (?, 1, 'Unpaid', ?, ?, ?, NOW())");

    // Hardcoding user_id 1 for now
    $stmtOrder->execute([1, $fulfillment, $locationPin, $scheduledTime]);

    // Grab the new Order ID
    $newOrderId = $pdo->lastInsertId();

    // 2. Insert the File, linking it to the Order ID
    $stmtFile = $pdo->prepare("INSERT INTO files (order_id, filename, file_path, paper_size, is_duplex, total_pages, price, created_at) 
                               VALUES (?, ?, ?, ?, ?, ?, ?, NOW())");

    $stmtFile->execute([
        $newOrderId,
        $data['filename'],
        $data['file_path'],
        $data['paper_size'],
        $data['is_duplex'],
        $data['total_pages'],
        $data['price']
    ]);

    // Commit the transaction
    $pdo->commit();

    echo json_encode(['status' => 'success', 'message' => 'Order saved successfully!', 'order_id' => $newOrderId]);

} catch (PDOException $e) {
    // If anything fails, undo the database changes
    $pdo->rollBack();
    echo json_encode(['status' => 'error', 'message' => 'Database error: ' . $e->getMessage()]);
}
?>