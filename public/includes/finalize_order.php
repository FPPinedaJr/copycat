<?php
// finalize_order.php
require_once __DIR__ . '/connect_db.php';

header('Content-Type: application/json');

// Check for the multiple files array 'pdf_files'
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_FILES['pdf_files'])) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid request or missing files.']);
    exit;
}

try {
    $pdo->beginTransaction();

    $customerName = !empty($_POST['customer_name']) ? $_POST['customer_name'] : 'Unknown';
    $fulfillment = ($_POST['delivery'] === 'pickup') ? 'Pick Up' : 'Meet Up';
    $scheduledTime = !empty($_POST['scheduled_time']) ? $_POST['scheduled_time'] : null;
    $locationPin = !empty($_POST['notes']) ? $_POST['notes'] : null;

    // 1. Insert the Order
    $stmtOrder = $pdo->prepare("INSERT INTO orders (user_id, status_id, customer_name, payment_status, fulfillment_type, location_pin, scheduled_time, created_at) 
                                VALUES (?, 1, ?, 'Unpaid', ?, ?, ?, NOW())");
    $stmtOrder->execute([1, $customerName, $fulfillment, $locationPin, $scheduledTime]);
    $newOrderId = $pdo->lastInsertId();

    // 2. Handle the File Uploads & Database Inserts
    $uploadDir = __DIR__ . '/../uploads/';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0777, true);
    }

    // Decode the JSON string containing metadata for all files
    $filesMeta = json_decode($_POST['files_meta'], true);

    $stmtFile = $pdo->prepare("INSERT INTO files (order_id, filename, file_path, paper_size, is_duplex, total_pages, price, copies, excluded_pages, created_at) 
                               VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())");

    foreach ($_FILES['pdf_files']['tmp_name'] as $index => $tmpName) {
        if ($_FILES['pdf_files']['error'][$index] !== UPLOAD_ERR_OK)
            continue;

        $meta = $filesMeta[$index];
        $cleanFileName = preg_replace('/[^a-zA-Z0-9.-]/', '_', $_FILES['pdf_files']['name'][$index]);
        $newFileName = time() . '_' . $index . '_' . $cleanFileName;
        $destination = $uploadDir . $newFileName;

        if (!move_uploaded_file($tmpName, $destination)) {
            throw new Exception("Failed to move uploaded file: " . $cleanFileName);
        }

        $publicFilePath = 'uploads/' . $newFileName;

        $stmtFile->execute([
            $newOrderId,
            $meta['filename'],
            $publicFilePath,
            $meta['paper_size'],
            0, // is_duplex (update if you add this UI later)
            $meta['total_pages'],
            $meta['price'],
            $meta['copies'],
            $meta['excluded_pages']
        ]);
    }

    $pdo->commit();
    echo json_encode(['status' => 'success', 'message' => 'Order saved successfully!', 'order_id' => $newOrderId]);

} catch (Exception $e) {
    $pdo->rollBack();
    echo json_encode(['status' => 'error', 'message' => 'System error: ' . $e->getMessage()]);
}
?>