<?php
require_once 'config/database.php';
session_start();

// التحقق من وجود جلسة دردشة نشطة
$active_chat = null;
if (isset($_SESSION['chat_session_id'])) {
    $chat_session_id = $_SESSION['chat_session_id'];
    
    // جلب معلومات المحادثة
    $stmt = $pdo->prepare("
        SELECT cs.*, 
               (SELECT COUNT(*) FROM chat_messages WHERE chat_session_id = cs.id AND is_read = 0 AND sender_type = 'admin') as unread_count
        FROM chat_sessions cs
        WHERE cs.id = ?
    ");
    $stmt->execute([$chat_session_id]);
    $active_chat = $stmt->fetch();
    
    if ($active_chat && $active_chat['status'] === 'closed') {
        unset($_SESSION['chat_session_id']);
        $active_chat = null;
    }
}

$page_title = 'الدردشة';
include 'includes/header.php';

// إذا تم الضغط على زر بدء محادثة جديدة وكانت هناك محادثة نشطة
if (isset($_GET['new_chat'])) {
    if ($active_chat) {
        $_SESSION['show_chat_warning'] = true;
    }
    echo '<script>window.location.href = "support.php";</script>';
    exit;
}
?>

<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-md-8">
            <div class="card shadow">
                <div class="card-body p-4">
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <h2 class="mb-0">الدردشة المباشرة</h2>
                        <button type="button" class="btn btn-primary" id="newChatBtn">
                            <i class="fas fa-plus-circle me-2"></i>
                            بدء محادثة جديدة
                        </button>
                    </div>
                    
                    <?php if ($active_chat): ?>
                    <!-- استئناف المحادثة السابقة -->
                    <a href="chat_session.php" class="text-decoration-none">
                        <div class="chat-option mb-4 p-4 border rounded hover-effect">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h5 class="mb-1">استئناف المحادثة السابقة</h5>
                                    <p class="mb-1 text-muted"><?php echo htmlspecialchars($active_chat['subject'] ?? ''); ?></p>
                                    <small class="text-muted">
                                        آخر تحديث: <?php echo isset($active_chat['updated_at']) ? date('Y-m-d H:i', strtotime($active_chat['updated_at'])) : ''; ?>
                                    </small>
                                </div>
                                <?php if (isset($active_chat['unread_count']) && $active_chat['unread_count'] > 0): ?>
                                <span class="badge bg-danger"><?php echo $active_chat['unread_count']; ?> رسائل جديدة</span>
                                <?php endif; ?>
                            </div>
                        </div>
                    </a>
                    <?php else: ?>
                    <!-- رسالة ترحيبية إذا لم تكن هناك محادثات نشطة -->
                    <div class="text-center py-5">
                        <i class="fas fa-comments fa-3x text-primary mb-3"></i>
                        <h4 class="mb-4">مرحباً بك في خدمة الدردشة المباشرة</h4>
                        <p class="text-muted mb-4">يمكنك بدء محادثة جديدة مع فريق الدعم بالضغط على زر "بدء محادثة جديدة"</p>
                        
                        <div class="row justify-content-center g-4">
                            <div class="col-md-4">
                                <div class="feature-card p-4 rounded-3 h-100">
                                    <div class="feature-icon mb-3">
                                        <i class="fas fa-bolt text-warning"></i>
                                    </div>
                                    <h5 class="mb-3">ردود سريعة</h5>
                                    <p class="text-muted mb-0">فريق الدعم جاهز للرد على استفساراتك في أسرع وقت ممكن</p>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="feature-card p-4 rounded-3 h-100">
                                    <div class="feature-icon mb-3">
                                        <i class="fas fa-user-tie text-primary"></i>
                                    </div>
                                    <h5 class="mb-3">دعم فني متخصص</h5>
                                    <p class="text-muted mb-0">فريق من المتخصصين المؤهلين لحل جميع مشاكلك الفنية</p>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="feature-card p-4 rounded-3 h-100">
                                    <div class="feature-icon mb-3">
                                        <i class="fas fa-tachometer-alt text-success"></i>
                                    </div>
                                    <h5 class="mb-3">حلول سريعة</h5>
                                    <p class="text-muted mb-0">نقدم حلولاً فعالة وسريعة لجميع المشكلات التي تواجهك</p>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- معلومات إضافية -->
<div class="container mb-5">
    <div class="row justify-content-center">
        <div class="col-md-8">
            <div class="card shadow-sm">
                <div class="card-body p-4">
                    <h5 class="mb-4">معلومات مهمة</h5>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <div class="d-flex align-items-center">
                                <i class="fas fa-clock text-primary me-3 fa-2x"></i>
                                <div>
                                    <h6 class="mb-1">ساعات العمل</h6>
                                    <p class="text-muted mb-0">من الساعة 9 صباحاً حتى 5 مساءً</p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6 mb-3">
                            <div class="d-flex align-items-center">
                                <i class="fas fa-headset text-primary me-3 fa-2x"></i>
                                <div>
                                    <h6 class="mb-1">فريق الدعم</h6>
                                    <p class="text-muted mb-0">متاح لمساعدتك على مدار الساعة</p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6 mb-3">
                            <div class="d-flex align-items-center">
                                <i class="fas fa-shield-alt text-primary me-3 fa-2x"></i>
                                <div>
                                    <h6 class="mb-1">خصوصية وأمان</h6>
                                    <p class="text-muted mb-0">جميع المحادثات مشفرة وآمنة</p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6 mb-3">
                            <div class="d-flex align-items-center">
                                <i class="fas fa-history text-primary me-3 fa-2x"></i>
                                <div>
                                    <h6 class="mb-1">سجل المحادثات</h6>
                                    <p class="text-muted mb-0">يمكنك الوصول إلى محادثاتك السابقة</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.chat-option {
    transition: all 0.3s ease;
    cursor: pointer;
}

.hover-effect:hover {
    background-color: #f8f9fa;
    transform: translateY(-2px);
    box-shadow: 0 4px 8px rgba(0,0,0,0.1);
}

.badge {
    font-size: 0.9rem;
    padding: 0.5em 0.8em;
}

.card {
    border: none;
    border-radius: 15px;
}

.btn-primary {
    background-color: #578e7e;
    border-color: #578e7e;
}

.btn-primary:hover {
    background-color: #4a7a6a;
    border-color: #4a7a6a;
}

.text-primary {
    color: #578e7e !important;
}

.fa-2x {
    width: 40px;
    text-align: center;
}

.feature-card {
    background: #fff;
    border: 1px solid rgba(0,0,0,0.1);
    transition: all 0.3s ease;
}

.feature-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 10px 20px rgba(0,0,0,0.1);
}

