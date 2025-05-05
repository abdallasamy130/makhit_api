<?php
require_once 'config/database.php';
session_start();

header('Content-Type: application/json');

if (!isset($_SESSION['chat_session_id'])) {
    echo json_encode(['error' => 'جلسة المحادثة غير موجودة']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);

if (!isset($data['message']) || empty(trim($data['message']))) {
    echo json_encode(['error' => 'الرسالة مطلوبة']);
    exit;
}

$chat_session_id = $_SESSION['chat_session_id'];
$message = trim($data['message']);

try {
    // إضافة الرسالة الجديدة
    $stmt = $pdo->prepare("
        INSERT INTO chat_messages (chat_session_id, message, sender_type, created_at) 
        VALUES (?, ?, 'customer', NOW())
    ");
    
    if (!$stmt->execute([$chat_session_id, $message])) {
        throw new Exception("فشل في إضافة الرسالة");
    }
    
    // تحديث وقت آخر تحديث للمحادثة
    $stmt = $pdo->prepare("
        UPDATE chat_sessions 
        SET updated_at = NOW() 
        WHERE id = ?
    ");
    $stmt->execute([$chat_session_id]);
    
    echo json_encode(['success' => true]);
    
} catch(PDOException $e) {
    error_log("Database Error in send_message.php: " . $e->getMessage());
    echo json_encode(['error' => 'حدث خطأ في إرسال الرسالة']);
} catch(Exception $e) {
    error_log("General Error in send_message.php: " . $e->getMessage());
    echo json_encode(['error' => $e->getMessage()]);
}
?> 