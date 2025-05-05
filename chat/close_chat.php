<?php
require_once 'config/database.php';
session_start();

// التحقق من وجود جلسة دردشة نشطة
if (!isset($_SESSION['chat_session_id'])) {
    header('Location: chat.php');
    exit;
}

try {
    // تحديث حالة المحادثة إلى مغلقة
    $stmt = $pdo->prepare("
        UPDATE chat_sessions 
        SET status = 'closed', 
            updated_at = NOW() 
        WHERE id = ?
    ");
    $stmt->execute([$_SESSION['chat_session_id']]);
    
    // حذف معرف الجلسة من الجلسة
    unset($_SESSION['chat_session_id']);
    
    // إضافة رسالة إغلاق
    $stmt = $pdo->prepare("
        INSERT INTO chat_messages (
            chat_session_id, 
            message, 
            sender_type, 
            created_at
        ) VALUES (
            ?, 
            'تم إغلاق المحادثة من قبل المستخدم', 
            'system', 
            NOW()
        )
    ");
    $stmt->execute([$_SESSION['chat_session_id']]);
    
    // توجيه المستخدم إلى صفحة الدعم
    header('Location: support.php');
    exit;
    
} catch(PDOException $e) {
    // في حالة حدوث خطأ، توجيه المستخدم إلى صفحة الدردشة مع رسالة خطأ
    $_SESSION['error'] = 'حدث خطأ أثناء إغلاق المحادثة';
    header('Location: chat.php');
    exit;
}
?> 