<?php
session_start();

// تهيئة متغير العدد
$count = 0;

// التحقق من وجود عناصر في السلة
if (isset($_SESSION['cart']) && is_array($_SESSION['cart'])) {
    // حساب العدد الإجمالي للعناصر
    foreach ($_SESSION['cart'] as $item) {
        $count += $item['quantity'];
    }
}

// إرجاع العدد بتنسيق JSON
header('Content-Type: application/json');
echo json_encode(['count' => $count]);
?> 