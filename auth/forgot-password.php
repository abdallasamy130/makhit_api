<?php
session_start();
require_once 'includes/db.php';

// التحقق من تسجيل الدخول بالفعل
if (isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit();
}

$success_message = '';
$error_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = filter_var($_POST['username'], FILTER_SANITIZE_STRING);
    $email = filter_var($_POST['email'], FILTER_SANITIZE_EMAIL);
    $phone = filter_var($_POST['phone'], FILTER_SANITIZE_STRING);
    
    if ((!empty($username) || filter_var($email, FILTER_VALIDATE_EMAIL)) && !empty($phone)) {
        try {
            // التحقق من وجود المستخدم في قاعدة البيانات باستخدام اسم المستخدم أو البريد الإلكتروني
            $stmt = $pdo->prepare("SELECT id, username, email FROM users WHERE username = ? OR email = ?");
            $stmt->execute([$username, $email]);
            $user = $stmt->fetch();
            
            if ($user) {
                // التحقق من عدم وجود طلب سابق في آخر 24 ساعة
                $stmt = $pdo->prepare("SELECT id FROM password_reset_requests WHERE user_id = ? AND created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)");
                $stmt->execute([$user['id']]);
                $existing_request = $stmt->fetch();
                
                if ($existing_request) {
                    $error_message = "تم تقديم طلب استرجاع كلمة المرور مؤخراً. يرجى الانتظار 24 ساعة قبل تقديم طلب جديد.";
                } else {
                    // تسجيل طلب استرجاع كلمة المرور مع رقم الهاتف
                    $stmt = $pdo->prepare("INSERT INTO password_reset_requests (user_id, phone, status, created_at) VALUES (?, ?, 'pending', NOW())");
                    $stmt->execute([$user['id'], $phone]);
                    
                    $success_message = "تم استلام طلب استرجاع كلمة المرور بنجاح. سيتم التواصل معك قريباً عبر رقم الهاتف المدخل.";
                }
            } else {
                $error_message = "المعلومات المدخلة غير صحيحة. يرجى التحقق من البيانات والمحاولة مرة أخرى.";
            }
        } catch (PDOException $e) {
            error_log("Password reset request error: " . $e->getMessage());
            $error_message = "حدث خطأ أثناء معالجة طلبك. يرجى المحاولة مرة أخرى لاحقاً.";
        }
    } else {
        $error_message = "يرجى إدخال اسم المستخدم أو البريد الإلكتروني ورقم الهاتف بشكل صحيح.";
    }
}

// تضمين الهيدر
include 'includes/header.php';
?>

<!-- قسم نسيت كلمة المرور -->
<div class="forgot-password-section py-5">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-6 col-lg-5">
                <div class="card forgot-password-card">
                    <div class="card-body p-4 p-md-5">
                        <div class="text-center mb-4">
                            <i class="bi bi-key-fill forgot-password-icon"></i>
                            <h2 class="forgot-password-title">نسيت كلمة المرور؟</h2>
                            <p class="text-muted">أدخل اسم المستخدم أو البريد الإلكتروني للتحقق من هويتك</p>
                        </div>
                        
                        <?php if (!empty($success_message)): ?>
                            <div class="alert alert-success alert-dismissible fade show" role="alert">
                                <i class="bi bi-check-circle-fill me-2"></i>
                                <?php echo $success_message; ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                            </div>
                        <?php endif; ?>
                        
                        <?php if (!empty($error_message)): ?>
                            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                                <i class="bi bi-exclamation-triangle-fill me-2"></i>
                                <?php echo $error_message; ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                            </div>
                        <?php endif; ?>
                        
                        <form method="POST" action="forgot-password.php" class="forgot-password-form">
                            <div class="form-floating mb-3">
                                <input type="text" class="form-control" id="username" name="username" placeholder="اسم المستخدم">
                                <label for="username">اسم المستخدم</label>
                            </div>
                            
                            <div class="form-floating mb-3">
                                <input type="email" class="form-control" id="email" name="email" placeholder="البريد الإلكتروني">
                                <label for="email">البريد الإلكتروني</label>
                            </div>
                            
                            <div class="form-floating mb-4">
                                <input type="tel" class="form-control" id="phone" name="phone" placeholder="رقم الهاتف (اختياري)">
                                <label for="phone">رقم الهاتف (اختياري)</label>
                            </div>
                            
                            <div class="d-grid mb-4">
                                <button type="submit" class="btn btn-primary btn-lg reset-btn">
                                    <i class="bi bi-send-fill me-2"></i> إرسال الطلب
                                </button>
                            </div>
                        </form>
                        
                        <div class="text-center mt-4">
                            <p class="mb-0">تذكرت كلمة المرور؟ <a href="login.php" class="text-decoration-none">تسجيل الدخول</a></p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
    .forgot-password-section {
        background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
        min-height: calc(100vh - 300px);
        display: flex;
        align-items: center;
    }
    
    .forgot-password-card {
        border: none;
        border-radius: 15px;
        box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
        overflow: hidden;
        transition: transform 0.3s ease, box-shadow 0.3s ease;
    }
    
    .forgot-password-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 15px 35px rgba(0, 0, 0, 0.15);
    }
    
    .forgot-password-icon {
        font-size: 3rem;
        color: #dc3545;
        margin-bottom: 1rem;
        display: inline-block;
        animation: pulse 2s infinite;
    }
    
    @keyframes pulse {
        0% { transform: scale(1); }
        50% { transform: scale(1.1); }
        100% { transform: scale(1); }
    }
    
    .forgot-password-title {
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
        border-color: #dc3545;
        box-shadow: 0 0 0 0.25rem rgba(220, 53, 69, 0.25);
    }
    
    .reset-btn {
        background: linear-gradient(45deg, #dc3545, #c82333);
        border: none;
        padding: 12px;
        font-weight: 600;
        border-radius: 10px;
        transition: all 0.3s ease;
    }
    
    .reset-btn:hover {
        transform: translateY(-2px);
        box-shadow: 0 5px 15px rgba(220, 53, 69, 0.4);
    }
    
    @media (max-width: 768px) {
        .forgot-password-section {
            padding: 30px 0;
        }
        
        .forgot-password-card {
            margin: 0 15px;
        }
    }
</style>

<?php
// تضمين الفوتر
include 'includes/footer.php';
?> 