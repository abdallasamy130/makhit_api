<?php
// Enable error reporting for development
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Set CORS headers
header('Access-Control-Allow-Origin: *'); // في الإنتاج، قم بتحديد النطاقات المسموح بها
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Content-Type: application/json; charset=UTF-8');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Error handling function
function handleError($message, $code = 500) {
    http_response_code($code);
    echo json_encode([
        'error' => true,
        'message' => $message,
        'timestamp' => date('Y-m-d H:i:s')
    ]);
    exit();
}

// config.php
$host = 'sql105.infinityfree.com';
$dbname = 'if0_38728339_makhit_store';
$username = 'if0_38728339';
$password = 'mwuPnmfHcG';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    handleError('Database connection failed: ' . $e->getMessage());
}

// Simple API Key protection
function authenticate() {
    $headers = apache_request_headers();
    if (!isset($headers['Authorization']) || $headers['Authorization'] != 'Bearer 18112024') {
        handleError('Unauthorized access', 401);
    }
}
?>


<?php
// login.php
include 'config.php';

try {
    $email = $_POST['email'] ?? '';
    $password = $_POST['password'] ?? '';

    if (empty($email) || empty($password)) {
        handleError('Email and password are required', 400);
    }

    $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user && password_verify($password, $user['password'])) {
        echo json_encode(['token' => '18112024']);
    } else {
        handleError('Invalid credentials', 401);
    }
} catch (Exception $e) {
    handleError('Login failed: ' . $e->getMessage());
}
?>


<?php
// orders.php
include 'config.php';
authenticate();

try {
    $date = $_GET['date'] ?? '';
    $search = $_GET['search'] ?? '';
    $search_type = $_GET['search_type'] ?? 'all'; // 'all', 'name', 'id'

    $params = [];
    $conditions = [];

    // Add date condition if provided
    if ($date) {
        $conditions[] = "DATE(o.created_at) = ?";
        $params[] = $date;
    }

    // Add search conditions
    if ($search) {
        switch ($search_type) {
            case 'name':
                $conditions[] = "u.name LIKE ?";
                $params[] = "%$search%";
                break;
            case 'id':
                $conditions[] = "o.id = ?";
                $params[] = $search;
                break;
            default: // 'all'
                $conditions[] = "(u.name LIKE ? OR o.id = ?)";
                $params[] = "%$search%";
                $params[] = $search;
                break;
        }
    }

    // Build the SQL query
    $sql = "SELECT o.id, o.total_price, o.status, o.created_at, u.name AS customer_name
            FROM orders o
            JOIN users u ON o.user_id = u.id";

    // Add WHERE clause if there are conditions
    if (!empty($conditions)) {
        $sql .= " WHERE " . implode(" AND ", $conditions);
    }

    $sql .= " ORDER BY o.created_at DESC";

    // Prepare and execute the query
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Add search metadata to response
    $response = [
        'orders' => $orders,
        'search_info' => [
            'total_results' => count($orders),
            'search_term' => $search,
            'search_type' => $search_type,
            'date_filter' => $date
        ]
    ];

    echo json_encode($response);
} catch (Exception $e) {
    handleError('Failed to fetch orders: ' . $e->getMessage());
}
?>


<?php
// order_details.php
include 'config.php';
authenticate();

try {
    $order_id = $_GET['id'] ?? 0;

    if (!$order_id) {
        handleError('Order ID is required', 400);
    }

    // Get order + user
    $sql = "SELECT o.*, u.name AS customer_name, u.email, u.phone
            FROM orders o
            JOIN users u ON o.user_id = u.id
            WHERE o.id = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$order_id]);
    $order = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$order) {
        handleError('Order not found', 404);
    }

    // Get products
    $sql = "SELECT oi.quantity, oi.price, p.name AS product_name, p.image
            FROM order_items oi
            JOIN products p ON oi.product_id = p.id
            WHERE oi.order_id = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$order_id]);
    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $order['items'] = $items;
    echo json_encode($order);
} catch (Exception $e) {
    handleError('Failed to fetch order details: ' . $e->getMessage());
}
?>


<?php
// new_orders.php
include 'config.php';
authenticate();

try {
    $sql = "SELECT o.id, o.total_price, o.status, o.created_at, u.name AS customer_name
            FROM orders o
            JOIN users u ON o.user_id = u.id
            WHERE o.status = 'pending' OR o.status = 'new'
            ORDER BY o.created_at DESC";
    $stmt = $pdo->query($sql);
    $new_orders = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode($new_orders);
} catch (Exception $e) {
    handleError('Failed to fetch new orders: ' . $e->getMessage());
}
?>


<?php
// update_order_status.php
include 'config.php';
authenticate();

try {
    $order_id = $_POST['order_id'] ?? 0;
    $new_status = $_POST['status'] ?? '';

    if (!$order_id || !$new_status) {
        handleError('Order ID and new status are required', 400);
    }

    // Validate status
    $valid_statuses = ['pending', 'processing', 'completed', 'cancelled'];
    if (!in_array($new_status, $valid_statuses)) {
        handleError('Invalid status value', 400);
    }

    $sql = "UPDATE orders SET status = ? WHERE id = ?";
    $stmt = $pdo->prepare($sql);
    $updated = $stmt->execute([$new_status, $order_id]);

    if ($updated) {
        echo json_encode(['success' => true, 'message' => 'Order status updated successfully']);
    } else {
        handleError('Failed to update order status', 500);
    }
} catch (Exception $e) {
    handleError('Failed to update order status: ' . $e->getMessage());
}
?>


<?php
// new_orders_count.php
include 'config.php';
authenticate();

try {
    $sql = "SELECT COUNT(*) AS count
            FROM orders
            WHERE status = 'pending' OR status = 'new'";
    $stmt = $pdo->query($sql);
    $count = $stmt->fetch(PDO::FETCH_ASSOC);
    echo json_encode(['new_orders_count' => $count['count']]);
} catch (Exception $e) {
    handleError('Failed to get new orders count: ' . $e->getMessage());
}
?>
