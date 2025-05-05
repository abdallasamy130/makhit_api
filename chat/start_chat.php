<?php
require_once 'config/database.php';
session_start();

header('Content-Type: application/json');

// تمكين تسجيل الأخطاء
error_reporting(E_ALL);
ini_set('display_errors', 1);

$data = json_decode(file_get_contents('php://input'), true);

// تسجيل البيانات المستلمة
error_log("Received data: " . print_r($data, true));

if (!isset($data['name']) || !isset($data['email']) || !isset($data['subject']) || !isset($data['message'])) {
    error_log("Missing required fields");
    echo json_encode(['error' => 'جميع الحقول مطلوبة']);
    exit;
}

$name = trim($data['name']);
$email = trim($data['email']);
$subject = trim($data['subject']);
$message = trim($data['message']);

// التحقق من صحة البيانات
if (empty($name) || empty($email) || empty($subject) || empty($message)) {
    error_log("Empty fields detected");
    echo json_encode(['error' => 'جميع الحقول مطلوبة']);
    exit;
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    error_log("Invalid email format: " . $email);
    echo json_encode(['error' => 'البريد الإلكتروني غير صحيح']);
    exit;
}

try {
    // التحقق من اتصال قاعدة البيانات
    if (!$pdo) {
        throw new Exception("فشل الاتصال بقاعدة البيانات");
    }

    // التحقق من وجود الجداول
    $tables = $pdo->query("SHOW TABLES LIKE 'chat_sessions'")->fetch();
    if (!$tables) {
        throw new Exception("جدول جلسات المحادثة غير موجود");
    }

    $tables = $pdo->query("SHOW TABLES LIKE 'chat_messages'")->fetch();
    if (!$tables) {
        throw new Exception("جدول رسائل المحادثة غير موجود");
    }

    // بدء المعاملة
    $pdo->beginTransaction();

    // إنشاء جلسة محادثة جديدة
    $stmt = $pdo->prepare("
        INSERT INTO chat_sessions (customer_name, customer_email, subject, status, created_at, updated_at) 
        VALUES (?, ?, ?, 'active', NOW(), NOW())
    ");
    
    if (!$stmt->execute([$name, $email, $subject])) {
        throw new Exception("فشل في إنشاء جلسة المحادثة");
    }
    
    $chat_session_id = $pdo->lastInsertId();
    
    if (!$chat_session_id) {
        throw new Exception("فشل في الحصول على معرف الجلسة");
    }
    
    // إضافة الرسالة الأولى
    $stmt = $pdo->prepare("
        INSERT INTO chat_messages (chat_session_id, message, sender_type, created_at) 
        VALUES (?, ?, 'customer', NOW())
    ");
    
    if (!$stmt->execute([$chat_session_id, $message])) {
        throw new Exception("فشل في إضافة الرسالة الأولى");
    }
    
    // حفظ معرف الجلسة في الجلسة
    $_SESSION['chat_session_id'] = $chat_session_id;
    
    // تأكيد المعاملة
    $pdo->commit();
    
    error_log("Chat session created successfully with ID: " . $chat_session_id);
    echo json_encode(['success' => true]);
} catch(PDOException $e) {
    // إلغاء المعاملة في حالة حدوث خطأ
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log("Database Error: " . $e->getMessage());
    echo json_encode(['error' => 'حدث خطأ في قاعدة البيانات: ' . $e->getMessage()]);
} catch(Exception $e) {
    // إلغاء المعاملة في حالة حدوث خطأ
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log("General Error: " . $e->getMessage());
    echo json_encode(['error' => $e->getMessage()]);
}
?> 