<?php
if(!isset($_SESSION)){
session_start();
}
require_once 'includes/db.php';
require_once 'includes/functions.php';

// جلب إعدادات المتجر
$settings = [];
try {
    $stmt = $pdo->query("SELECT setting_key, setting_value FROM settings");
    while ($row = $stmt->fetch()) {
        $settings[$row['setting_key']] = $row['setting_value'];
    }
} catch (PDOException $e) {
    error_log("Settings Error: " . $e->getMessage());
}

// تحديد رمز العملة
$currency_symbol = $settings['currency_symbol_' . ($settings['currency'] ?? 'جنيه مصري')] ?? 'ج.م';

// جلب التصنيفات للفلترة
$stmt = $pdo->query("SELECT * FROM categories ORDER BY name");
$categories = $stmt->fetchAll();

// معالجة معاملات الفلترة
$category_id = isset($_GET['category']) ? (int)$_GET['category'] : null;
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$sort = isset($_GET['sort']) ? $_GET['sort'] : 'newest';
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$per_page = 12; // عدد المنتجات في كل صفحة

// حساب عدد الصفحات
$count_query = "SELECT COUNT(DISTINCT p.id) as total FROM products p WHERE 1=1";
$count_params = [];

if ($category_id) {
    $count_query .= " AND p.category_id = ?";
    $count_params[] = $category_id;
}
if ($search) {
    $count_query .= " AND (p.name LIKE ? OR p.description LIKE ?)";
    $count_params[] = "%$search%";
    $count_params[] = "%$search%";
}

$stmt = $pdo->prepare($count_query);
$stmt->execute($count_params);
$total_products = $stmt->fetch()['total'];
$total_pages = ceil($total_products / $per_page);

// التأكد من أن رقم الصفحة صحيح
if ($page < 1) $page = 1;
if ($page > $total_pages && $total_pages > 0) $page = $total_pages;

// حساب الإزاحة
$offset = ($page - 1) * $per_page;

// جلب المنتجات مع التصفية والترتيب
$query = "SELECT p.*, c.name as category_name, 
          COALESCE(AVG(r.rating), 0) as avg_rating,
          COUNT(DISTINCT r.id) as reviews_count
        FROM products p 
        LEFT JOIN categories c ON p.category_id = c.id 
          LEFT JOIN reviews r ON p.id = r.product_id";

$where_conditions = [];
$params = [];

if ($category_id) {
    $where_conditions[] = "p.category_id = ?";
    $params[] = $category_id;
}

