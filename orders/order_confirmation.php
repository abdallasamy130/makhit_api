<?php
session_start();
require_once 'includes/db.php';
require_once 'includes/settings.php';

// التحقق من تسجيل الدخول
if (!isset($_SESSION['user_id'])) {
    $_SESSION['error'] = 'يجب تسجيل الدخول أولاً';
    header('Location: login.php');
    exit();
}

// التحقق من وجود معرف الطلب
if (!isset($_GET['order_id'])) {
    $_SESSION['error'] = 'لم يتم العثور على معرف الطلب';
    header('Location: index.php');
    exit();
}

$order_id = $_GET['order_id'];

try {
    // جلب تفاصيل الطلب
$stmt = $pdo->prepare("
        SELECT o.*, u.username
    FROM orders o 
        JOIN users u ON o.user_id = u.id
        WHERE o.id = ? AND o.user_id = ?
");
    $stmt->execute([$order_id, $_SESSION['user_id']]);
    $order = $stmt->fetch();

    if (!$order) {
        $_SESSION['error'] = 'لم يتم العثور على الطلب';
    header('Location: index.php');
    exit();
}

    // جلب تفاصيل المنتجات في الطلب
    $stmt = $pdo->prepare("
        SELECT oi.*, p.name, p.image
        FROM order_items oi
        JOIN products p ON oi.product_id = p.id
        WHERE oi.order_id = ?
    ");
    $stmt->execute([$order_id]);
    $order_items = $stmt->fetchAll();

// جلب إعدادات المتجر
$settings = [];
$stmt = $pdo->query("SELECT setting_key, setting_value FROM settings");
while ($row = $stmt->fetch()) {
    $settings[$row['setting_key']] = $row['setting_value'];
}

// تعيين رمز العملة مباشرة
$currency_symbol = 'ج.م';

} catch (Exception $e) {
    error_log("Error fetching order details: " . $e->getMessage());
    $_SESSION['error'] = 'حدث خطأ أثناء جلب تفاصيل الطلب';
    header('Location: index.php');
    exit();
}
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>تأكيد الطلب - مخيط</title>
    <link rel="shortcut icon" href="assets/images/makhit-favicon.ico" type="image/x-icon">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .confirmation-section {
            background-color: #f8f9fa;
            min-height: 100vh;
            padding: 2rem 0;
        }
        
        .confirmation-card {
            background: white;
            border-radius: 15px;
            box-shadow: 0 0 20px rgba(0,0,0,0.1);
            margin-bottom: 1.5rem;
            transition: all 0.3s ease;
            border: none;
        }
        
        .confirmation-card .card-header {
            background: #f8f9fa;
            border-bottom: 1px solid #eee;
            padding: 1.25rem;
            border-radius: 15px 15px 0 0 !important;
        }
        
        .confirmation-card .card-header h5 {
            font-weight: 600;
            color: #333;
            margin: 0;
        }
        
        .confirmation-card .card-body {
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
            width: 80px;
            height: 80px;
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
            color: #333;
        }
        
        .order-item-price {
            color: #666;
        }
        
        .total-section {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 1.5rem;
            margin-top: 1.5rem;
        }
        
        .total-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 0.5rem;
            padding: 0.5rem 0;
            border-bottom: 1px solid #eee;
        }
        
        .total-row:last-child {
            margin-bottom: 0;
            font-weight: 600;
            font-size: 1.1rem;
            border-bottom: none;
            color: #198754;
        }
        
        .btn-primary {
            padding: 0.75rem 2rem;
            font-size: 1.1rem;
        }
        
        .success-icon {
            font-size: 5rem;
            color: #198754;
            margin-bottom: 1.5rem;
        }
        
        .order-number {
            font-size: 1.5rem;
            font-weight: 600;
            color: #198754;
            margin-bottom: 1rem;
        }
        
        .order-status {
            display: inline-block;
            padding: 0.5rem 1rem;
            border-radius: 50px;
            font-weight: 600;
            margin-bottom: 1.5rem;
        }
        
        .status-pending {
            background-color: #fff3cd;
            color: #856404;
        }
        
        .status-processing {
            background-color: #cce5ff;
            color: #004085;
        }
        
        .status-completed {
            background-color: #d4edda;
            color: #155724;
        }
        
        .status-cancelled {
            background-color: #f8d7da;
            color: #721c24;
        }
        
        .shipping-info, .payment-info {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
        }
        
        .info-title {
            font-weight: 600;
            color: #333;
            margin-bottom: 1rem;
        }
        
        .info-item {
            display: flex;
            margin-bottom: 0.5rem;
        }
        
        .info-label {
            font-weight: 600;
            color: #666;
            width: 150px;
        }
        
        .info-value {
            color: #333;
        }
        
        @media (max-width: 768px) {
            .order-item {
                flex-direction: column;
                text-align: center;
            }
            
            .order-item img {
                margin: 0 0 1rem 0;
            }
            
            .info-item {
                flex-direction: column;
            }
            
            .info-label {
                width: 100%;
                margin-bottom: 0.25rem;
            }
        }
    </style>
</head>
<body>
    <?php include 'includes/header.php'; ?>
    
    <div class="confirmation-section">
        <div class="container">
            <div class="row justify-content-center">
                <div class="col-lg-8">
                    <div class="confirmation-card text-center">
                        <div class="card-body">
                            <i class="fas fa-check-circle success-icon"></i>
                            <h2 class="mb-3">تم استلام طلبك بنجاح!</h2>
                            <p class="text-muted mb-4">شكراً لك على ثقتك بنا. سنقوم بمعالجة طلبك في أقرب وقت ممكن.</p>
                            
                            <div class="order-number">
                                رقم الطلب: #<?php echo $order['order_number']; ?>
    </div>

                            <div class="order-status status-<?php echo $order['status']; ?>">
                                <?php
                                switch($order['status']) {
                                    case 'pending':
                                        echo 'قيد الانتظار';
                                        break;
                                    case 'processing':
                                        echo 'قيد المعالجة';
                                        break;
                                    case 'completed':
                                        echo 'مكتمل';
                                        break;
                                    case 'cancelled':
                                        echo 'ملغي';
                                        break;
                                    default:
                                        echo 'غير معروف';
                                }
                                ?>
                    </div>
                            
                            <div class="row mt-4">
                            <div class="col-md-6">
                                    <div class="shipping-info">
                                        <h5 class="info-title">معلومات الشحن</h5>
                                        <div class="info-item">
                                            <div class="info-label">الاسم:</div>
                                            <div class="info-value"><?php echo htmlspecialchars($order['shipping_full_name']); ?></div>
                                        </div>
                                        <div class="info-item">
                                            <div class="info-label">الهاتف:</div>
                                            <div class="info-value"><?php echo htmlspecialchars($order['shipping_phone']); ?></div>
                                        </div>
                                        <div class="info-item">
                                            <div class="info-label">العنوان:</div>
                                            <div class="info-value"><?php echo htmlspecialchars($order['shipping_address']); ?></div>
                                    </div>
                                        <div class="info-item">
                                            <div class="info-label">المدينة:</div>
                                            <div class="info-value"><?php echo htmlspecialchars($order['shipping_city']); ?></div>
                                    </div>
                                        <?php if (!empty($order['shipping_postal_code'])): ?>
                                        <div class="info-item">
                                            <div class="info-label">الرمز البريدي:</div>
                                            <div class="info-value"><?php echo htmlspecialchars($order['shipping_postal_code']); ?></div>
                                    </div>
                                        <?php endif; ?>
                                </div>
                            </div>
                            <div class="col-md-6">
                                    <div class="payment-info">
                                        <h5 class="info-title">معلومات الدفع</h5>
                                        <div class="info-item">
                                            <div class="info-label">طريقة الدفع:</div>
                                        <div class="info-value">
                                                <?php 
                                                switch($order['payment_method']) {
                                                    case 'cash':
                                                        echo 'الدفع عند الاستلام';
                                                        break;
                                                    case 'whatsapp':
                                                        echo 'واتساب';
                                                        break;
                                                    case 'instapay':
                                                        echo 'انستا باي';
                                                        break;
                                                    default:
                                                        echo 'غير معروف';
                                                }
                                                ?>
                                            </div>
                                        </div>
                                        <div class="info-item">
                                            <div class="info-label">طريقة التوصيل:</div>
                                            <div class="info-value">
                                                <?php 
                                                switch($order['delivery_type']) {
                                                    case 'home':
                                                        echo 'توصيل لباب البيت';
                                                        break;
                                                    case 'station':
                                                        echo 'توصيل لأقرب محطة';
                                                        break;
                                                    default:
                                                        echo 'غير معروف';
                                                }
                                                ?>
                                    </div>
                                </div>
                                        <div class="info-item">
                                            <div class="info-label">نوع التغليف:</div>
                                            <div class="info-value">
                                                <?php 
                                                switch($order['packaging_type']) {
                                                    case 'standard':
                                                        echo 'عادي';
                                                        break;
                                                    case 'premium':
                                                        echo 'مميز';
                                                        break;
                                                    case 'gift':
                                                        echo 'هدية';
                                                        break;
                                                    default:
                                                        echo 'غير معروف';
                                                }
                                                ?>
                                    </div>
                                    </div>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="confirmation-card mt-4">
                                <div class="card-header">
                                    <h5 class="mb-0">تفاصيل الطلب</h5>
                        </div>
                                <div class="card-body">
            <?php foreach ($order_items as $item): ?>
                <div class="order-item">
                                            <img src="assets/images/products/<?php echo htmlspecialchars($item['image']); ?>" 
                                                 alt="<?php echo htmlspecialchars($item['name']); ?>"
                                                 onerror="this.src='assets/images/no-image.png'">
                    <div class="order-item-details">
                        <div class="order-item-title"><?php echo htmlspecialchars($item['name']); ?></div>
                                                <?php if (!empty($item['size'])): ?>
                                                    <div class="order-item-size">المقاس: <?php echo htmlspecialchars($item['size']); ?></div>
                                                <?php endif; ?>
                        <div class="order-item-price">
                            <?php echo $item['quantity']; ?> × <?php echo number_format($item['price'], 2); ?> <?php echo $currency_symbol; ?>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>

            <div class="total-section">
                <div class="total-row">
                    <span>المجموع الفرعي</span>
                                            <span><?php echo number_format($order['total_amount'] - $order['delivery_fee'] - $order['packaging_fee'], 2); ?> <?php echo $currency_symbol; ?></span>
                </div>
                <div class="total-row">
                    <span>رسوم الشحن</span>
                                            <span><?php echo number_format($order['delivery_fee'], 2); ?> <?php echo $currency_symbol; ?></span>
                </div>
                <div class="total-row">
                    <span>رسوم التغليف</span>
                                            <span><?php echo number_format($order['packaging_fee'], 2); ?> <?php echo $currency_symbol; ?></span>
                </div>
                <div class="total-row">
                    <span>المجموع الكلي</span>
                                            <span><?php echo number_format($order['total_amount'], 2); ?> <?php echo $currency_symbol; ?></span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="mt-4">
                                <a href="index.php" class="btn btn-primary">
                                    <i class="fas fa-home me-2"></i>
                                    العودة للرئيسية
                                </a>
                                <a href="orders.php" class="btn btn-outline-primary ms-2">
                                    <i class="fas fa-list me-2"></i>
                                    عرض طلباتي
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
</div> 

    <?php include 'includes/footer.php'; ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html> 