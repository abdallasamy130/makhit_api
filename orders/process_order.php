<?php
session_start();
require_once 'config/database.php';
require_once 'includes/functions.php';

// تفعيل عرض الأخطاء للتطوير
ini_set('display_errors', 1);
error_reporting(E_ALL);

// التحقق من تسجيل الدخول
if (!isset($_SESSION['user_id'])) {
    $_SESSION['error'] = 'يجب تسجيل الدخول أولاً';
    header('Location: login.php');
    exit();
}

// التحقق من وجود بيانات الطلب
if (!isset($_SESSION['order_data'])) {
    $_SESSION['error'] = 'لا توجد بيانات طلب';
    header('Location: cart.php');
    exit();
}

try {
    // التحقق من اتصال قاعدة البيانات
    if (!$pdo) {
        throw new Exception('فشل الاتصال بقاعدة البيانات');
    }

    // جلب محتويات السلة
    $stmt = $pdo->prepare("
        SELECT c.*, p.name, p.price, p.stock, p.image, p.sizes
        FROM cart c 
        JOIN products p ON c.product_id = p.id 
        WHERE c.user_id = ?
    ");
    
    if (!$stmt->execute([$_SESSION['user_id']])) {
        throw new Exception('فشل في جلب محتويات السلة: ' . implode(', ', $stmt->errorInfo()));
    }
    
    $cart_items = $stmt->fetchAll();

    if (empty($cart_items)) {
        $_SESSION['error'] = 'السلة فارغة';
        header('Location: cart.php');
        exit();
    }

    // التحقق من توفر المخزون
    foreach ($cart_items as $item) {
        if ($item['quantity'] > $item['stock']) {
            $_SESSION['error'] = "المنتج {$item['name']} غير متوفر بالكمية المطلوبة";
            header('Location: cart.php');
            exit();
        }
    }

    // بدء المعاملة
    $pdo->beginTransaction();

    // جلب آخر معرف طلب
    $stmt = $pdo->query("SELECT MAX(id) as last_id FROM orders");
    $last_order = $stmt->fetch();
    $new_order_id = ($last_order['last_id'] ?? 0) + 1;

    // إنشاء رقم الطلب
    $order_number = 'ORD-' . date('Ymd') . '-' . $new_order_id;

    // إنشاء الطلب
    $stmt = $pdo->prepare("
        INSERT INTO orders (
            user_id, 
            order_number,
            shipping_full_name, 
            shipping_phone, 
            shipping_address, 
            shipping_city, 
            shipping_postal_code,
            notes,
            payment_method,
            delivery_type,
            packaging_type,
            delivery_fee,
            packaging_fee,
            subtotal,
            total_amount,
            status,
            created_at
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending', NOW())
    ");

    if (!$stmt->execute([
        $_SESSION['user_id'],
        $order_number,
        $_SESSION['order_data']['shipping_full_name'],
        $_SESSION['order_data']['shipping_phone'],
        $_SESSION['order_data']['shipping_address'],
        $_SESSION['order_data']['shipping_city'],
        $_SESSION['order_data']['shipping_postal_code'] ?? '',
        $_SESSION['order_data']['notes'] ?? '',
        $_SESSION['order_data']['payment_method'],
        $_SESSION['order_data']['delivery_type'],
        $_SESSION['order_data']['packaging_type'],
        $_SESSION['order_data']['delivery_fee'],
        $_SESSION['order_data']['packaging_fee'],
        $_SESSION['order_data']['subtotal'],
        $_SESSION['order_data']['total']
    ])) {
        throw new Exception('فشل في إنشاء الطلب: ' . implode(', ', $stmt->errorInfo()));
    }

    $order_id = $pdo->lastInsertId();

    // إضافة منتجات الطلب
    $stmt = $pdo->prepare("
        INSERT INTO order_items (
            order_id, 
            product_id, 
            quantity, 
            price,
            size,
            created_at
        ) VALUES (?, ?, ?, ?, ?, NOW())
    ");

    foreach ($cart_items as $item) {
        if (!$stmt->execute([
            $order_id,
            $item['product_id'],
            $item['quantity'],
            $item['price'],
            $item['size'] ?? null
        ])) {
            throw new Exception('فشل في إضافة منتج للطلب: ' . implode(', ', $stmt->errorInfo()));
        }

        // تحديث المخزون
        $update_stock = $pdo->prepare("
            UPDATE products 
            SET stock = stock - ? 
            WHERE id = ?
        ");
        
        if (!$update_stock->execute([$item['quantity'], $item['product_id']])) {
            throw new Exception('فشل في تحديث المخزون: ' . implode(', ', $update_stock->errorInfo()));
        }
    }

    // حذف محتويات السلة
    $stmt = $pdo->prepare("DELETE FROM cart WHERE user_id = ?");
    if (!$stmt->execute([$_SESSION['user_id']])) {
        throw new Exception('فشل في حذف محتويات السلة: ' . implode(', ', $stmt->errorInfo()));
    }

    // تأكيد المعاملة
    $pdo->commit();

    // مسح بيانات الطلب من الجلسة
    unset($_SESSION['order_data']);

    // إعادة التوجيه إلى صفحة تأكيد الطلب
    header("Location: order_confirmation.php?order_id=" . $order_id);
    exit();

} catch (Exception $e) {
    // التراجع عن المعاملة في حالة حدوث خطأ
    if ($pdo && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    
    // تسجيل الخطأ
    error_log("Error processing order: " . $e->getMessage());
    error_log("Stack trace: " . $e->getTraceAsString());
    
    // حفظ تفاصيل الخطأ في الجلسة
    $_SESSION['error'] = 'حدث خطأ أثناء معالجة الطلب: ' . $e->getMessage();
    $_SESSION['error_details'] = [
        'message' => $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine()
    ];
    
    header('Location: cart.php');
    exit();
}
?> 