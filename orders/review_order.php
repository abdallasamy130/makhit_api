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
$order_id = isset($_GET['order_id']) ? (int)$_GET['order_id'] : 0;
$error = '';
$success = '';

// التحقق من وجود الطلب وأنه مكتمل
try {
    $stmt = $pdo->prepare("
        SELECT o.*, u.username as customer_name
        FROM orders o
        JOIN users u ON o.user_id = u.id
        WHERE o.id = ? AND o.user_id = ? AND o.status IN ('shipped', 'delivered')
    ");
    $stmt->execute([$order_id, $user_id]);
    $order = $stmt->fetch();

    if (!$order) {
        header('Location: orders.php');
        exit();
    }

    // جلب منتجات الطلب التي لم يتم تقييمها
    $stmt = $pdo->prepare("
        SELECT oi.*, p.name, p.image
        FROM order_items oi
        JOIN products p ON oi.product_id = p.id
        LEFT JOIN reviews r ON oi.product_id = r.product_id AND r.order_id = oi.order_id
        WHERE oi.order_id = ? AND r.id IS NULL
    ");
    $stmt->execute([$order_id]);
    $order_items = $stmt->fetchAll();

    if (empty($order_items)) {
        $error = 'لا توجد منتجات متبقية للتقييم';
    }

    // التحقق من وجود تقييمات سابقة
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM reviews WHERE order_id = ?");
    $stmt->execute([$order_id]);
    if ($stmt->fetchColumn() > 0) {
        $error = 'لقد قمت بتقييم هذه المنتجات مسبقاً';
    }

} catch (Exception $e) {
    $error = 'حدث خطأ أثناء جلب بيانات الطلب';
}

// معالجة إرسال التقييم
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$error) {
    try {
        $pdo->beginTransaction();

        foreach ($order_items as $item) {
            $product_id = $item['product_id'];
            $rating = isset($_POST['rating'][$product_id]) ? (int)$_POST['rating'][$product_id] : 0;
            $comment = isset($_POST['comment'][$product_id]) ? trim($_POST['comment'][$product_id]) : '';
            $is_anonymous = isset($_POST['anonymous'][$product_id]) ? 1 : 0;

            if ($rating > 0) {
                $stmt = $pdo->prepare("
                    INSERT INTO reviews (order_id, product_id, user_id, rating, comment, is_anonymous)
                    VALUES (?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([$order_id, $product_id, $user_id, $rating, $comment, $is_anonymous]);
            }
        }

        $pdo->commit();
        $success = 'تم إرسال تقييمك بنجاح';
        header("refresh:2;url=order_details.php?id=" . $order_id);
    } catch (Exception $e) {
        $pdo->rollBack();
        $error = 'حدث خطأ أثناء حفظ التقييم';
    }
}
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>تقييم المنتجات - مخيط</title>
    <link rel="shortcut icon" href="assets/images/makhit-favicon.ico" type="image/x-icon">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .review-container {
            background-color: #f8f9fa;
            min-height: 100vh;
            padding: 2rem 0;
        }
        
        .review-card {
            background: white;
            border-radius: 15px;
            box-shadow: 0 0 20px rgba(0,0,0,0.1);
            margin-bottom: 1.5rem;
        }
        
        .review-card .card-header {
            background: #f8f9fa;
            border-bottom: 1px solid #eee;
            padding: 1.25rem;
        }
        
        .review-card .card-body {
            padding: 1.5rem;
        }
        
        .product-review {
            padding: 1.5rem;
            border-bottom: 1px solid #eee;
        }
        
        .product-review:last-child {
            border-bottom: none;
        }
        
        .product-image {
            width: 100px;
            height: 100px;
            object-fit: cover;
            border-radius: 8px;
            margin-left: 1rem;
        }
        
        .rating {
            display: flex;
            flex-direction: row-reverse;
            justify-content: flex-end;
        }
        
        .rating input {
            display: none;
        }
        
        .rating label {
            cursor: pointer;
            font-size: 1.5rem;
            color: #ddd;
            margin-left: 0.5rem;
        }
        
        .rating input:checked ~ label,
        .rating label:hover,
        .rating label:hover ~ label {
            color: #ffc107;
        }
        
        .rating input:checked ~ label {
            color: #ffc107;
        }
        
        .form-check-input:checked {
            background-color: #198754;
            border-color: #198754;
        }
    </style>
</head>
<body>
    <?php include 'includes/header.php'; ?>

    <div class="review-container">
        <div class="container">
            <?php if ($error): ?>
                <div class="alert alert-danger"><?php echo $error; ?></div>
            <?php endif; ?>
            
            <?php if ($success): ?>
                <div class="alert alert-success"><?php echo $success; ?></div>
            <?php endif; ?>
            
            <?php if (!$error && !$success): ?>
                <div class="review-card">
                    <div class="card-header">
                        <h4 class="mb-0">
                            <i class="fas fa-star me-2"></i>
                            تقييم منتجات الطلب #<?php echo $order_id; ?>
                        </h4>
                    </div>
                    <div class="card-body">
                        <form method="POST" enctype="multipart/form-data">
                            <?php foreach ($order_items as $item): ?>
                                <div class="product-review">
                                    <div class="d-flex align-items-center mb-3">
                                        <img src="assets/images/products/<?php echo $item['image']; ?>" 
                                             alt="<?php echo $item['name']; ?>" 
                                             class="product-image">
                                        <div>
                                            <h5><?php echo $item['name']; ?></h5>
                                            <p class="text-muted mb-0">
                                                الكمية: <?php echo $item['quantity']; ?>
                                                <?php if ($item['size']): ?>
                                                    - المقاس: <?php echo $item['size']; ?>
                                                <?php endif; ?>
                                            </p>
                                        </div>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label class="form-label">التقييم</label>
                                        <div class="rating">
                                            <input type="radio" name="rating[<?php echo $item['product_id']; ?>]" value="5" id="star5_<?php echo $item['product_id']; ?>">
                                            <label for="star5_<?php echo $item['product_id']; ?>"><i class="fas fa-star"></i></label>
                                            <input type="radio" name="rating[<?php echo $item['product_id']; ?>]" value="4" id="star4_<?php echo $item['product_id']; ?>">
                                            <label for="star4_<?php echo $item['product_id']; ?>"><i class="fas fa-star"></i></label>
                                            <input type="radio" name="rating[<?php echo $item['product_id']; ?>]" value="3" id="star3_<?php echo $item['product_id']; ?>">
                                            <label for="star3_<?php echo $item['product_id']; ?>"><i class="fas fa-star"></i></label>
                                            <input type="radio" name="rating[<?php echo $item['product_id']; ?>]" value="2" id="star2_<?php echo $item['product_id']; ?>">
                                            <label for="star2_<?php echo $item['product_id']; ?>"><i class="fas fa-star"></i></label>
                                            <input type="radio" name="rating[<?php echo $item['product_id']; ?>]" value="1" id="star1_<?php echo $item['product_id']; ?>">
                                            <label for="star1_<?php echo $item['product_id']; ?>"><i class="fas fa-star"></i></label>
                                        </div>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="comment_<?php echo $item['product_id']; ?>" class="form-label">تعليقك</label>
                                        <textarea class="form-control" 
                                                  id="comment_<?php echo $item['product_id']; ?>" 
                                                  name="comment[<?php echo $item['product_id']; ?>]" 
                                                  rows="3" 
                                                  placeholder="شاركنا تجربتك مع هذا المنتج"></textarea>
                                    </div>
                                    
                                    <div class="form-check mb-3">
                                        <input class="form-check-input" 
                                               type="checkbox" 
                                               name="anonymous[<?php echo $item['product_id']; ?>]" 
                                               id="anonymous_<?php echo $item['product_id']; ?>">
                                        <label class="form-check-label" for="anonymous_<?php echo $item['product_id']; ?>">
                                            تقييم مجهول
                                        </label>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                            
                            <div class="text-center mt-4">
                                <button type="submit" class="btn btn-success btn-lg">
                                    <i class="fas fa-paper-plane me-2"></i>
                                    إرسال التقييم
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <?php include 'includes/footer.php'; ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html> 