if ($search) {
    $where_conditions[] = "(p.name LIKE ? OR p.description LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

if (!empty($where_conditions)) {
    $query .= " WHERE " . implode(" AND ", $where_conditions);
}

$query .= " GROUP BY p.id";

// إضافة الترتيب
switch ($sort) {
    case 'price_asc':
        $query .= " ORDER BY p.price ASC";
        break;
    case 'price_desc':
        $query .= " ORDER BY p.price DESC";
        break;
    case 'name':
        $query .= " ORDER BY p.name ASC";
        break;
    case 'popular':
        $query .= " ORDER BY p.views DESC";
        break;
    case 'rating':
        $query .= " ORDER BY avg_rating DESC, reviews_count DESC";
        break;
    default: // newest
        $query .= " ORDER BY p.created_at DESC";
}

// إضافة LIMIT مباشرة في الاستعلام
$query .= " LIMIT " . (int)$offset . ", " . (int)$per_page;

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$products = $stmt->fetchAll();

// جلب المنتجات المضافة حديثاً
$stmt = $pdo->query("SELECT p.*, c.name as category_name,
                     COALESCE(AVG(r.rating), 0) as avg_rating,
                     COUNT(DISTINCT r.id) as reviews_count
                     FROM products p 
                     LEFT JOIN categories c ON p.category_id = c.id 
                     LEFT JOIN reviews r ON p.id = r.product_id
                     WHERE p.stock > 0 
                     GROUP BY p.id
                     ORDER BY p.created_at DESC 
                     LIMIT 4");
$recent_products = $stmt->fetchAll();

// جلب المنتجات المفضلة للمستخدم الحالي
$favorite_products = [];
if (isset($_SESSION['user_id'])) {
    $stmt = $pdo->prepare("SELECT product_id FROM favorites WHERE user_id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $favorite_products = $stmt->fetchAll(PDO::FETCH_COLUMN);
}

// تصحيح المسارات
$base_url = 'http://' . $_SERVER['HTTP_HOST'] . dirname($_SERVER['PHP_SELF']);
$base_url = rtrim($base_url, '/');

// جلب إعدادات الموقع
try {
    $stmt = $pdo->prepare("SELECT * FROM settings WHERE id = 1");
    $stmt->execute();
    $settings = $stmt->fetch();
    
    // التحقق من وجود الإعدادات المطلوبة
    if (!$settings) {
        $settings = [
            'store_name' => 'مخيط',
            'store_description' => 'متجر مخيط هو وجهتك الأولى لشراء المنتجات عالية الجودة بأسعار مناسبة.',
            'phone' => '1129291832',
            'email' => 'info@makhit.com',
            'address' => 'القاهرة, مصر',
            'facebook' => '#',
            'twitter' => '#',
            'instagram' => '#',
            'whatsapp' => '#'
        ];
    } else {
        // التأكد من وجود المفاتيح المطلوبة
        if (!isset($settings['store_name'])) $settings['store_name'] = 'مخيط';
        if (!isset($settings['store_description'])) $settings['store_description'] = 'متجر مخيط هو وجهتك الأولى لشراء المنتجات عالية الجودة بأسعار مناسبة.';
        if (!isset($settings['phone'])) $settings['phone'] = '1129291832';
        if (!isset($settings['email'])) $settings['email'] = 'info@makhit.com';
        if (!isset($settings['address'])) $settings['address'] = 'القاهرة, مصر';
        if (!isset($settings['facebook'])) $settings['facebook'] = '#';
        if (!isset($settings['twitter'])) $settings['twitter'] = '#';
        if (!isset($settings['instagram'])) $settings['instagram'] = '#';
        if (!isset($settings['whatsapp'])) $settings['whatsapp'] = '#';
    }
} catch (Exception $e) {
    $settings = [
        'store_name' => 'مخيط',
        'store_description' => 'متجر مخيط هو وجهتك الأولى لشراء المنتجات عالية الجودة بأسعار مناسبة.',
        'phone' => '1129291832',
        'email' => 'info@makhit.com',
        'address' => 'القاهرة, مصر',
        'facebook' => '#',
        'twitter' => '#',
        'instagram' => '#',
        'whatsapp' => '#'
    ];
}

// Include header
include 'includes/header.php';
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>المنتجات - مخيط</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css" rel="stylesheet">
    <!-- إضافة خط جديد -->
    <link href="https://fonts.googleapis.com/css2?family=Tajawal:wght@200;300;400;500;700;800;900&display=swap" rel="stylesheet">
    <style>
        /* تطبيق الخط الجديد */
        body {
            font-family: 'Tajawal', sans-serif;
        }
        
        /* تخصيص الخط للعناوين */
        h1, h2, h3, h4, h5, h6 {
            font-family: 'Tajawal', sans-serif;
            font-weight: 700;
        }
        
        /* تخصيص الخط للأزرار */
        .btn {
            font-family: 'Tajawal', sans-serif;
            font-weight: 500;
        }
        
        /* تخصيص الخط للنصوص */
        p, span, a {
            font-family: 'Tajawal', sans-serif;
            font-weight: 400;
        }
        
        /* تخصيص الخط للأسعار */
        .price {
            font-family: 'Tajawal', sans-serif;
            font-weight: 700;
        }
        
        /* تخصيص الخط للتنقل */
        .pagination .page-link {
            font-family: 'Tajawal', sans-serif;
            font-weight: 500;
        }
        
        /* تخصيص الخط للتنبيهات */
        .toast-body {
            font-family: 'Tajawal', sans-serif;
            font-weight: 400;
        }
        
        /* تخصيص الخط للبحث */
        .form-control {
            font-family: 'Tajawal', sans-serif;
            font-weight: 400;
        }
        
        /* تخصيص الخط للتصنيفات */
        .category-badge {
            font-family: 'Tajawal', sans-serif;
            font-weight: 500;
        }
        
        /* تخصيص الخط للتصنيف */
        .sort-select {
            font-family: 'Tajawal', sans-serif;
            font-weight: 400;
        }

        /* تخصيص التقييمات */
        .product-rating {
            display: flex;
            align-items: center;
            gap: 0.25rem;
        }

        .product-rating i {
            font-size: 1.1rem;
        }

        .product-card {
            border: none;
            border-radius: 10px;
            box-shadow: 0 2px 15px rgba(0,0,0,0.1);
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }

        .product-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 5px 20px rgba(0,0,0,0.15);
        }

        .product-image {
            position: relative;
            overflow: hidden;
            border-radius: 10px 10px 0 0;
            height: 200px;
        }

        .product-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: transform 0.3s ease;
        }

        .product-card:hover .product-image img {
            transform: scale(1.05);
        }

        /* تنسيق الشارات */
        .product-badges {
            position: absolute;
            top: 10px;
            right: 10px;
            z-index: 1;
            display: flex;
            flex-direction: column;
            gap: 5px;
        }

        .product-badge {
            display: inline-block;
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: bold;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            color: #fff;
        }

        .stock-badge {
            background: #198754;
        }

        .stock-badge.bg-danger {
            background: #dc3545;
        }

        .stock-badge.bg-warning {
            background: #ffc107;
            color: #000;
        }

        .stock-badge.bg-success {
            background: #198754;
        }

        .discount-badge {
            background: #dc3545;
        }

        .category-badge {
            background: #0dcaf0;
        }

        @media (max-width: 768px) {
            .product-image {
                height: 150px;
            }
        }

        
    </style>
</head>

<body>
<div class="container py-5">
    <!-- Filter Section -->
    <div class="row mb-4">
        <div class="col-md-12">
            <form action="" method="GET" class="card p-3">
                <div class="row g-3">
                    <div class="col-md-4">
                        <select name="category" class="form-select">
                            <option value="">جميع الفئات</option>
                            <?php foreach ($categories as $category): ?>
                                <option value="<?= $category['id'] ?>" <?= $category_id == $category['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($category['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <input type="text" name="search" class="form-control" placeholder="ابحث عن منتج..." value="<?= htmlspecialchars($search) ?>">
                    </div>
                    <div class="col-md-2">
                        <select name="sort" class="form-select">
                            <option value="newest" <?= $sort == 'newest' ? 'selected' : '' ?>>الأحدث</option>
                            <option value="price_asc" <?= $sort == 'price_asc' ? 'selected' : '' ?>>السعر: من الأقل إلى الأعلى</option>
                            <option value="price_desc" <?= $sort == 'price_desc' ? 'selected' : '' ?>>السعر: من الأعلى إلى الأقل</option>
                            <option value="rating" <?= $sort == 'rating' ? 'selected' : '' ?>>التقييم</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <button type="submit" class="btn btn-primary w-100">تصفية</button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- Products Grid -->
    <div class="row g-4">
        <?php if (empty($products)): ?>
            <div class="col-12 text-center py-5">
                <i class="fas fa-search fa-3x text-muted mb-3"></i>
                <h3>لم يتم العثور على منتجات</h3>
                <p class="text-muted">جرب تغيير معايير البحث</p>
            </div>
        <?php else: ?>
            <?php foreach ($products as $product): ?>
                <div class="col-md-3">
                    <div class="card h-100 product-card">
                        <div class="product-image">
                            <?php if (!empty($product['image'])): ?>
                                <img src="assets/images/products/<?php echo htmlspecialchars($product['image']); ?>" 
                                     class="card-img-top" 
                                     alt="<?php echo htmlspecialchars($product['name']); ?>"
                                     onerror="this.src='assets/images/no-image.png'">
                            <?php else: ?>
                                <img src="assets/images/no-image.png" 
                                     class="card-img-top" 
                                     alt="لا توجد صورة">
                            <?php endif; ?>
                            <div class="product-badges">
                                <?php if ($product['stock'] <= 0): ?>
                                    <span class="product-badge stock-badge bg-danger">غير متوفر</span>
                                <?php elseif ($product['stock'] <= 5): ?>
                                    <span class="product-badge stock-badge bg-warning">آخر <?php echo $product['stock']; ?> قطع</span>
                                <?php else: ?>
                                    <span class="product-badge stock-badge bg-success">متوفر</span>
                                <?php endif; ?>
                                <?php if ($product['discount'] > 0): ?>
                                    <span class="product-badge discount-badge">خصم <?php echo $product['discount']; ?>%</span>
                                <?php endif; ?>
                                <span class="product-badge category-badge">
                                    <?php echo htmlspecialchars($product['category_name']); ?>
                                </span>
                            </div>
                        </div>
                        <div class="card-body">
                            <h5 class="card-title">
                                <a href="product.php?id=<?php echo $product['id']; ?>" class="text-decoration-none text-dark">
                                    <?php echo htmlspecialchars($product['name']); ?>
                                </a>
                            </h5>
                            <div class="product-rating mb-2">
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
                                <small class="text-muted">(<?= $product['reviews_count'] ?> تقييم)</small>
                            </div>
                            <p class="card-text price"><?= number_format($product['price'], 2) ?> ج.م</p>
                            <div class="product-actions">
                                <a href="product.php?id=<?php echo $product['id']; ?>" class="btn btn-primary w-100">
                                    <i class="bi bi-eye"></i> عرض التفاصيل
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <!-- Pagination -->
    <?php if ($total_pages > 1): ?>
        <nav class="mt-4">
            <ul class="pagination justify-content-center">
                <?php if ($page > 1): ?>
                    <li class="page-item">
                        <a class="page-link" href="?page=<?= $page - 1 ?>&category=<?= $category_id ?>&search=<?= urlencode($search) ?>&sort=<?= $sort ?>">السابق</a>
                    </li>
                <?php endif; ?>
                
                <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                    <li class="page-item <?= $i == $page ? 'active' : '' ?>">
                        <a class="page-link" href="?page=<?= $i ?>&category=<?= $category_id ?>&search=<?= urlencode($search) ?>&sort=<?= $sort ?>"><?= $i ?></a>
                    </li>
                <?php endfor; ?>
                
                <?php if ($page < $total_pages): ?>
                    <li class="page-item">
                        <a class="page-link" href="?page=<?= $page + 1 ?>&category=<?= $category_id ?>&search=<?= urlencode($search) ?>&sort=<?= $sort ?>">التالي</a>
                    </li>
                <?php endif; ?>
            </ul>
        </nav>
    <?php endif; ?>
</div>

<!-- Toast Container -->
<div class="toast-container position-fixed bottom-0 end-0 p-3">
    <div id="toast" class="toast" role="alert" aria-live="assertive" aria-atomic="true">
        <div class="toast-body"></div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
function showLoginAlert() {
    Swal.fire({
        title: 'تسجيل الدخول مطلوب',
        text: 'يجب تسجيل الدخول أولاً لإضافة منتج إلى السلة',
        icon: 'info',
        showCancelButton: true,
        confirmButtonText: 'تسجيل الدخول',
        cancelButtonText: 'إلغاء',
        reverseButtons: true
    }).then((result) => {
        if (result.isConfirmed) {
            window.location.href = 'login.php';
        }
    });
}

document.addEventListener('DOMContentLoaded', function() {
    // جلب عناصر التصفية
    const categorySelect = document.querySelector('select[name="category"]');
    const searchInput = document.querySelector('input[name="search"]');
    const sortSelect = document.querySelector('select[name="sort"]');
    const filterForm = document.querySelector('form');

    // إضافة مستمعي أحداث للعناصر
    categorySelect.addEventListener('change', () => filterForm.submit());
    sortSelect.addEventListener('change', () => filterForm.submit());
    
    // إضافة تأخير للبحث لتجنب الطلبات المتكررة
    let searchTimeout;
    searchInput.addEventListener('input', () => {
        clearTimeout(searchTimeout);
        searchTimeout = setTimeout(() => filterForm.submit(), 500);
    });
});

// إزالة منتج من السلة
document.addEventListener('click', function(e) {
    if (e.target.closest('.remove-item')) {
        const button = e.target.closest('.remove-item');
        const itemId = button.dataset.id;
        
        fetch('remove_from_cart.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                item_id: itemId
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                updateCart();
            }
        })
        .catch(error => {
            console.error('Error:', error);
        });
    }
});