.feature-icon {
    width: 60px;
    height: 60px;
    background: rgba(87, 142, 126, 0.1);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    margin: 0 auto;
}

.feature-icon i {
    font-size: 24px;
}

.text-warning {
    color: #ffc107 !important;
}

.text-success {
    color: #28a745 !important;
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const newChatBtn = document.getElementById('newChatBtn');
    
    newChatBtn.addEventListener('click', function() {
        <?php if ($active_chat): ?>
        Swal.fire({
            title: 'إغلاق المحادثة الحالية',
            text: 'يجب إغلاق المحادثة الحالية قبل بدء محادثة جديدة. هل تريد إغلاق المحادثة الحالية؟',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: 'نعم، إغلاق المحادثة',
            cancelButtonText: 'إلغاء',
            confirmButtonColor: '#d33',
            cancelButtonColor: '#3085d6'
        }).then((result) => {
            if (result.isConfirmed) {
                // إرسال طلب إغلاق المحادثة
                fetch('close_chat.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({ chat_id: <?php echo $active_chat['id']; ?> })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        window.location.href = 'support.php';
                    } else {
                        Swal.fire({
                            title: 'خطأ',
                            text: data.error || 'حدث خطأ أثناء إغلاق المحادثة',
                            icon: 'error',
                            confirmButtonText: 'حسناً'
                        });
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    Swal.fire({
                        title: 'تم حذف المحادثة',
                        text: 'قم بتحديث الصفحة',
                        icon: 'success',
                        confirmButtonText: 'حسناً'
                    });
                });
            }
        });
        <?php else: ?>
        window.location.href = 'support.php';
        <?php endif; ?>
    });
});
</script>

<?php include 'includes/footer.php'; ?>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11.7.32/dist/sweetalert2.all.min.js"></script> 