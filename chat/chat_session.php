<?php
require_once 'config/database.php';
session_start();

// التحقق من وجود جلسة دردشة نشطة
if (!isset($_SESSION['chat_session_id'])) {
    header('Location: chat.php');
    exit;
}

$chat_session_id = $_SESSION['chat_session_id'];

// جلب معلومات المحادثة
$stmt = $pdo->prepare("
    SELECT cs.*, 
           (SELECT COUNT(*) FROM chat_messages WHERE chat_session_id = cs.id AND is_read = 0 AND sender_type = 'admin') as unread_count
    FROM chat_sessions cs
    WHERE cs.id = ?
");
$stmt->execute([$chat_session_id]);
$chat = $stmt->fetch();

if (!$chat) {
    header('Location: chat.php');
    exit;
}

// التحقق من وجود رسالة ترحيب
$stmt = $pdo->prepare("
    SELECT COUNT(*) 
    FROM chat_messages 
    WHERE chat_session_id = ? AND sender_type = 'system' AND message LIKE 'مرحباً%'
");
$stmt->execute([$chat_session_id]);
$hasWelcomeMessage = $stmt->fetchColumn() > 0;

// إضافة رسالة ترحيب إذا لم تكن موجودة
if (!$hasWelcomeMessage) {
    $welcomeMessage = "مرحباً بك في خدمة الدعم المباشر. رسالتك مهمة جداً بالنسبة لنا وسوف يتم التواصل معك مباشرة إذا كان أحد موظفي خدمة العملاء متاح. إذا لم يكن متاحاً حالياً، سوف يتم التواصل معك بأسرع وقت ممكن.";
    
    try {
        $stmt = $pdo->prepare("
            INSERT INTO chat_messages (
                chat_session_id, 
                message, 
                sender_type, 
                created_at
            ) VALUES (?, ?, 'system', NOW())
        ");
        $stmt->execute([$chat_session_id, $welcomeMessage]);
    } catch(PDOException $e) {
        // في حالة حدوث خطأ، نستخدم 'admin' بدلاً من 'system'
        $stmt = $pdo->prepare("
            INSERT INTO chat_messages (
                chat_session_id, 
                message, 
                sender_type, 
                created_at
            ) VALUES (?, ?, 'admin', NOW())
        ");
        $stmt->execute([$chat_session_id, $welcomeMessage]);
    }
}

// تحديث حالة الرسائل غير المقروءة
$stmt = $pdo->prepare("
    UPDATE chat_messages 
    SET is_read = 1 
    WHERE chat_session_id = ? AND sender_type = 'admin' AND is_read = 0
");
$stmt->execute([$chat_session_id]);

$page_title = 'محادثة الدعم';
include 'includes/header.php';
?>

<div class="container py-4">
    <div class="row">
        <div class="col-md-12">
            <div class="card shadow">
                <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
                    <div>
                        <h5 class="mb-0">
                            <i class="fas fa-headset me-2"></i>
                            <?php echo htmlspecialchars($chat['subject']); ?>
                        </h5>
                        <small>
                            <i class="fas fa-user me-1"></i>
                            <?php echo htmlspecialchars($chat['customer_name']); ?>
                            <i class="fas fa-envelope ms-2 me-1"></i>
                            <?php echo htmlspecialchars($chat['customer_email']); ?>
                        </small>
                    </div>
                    <div>
                        <a href="close_chat.php" class="btn btn-light btn-sm">
                            <i class="fas fa-times me-1"></i>
                            إغلاق المحادثة
                        </a>
                    </div>
                </div>
                <div class="card-body">
                    <div id="chatMessages" class="chat-messages mb-3" style="height: 400px; overflow-y: auto;">
                        <!-- سيتم تحميل الرسائل هنا عبر JavaScript -->
                    </div>
                    <form id="messageForm" class="mt-3">
                        <div class="input-group">
                            <textarea id="messageInput" class="form-control" rows="1" placeholder="اكتب رسالتك هنا..." required></textarea>
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-paper-plane"></i>
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.chat-messages {
    direction: rtl;
    text-align: right;
}

.message {
    margin-bottom: 1rem;
    padding: 0.75rem;
    border-radius: 10px;
    max-width: 80%;
}

.customer-message {
    background-color: #e9f5ff;
    margin-right: 0;
    margin-left: auto;
}

.admin-message {
    background-color: #f0f0f0;
    margin-right: auto;
    margin-left: 0;
}

.system-message {
    background-color: #fff3cd;
    margin: 0 auto;
    text-align: center;
    font-style: italic;
}

.message-time {
    font-size: 0.75rem;
    color: #6c757d;
    margin-top: 0.25rem;
}

#messageInput {
    resize: none;
    direction: rtl;
    text-align: right;
}

.card-header {
    direction: rtl;
    text-align: right;
}

.btn-light {
    background-color: #fff;
    border-color: #fff;
}

.btn-light:hover {
    background-color: #f8f9fa;
    border-color: #f8f9fa;
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const chatMessages = document.getElementById('chatMessages');
    const messageForm = document.getElementById('messageForm');
    const messageInput = document.getElementById('messageInput');
    
    // تحميل الرسائل كل 3 ثواني
    function loadMessages() {
        fetch('get_messages.php')
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    chatMessages.innerHTML = '';
                    data.messages.forEach(message => {
                        const messageDiv = document.createElement('div');
                        messageDiv.className = `message ${message.sender_type}-message`;
                        
                        const messageContent = document.createElement('div');
                        messageContent.textContent = message.message;
                        
                        const messageTime = document.createElement('div');
                        messageTime.className = 'message-time';
                        messageTime.textContent = message.created_at;
                        
                        messageDiv.appendChild(messageContent);
                        messageDiv.appendChild(messageTime);
                        chatMessages.appendChild(messageDiv);
                    });
                    
                    // التمرير إلى آخر رسالة
                    chatMessages.scrollTop = chatMessages.scrollHeight;
                }
            });
    }
    
    // تحميل الرسائل عند فتح الصفحة
    loadMessages();
    
    // تحديث الرسائل كل 3 ثواني
    setInterval(loadMessages, 3000);
    
    // إرسال رسالة جديدة
    messageForm.addEventListener('submit', function(e) {
        e.preventDefault();
        
        const message = messageInput.value.trim();
        if (!message) return;
        
        fetch('send_message.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({ message })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                messageInput.value = '';
                loadMessages();
            } else {
                alert(data.error || 'حدث خطأ أثناء إرسال الرسالة');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('حدث خطأ أثناء إرسال الرسالة');
        });
    });
    
    // تعديل حجم حقل الإدخال تلقائياً
    messageInput.addEventListener('input', function() {
        this.style.height = 'auto';
        this.style.height = (this.scrollHeight) + 'px';
    });
});
</script>

<?php include 'includes/footer.php'; ?> 