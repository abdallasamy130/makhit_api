<?php
session_start();
require_once 'config/database.php';

// التحقق من وجود معرف المنتج
if (!isset($_GET['id'])) {
    header('Location: products.php');
    exit();
}

$product_id = (int)$_GET['id'];

// Debug information
error_log("Attempting to fetch product with ID: " . $product_id);

// جلب تفاصيل المنتج
$stmt = $pdo->prepare("
    SELECT p.*, c.name as category_name,
           COALESCE(AVG(r.rating), 0) as avg_rating,
           COUNT(DISTINCT r.id) as reviews_count
    FROM products p
    LEFT JOIN categories c ON p.category_id = c.id
    LEFT JOIN reviews r ON p.id = r.product_id
    WHERE p.id = ?
    GROUP BY p.id
");
$stmt->execute([$product_id]);
$product = $stmt->fetch();

if (!$product) {
    // Log the error for debugging
    error_log("Product not found with ID: " . $product_id);
    
    // Check if the product exists at all
    $check_stmt = $pdo->prepare("SELECT COUNT(*) FROM products WHERE id = ?");
    $check_stmt->execute([$product_id]);
    $exists = $check_stmt->fetchColumn() > 0;
    
    if (!$exists) {
        // Product doesn't exist in the database
        $_SESSION['error'] = "لم يتم العثور على المنتج المطلوب";
    } else {
        // Product exists but there might be an issue with the query
        $_SESSION['error'] = "حدث خطأ أثناء جلب تفاصيل المنتج";
    }
    
    header('Location: products.php');
    exit();
}

// زيادة عدد المشاهدات
try {
    $stmt = $pdo->prepare("UPDATE products SET views = views + 1 WHERE id = ?");
    $stmt->execute([$product_id]);
} catch (PDOException $e) {
    // إذا لم يكن عمود المشاهدات موجوداً، نقوم بإنشائه
    if ($e->getCode() == '42S22') { // Column not found error
        try {
            $pdo->exec("ALTER TABLE products ADD COLUMN views INT DEFAULT 0");
            // بعد إضافة العمود، نقوم بتحديث عدد المشاهدات
            $stmt = $pdo->prepare("UPDATE products SET views = 1 WHERE id = ?");
            $stmt->execute([$product_id]);
        } catch (PDOException $e2) {
            // إذا فشل إضافة العمود، نتجاهل الخطأ ونستمر
            error_log("Failed to add views column: " . $e2->getMessage());
        }
    } else {
        // إذا كان هناك خطأ آخر، نسجله
        error_log("Error updating views: " . $e->getMessage());
    }
}

// جلب صور المنتج
$stmt = $pdo->prepare("SELECT * FROM product_images WHERE product_id = ? ORDER BY sort_order");
$stmt->execute([$product_id]);
$product_images = $stmt->fetchAll();

// إذا لم يكن هناك صور إضافية، نستخدم الصورة الرئيسية
if (empty($product_images)) {
    $product_images = [['image_path' => $product['image']]];
}

// جلب المنتجات ذات الصلة (من نفس التصنيف)
$stmt = $pdo->prepare("
    SELECT p.*, c.name as category_name,
           COALESCE(AVG(r.rating), 0) as avg_rating,
           COUNT(DISTINCT r.id) as reviews_count
    FROM products p
    LEFT JOIN categories c ON p.category_id = c.id
    LEFT JOIN reviews r ON p.id = r.product_id
    WHERE p.category_id = ? AND p.id != ? AND p.stock > 0
    GROUP BY p.id
    ORDER BY RAND()
    LIMIT 4
");
$stmt->execute([$product['category_id'], $product_id]);
$related_products = $stmt->fetchAll();

// جلب تقييمات المنتج
$stmt = $pdo->prepare("
    SELECT r.*, u.username, u.email
    FROM reviews r
    LEFT JOIN users u ON r.user_id = u.id
    WHERE r.product_id = ?
    ORDER BY r.rating DESC, r.created_at DESC
    LIMIT 3
");
$stmt->execute([$product_id]);
$reviews = $stmt->fetchAll();

// Debug information
error_log("Number of reviews found: " . count($reviews));
if (count($reviews) > 0) {
    error_log("First review data: " . print_r($reviews[0], true));
}

// التحقق مما إذا كان المنتج في المفضلة
$is_favorite = false;
if (isset($_SESSION['user_id'])) {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM favorites WHERE user_id = ? AND product_id = ?");
    $stmt->execute([$_SESSION['user_id'], $product_id]);
    $is_favorite = $stmt->fetchColumn() > 0;
}

// جلب إعدادات المتجر
try {
    $stmt = $pdo->prepare("SELECT * FROM settings WHERE id = 1");
    $stmt->execute();
    $settings = $stmt->fetch();
    
    // التحقق من وجود الإعدادات المطلوبة
    if (!$settings) {
        $settings = [
            'store_name' => 'مخيط',
            'store_description' => 'متجر مخيط هو وجهتك الأولى لشراء المنتجات عالية الجودة بأسعار مناسبة.',
            'phone' => '1234567890',
            'email' => 'info@makhit.com',
            'address' => 'الرياض، المملكة العربية السعودية',
            'facebook' => '#',
            'twitter' => '#',
            'instagram' => '#',
            'whatsapp' => '#',
            'currency_symbol' => 'ج.م'
        ];
    } else {
        // التأكد من وجود المفاتيح المطلوبة
        if (!isset($settings['store_name'])) $settings['store_name'] = 'مخيط';
        if (!isset($settings['store_description'])) $settings['store_description'] = 'متجر مخيط هو وجهتك الأولى لشراء المنتجات عالية الجودة بأسعار مناسبة.';
        if (!isset($settings['phone'])) $settings['phone'] = '1234567890';
        if (!isset($settings['email'])) $settings['email'] = 'info@makhit.com';
        if (!isset($settings['address'])) $settings['address'] = 'الرياض، المملكة العربية السعودية';
        if (!isset($settings['facebook'])) $settings['facebook'] = '#';
        if (!isset($settings['twitter'])) $settings['twitter'] = '#';
        if (!isset($settings['instagram'])) $settings['instagram'] = '#';
        if (!isset($settings['whatsapp'])) $settings['whatsapp'] = '#';
        if (!isset($settings['currency_symbol'])) $settings['currency_symbol'] = 'ج.م';
    }
} catch (Exception $e) {
    $settings = [
        'store_name' => 'مخيط',
        'store_description' => 'متجر مخيط هو وجهتك الأولى لشراء المنتجات عالية الجودة بأسعار مناسبة.',
        'phone' => '1234567890',
        'email' => 'info@makhit.com',
        'address' => 'الرياض، المملكة العربية السعودية',
        'facebook' => '#',
        'twitter' => '#',
        'instagram' => '#',
        'whatsapp' => '#',
        'currency_symbol' => 'ج.م'
    ];
}

$currency_symbol = $settings['currency_symbol'];

// Include header
include 'includes/header.php';
?>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@200;300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.css" rel="stylesheet">

    <div class="container py-5">
        <div class="row">
            <!-- صورة المنتج -->
            <div class="col-md-6 mb-4">
                <div class="product-image-container">
                    <div class="swiper product-image-swiper">
                        <div class="swiper-wrapper">
                            <?php foreach ($product_images as $image): ?>
                                <div class="swiper-slide">
                                    <img src="assets/images/products/<?php echo htmlspecialchars($image['image_path']); ?>" 
                                         alt="<?php echo htmlspecialchars($product['name']); ?>" 
                                         class="img-fluid rounded">
                                </div>
                            <?php endforeach; ?>
                        </div>
                        <div class="swiper-button-next"></div>
                        <div class="swiper-button-prev"></div>
                        <div class="swiper-pagination"></div>
                    </div>
                    <?php if ($product['stock'] <= 0): ?>
                        <div class="position-absolute top-0 start-0 bg-danger text-white p-2 rounded">
                            غير متوفر
                        </div>
                    <?php endif; ?>
                </div>
                
                <!-- معاينة الصور المصغرة -->
                <div class="swiper product-thumbs-swiper mt-3">
                    <div class="swiper-wrapper">
                        <?php foreach ($product_images as $image): ?>
                            <div class="swiper-slide">
                                <img src="assets/images/products/<?php echo htmlspecialchars($image['image_path']); ?>" 
                                     alt="<?php echo htmlspecialchars($product['name']); ?>" 
                                     class="img-thumbnail">
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

            <!-- تفاصيل المنتج -->
            <div class="col-md-6">
                <h1 class="mb-3"><?php echo htmlspecialchars($product['name']); ?></h1>
                
                <div class="mb-3">
                    <span class="badge bg-primary"><?php echo htmlspecialchars($product['category_name']); ?></span>
                </div>

                <div class="product-rating mb-3">
                    <?php
                    $rating = round($product['avg_rating'], 1);
                    for ($i = 1; $i <= 5; $i++) {
                        if ($i <= $rating) {
                            echo '<i class="bi bi-star-fill text-warning"></i>';
                        } elseif ($i - 0.5 <= $rating) {
                            echo '<i class="bi bi-star-half text-warning"></i>';
                        } else {
                            echo '<i class="bi bi-star text-warning"></i>';
                        }
                    }
                    ?>
                    <span class="text-muted">(<?php echo $product['reviews_count']; ?> تقييم)</span>
                </div>

                <div class="product-price mb-4">
                    <h2 class="text-primary"><?php echo $currency_symbol . number_format($product['price'], 2); ?></h2>
                </div>

                <div class="product-description mb-4">
                    <h4>الوصف</h4>
                    <p><?php echo nl2br(htmlspecialchars($product['description'])); ?></p>
                </div>

                <?php if ($product['stock'] > 0): ?>
                    <div class="product-actions">
                        <form id="addToCartForm" class="mt-4">
                            <input type="hidden" name="product_id" value="<?php echo $product['id']; ?>">
                            
                            <?php if ($sizes = json_decode($product['sizes'], true)): ?>
                            <div class="mb-3">
                                <label class="form-label">المقاس:</label>
                                <div class="size-buttons">
                                    <?php foreach ($sizes as $size): ?>
                                    <input type="radio" class="btn-check" name="size" id="size-<?php echo $size; ?>" value="<?php echo $size; ?>" required>
                                    <label class="btn btn-outline-primary me-2" for="size-<?php echo $size; ?>"><?php echo $size; ?></label>
                                    <?php endforeach; ?>
                                </div>
                                <div class="invalid-feedback">يرجى اختيار المقاس</div>
                            <div class="mt-2">
                                <a href="size-guide.php" class="text-primary">
                                    <i class="bi bi-rulers"></i> اعرف مقاسك
                                </a>
                            </div>
                            </div>
                            <?php endif; ?>
                            
                            <div class="mb-3">
                                <label class="form-label">الكمية:</label>
                                <div class="quantity-control">
                                    <button type="button" class="quantity-btn" id="decreaseQuantity">
                                        <i class="bi bi-dash-lg"></i>
                                    </button>
                                    <input type="number" class="quantity-input" name="quantity" value="1" min="1" max="<?php echo $product['stock']; ?>" required>
                                    <button type="button" class="quantity-btn" id="increaseQuantity">
                                        <i class="bi bi-plus-lg"></i>
                                    </button>
                                </div>
                            </div>
                            
                            <button type="submit" class="btn btn-primary btn-lg w-100">
                                <i class="fas fa-shopping-cart me-2"></i>
                                إضافة إلى السلة
                            </button>
                        </form>
                    </div>
                <?php else: ?>
                    <div class="alert alert-warning">
                        <i class="bi bi-exclamation-triangle"></i>نعمل علي اعادة توفير المنتج هذا المنتج غير متوفر حالياً
                    </div>
                <?php endif; ?>

                <div class="product-meta mt-4">
                    <div class="row g-3">
                        <div class="col-md-4">
                            <div class="meta-item d-flex align-items-center p-3 bg-light rounded">
                                <div class="meta-icon ms-3">
                                    <i class="bi bi-box-seam text-primary fs-4"></i>
                                </div>
                                <div class="meta-info text-end">
                                    <div class="meta-label text-muted small">المخزون</div>
                                    <div class="meta-value fw-bold">
                                        <?php if ($product['stock'] > 0): ?>
                                            <span class="text-success"><?php echo $product['stock']; ?> قطعة متوفرة</span>
                                        <?php else: ?>
                                            <span class="text-danger">غير متوفر</span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="meta-item d-flex align-items-center p-3 bg-light rounded">
                                <div class="meta-icon ms-3">
                                    <i class="bi bi-eye text-primary fs-4"></i>
                                </div>
                                <div class="meta-info text-end">
                                    <div class="meta-label text-muted small">عدد المشاهدات</div>
                                    <div class="meta-value fw-bold">
                                        <?php echo number_format($product['views']); ?> مشاهدة
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="meta-item d-flex align-items-center p-3 bg-light rounded">
                                <div class="meta-icon ms-3">
                                    <i class="bi bi-calendar3 text-primary fs-4"></i>
                                </div>
                                <div class="meta-info text-end">
                                    <div class="meta-label text-muted small">تاريخ الإضافة</div>
                                    <div class="meta-value fw-bold">
                                        <?php echo date('Y-m-d', strtotime($product['created_at'])); ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- التقييمات -->
        <div class="row mt-5">
            <div class="col-12">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h3 class="mb-0">تقييمات المنتج</h3>
                    <div class="overall-rating">
                        <div class="d-flex align-items-center">
                            <div class="rating-display me-2">
                                <?php
                                $rating = round($product['avg_rating'], 1);
                                for ($i = 1; $i <= 5; $i++) {
                                    if ($i <= $rating) {
                                        echo '<i class="bi bi-star-fill text-warning fs-4"></i>';
                                    } elseif ($i - 0.5 <= $rating) {
                                        echo '<i class="bi bi-star-half text-warning fs-4"></i>';
                                    } else {
                                        echo '<i class="bi bi-star text-warning fs-4"></i>';
                                    }
                                }
                                ?>
                            </div>
                            <div class="rating-info">
                                <div class="h4 mb-0"><?= number_format($rating, 1) ?></div>
                                <small class="text-muted">من <?= $product['reviews_count'] ?> تقييم</small>
                            </div>
                        </div>
                    </div>
                </div>
                
                <?php if (empty($reviews)): ?>
                    <div class="alert alert-info">
                        <i class="bi bi-info-circle"></i> لا توجد تقييمات لهذا المنتج بعد
                    </div>
                <?php else: ?>
                    <div class="reviews-container">
                        <?php foreach ($reviews as $review): ?>
                            <div class="card mb-3 review-card">
                                <div class="card-body">
                                    <div class="d-flex justify-content-between align-items-center mb-2">
                                        <div class="reviewer-info">
                                            <h5 class="card-title mb-0">
                                                <?php echo htmlspecialchars($review['username'] ?? 'مستخدم'); ?>
                                            </h5>
                                            <small class="text-muted">
                                                <?php echo date('Y-m-d', strtotime($review['created_at'])); ?>
                                            </small>
                                        </div>
                                        <div class="rating">
                                            <?php
                                            for ($i = 1; $i <= 5; $i++) {
                                                if ($i <= $review['rating']) {
                                                    echo '<i class="bi bi-star-fill text-warning"></i>';
                                                } else {
                                                    echo '<i class="bi bi-star text-warning"></i>';
                                                }
                                            }
                                            ?>
                                        </div>
                                    </div>
                                    <p class="card-text review-content"><?php echo nl2br(htmlspecialchars($review['comment'])); ?></p>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- المنتجات ذات الصلة -->
        <?php if (!empty($related_products)): ?>
        <div class="row mt-5">
            <div class="col-12">
                <h3 class="mb-4">منتجات ذات صلة</h3>
            </div>
            <?php foreach ($related_products as $related): ?>
                <div class="col-md-3 mb-4">
                    <div class="card h-100 related-product-card">
                        <div class="related-product-image-container">
                            <img src="assets/images/products/<?php echo htmlspecialchars($related['image']); ?>" 
                                 alt="<?php echo htmlspecialchars($related['name']); ?>" 
                                 class="related-product-image">
                            <div class="related-product-badges">
                                <?php if ($related['stock'] > 0): ?>
                                    <span class="related-product-badge stock-badge">
                                        <i class="bi bi-check-circle"></i> متوفر
                                    </span>
                                <?php else: ?>
                                    <span class="related-product-badge out-of-stock-badge">
                                        <i class="bi bi-x-circle"></i> غير متوفر
                                    </span>
                                <?php endif; ?>
                                <span class="related-product-badge category-badge">
                                    <i class="bi bi-tag"></i> <?php echo htmlspecialchars($related['category_name']); ?>
                                </span>
                            </div>
                        </div>
                        <div class="related-product-info">
                            <h3 class="related-product-title">
                                <a href="product.php?id=<?php echo $related['id']; ?>" class="text-decoration-none text-dark">
                                    <?php echo htmlspecialchars($related['name']); ?>
                                </a>
                            </h3>
                            <div class="related-product-price">
                                <?php echo $currency_symbol . number_format($related['price'], 2); ?>
                            </div>
                            <div class="related-product-rating">
                                <?php
                                $rating = round($related['avg_rating'], 1);
                                for ($i = 1; $i <= 5; $i++) {
                                    if ($i <= $rating) {
                                        echo '<i class="bi bi-star-fill"></i>';
                                    } elseif ($i - 0.5 <= $rating) {
                                        echo '<i class="bi bi-star-half"></i>';
                                    } else {
                                        echo '<i class="bi bi-star"></i>';
                                    }
                                }
                                ?>
                                <span class="related-reviews-count">(<?php echo $related['reviews_count']; ?>)</span>
                            </div>
                            <div class="related-product-actions">
                                <a href="product.php?id=<?php echo $related['id']; ?>" class="btn btn-primary">
                                    <i class="bi bi-eye"></i> عرض التفاصيل
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>

    <style>
    /* تنسيقات الصور الرئيسية */
    .product-image-container {
        position: relative;
        border-radius: 10px;
        overflow: hidden;
        background: #f8f9fa;
    }

    .product-image-swiper {
        width: 100%;
        height: 400px;
        cursor: zoom-in;
    }

    .product-image-swiper .swiper-slide {
        display: flex;
        align-items: center;
        justify-content: center;
        background: #fff;
        position: relative;
    }

    .product-image-swiper .swiper-slide img {
        max-width: 100%;
        max-height: 100%;
        object-fit: contain;
        transition: transform 0.3s ease;
    }

    .product-image-swiper .swiper-slide:hover img {
        transform: scale(1.5);
    }

    /* تأثير العدسة المكبرة */
    .zoom-lens {
        position: absolute;
        border: 2px solid rgba(0, 0, 0, 0.1);
        width: 100px;
        height: 100px;
        background-repeat: no-repeat;
        cursor: zoom-in;
        display: none;
        z-index: 1000;
        pointer-events: none;
    }

    .zoom-result {
        position: absolute;
        border: 1px solid #d4d4d4;
        width: 300px;
        height: 300px;
        background-repeat: no-repeat;
        display: none;
        z-index: 1000;
        right: 100%;
        top: 0;
        background-color: white;
        box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
    }

    .product-thumbs-swiper {
        height: 100px;
        box-sizing: border-box;
        padding: 10px 0;
    }

    .product-thumbs-swiper .swiper-slide {
        width: 25%;
        height: 100%;
        opacity: 0.4;
        cursor: pointer;
    }

    .product-thumbs-swiper .swiper-slide-thumb-active {
        opacity: 1;
    }

    .product-thumbs-swiper .swiper-slide img {
        width: 100%;
        height: 100%;
        object-fit: cover;
        border-radius: 5px;
    }

    /* تنسيقات المنتجات ذات الصلة */
    .related-product-card {
        transition: transform 0.3s ease, box-shadow 0.3s ease;
        border: none;
        border-radius: 10px;
        overflow: hidden;
        background: #fff;
        box-shadow: 0 2px 10px rgba(0,0,0,0.1);
    }

    .related-product-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 5px 20px rgba(0,0,0,0.15);
    }

    .related-product-image-container {
        position: relative;
        height: 200px;
        overflow: hidden;
        background: #f8f9fa;
    }

    .related-product-image {
        width: 100%;
        height: 100%;
        object-fit: cover;
        transition: transform 0.3s ease;
    }

    .related-product-card:hover .related-product-image {
        transform: scale(1.05);
    }

    .related-product-badges {
        position: absolute;
        top: 10px;
        right: 10px;
        z-index: 1;
        display: flex;
        flex-direction: column;
        gap: 5px;
    }

    .related-product-badge {
        display: inline-block;
        padding: 5px 12px;
        border-radius: 20px;
        font-size: 12px;
        font-weight: 600;
        text-align: center;
        box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        transition: all 0.3s ease;
    }

    .related-product-badge:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 8px rgba(0, 0, 0, 0.15);
    }

    .stock-badge {
        background-color: #28a745;
        color: white;
    }

    .out-of-stock-badge {
        background-color: #dc3545;
        color: white;
    }

    .category-badge {
        background-color: #007bff;
        color: white;
    }

    /* تنسيقات إضافية للشارات */
    .related-product-badge i {
        margin-left: 5px;
    }

    .related-product-badge.stock-badge i,
    .related-product-badge.out-of-stock-badge i,
    .related-product-badge.category-badge i {
        color: #fff;
    }

    .related-product-info {
        padding: 15px;
    }

    .related-product-title {
        font-size: 16px;
        font-weight: bold;
        margin-bottom: 10px;
        height: 40px;
        overflow: hidden;
        display: -webkit-box;
        -webkit-line-clamp: 2;
        -webkit-box-orient: vertical;
    }

    .related-product-price {
        font-size: 18px;
        font-weight: bold;
        color: #198754;
        margin-bottom: 10px;
    }

    .related-product-rating {
        color: #ffc107;
        margin-bottom: 10px;
    }

    .related-product-rating .related-reviews-count {
        font-size: 12px;
        color: #6c757d;
    }

    .related-product-actions {
        display: flex;
        gap: 10px;
    }

    .related-product-actions .btn {
        flex: 1;
    }

    /* تنسيقات Swiper */
    .swiper-button-next,
    .swiper-button-prev {
        color: #000;
        background: rgba(255, 255, 255, 0.8);
        width: 40px;
        height: 40px;
        border-radius: 50%;
    }

    .swiper-button-next:after,
    .swiper-button-prev:after {
        font-size: 20px;
    }

    .swiper-pagination-bullet {
        width: 10px;
        height: 10px;
        background: #000;
    }

    .swiper-pagination-bullet-active {
        background: #000;
    }

    /* تطبيق الخط الجديد */
    body {
        font-family: 'Cairo', sans-serif;
    }

    /* تخصيص الخط للعناوين */
    h1, h2, h3, h4, h5, h6 {
        font-family: 'Cairo', sans-serif;
        font-weight: 700;
    }

    /* تخصيص الخط للأزرار */
    .btn {
        font-family: 'Cairo', sans-serif;
        font-weight: 500;
    }

    /* تخصيص الخط للنصوص */
    p, span, a {
        font-family: 'Cairo', sans-serif;
        font-weight: 400;
    }

    /* تخصيص الخط للأسعار */
    .price {
        font-family: 'Cairo', sans-serif;
        font-weight: 700;
    }

    /* تخصيص الخط للتنقل */
    .pagination .page-link {
        font-family: 'Cairo', sans-serif;
        font-weight: 500;
    }

    /* تخصيص الخط للتنبيهات */
    .toast-body {
        font-family: 'Cairo', sans-serif;
        font-weight: 400;
    }

    /* تخصيص الخط للبحث */
    .form-control {
        font-family: 'Cairo', sans-serif;
        font-weight: 400;
    }

    /* تخصيص الخط للتصنيفات */
    .category-badge {
        font-family: 'Cairo', sans-serif;
        font-weight: 500;
    }

    /* تخصيص الخط للتصنيف */
    .sort-select {
        font-family: 'Cairo', sans-serif;
        font-weight: 400;
    }

    /* تخصيص التقييمات */
    .overall-rating {
        background-color: #f8f9fa;
        padding: 1rem;
        border-radius: 0.5rem;
    }

    .rating-display i {
        font-size: 1.5rem;
    }

    .rating-info {
        text-align: center;
    }

    .review-card {
        border: none;
        box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        transition: transform 0.2s;
    }

    .review-card:hover {
        transform: translateX(5px);
    }

    .reviewer-info {
        display: flex;
        flex-direction: column;
    }

    .review-content {
        margin-top: 1rem;
        line-height: 1.6;
    }

    .rating i {
        font-size: 1.1rem;
    }

    /* تخصيص المقاسات */
    .size-options {
        display: flex;
        flex-wrap: nowrap;
        gap: 10px;
        margin-top: 10px;
        overflow-x: auto;
        padding-bottom: 5px;
        -webkit-overflow-scrolling: touch;
        scrollbar-width: thin;
    }

    .size-options::-webkit-scrollbar {
        height: 4px;
    }

    .size-options::-webkit-scrollbar-track {
        background: #f1f1f1;
        border-radius: 4px;
    }

    .size-options::-webkit-scrollbar-thumb {
        background: #888;
        border-radius: 4px;
    }

    .size-options::-webkit-scrollbar-thumb:hover {
        background: #555;
    }

    .size-option {
        flex: 0 0 auto;
        background: #f8f9fa;
        border: 1px solid #ddd;
        border-radius: 8px;
        padding: 10px 20px;
        text-align: center;
        cursor: pointer;
        transition: all 0.3s ease;
        min-width: 60px;
    }

    .size-option:hover {
        background: #e9ecef;
        transform: translateY(-2px);
    }

    .size-option.selected {
        background: #0d6efd;
        color: white;
        border-color: #0d6efd;
    }

    .size-option input[type="radio"] {
        display: none;
    }

    .size-option label {
        display: block;
        cursor: pointer;
        margin: 0;
        font-weight: 500;
    }

    /* تنسيقات أزرار التحكم في الكمية */
    .quantity-control {
        display: flex;
        align-items: center;
        width: 150px;
        border: 1px solid #dee2e6;
        border-radius: 8px;
        overflow: hidden;
        background: #fff;
    }

    .quantity-btn {
        width: 40px;
        height: 40px;
        display: flex;
        align-items: center;
        justify-content: center;
        background: #f8f9fa;
        border: none;
        font-size: 1.2rem;
        color: #495057;
        cursor: pointer;
        transition: all 0.3s ease;
    }

    .quantity-btn:hover {
        background: #e9ecef;
        color: #000;
    }

    .quantity-btn:active {
        transform: scale(0.95);
    }

    .quantity-input {
        width: 70px;
        height: 40px;
        border: none;
        text-align: center;
        font-size: 1rem;
        font-weight: 500;
        color: #212529;
        background: transparent;
    }

    .quantity-input::-webkit-inner-spin-button,
    .quantity-input::-webkit-outer-spin-button {
        -webkit-appearance: none;
        margin: 0;
    }

    .quantity-input:focus {
        outline: none;
        box-shadow: none;
    }
    </style>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.js"></script>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // معالجة إضافة المنتج إلى السلة
        const addToCartForm = document.getElementById('addToCartForm');
        if (addToCartForm) {
            addToCartForm.addEventListener('submit', function(e) {
                e.preventDefault();
                
                const formData = new FormData(this);
                
                // إظهار مؤشر التحميل
                const submitButton = this.querySelector('button[type="submit"]');
                const originalText = submitButton.innerHTML;
                submitButton.disabled = true;
                submitButton.innerHTML = '<i class="bi bi-arrow-repeat spin"></i> جاري الإضافة...';
                
                fetch('add_to_cart.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    // إعادة تفعيل الزر
                    submitButton.disabled = false;
                    submitButton.innerHTML = originalText;
                    
                    if (data.status === 'success') {
                        // تحديث عدد العناصر في السلة
                        const cartCount = document.getElementById('cartCount');
                        if (cartCount) {
                            cartCount.textContent = data.cart_count;
                        }
                        
                        // تحديث عدد العناصر في السلة في جميع أنحاء الموقع
                        document.querySelectorAll('.cart-count').forEach(element => {
                            element.textContent = data.cart_count;
                        });
                        
                        // رسالة النجاح
                        Swal.fire({
                            title: 'تم بنجاح!',
                            text: data.message,
                            icon: 'success',
                            showCancelButton: true,
                            confirmButtonText: 'عرض السلة',
                            cancelButtonText: 'استكمال التسوق',
                            confirmButtonColor: '#198754',
                            cancelButtonColor: '#0d6efd'
                        }).then((result) => {
                            if (result.isConfirmed) {
                                window.location.href = 'cart.php';
                            } else if (result.dismiss === Swal.DismissReason.cancel) {
                                window.location.href = 'products.php';
                            }
                        });
                    } else {
                        // رسالة الخطأ
                        Swal.fire({
                            title: 'خطأ',
                            text: data.message,
                            icon: 'error',
                            confirmButtonText: 'حسناً'
                        });
                    }
                })
                .catch(error => {
                    // إعادة تفعيل الزر
                    submitButton.disabled = false;
                    submitButton.innerHTML = originalText;
                    
                    // رسالة الخطأ
                    Swal.fire({
                        title: 'خطأ',
                        text: 'حدث خطأ أثناء إضافة المنتج إلى السلة',
                        icon: 'error',
                        confirmButtonText: 'حسناً'
                    });
                    console.error('Error:', error);
                });
            });
        }

        const thumbsSwiper = new Swiper('.product-thumbs-swiper', {
            spaceBetween: 10,
            slidesPerView: 4,
            freeMode: true,
            watchSlidesProgress: true,
        });

        const mainSwiper = new Swiper('.product-image-swiper', {
            spaceBetween: 10,
            navigation: {
                nextEl: '.swiper-button-next',
                prevEl: '.swiper-button-prev',
            },
            pagination: {
                el: '.swiper-pagination',
                clickable: true,
            },
            thumbs: {
                swiper: thumbsSwiper,
            },
            on: {
                init: function() {
                    initZoomEffect();
                }
            }
        });

        function initZoomEffect() {
            const slides = document.querySelectorAll('.product-image-swiper .swiper-slide');
            slides.forEach(slide => {
                const img = slide.querySelector('img');
                if (img) {
                    // إنشاء عنصر العدسة
                    const lens = document.createElement('div');
                    lens.className = 'zoom-lens';
                    slide.appendChild(lens);

                    // إنشاء عنصر النتيجة المكبرة
                    const result = document.createElement('div');
                    result.className = 'zoom-result';
                    slide.appendChild(result);

                    // إضافة مستمعي الأحداث
                    slide.addEventListener('mousemove', moveLens);
                    slide.addEventListener('mouseenter', () => {
                        lens.style.display = 'block';
                        result.style.display = 'block';
                    });
                    slide.addEventListener('mouseleave', () => {
                        lens.style.display = 'none';
                        result.style.display = 'none';
                    });
                }
            });
        }

        function moveLens(e) {
            const slide = e.currentTarget;
            const img = slide.querySelector('img');
            const lens = slide.querySelector('.zoom-lens');
            const result = slide.querySelector('.zoom-result');

            // حساب موضع العدسة
            const slideRect = slide.getBoundingClientRect();
            const x = e.clientX - slideRect.left - lens.offsetWidth / 2;
            const y = e.clientY - slideRect.top - lens.offsetHeight / 2;

            // تحديد حدود العدسة
            const maxX = slideRect.width - lens.offsetWidth;
            const maxY = slideRect.height - lens.offsetHeight;

            // تحديث موضع العدسة
            lens.style.left = Math.min(Math.max(0, x), maxX) + 'px';
            lens.style.top = Math.min(Math.max(0, y), maxY) + 'px';

            // تحديث خلفية النتيجة المكبرة
            const imgRect = img.getBoundingClientRect();
            const xPercent = (x / slideRect.width) * 100;
            const yPercent = (y / slideRect.height) * 100;
            result.style.backgroundImage = `url('${img.src}')`;
            result.style.backgroundSize = `${imgRect.width * 2}px ${imgRect.height * 2}px`;
            result.style.backgroundPosition = `${xPercent}% ${yPercent}%`;
        }

        // التحكم في الكمية
        const quantityInput = document.querySelector('input[name="quantity"]');
        const decreaseBtn = document.getElementById('decreaseQuantity');
        const increaseBtn = document.getElementById('increaseQuantity');
        const maxQuantity = <?php echo $product['stock']; ?>;

        function updateQuantity(value) {
            let newValue = parseInt(quantityInput.value) + value;
            if (newValue >= 1 && newValue <= maxQuantity) {
                quantityInput.value = newValue;
                // إضافة تأثير حركي
                quantityInput.classList.add('quantity-update');
                setTimeout(() => {
                    quantityInput.classList.remove('quantity-update');
                }, 300);
            }
        }

        decreaseBtn.addEventListener('click', () => updateQuantity(-1));
        increaseBtn.addEventListener('click', () => updateQuantity(1));

        // التحقق من صحة القيمة عند التغيير
        quantityInput.addEventListener('change', function() {
            let value = parseInt(this.value);
            if (isNaN(value) || value < 1) {
                this.value = 1;
            } else if (value > maxQuantity) {
                this.value = maxQuantity;
            }
        });
    });
    </script>

<?php include 'includes/footer.php'; ?> 