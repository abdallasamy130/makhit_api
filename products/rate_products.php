<?php
session_start();
require_once 'includes/db.php';

// التحقق من تسجيل الدخول
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

$user_id = $_SESSION['user_id'];
$order_id = isset($_GET['order_id']) ? (int)$_GET['order_id'] : 0;

// التحقق من وجود الطلب وأنه مكتمل
$stmt = $pdo->prepare("
    SELECT * FROM orders 
    WHERE id = ? AND user_id = ? AND status = 'completed'
");
$stmt->execute([$order_id, $user_id]);
$order = $stmt->fetch();

if (!$order) {
    header('Location: profile.php');
    exit();
}

// جلب منتجات الطلب
$stmt = $pdo->prepare("
    SELECT oi.*, p.name, p.image 
    FROM order_items oi 
    JOIN products p ON oi.product_id = p.id 
    WHERE oi.order_id = ?
");
$stmt->execute([$order_id]);
$order_items = $stmt->fetchAll();

// التحقق من وجود تقييمات سابقة
$stmt = $pdo->prepare("
    SELECT product_id, rating, review 
    FROM product_ratings 
    WHERE order_id = ?
");
$stmt->execute([$order_id]);
$existing_ratings = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

// معالجة تقديم التقييمات
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    foreach ($_POST['ratings'] as $product_id => $rating) {
        $review = $_POST['reviews'][$product_id] ?? '';
        
        // التحقق من صحة التقييم
        $rating = max(1, min(5, (int)$rating));
        
        // التحقق من وجود تقييم سابق
        $stmt = $pdo->prepare("
            SELECT id FROM product_ratings 
            WHERE order_id = ? AND product_id = ?
        ");
        $stmt->execute([$order_id, $product_id]);
        $existing = $stmt->fetch();
        
        if ($existing) {
            // تحديث التقييم الموجود
            $stmt = $pdo->prepare("
                UPDATE product_ratings 
                SET rating = ?, review = ?, updated_at = NOW() 
                WHERE order_id = ? AND product_id = ?
            ");
            $stmt->execute([$rating, $review, $order_id, $product_id]);
        } else {
            // إضافة تقييم جديد
            $stmt = $pdo->prepare("
                INSERT INTO product_ratings (order_id, product_id, user_id, rating, review) 
                VALUES (?, ?, ?, ?, ?)
            ");
            $stmt->execute([$order_id, $product_id, $user_id, $rating, $review]);
        }
    }
    
    header('Location: order_details.php?id=' . $order_id);
    exit();
}

$page_title = "تقييم المنتجات";
include 'includes/header.php';
?>

<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-md-8">
            <div class="card shadow">
                <div class="card-header bg-primary text-white">
                    <h4 class="mb-0">تقييم منتجات الطلب #<?php echo $order_id; ?></h4>
                </div>
                <div class="card-body">
                    <form method="post" action="">
                        <?php foreach ($order_items as $item): ?>
                            <div class="card mb-4">
                                <div class="card-body">
                                    <div class="row">
                                        <div class="col-md-3">
                                            <img src="<?php echo $item['image']; ?>" alt="<?php echo $item['name']; ?>" class="img-fluid rounded">
                                        </div>
                                        <div class="col-md-9">
                                            <h5 class="card-title"><?php echo $item['name']; ?></h5>
                                            <div class="mb-3">
                                                <label class="form-label">التقييم:</label>
                                                <div class="rating">
                                                    <?php for ($i = 5; $i >= 1; $i--): ?>
                                                        <input type="radio" name="ratings[<?php echo $item['product_id']; ?>]" 
                                                               value="<?php echo $i; ?>" 
                                                               id="star<?php echo $item['product_id']; ?>_<?php echo $i; ?>"
                                                               <?php echo (isset($existing_ratings[$item['product_id']]) && $existing_ratings[$item['product_id']] == $i) ? 'checked' : ''; ?>>
                                                        <label for="star<?php echo $item['product_id']; ?>_<?php echo $i; ?>">☆</label>
                                                    <?php endfor; ?>
                                                </div>
                                            </div>
                                            <div class="mb-3">
                                                <label for="review_<?php echo $item['product_id']; ?>" class="form-label">تعليق (اختياري):</label>
                                                <textarea class="form-control" id="review_<?php echo $item['product_id']; ?>" 
                                                          name="reviews[<?php echo $item['product_id']; ?>]" rows="2"><?php echo $existing_ratings[$item['product_id']] ?? ''; ?></textarea>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                        
                        <div class="text-center">
                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-check-circle"></i> حفظ التقييمات
                            </button>
                            <a href="order_details.php?id=<?php echo $order_id; ?>" class="btn btn-secondary">
                                <i class="bi bi-x-circle"></i> إلغاء
                            </a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
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
    font-size: 25px;
    color: #ddd;
    padding: 0 2px;
}

.rating input:checked ~ label,
.rating label:hover,
.rating label:hover ~ label {
    color: #ffd700;
}

.rating:hover input:checked ~ label {
    color: #ddd;
}
</style>

<?php include 'includes/footer.php'; ?> 