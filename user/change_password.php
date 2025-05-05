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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $current_password = $_POST['current_password'] ?? '';
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    // التحقق من إدخال جميع الحقول
    if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
        $error = 'الرجاء إدخال جميع الحقول المطلوبة';
    }
    // التحقق من تطابق كلمتي المرور الجديدتين
    elseif ($new_password !== $confirm_password) {
        $error = 'كلمتا المرور الجديدتان غير متطابقتين';
    }
    // التحقق من طول كلمة المرور الجديدة
    elseif (strlen($new_password) < 6) {
        $error = 'يجب أن تكون كلمة المرور الجديدة 6 أحرف على الأقل';
    }
    else {
        try {
            // التحقق من صحة كلمة المرور الحالية
            $stmt = $pdo->prepare("SELECT password FROM users WHERE id = ?");
            $stmt->execute([$user_id]);
            $user = $stmt->fetch();

            if ($user && password_verify($current_password, $user['password'])) {
                // تحديث كلمة المرور
                $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
                $stmt->execute([$hashed_password, $user_id]);
                
                $success = 'تم تغيير كلمة المرور بنجاح';
            } else {
                $error = 'كلمة المرور الحالية غير صحيحة';
            }
        } catch (Exception $e) {
            $error = 'حدث خطأ أثناء تغيير كلمة المرور';
        }
    }
}
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>تغيير كلمة المرور - مخيط</title>
    <link rel="shortcut icon" href="assets/images/makhit-favicon.ico" type="image/x-icon">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .change-password {
            background-color: #f8f9fa;
            min-height: 100vh;
            padding: 2rem 0;
        }
        
        .password-card {
            background: white;
            border-radius: 15px;
            box-shadow: 0 0 20px rgba(0,0,0,0.1);
            max-width: 500px;
            margin: 0 auto;
        }
        
        .password-card .card-header {
            background: #f8f9fa;
            border-bottom: 1px solid #eee;
            padding: 1.25rem;
        }
        
        .password-card .card-body {
            padding: 1.5rem;
        }
        
        .form-floating > .form-control:focus ~ label,
        .form-floating > .form-control:not(:placeholder-shown) ~ label {
            color: #0d6efd;
        }
        
        .password-toggle {
            position: absolute;
            right: 10px;
            top: 50%;
            transform: translateY(-50%);
            cursor: pointer;
            color: #666;
        }
        
        .password-toggle:hover {
            color: #0d6efd;
        }
    </style>
</head>
<body>
    <?php include 'includes/header.php'; ?>

    <div class="change-password">
        <div class="container">
            <div class="password-card">
                <div class="card-header">
                    <h5 class="mb-0">
                        <i class="fas fa-lock me-2"></i>
                        تغيير كلمة المرور
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
                        <div class="form-floating mb-3 position-relative">
                            <input type="password" class="form-control" id="current_password" name="current_password" placeholder="كلمة المرور الحالية" required>
                            <label for="current_password">كلمة المرور الحالية</label>
                            <i class="fas fa-eye password-toggle" onclick="togglePassword('current_password')"></i>
                        </div>
                        
                        <div class="form-floating mb-3 position-relative">
                            <input type="password" class="form-control" id="new_password" name="new_password" placeholder="كلمة المرور الجديدة" required>
                            <label for="new_password">كلمة المرور الجديدة</label>
                            <i class="fas fa-eye password-toggle" onclick="togglePassword('new_password')"></i>
                        </div>
                        
                        <div class="form-floating mb-3 position-relative">
                            <input type="password" class="form-control" id="confirm_password" name="confirm_password" placeholder="تأكيد كلمة المرور الجديدة" required>
                            <label for="confirm_password">تأكيد كلمة المرور الجديدة</label>
                            <i class="fas fa-eye password-toggle" onclick="togglePassword('confirm_password')"></i>
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
    <script>
        function togglePassword(inputId) {
            const input = document.getElementById(inputId);
            const icon = input.nextElementSibling.nextElementSibling;
            
            if (input.type === 'password') {
                input.type = 'text';
                icon.classList.remove('fa-eye');
                icon.classList.add('fa-eye-slash');
            } else {
                input.type = 'password';
                icon.classList.remove('fa-eye-slash');
                icon.classList.add('fa-eye');
            }
        }
    </script>
</body>
</html> 