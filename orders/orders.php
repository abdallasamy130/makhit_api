<?php
session_start();
require_once 'includes/db.php';
require_once 'includes/settings.php';

// التحقق من تسجيل الدخول
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

$user_id = $_SESSION['user_id'];
$error = '';
$success = '';

// جلب إعدادات العملة
$currency_settings = [];
try {
    $stmt = $pdo->query("SELECT setting_key, setting_value FROM settings WHERE setting_key IN ('currency', 'currency_symbol')");
    while ($row = $stmt->fetch()) {
        $currency_settings[$row['setting_key']] = $row['setting_value'];
    }
} catch (PDOException $e) {
    // تجاهل الخطأ إذا حدث
}

// استخدام إعدادات العملة مع قيم افتراضية
$currency = $currency_settings['currency'] ?? 'جنيه';
$currency_symbol = $currency_settings['currency_symbol'] ?? 'ج.م';

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

// جلب طلبات المستخدم
try {
$stmt = $pdo->prepare("
        SELECT o.*, 
               (SELECT COUNT(*) FROM order_items WHERE order_id = o.id) as items_count,
               GROUP_CONCAT(p.name) as products_names
    FROM orders o 
        LEFT JOIN order_items oi ON o.id = oi.order_id
        LEFT JOIN products p ON oi.product_id = p.id
        WHERE o.user_id = ?
        GROUP BY o.id
    ORDER BY o.created_at DESC
");
    $stmt->execute([$user_id]);
$orders = $stmt->fetchAll();
} catch (Exception $e) {
    $error = 'حدث خطأ أثناء جلب بيانات الطلبات';
}
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>طلباتي - مخيط</title>
    <link rel="shortcut icon" href="assets/images/makhit-favicon.ico" type="image/x-icon">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .orders-page {
            background-color: #f8f9fa;
            min-height: 100vh;
            padding: 2rem 0;
        }
        
        .orders-card {
            background: white;
            border-radius: 15px;
            box-shadow: 0 0 20px rgba(0,0,0,0.1);
        }
        
        .orders-card .card-header {
            background: #f8f9fa;
            border-bottom: 1px solid #eee;
            padding: 1.25rem;
        }
        
        .orders-card .card-body {
            padding: 1.5rem;
        }
        
        .order-card {
            background: white;
            border-radius: 15px;
            box-shadow: 0 0 20px rgba(0,0,0,0.1);
            margin-bottom: 1rem;
            transition: transform 0.2s;
        }
        
        .order-card:hover {
            transform: translateY(-5px);
        }
        
        .order-status {
            display: inline-flex;
            align-items: center;
            padding: 0.5rem 1rem;
            border-radius: 50px;
            font-size: 0.9rem;
            font-weight: 500;
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
        
        .order-status i {
            margin-left: 0.5rem;
        }
    </style>
</head>
<body>
    <?php include 'includes/header.php'; ?>
    
    <div class="orders-page">
        <div class="container">
            <?php if ($error): ?>
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-circle me-2"></i>
                    <?php echo $error; ?>
            </div>
        <?php endif; ?>
        
            <div class="orders-card">
                <div class="card-header">
                    <h5 class="mb-0">
                        <i class="fas fa-shopping-bag me-2"></i>
                        طلباتي
                    </h5>
                </div>
                <div class="card-body">
                    <?php if (empty($orders)): ?>
                        <div class="text-center text-muted">
                            <i class="fas fa-shopping-bag fa-3x mb-3"></i>
                            <p>لا توجد طلبات سابقة</p>
                        </div>
                    <?php else: ?>
                                <?php foreach ($orders as $order): ?>
                            <div class="order-card p-3 mb-3">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <h6 class="mb-1">طلب #<?php echo $order['id']; ?></h6>
                                        <small class="text-muted">
                                            <?php echo $order['items_count']; ?> منتج
                                        </small>
                                    </div>
                                    <div>
                                        <span class="order-status status-<?php echo $order['status']; ?>">
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
                                </div>
                                <div class="mt-2">
                                    <small class="text-muted">
                                        <?php echo date('Y-m-d H:i', strtotime($order['created_at'])); ?>
                                    </small>
                                    <div class="float-start">
                                        <strong><?php echo number_format($order['total_amount'], 2); ?> <?php echo $currency; ?></strong>
                                    </div>
                                </div>
                                <div class="mt-2">
                                    <a href="order_details.php?id=<?php echo $order['id']; ?>" class="btn btn-sm btn-outline-primary">
                                        <i class="fas fa-eye me-1"></i>
                                        عرض التفاصيل
                                    </a>
                                </div>
                            </div>
                                <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <?php include 'includes/footer.php'; ?>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html> 