<?php
session_start();
require_once 'includes/db.php';

// التحقق من تسجيل الدخول
if (!isset($_SESSION['user_id'])) {
    echo json_encode([
        'status' => 'error',
        'message' => 'يجب تسجيل الدخول أولاً',
        'redirect' => 'login.php'
    ]);
    exit();
}

// التحقق من وجود البيانات المطلوبة
if (!isset($_POST['product_id']) || !isset($_POST['quantity'])) {
    echo json_encode([
        'status' => 'error',
        'message' => 'بيانات غير مكتملة'
    ]);
    exit();
}

$product_id = (int)$_POST['product_id'];
$quantity = (int)$_POST['quantity'];
$size = isset($_POST['size']) ? trim($_POST['size']) : null;
$user_id = $_SESSION['user_id'];

// التحقق من وجود المنتج
$stmt = $pdo->prepare("SELECT * FROM products WHERE id = ? AND stock > 0");
$stmt->execute([$product_id]);
$product = $stmt->fetch();

if (!$product) {
    echo json_encode([
        'status' => 'error',
        'message' => 'المنتج غير متوفر'
    ]);
    exit();
}

// التحقق من صحة المقاس إذا كان المنتج يحتوي على مقاسات
if ($product['sizes']) {
    $sizes = json_decode($product['sizes'], true);
    if ($sizes && !in_array($size, $sizes)) {
        echo json_encode([
            'status' => 'error',
            'message' => 'المقاس المحدد غير متوفر'
        ]);
        exit();
    }
}

// التحقق من كمية المخزون
if ($quantity > $product['stock']) {
    echo json_encode([
        'status' => 'error',
        'message' => 'الكمية المطلوبة غير متوفرة في المخزون'
    ]);
    exit();
}

// التحقق من وجود المنتج في السلة
$stmt = $pdo->prepare("SELECT * FROM cart WHERE user_id = ? AND product_id = ? AND size = ?");
$stmt->execute([$user_id, $product_id, $size]);
$existing_item = $stmt->fetch();

if ($existing_item) {
    // تحديث الكمية إذا كان المنتج موجوداً
    $new_quantity = $existing_item['quantity'] + $quantity;
    if ($new_quantity > $product['stock']) {
        echo json_encode([
            'status' => 'error',
            'message' => 'الكمية الإجمالية تتجاوز المخزون المتاح'
        ]);
        exit();
    }

    $stmt = $pdo->prepare("UPDATE cart SET quantity = ? WHERE user_id = ? AND product_id = ? AND size = ?");
    $stmt->execute([$new_quantity, $user_id, $product_id, $size]);
} else {
    // إضافة منتج جديد إلى السلة
    $stmt = $pdo->prepare("INSERT INTO cart (user_id, product_id, quantity, size) VALUES (?, ?, ?, ?)");
    $stmt->execute([$user_id, $product_id, $quantity, $size]);
}

// حساب عدد العناصر في السلة
$stmt = $pdo->prepare("SELECT SUM(quantity) as total_items FROM cart WHERE user_id = ?");
$stmt->execute([$user_id]);
$cart_count = $stmt->fetch()['total_items'] ?? 0;

// إرجاع النتيجة
echo json_encode([
    'status' => 'success',
    'message' => 'تمت إضافة المنتج "' . $product['name'] . '" إلى السلة بنجاح',
    'cart_count' => $cart_count,
    'product_name' => $product['name'],
    'buttons' => [
        'cart' => [
            'text' => 'عرض السلة',
            'url' => 'cart.php'
        ],
        'continue' => [
            'text' => 'استكمال التسوق',
            'url' => 'products.php'
        ]
    ]
]);
exit(); 