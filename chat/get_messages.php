<?php
require_once 'config/database.php';
session_start();

header('Content-Type: application/json');

if (!isset($_SESSION['chat_session_id'])) {
    echo json_encode(['error' => 'جلسة المحادثة غير موجودة']);
    exit;
}

$chat_session_id = $_SESSION['chat_session_id'];
$last_id = isset($_GET['last_id']) ? (int)$_GET['last_id'] : 0;

try {
    // جلب الرسائل الجديدة فقط
    $stmt = $pdo->prepare("
        SELECT id, message, sender_type, created_at 
        FROM chat_messages 
        WHERE chat_session_id = ? AND id > ?
        ORDER BY created_at ASC
    ");
    
    $stmt->execute([$chat_session_id, $last_id]);
    $messages = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // تحديث حالة الرسائل غير المقروءة
    $stmt = $pdo->prepare("
        UPDATE chat_messages 
        SET is_read = 1 
        WHERE chat_session_id = ? AND sender_type = 'admin' AND is_read = 0
    ");
    $stmt->execute([$chat_session_id]);
    
    echo json_encode([
        'success' => true,
        'messages' => $messages
    ]);
    
} catch(PDOException $e) {
    error_log("Database Error in get_messages.php: " . $e->getMessage());
    echo json_encode(['error' => 'حدث خطأ في جلب الرسائل']);
}
?> 