<?php
session_start();
require_once 'includes/db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'];
    $email = $_POST['email'];
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    
    // Validate input
    $errors = [];
    
    if (strlen($username) < 3) {
        $errors[] = "اسم المستخدم يجب أن يكون 3 أحرف على الأقل";
    }
    
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "البريد الإلكتروني غير صحيح";
    }
    
    if (strlen($password) < 6) {
        $errors[] = "كلمة المرور يجب أن تكون 6 أحرف على الأقل";
    }
    
    if ($password !== $confirm_password) {
        $errors[] = "كلمتا المرور غير متطابقتين";
    }
    
    // Check if email or username already exists
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE email = ? OR username = ?");
    $stmt->execute([$email, $username]);
    if ($stmt->fetchColumn() > 0) {
        $errors[] = "البريد الإلكتروني أو اسم المستخدم موجود بالفعل";
    }
    
    if (empty($errors)) {
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        
        $stmt = $pdo->prepare("INSERT INTO users (username, email, password) VALUES (?, ?, ?)");
        if ($stmt->execute([$username, $email, $hashed_password])) {
            $_SESSION['success'] = "تم إنشاء الحساب بنجاح. يمكنك الآن تسجيل الدخول";
            header('Location: login.php');
            exit();
        } else {
            $errors[] = "حدث خطأ أثناء إنشاء الحساب";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>إنشاء حساب - مخيط</title>
    <link rel="shortcut icon" href="assets/images/makhit-favicon.ico" type="image/x-icon">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@200;300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        :root {
            --primary-color: #2c3e50;
            --secondary-color: #34495e;
            --accent-color: #3498db;
            --success-color: #27ae60;
            --warning-color: #f1c40f;
            --danger-color: #e74c3c;
            --light-bg: #f8f9fa;
            --border-color: #e9ecef;
        }
        
        body {
            background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            padding: 2rem 0;
            font-family: 'Cairo', sans-serif;
            direction: rtl;
        }
        
        .register-container {
            max-width: 500px;
            margin: 0 auto;
        }
        
        .card {
            border: none;
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
            overflow: hidden;
        }
        
        .card-header {
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            color: white;
            padding: 1.5rem;
            border: none;
            text-align: center;
        }
        
        .card-header h3 {
            margin: 0;
            font-weight: 600;
        }
        
        .card-body {
            padding: 2rem;
            background-color: white;
        }
        
        .form-label {
            font-weight: 500;
            color: var(--secondary-color);
            margin-bottom: 0.5rem;
            text-align: right;
        }
        
        .form-control {
            border: 2px solid var(--border-color);
            border-radius: 10px;
            padding: 0.75rem 1rem;
            transition: all 0.3s ease;
            text-align: right;
        }
        
        .form-control:focus {
            border-color: var(--accent-color);
            box-shadow: 0 0 0 0.25rem rgba(52, 152, 219, 0.25);
        }
        
        .input-group-text {
            background-color: var(--light-bg);
            border: 2px solid var(--border-color);
            border-right: none;
            border-radius: 10px 0 0 10px;
            color: var(--secondary-color);
        }
        
        .input-group .form-control {
            border-left: none;
            border-radius: 0 10px 10px 0;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, var(--accent-color), #2980b9);
            border: none;
            padding: 0.75rem 2rem;
            font-weight: 600;
            border-radius: 10px;
            transition: all 0.3s ease;
        }
        
        .btn-primary:hover {
            background: linear-gradient(135deg, #2980b9, var(--accent-color));
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(52, 152, 219, 0.3);
        }
        
        .alert {
            border-radius: 10px;
            padding: 1rem 1.5rem;
            margin-bottom: 1.5rem;
            border: none;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
            text-align: right;
        }
        
        .alert-danger {
            background-color: rgba(231, 76, 60, 0.1);
            color: var(--danger-color);
        }
        
        .register-link {
            color: var(--accent-color);
            text-decoration: none;
            font-weight: 500;
            transition: all 0.3s ease;
        }
        
        .register-link:hover {
            color: var(--primary-color);
            text-decoration: underline;
        }
        
        .password-toggle {
            cursor: pointer;
        }
        
        .form-check {
            margin-top: 1rem;
            text-align: right;
        }
        
        .form-check-input {
            margin-left: 0.5rem;
            margin-right: 0;
        }
        
        .form-check-label {
            margin-right: 1.5rem;
        }
        
        .text-center {
            text-align: center !important;
        }
        
        .me-2 {
            margin-left: 0.5rem !important;
            margin-right: 0 !important;
        }
        
        .mb-4 {
            margin-bottom: 1.5rem !important;
        }
        
        .mt-3 {
            margin-top: 1rem !important;
        }
        
        .mt-4 {
            margin-top: 1.5rem !important;
        }
        
        .p-5 {
            padding: 3rem !important;
        }
        
        @media (max-width: 576px) {
            .card-body {
                padding: 1.5rem;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-6">
                <div class="card shadow-sm">
                    <div class="card-body p-5">
                        <div class="text-center mb-4">
                            <img src="assets/images/logo.png" alt="مخيط" class="img-fluid" style="max-height: 100px;">
                            <h4 class="mt-3">إنشاء حساب جديد</h4>
                        </div>
                        <?php if (isset($error)): ?>
                            <div class="alert alert-danger">
                                <i class="bi bi-exclamation-triangle-fill me-2"></i>
                                <?php echo $error; ?>
                            </div>
                        <?php endif; ?>
                        
                        <form method="POST" action="register.php" class="needs-validation" novalidate>
                            <div class="mb-4">
                                <label for="username" class="form-label">اسم المستخدم</label>
                                <div class="input-group">
                                    <input type="text" class="form-control" id="username" name="username" required>
                                    <span class="input-group-text"><i class="bi bi-person"></i></span>
                                </div>
                                <div class="invalid-feedback">الرجاء إدخال اسم المستخدم</div>
                            </div>
                            
                            <div class="mb-4">
                                <label for="email" class="form-label">البريد الإلكتروني</label>
                                <div class="input-group">
                                    <input type="email" class="form-control" id="email" name="email" required>
                                    <span class="input-group-text"><i class="bi bi-envelope"></i></span>
                                </div>
                                <div class="invalid-feedback">الرجاء إدخال بريد إلكتروني صحيح</div>
                            </div>
                            
                            <div class="mb-4">
                                <label for="password" class="form-label">كلمة المرور</label>
                                <div class="input-group">
                                    <input type="password" class="form-control" id="password" name="password" required>
                                    <span class="input-group-text password-toggle" id="password-toggle">
                                        <i class="bi bi-eye"></i>
                                    </span>
                                </div>
                                <div class="invalid-feedback">الرجاء إدخال كلمة المرور</div>
                            </div>
                            
                            <div class="mb-4">
                                <label for="confirm_password" class="form-label">تأكيد كلمة المرور</label>
                                <div class="input-group">
                                    <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
                                    <span class="input-group-text password-toggle" id="confirm-password-toggle">
                                        <i class="bi bi-eye"></i>
                                    </span>
                                </div>
                                <div class="invalid-feedback">الرجاء تأكيد كلمة المرور</div>
                            </div>
                            
                            <div class="d-grid">
                                <button type="submit" class="btn btn-primary">
                                    <i class="bi bi-person-plus me-2"></i> إنشاء حساب
                                </button>
                            </div>
                        </form>
                        
                        <div class="text-center mt-4">
                            <p>لديك حساب بالفعل؟ <a href="login.php" class="register-link">تسجيل الدخول</a></p>
                            <a href="index.php" class="btn btn-outline-secondary mt-3">
                                <i class="bi bi-house-door"></i> العودة للرئيسية
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // تفعيل التحقق من صحة النموذج
            const form = document.querySelector('.needs-validation');
            form.addEventListener('submit', function(event) {
                if (!form.checkValidity()) {
                    event.preventDefault();
                    event.stopPropagation();
                }
                form.classList.add('was-validated');
            });
            
            // إظهار/إخفاء كلمة المرور
            const passwordToggles = document.querySelectorAll('.password-toggle');
            passwordToggles.forEach(toggle => {
                toggle.addEventListener('click', function() {
                    const input = this.parentElement.querySelector('input');
                    const icon = this.querySelector('i');
                    
                    if (input.type === 'password') {
                        input.type = 'text';
                        icon.classList.remove('bi-eye');
                        icon.classList.add('bi-eye-slash');
                    } else {
                        input.type = 'password';
                        icon.classList.remove('bi-eye-slash');
                        icon.classList.add('bi-eye');
                    }
                });
            });
        });
    </script>
</body>
</html> 