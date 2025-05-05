<?php
session_start();
require_once 'includes/db.php';
require_once 'includes/settings.php';

// التحقق من وجود معرف الطلب
if (!isset($_GET['id'])) {
    header('Location: index.php');
    exit();
}

$order_id = $_GET['id'];
$error = '';
$success = '';

// جلب إعدادات الموقع
try {
    $stmt = $pdo->prepare("SELECT * FROM settings WHERE id = 1");
    $stmt->execute();
    $settings = $stmt->fetch();
    
    // التحقق من وجود الإعدادات المطلوبة
    if (!$settings) {
        $settings = [
            'currency' => 'جنيه',
            'currency_symbol' => 'ج.م'
        ];
    } else {
        // التأكد من وجود المفاتيح المطلوبة
        if (!isset($settings['currency'])) {
            $settings['currency'] = 'جنيه';
        }
        if (!isset($settings['currency_symbol'])) {
            $settings['currency_symbol'] = 'ج.م';
        }
    }
} catch (Exception $e) {
    $settings = [
        'currency' => 'جنيه',
        'currency_symbol' => 'ج.م'
    ];
}

// جلب تفاصيل الطلب
try {
$stmt = $pdo->prepare("
        SELECT o.*, 
               u.username as customer_name,
               u.phone as customer_phone,
               u.city as customer_city,
               u.address as customer_address
    FROM orders o
        LEFT JOIN users u ON o.user_id = u.id
        WHERE o.id = ?
");
    $stmt->execute([$order_id]);
$order = $stmt->fetch();

if (!$order) {
        header('Location: index.php');
    exit();
}

    // جلب منتجات الطلب
$stmt = $pdo->prepare("
    SELECT oi.*, p.name, p.image
    FROM order_items oi
        LEFT JOIN products p ON oi.product_id = p.id
    WHERE oi.order_id = ?
");
$stmt->execute([$order_id]);
    $order_items = $stmt->fetchAll();

} catch (Exception $e) {
    $error = 'حدث خطأ أثناء جلب بيانات الطلب';
}

// جلب تقييمات المنتجات
try {
    $stmt = $pdo->prepare("
        SELECT r.*, p.name as product_name, p.image as product_image, u.username
        FROM reviews r
        JOIN products p ON r.product_id = p.id
        JOIN users u ON r.user_id = u.id
        WHERE r.order_id = ?
    ");
    $stmt->execute([$order_id]);
    $reviews = $stmt->fetchAll();

    // جلب المنتجات التي لم يتم تقييمها
    $stmt = $pdo->prepare("
        SELECT oi.*, p.name, p.image
        FROM order_items oi
        JOIN products p ON oi.product_id = p.id
        LEFT JOIN reviews r ON oi.product_id = r.product_id AND r.order_id = oi.order_id
        WHERE oi.order_id = ? AND r.id IS NULL
    ");
    $stmt->execute([$order_id]);
    $unreviewed_items = $stmt->fetchAll();

} catch (Exception $e) {
    $error = 'حدث خطأ أثناء جلب بيانات التقييمات';
}

// التحقق من وجود المفاتيح المطلوبة في بيانات الطلب
if (!isset($order['customer_name'])) {
    $order['customer_name'] = 'غير محدد';
}
if (!isset($order['delivery_method'])) {
    $order['delivery_method'] = 'غير محدد';
}
if (!isset($order['payment_method'])) {
    $order['payment_method'] = 'غير محدد';
}
if (!isset($order['packaging_type'])) {
    $order['packaging_type'] = 'غير محدد';
}
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>تفاصيل الطلب - مخيط</title>
    <link rel="shortcut icon" href="assets/images/makhit-favicon.ico" type="image/x-icon">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .order-details {
            background-color: #f8f9fa;
            min-height: 100vh;
            padding: 2rem 0;
        }
        
        .order-card {
            background: white;
            border-radius: 15px;
            box-shadow: 0 0 20px rgba(0,0,0,0.1);
            margin-bottom: 1.5rem;
        }
        
        .order-card .card-header {
            background: #f8f9fa;
            border-bottom: 1px solid #eee;
            padding: 1.25rem;
        }
        
        .order-card .card-body {
            padding: 1.5rem;
        }
        
        .order-item {
            display: flex;
            align-items: center;
            padding: 1rem 0;
            border-bottom: 1px solid #eee;
        }
        
        .order-item:last-child {
            border-bottom: none;
        }
        
        .order-item img {
            width: 60px;
            height: 60px;
            object-fit: cover;
            border-radius: 8px;
            margin-left: 1rem;
        }
        
        .order-item-details {
            flex: 1;
        }
        
        .order-item-title {
            font-weight: 600;
            margin-bottom: 0.25rem;
        }
        
        .order-item-price {
            color: #666;
        }
        
        .thank-you-message {
            text-align: center;
            padding: 2rem;
            background: #f8f9fa;
            border-radius: 10px;
            margin-bottom: 2rem;
        }
        
        .thank-you-message i {
            font-size: 3rem;
            color: #198754;
            margin-bottom: 1rem;
        }

        /* تنسيقات حالة الطلب */
        .order-status {
            padding: 0.5rem 1rem;
            border-radius: 50px;
            font-size: 0.9rem;
            font-weight: 500;
            display: inline-flex;
            align-items: center;
        }

        .order-status i {
            margin-left: 0.5rem;
        }
        .status-new {
            background-color: #e7f1ff;
            color: #0d6efd;
            animation: pulse 2s infinite;
        }
        .status-pending {
            background-color: #fff3cd;
            color: #856404;
        }

        .status-processing {
            background-color: #cce5ff;
            color: #004085;
        }

        .status-shipped {
            background-color: #d1ecf1;
            color: #0c5460;
        }

        .status-delivered {
            background-color: #d4edda;
            color: #155724;
        }

        .status-cancelled {
            background-color: #f8d7da;
            color: #721c24;
        }

        .status-paid {
            background-color: #d4edda;
            color: #155724;
        }

        .status-failed {
            background-color: #f8d7da;
            color: #721c24;
        }

        .gap-2 {
            gap: 0.5rem;
        }

        .review-item {
            padding: 1rem;
            border-bottom: 1px solid #eee;
        }
        
        .review-item:last-child {
            border-bottom: none;
        }
        
        .review-header {
            display: flex;
            align-items: center;
            margin-bottom: 0.5rem;
        }
        
        .reviewer-name {
            font-weight: 600;
            margin-left: 0.5rem;
        }
        
        .review-date {
            color: #6c757d;
            font-size: 0.875rem;
        }
        
        .review-rating {
            color: #ffc107;
            margin-bottom: 0.5rem;
        }
        
        .review-comment {
            color: #495057;
        }
        
        .review-product {
            display: flex;
            align-items: center;
            margin-bottom: 1rem;
        }
        
        .review-product img {
            width: 50px;
            height: 50px;
            object-fit: cover;
            border-radius: 4px;
            margin-left: 0.5rem;
        }
    </style>
</head>
<body>
    <?php include 'includes/header.php'; ?>

    <div class="order-details">
        <div class="container">
            <div class="thank-you-message">
                <i class="fas fa-check-circle"></i>
                <h2>شكراً لطلبك!</h2>
                <p class="text-muted">تم استلام طلبك بنجاح وسيتم معالجته في أقرب وقت</p>
        </div>
        
        <div class="row">
                <div class="col-lg-8">
                    <div class="order-card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                            <h5 class="mb-0">
                                <i class="fas fa-shopping-bag me-2"></i>
                                تفاصيل الطلب #<?php echo $order['id']; ?>
                            </h5>
                            <div class="d-flex gap-2">
                                <div class="d-flex flex-column align-items-center">
                                    <small class="text-muted mb-1">حالة الطلب</small>
                            <span class="order-status status-<?php echo $order['status']; ?>">
                                <i class="fas fa-<?php 
                                    switch($order['status']) {
                                case 'pending':
                                            echo 'clock';
                                    break;
                                case 'processing':
                                            echo 'cog';
                                    break;
                                case 'shipped':
                                            echo 'truck';
                                    break;
                                case 'delivered':
                                            echo 'check-circle';
                                    break;
                                case 'cancelled':
                                            echo 'times-circle';
                                    break;
                            }
                                ?>"></i>
                                <?php
                                switch($order['status']) {
                                    case 'new':
                                        echo '<i class="fas fa-star"></i> طلب جديد';
                                        break;
                                    case 'pending':
                                        echo '<i class="fas fa-clock"></i> قيد الانتظار';
                                        break;
                                    case 'processing':
                                        echo '<i class="fas fa-cog fa-spin"></i> قيد المعالجة';
                                        break;
                                    case 'shipped':
                                        echo '<i class="fas fa-truck"></i> تم الشحن';
                                        break;
                                    case 'delivered':
                                        echo '<i class="fas fa-check-circle"></i> تم التسليم';
                                        break;
                                    case 'cancelled':
                                        echo '<i class="fas fa-times-circle"></i> ملغي';
                                        break;
                                }
                                ?>
                            </span>
                                </div>
                                <div class="d-flex flex-column align-items-center">
                                    <small class="text-muted mb-1">حالة الدفع</small>
                                    <span class="order-status status-<?php echo $order['payment_status'] ?? 'pending'; ?>">
                                        <i class="fas fa-<?php 
                                            switch($order['payment_status'] ?? 'pending') {
                                                case 'paid':
                                                    echo 'check-circle';
                                                    break;
                                                case 'pending':
                                                    echo 'clock';
                                                    break;
                                                case 'failed':
                                                    echo 'times-circle';
                                                    break;
                                                default:
                                                    echo 'clock';
                                            }
                                        ?>"></i>
                                        <?php
                                        switch($order['payment_status'] ?? 'pending') {
                                            case 'paid':
                                                echo 'تم الدفع';
                                                break;
                                            case 'pending':
                                                echo 'قيد الانتظار';
                                                break;
                                            case 'failed':
                                                echo 'فشل الدفع';
                                                break;
                                            default:
                                                echo 'قيد الانتظار';
                                        }
                                        ?>
                                    </span>
                                </div>
                            </div>
                        </div>
                        <div class="card-body">
                            <?php foreach ($order_items as $item): ?>
                                <div class="order-item">
                                    <img src="assets/images/products/<?php echo $item['image']; ?>" alt="<?php echo $item['name']; ?>">
                                    <div class="order-item-details">
                                        <div class="order-item-title"><?php echo $item['name']; ?></div>
                                        <div class="order-item-price">
                                            <?php echo $item['quantity']; ?> × <?php echo number_format($item['price'], 2); ?>ج.م
                                        </div>
                                    </div>
                                    <div class="order-item-total">
                                        <?php echo number_format($item['price'] * $item['quantity'], 2); ?>ج.م
                                    </div>
                                </div>
                            <?php endforeach; ?>
                    </div>
                </div>
                
                    <div class="order-card">
                    <div class="card-header">
                            <h5 class="mb-0">
                                <i class="fas fa-truck me-2"></i>
                                معلومات التوصيل
                            </h5>
                    </div>
                    <div class="card-body">
                            <div class="row">
                                <div class="col-md-6">
                                    <p><strong>الاسم:</strong> <?php echo htmlspecialchars($order['shipping_full_name']); ?></p>
                                    <p><strong>رقم الجوال:</strong> <?php echo htmlspecialchars($order['shipping_phone']); ?></p>
                                    <p><strong>المدينة:</strong> <?php echo htmlspecialchars($order['shipping_city']); ?></p>
                                </div>
                                <div class="col-md-6">
                                    <p><strong>العنوان:</strong> <?php echo htmlspecialchars($order['shipping_address']); ?></p>
                                    <p><strong>الرمز البريدي:</strong> <?php echo htmlspecialchars($order['shipping_postal_code'] ?? 'غير محدد'); ?></p>
                                    <p><strong>ملاحظات:</strong> <?php echo htmlspecialchars($order['notes'] ?? 'لا توجد ملاحظات'); ?></p>
                                </div>
                            </div>
                            <div class="row mt-3">
                                <div class="col-md-6">
                                    <p><strong>طريقة التوصيل:</strong> 
                                        <?php 
                                        switch($order['delivery_type']) {
                                            case 'home':
                                                echo 'توصيل لباب المنزل';
                                                break;
                                            case 'station':
                                                echo 'توصيل لأقرب محطة مترو';
                                                break;
                                            default:
                                                echo 'غير محدد';
                                        }
                                        ?>
                                    </p>
                                </div>
                                <div class="col-md-6">
                                    <p><strong>نوع التغليف:</strong> 
                                        <?php 
                                        switch($order['packaging_type']) {
                                            case 'standard':
                                                echo 'تغليف عادي';
                                                break;
                                            case 'premium':
                                                echo 'تغليف مميز';
                                                break;
                                            case 'gift':
                                                echo 'تغليف هدايا';
                                                break;
                                            default:
                                                echo 'غير محدد';
                                        }
                                        ?>
                                    </p>
                                </div>
                            </div>
                    </div>
                </div>
            </div>
            
                <div class="col-lg-4">
                    <div class="order-card">
                    <div class="card-header">
                            <h5 class="mb-0">
                                <i class="fas fa-receipt me-2"></i>
                                ملخص الطلب
                            </h5>
                    </div>
                    <div class="card-body">
                            <div class="d-flex justify-content-between mb-2">
                                <span>طريقة التوصيل:</span>
                                <span><?php echo $order['delivery_method'] == 'home' ? 'توصيل المنزل' : 'توصيل المترو'; ?></span>
                            </div>
                            <div class="d-flex justify-content-between mb-2">
                                <span>طريقة الدفع:</span>
                                <span>
                            <?php
                                    switch ($order['payment_method']) {
                                        case 'cash':
                                            echo 'الدفع عند الاستلام';
                                    break;
                                        case 'whatsapp':
                                            echo 'الدفع عبر واتساب';
                                    break;
                                        case 'electronic':
                                            echo 'الدفع الإلكتروني';
                                    break;
                            }
                            ?>
                                </span>
                                                </div>
                            <div class="d-flex justify-content-between mb-2">
                                <span>نوع التغليف:</span>
                                <span>
                                <?php
                                    switch ($order['packaging_type']) {
                                        case 'normal':
                                            echo 'تغليف عادي';
                                        break;
                                        case 'premium':
                                            echo 'تغليف مميز';
                                        break;
                                        case 'gift':
                                            echo 'تغليف هدايا';
                                        break;
                                }
                                ?>
                            </span>
                        </div>
                            <hr>
                            <div class="d-flex justify-content-between mb-2">
                                <span>المجموع الكلي:</span>
                                <span class="fw-bold"><?php echo number_format($order['total_amount'], 2); ?> ج.م</span>
                    </div>
                </div>
                    </div>

                    <div class="d-grid gap-2 mt-3">
                        <a href="index.php" class="btn btn-primary btn-lg">
                            <i class="fas fa-store me-2"></i>
                            العودة للمتجر
                            </a>
                        </div>
                </div>
            </div>
        </div>
    </div>

    <?php if ($order['status'] === 'shipped' || $order['status'] === 'delivered'): ?>
        <?php if (empty($reviews)): ?>
            <div class="text-center mt-4 mb-4">
                <a href="review_order.php?order_id=<?php echo $order['id']; ?>" class="btn btn-success px-4 py-2">
                    <i class="fas fa-star me-2"></i>
                    تقييم المنتجات
                </a>
            </div>
        <?php elseif (!empty($reviews) && !empty($unreviewed_items)): ?>
            <div class="text-center mt-4 mb-4">
                <a href="review_order.php?order_id=<?php echo $order_id; ?>" class="btn btn-success px-4 py-2">
                    <i class="fas fa-star me-2"></i>
                    تقييم المنتجات المتبقية
                </a>
            </div>
        <?php endif; ?>
    <?php endif; ?>

    <?php if (!empty($reviews)): ?>
        <div class="order-card mt-4">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="fas fa-star me-2"></i>
                    تقييمات المنتجات
                </h5>
            </div>
            <div class="card-body">
                <?php foreach ($reviews as $review): ?>
                    <div class="review-item">
                        <div class="review-product">
                            <img src="assets/images/products/<?php echo $review['product_image']; ?>" 
                                 alt="<?php echo $review['product_name']; ?>">
                            <h6 class="mb-0"><?php echo $review['product_name']; ?></h6>
                        </div>
                        <div class="review-header">
                            <div class="review-rating">
                                <?php for ($i = 1; $i <= 5; $i++): ?>
                                    <i class="fas fa-star<?php echo $i <= $review['rating'] ? '' : '-o'; ?>"></i>
                                <?php endfor; ?>
                            </div>
                            <div class="reviewer-name">
                                <?php echo $review['is_anonymous'] ? 'مستخدم مجهول' : $review['username']; ?>
                            </div>
                            <div class="review-date">
                                <?php echo date('Y-m-d', strtotime($review['created_at'])); ?>
                            </div>
                        </div>
                        <?php if ($review['comment']): ?>
                            <div class="review-comment">
                                <?php echo nl2br(htmlspecialchars($review['comment'])); ?>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endif; ?>

    <?php include 'includes/footer.php'; ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html> 