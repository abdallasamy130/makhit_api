<?php
session_start();
require_once 'includes/db.php';

// Function to send JSON response
function sendJsonResponse($success, $message, $data = []) {
    header('Content-Type: application/json');
    echo json_encode(['success' => $success, 'message' => $message, 'data' => $data]);
    exit;
}

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    sendJsonResponse(false, 'يجب تسجيل الدخول أولاً');
}

$user_id = $_SESSION['user_id'];

try {
    // Get cart items with product details
    $stmt = $pdo->prepare("
        SELECT c.id, c.quantity, p.id as product_id, p.name, p.price, p.image, p.stock
        FROM cart c
        JOIN products p ON c.product_id = p.id
        WHERE c.user_id = ?
        ORDER BY c.created_at DESC
    ");
    $stmt->execute([$user_id]);
    $cart_items = $stmt->fetchAll();

    // Calculate total
    $total = 0;
    foreach ($cart_items as &$item) {
        $item['subtotal'] = $item['price'] * $item['quantity'];
        $total += $item['subtotal'];
    }

    // Get cart count
    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM cart WHERE user_id = ?");
    $stmt->execute([$user_id]);
    $cart_count = $stmt->fetch()['count'];

    sendJsonResponse(true, 'تم جلب عناصر السلة بنجاح', [
        'items' => $cart_items,
        'total' => $total,
        'count' => $cart_count
    ]);
} catch (PDOException $e) {
    sendJsonResponse(false, 'حدث خطأ أثناء جلب عناصر السلة: ' . $e->getMessage());
}
?> 