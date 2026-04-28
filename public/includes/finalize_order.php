<?php
// finalize_order.php
require_once __DIR__ . '/connect_db.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_FILES['pdf_file'])) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid request or missing file.']);
    exit;
}

try {
    $pdo->beginTransaction();

    // Grab the new customer name from FormData
    $customerName = !empty($_POST['customer_name']) ? $_POST['customer_name'] : 'Unknown';

    // 1. Insert the Order FIRST
    $fulfillment = ($_POST['delivery'] === 'pickup') ? 'Pick Up' : 'Meet Up';
    $scheduledTime = !empty($_POST['scheduled_time']) ? $_POST['scheduled_time'] : null;
    $locationPin = !empty($_POST['notes']) ? $_POST['notes'] : null;

    // Updated INSERT statement to include customer_name based on your schema
    $stmtOrder = $pdo->prepare("INSERT INTO orders (user_id, status_id, customer_name, payment_status, fulfillment_type, location_pin, scheduled_time, created_at) 
                                VALUES (?, 1, ?, 'Unpaid', ?, ?, ?, NOW())");

    // Hardcoding user_id 1 for now
    $stmtOrder->execute([1, $customerName, $fulfillment, $locationPin, $scheduledTime]);
    $newOrderId = $pdo->lastInsertId();

    // 2. Handle the File Upload
    $uploadDir = __DIR__ . '/../uploads/';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0777, true);
    }

    $cleanFileName = preg_replace('/[^a-zA-Z0-9.-]/', '_', $_FILES['pdf_file']['name']);
    $newFileName = time() . '_' . $cleanFileName;
    $destination = $uploadDir . $newFileName;

    if (!move_uploaded_file($_FILES['pdf_file']['tmp_name'], $destination)) {
        throw new Exception("Failed to move uploaded file to destination.");
    }

    $publicFilePath = 'uploads/' . $newFileName;

    // 3. Insert the File Record
    $stmtFile = $pdo->prepare("INSERT INTO files (order_id, filename, file_path, paper_size, is_duplex, total_pages, price, created_at) 
                               VALUES (?, ?, ?, ?, ?, ?, ?, NOW())");

    $stmtFile->execute([
        $newOrderId,
        $_POST['filename'],
        $publicFilePath,
        $_POST['paper_size'],
        $_POST['is_duplex'],
        $_POST['total_pages'],
        $_POST['price']
    ]);

    // Commit the transaction
    $pdo->commit();

    echo json_encode(['status' => 'success', 'message' => 'Order saved successfully!', 'order_id' => $newOrderId]);

} catch (Exception $e) {
    // If anything fails (DB or File Upload), undo the DB changes
    $pdo->rollBack();
    echo json_encode(['status' => 'error', 'message' => 'System error: ' . $e->getMessage()]);
}
?>