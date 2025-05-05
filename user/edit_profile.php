<?php
session_start();
require_once 'includes/db.php';

// التحقق من تسجيل الدخول
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

$user_id = $_SESSION['user_id'];
$error = '';
$success = '';

// جلب بيانات المستخدم الحالية
try {
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch();

    if (!$user) {
        header('Location: login.php');
        exit();
    }
} catch (Exception $e) {
    $error = 'حدث خطأ أثناء جلب بيانات المستخدم';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $phone = $_POST['phone'] ?? '';
    $city = $_POST['city'] ?? '';
    $address = $_POST['address'] ?? '';

    // التحقق من إدخال جميع الحقول المطلوبة
    if (empty($phone)) {
        $error = 'الرجاء إدخال رقم الجوال';
    }
    else {
        try {
            // تحديث بيانات المستخدم
            $stmt = $pdo->prepare("UPDATE users SET phone = ?, city = ?, address = ? WHERE id = ?");
            $stmt->execute([$phone, $city, $address, $user_id]);
            
            $success = 'تم تحديث البيانات بنجاح';
            
            // تحديث بيانات المستخدم في المتغير
            $user['phone'] = $phone;
            $user['city'] = $city;
            $user['address'] = $address;
        } catch (Exception $e) {
            $error = 'حدث خطأ أثناء تحديث البيانات';
        }
    }
}
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>تعديل الملف الشخصي - مخيط</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .edit-profile {
            background-color: #f8f9fa;
            min-height: 100vh;
            padding: 2rem 0;
        }
        
        .profile-card {
            background: white;
            border-radius: 15px;
            box-shadow: 0 0 20px rgba(0,0,0,0.1);
            max-width: 600px;
            margin: 0 auto;
        }
        
        .profile-card .card-header {
            background: #f8f9fa;
            border-bottom: 1px solid #eee;
            padding: 1.25rem;
        }
        
        .profile-card .card-body {
            padding: 1.5rem;
        }
        
        .form-floating > .form-control:focus ~ label,
        .form-floating > .form-control:not(:placeholder-shown) ~ label {
            color: #0d6efd;
        }

        .readonly-field {
            background-color: #f8f9fa;
            cursor: not-allowed;
        }
    </style>
</head>
<body>
    <?php include 'includes/header.php'; ?>

    <div class="edit-profile">
        <div class="container">
            <div class="profile-card">
                <div class="card-header">
                    <h5 class="mb-0">
                        <i class="fas fa-user-edit me-2"></i>
                        تعديل الملف الشخصي
                    </h5>
                </div>
                <div class="card-body">
                    <?php if ($error): ?>
                        <div class="alert alert-danger">
                            <i class="fas fa-exclamation-circle me-2"></i>
                            <?php echo $error; ?>
                        </div>
                    <?php endif; ?>
                    
                    <?php if ($success): ?>
                        <div class="alert alert-success">
                            <i class="fas fa-check-circle me-2"></i>
                            <?php echo $success; ?>
                        </div>
                    <?php endif; ?>

                    <form method="POST" action="">
                        <div class="form-floating mb-3">
                            <input type="text" class="form-control readonly-field" id="username" name="username" placeholder="اسم المستخدم" value="<?php echo htmlspecialchars($user['username'] ?? ''); ?>" readonly>
                            <label for="username">اسم المستخدم</label>
                            <small class="text-muted">لا يمكن تعديل اسم المستخدم</small>
                        </div>
                        
                        <div class="form-floating mb-3">
                            <input type="email" class="form-control readonly-field" id="email" name="email" placeholder="البريد الإلكتروني" value="<?php echo htmlspecialchars($user['email'] ?? ''); ?>" readonly>
                            <label for="email">البريد الإلكتروني</label>
                            <small class="text-muted">لا يمكن تعديل البريد الإلكتروني</small>
                            </div>

                        <div class="form-floating mb-3">
                            <input type="tel" class="form-control" id="phone" name="phone" placeholder="رقم الجوال" value="<?php echo htmlspecialchars($user['phone'] ?? ''); ?>" required>
                            <label for="phone">رقم الجوال</label>
                        </div>

                        <div class="form-floating mb-3">
                            <input type="text" class="form-control" id="city" name="city" placeholder="المدينة" value="<?php echo htmlspecialchars($user['city'] ?? ''); ?>">
                            <label for="city">المدينة</label>
                        </div>

                        <div class="form-floating mb-3">
                            <textarea class="form-control" id="address" name="address" placeholder="العنوان" style="height: 100px"><?php echo htmlspecialchars($user['address'] ?? ''); ?></textarea>
                            <label for="address">العنوان</label>
                        </div>

                        <div class="d-grid gap-2">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save me-2"></i>
                                حفظ التغييرات
                            </button>
                            <a href="profile.php" class="btn btn-outline-secondary">
                                <i class="fas fa-arrow-right me-2"></i>
                                العودة للصفحة الشخصية
                            </a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <?php include 'includes/footer.php'; ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html> 