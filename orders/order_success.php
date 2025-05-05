<?php
session_start();
require_once 'config/database.php';

// التحقق من وجود جلسة نجاح الطلب
if (!isset($_SESSION['order_success'])) {
    header('Location: index.php');
    exit();
}

$order_success = $_SESSION['order_success'];
unset($_SESSION['order_success']); // حذف رسالة النجاح من الجلسة
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>تم استلام طلبك - مخيط</title>
    <link rel="shortcut icon" href="assets/images/makhit-favicon.ico" type="image/x-icon">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css">
    <style>
        body {
            background-color: #f8f9fa;
        }
        .success-container {
            max-width: 600px;
            margin: 50px auto;
            padding: 30px;
            background: white;
            border-radius: 15px;
            box-shadow: 0 0 20px rgba(0,0,0,0.1);
            text-align: center;
        }
        .success-icon {
            font-size: 80px;
            color: #28a745;
            margin-bottom: 20px;
        }
        .thank-you {
            font-size: 24px;
            color: #333;
            margin-bottom: 20px;
        }
        .order-details {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 10px;
            margin: 20px 0;
        }
        .next-steps {
            text-align: right;
            margin-top: 30px;
        }
        .social-links {
            margin-top: 30px;
        }
        .social-links a {
            font-size: 24px;
            margin: 0 10px;
            color: #333;
            transition: color 0.3s;
        }
        .social-links a:hover {
            color: #0d6efd;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="success-container">
            <i class="bi bi-check-circle-fill success-icon"></i>
            <h1 class="thank-you">شكراً لطلبك من مخيط!</h1>
            
            <div class="order-details">
                <p class="mb-3"><?php echo $order_success; ?></p>
                <p class="mb-0">سنقوم بتحديثك على حالة طلبك عبر البريد الإلكتروني ورقم الهاتف.</p>
            </div>
            
            <div class="next-steps">
                <h5>الخطوات التالية:</h5>
                <ul class="list-unstyled">
                    <li><i class="bi bi-check2-circle text-success"></i> سنقوم بمراجعة طلبك</li>
                    <li><i class="bi bi-check2-circle text-success"></i> سنقوم بتجهيز طلبك</li>
                    <li><i class="bi bi-check2-circle text-success"></i> سنقوم بشحن طلبك</li>
                    <li><i class="bi bi-check2-circle text-success"></i> سنقوم بتحديثك على حالة الشحن</li>
                </ul>
            </div>
            
            <div class="social-links">
                <p class="mb-3">تابعنا على وسائل التواصل الاجتماعي</p>
                <a href="https://facebook.com/makhit" target="_blank"><i class="bi bi-facebook"></i></a>
                <a href="https://instagram.com/makhit" target="_blank"><i class="bi bi-instagram"></i></a>
                <a href="https://twitter.com/makhit" target="_blank"><i class="bi bi-twitter"></i></a>
            </div>
            
            <div class="mt-4">
                <a href="index.php" class="btn btn-primary">
                    <i class="bi bi-house-door"></i> العودة للرئيسية
                </a>
                <a href="orders.php" class="btn btn-outline-primary">
                    <i class="bi bi-bag"></i> متابعة طلباتي
                </a>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html> 