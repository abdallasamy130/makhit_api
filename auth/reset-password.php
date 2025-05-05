<?php
session_start();
require_once 'config/database.php';

// التحقق من تسجيل الدخول بالفعل
if (isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit();
}

$success_message = '';
$error_message = '';
$token_valid = false;
$user_id = null;

// التحقق من وجود رمز إعادة التعيين
if (isset($_GET['token']) && !empty($_GET['token'])) {
    $token = $_GET['token'];
    
    // التحقق من صحة الرمز وعدم انتهاء صلاحيته
    $stmt = $pdo->prepare("SELECT id FROM users WHERE reset_token = ? AND reset_expiry > NOW()");
    $stmt->execute([$token]);
    $user = $stmt->fetch();
    
    if ($user) {
        $token_valid = true;
        $user_id = $user['id'];
    } else {
        $error_message = "رابط إعادة تعيين كلمة المرور غير صالح أو منتهي الصلاحية. يرجى طلب رابط جديد.";
    }
} else {
    $error_message = "رابط إعادة تعيين كلمة المرور غير صالح. يرجى طلب رابط جديد.";
}

// معالجة نموذج إعادة تعيين كلمة المرور
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $token_valid) {
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    
    // التحقق من تطابق كلمتي المرور
    if ($password !== $confirm_password) {
        $error_message = "كلمتا المرور غير متطابقتين.";
    } elseif (strlen($password) < 8) {
        $error_message = "يجب أن تكون كلمة المرور 8 أحرف على الأقل.";
    } else {
        // تشفير كلمة المرور الجديدة
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        
        // تحديث كلمة المرور وإزالة رمز إعادة التعيين
        $stmt = $pdo->prepare("UPDATE users SET password = ?, reset_token = NULL, reset_expiry = NULL WHERE id = ?");
        $stmt->execute([$hashed_password, $user_id]);
        
        if ($stmt->rowCount() > 0) {
            $success_message = "تم إعادة تعيين كلمة المرور بنجاح. يمكنك الآن تسجيل الدخول باستخدام كلمة المرور الجديدة.";
        } else {
            $error_message = "حدث خطأ أثناء إعادة تعيين كلمة المرور. يرجى المحاولة مرة أخرى.";
        }
    }
}

// تضمين الهيدر
include 'includes/header.php';
?>

<!-- قسم إعادة تعيين كلمة المرور -->
<div class="reset-password-section py-5">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-6 col-lg-5">
                <div class="card reset-password-card">
                    <div class="card-body p-4 p-md-5">
                        <div class="text-center mb-4">
                            <i class="bi bi-shield-lock-fill reset-password-icon"></i>
                            <h2 class="reset-password-title">إعادة تعيين كلمة المرور</h2>
                            <p class="text-muted">أدخل كلمة المرور الجديدة</p>
                        </div>
                        
                        <?php if (!empty($success_message)): ?>
                            <div class="alert alert-success alert-dismissible fade show" role="alert">
                                <i class="bi bi-check-circle-fill me-2"></i>
                                <?php echo $success_message; ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                            </div>
                            <div class="text-center mt-4">
                                <a href="login.php" class="btn btn-primary">تسجيل الدخول</a>
                            </div>
                        <?php elseif (!empty($error_message)): ?>
                            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                                <i class="bi bi-exclamation-triangle-fill me-2"></i>
                                <?php echo $error_message; ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                            </div>
                            <?php if (!$token_valid): ?>
                                <div class="text-center mt-4">
                                    <a href="forgot-password.php" class="btn btn-primary">طلب رابط جديد</a>
                                </div>
                            <?php endif; ?>
                        <?php endif; ?>
                        
                        <?php if ($token_valid && empty($success_message)): ?>
                            <form method="POST" action="reset-password.php?token=<?php echo htmlspecialchars($_GET['token']); ?>" class="reset-password-form">
                                <div class="form-floating mb-3">
                                    <input type="password" class="form-control" id="password" name="password" placeholder="كلمة المرور الجديدة" required minlength="8">
                                    <label for="password">كلمة المرور الجديدة</label>
                                </div>
                                
                                <div class="form-floating mb-4">
                                    <input type="password" class="form-control" id="confirm_password" name="confirm_password" placeholder="تأكيد كلمة المرور" required minlength="8">
                                    <label for="confirm_password">تأكيد كلمة المرور</label>
                                </div>
                                
                                <div class="d-grid mb-4">
                                    <button type="submit" class="btn btn-primary btn-lg reset-btn">
                                        <i class="bi bi-check-lg me-2"></i> إعادة تعيين كلمة المرور
                                    </button>
                                </div>
                            </form>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
    .reset-password-section {
        background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
        min-height: calc(100vh - 300px);
        display: flex;
        align-items: center;
    }
    
    .reset-password-card {
        border: none;
        border-radius: 15px;
        box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
        overflow: hidden;
        transition: transform 0.3s ease, box-shadow 0.3s ease;
    }
    
    .reset-password-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 15px 35px rgba(0, 0, 0, 0.15);
    }
    
    .reset-password-icon {
        font-size: 3rem;
        color: #28a745;
        margin-bottom: 1rem;
        display: inline-block;
        animation: pulse 2s infinite;
    }
    
    @keyframes pulse {
        0% { transform: scale(1); }
        50% { transform: scale(1.1); }
        100% { transform: scale(1); }
    }
    
    .reset-password-title {
        font-size: 2rem;
        font-weight: 700;
        color: #343a40;
        margin-bottom: 0.5rem;
    }
    
    .form-floating > .form-control {
        padding: 1rem 0.75rem;
        height: calc(3.5rem + 2px);
        line-height: 1.25;
        border-radius: 10px;
        border: 1px solid #ced4da;
    }
    
    .form-floating > label {
        padding: 1rem 0.75rem;
    }
    
    .form-control:focus {
        border-color: #28a745;
        box-shadow: 0 0 0 0.25rem rgba(40, 167, 69, 0.25);
    }
    
    .reset-btn {
        background: linear-gradient(45deg, #28a745, #218838);
        border: none;
        padding: 12px;
        font-weight: 600;
        border-radius: 10px;
        transition: all 0.3s ease;
    }
    
    .reset-btn:hover {
        transform: translateY(-2px);
        box-shadow: 0 5px 15px rgba(40, 167, 69, 0.4);
    }
    
    @media (max-width: 768px) {
        .reset-password-section {
            padding: 30px 0;
        }
        
        .reset-password-card {
            margin: 0 15px;
        }
    }
</style>

<?php
// تضمين الفوتر
include 'includes/footer.php';
?> 