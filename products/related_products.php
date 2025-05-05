<?php
session_start();
require_once 'config/database.php';

// التحقق من وجود معرف المنتج
if (!isset($_GET['product_id'])) {
    header('Location: products.php');
    exit();
}

$product_id = (int)$_GET['product_id'];

// جلب معلومات المنتج الحالي
$stmt = $pdo->prepare("SELECT * FROM products WHERE id = ?");
$stmt->execute([$product_id]);
$current_product = $stmt->fetch();

if (!$current_product) {
    header('Location: products.php');
    exit();
}

// جلب المنتجات ذات الصلة (من نفس التصنيف)
$stmt = $pdo->prepare("SELECT * FROM products WHERE category_id = ? AND id != ? AND stock > 0 LIMIT 4");
$stmt->execute([$current_product['category_id'], $product_id]);
$related_products = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>منتجات ذات صلة - مخيط</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <?php include 'includes/header.php'; ?>

    <div class="container py-5">
        <div class="row">
            <div class="col-12">
                <h2 class="mb-4">منتجات ذات صلة بـ <?php echo $current_product['name']; ?></h2>
            </div>
        </div>

        <div class="row">
            <?php if (empty($related_products)): ?>
            <div class="col-12">
                <div class="alert alert-info">لا توجد منتجات ذات صلة متاحة</div>
            </div>
            <?php else: ?>
            <?php foreach ($related_products as $product): ?>
            <div class="col-md-3 mb-4">
                <div class="card h-100">
                    <img src="<?php echo $product['image']; ?>" class="card-img-top" alt="<?php echo $product['name']; ?>">
                    <div class="card-body">
                        <h5 class="card-title"><?php echo $product['name']; ?></h5>
                        <p class="card-text"><?php echo number_format($product['price'], 2); ?> جنية</p>
                        <?php if ($product['stock'] > 0): ?>
                        <form action="add_to_cart.php" method="POST">
                            <input type="hidden" name="product_id" value="<?php echo $product['id']; ?>">
                            <div class="input-group mb-3">
                                <input type="number" name="quantity" class="form-control" value="1" min="1" max="<?php echo $product['stock']; ?>">
                                <button type="submit" class="btn btn-primary">أضف للسلة</button>
                            </div>
                        </form>
                        <?php else: ?>
                        <div class="alert alert-warning">غير متوفر</div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <div class="row mt-4">
            <div class="col-12 text-center">
                <a href="products.php" class="btn btn-outline-primary">عرض جميع المنتجات</a>
            </div>
        </div>
    </div>

    <?php include 'includes/footer.php'; ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html> 