// إضافة/إزالة من المفضلة
document.querySelectorAll('.toggle-favorite').forEach(button => {
    button.addEventListener('click', function() {
        const productId = this.dataset.productId;
        
        // إظهار مؤشر التحميل
        const icon = this.querySelector('i');
        const originalClass = icon.className;
        icon.className = 'bi bi-arrow-repeat spin';
        this.disabled = true;
        
        fetch('toggle_favorite.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                product_id: productId
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // تحديث حالة الزر
                this.classList.toggle('active');
                icon.className = this.classList.contains('active') ? 'bi bi-heart-fill' : 'bi bi-heart';
                
                // إظهار رسالة نجاح
                const toast = document.createElement('div');
                toast.className = 'toast align-items-center text-white bg-success border-0 position-fixed top-0 start-50 translate-middle-x mt-3';
                toast.setAttribute('role', 'alert');
                toast.setAttribute('aria-live', 'assertive');
                toast.setAttribute('aria-atomic', 'true');
                toast.innerHTML = `
                    <div class="d-flex">
                        <div class="toast-body">
                            <i class="bi bi-check-circle me-2"></i> ${data.message}
                        </div>
                        <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
                    </div>
                `;
                document.body.appendChild(toast);
                const bsToast = new bootstrap.Toast(toast);
                bsToast.show();
                setTimeout(() => toast.remove(), 3000);
            } else {
                // إظهار رسالة خطأ
                const toast = document.createElement('div');
                toast.className = 'toast align-items-center text-white bg-danger border-0 position-fixed top-0 start-50 translate-middle-x mt-3';
                toast.setAttribute('role', 'alert');
                toast.setAttribute('aria-live', 'assertive');
                toast.setAttribute('aria-atomic', 'true');
                toast.innerHTML = `
                    <div class="d-flex">
                        <div class="toast-body">
                            <i class="bi bi-exclamation-circle me-2"></i> ${data.message || 'حدث خطأ أثناء تحديث المفضلة'}
                        </div>
                        <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
                    </div>
                `;
                document.body.appendChild(toast);
                const bsToast = new bootstrap.Toast(toast);
                bsToast.show();
                setTimeout(() => toast.remove(), 3000);
            }
        })
        .catch(error => {
            console.error('Error:', error);
            // إظهار رسالة خطأ
            const toast = document.createElement('div');
            toast.className = 'toast align-items-center text-white bg-danger border-0 position-fixed top-0 start-50 translate-middle-x mt-3';
            toast.setAttribute('role', 'alert');
            toast.setAttribute('aria-live', 'assertive');
            toast.setAttribute('aria-atomic', 'true');
            toast.innerHTML = `
                <div class="d-flex">
                    <div class="toast-body">
                        <i class="bi bi-exclamation-circle me-2"></i> حدث خطأ أثناء تحديث المفضلة
                    </div>
                    <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
                </div>
            `;
            document.body.appendChild(toast);
            const bsToast = new bootstrap.Toast(toast);
            bsToast.show();
            setTimeout(() => toast.remove(), 3000);
        })
        .finally(() => {
            // إعادة الزر إلى حالته الأصلية
            this.disabled = false;
        });
    });
});

// تحديث السلة عند تحميل الصفحة
updateCart();
</script>
</body>
</html> 