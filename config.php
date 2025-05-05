<?php
// API Base URL
define('API_BASE_URL', '/api');

// API Endpoints Configuration
$API_ENDPOINTS = [
    // Auth Endpoints
    'auth' => [
        'login' => API_BASE_URL . '/auth/login.php',
        'register' => API_BASE_URL . '/auth/register.php',
        'logout' => API_BASE_URL . '/auth/logout.php',
        'forgot_password' => API_BASE_URL . '/auth/forgot-password.php',
        'reset_password' => API_BASE_URL . '/auth/reset-password.php'
    ],
    
    // Cart Endpoints
    'cart' => [
        'add' => API_BASE_URL . '/cart/add_to_cart.php',
        'remove' => API_BASE_URL . '/cart/remove_from_cart.php',
        'update' => API_BASE_URL . '/cart/update_cart.php',
        'get_items' => API_BASE_URL . '/cart/get_cart_items.php',
        'get_count' => API_BASE_URL . '/cart/get_cart_count.php',
        'setup' => API_BASE_URL . '/cart/setup_cart.php'
    ],
    
    // Orders Endpoints
    'orders' => [
        'process' => API_BASE_URL . '/orders/process_order.php',
        'confirmation' => API_BASE_URL . '/orders/order_confirmation.php',
        'details' => API_BASE_URL . '/orders/order_details.php',
        'list' => API_BASE_URL . '/orders/orders.php',
        'success' => API_BASE_URL . '/orders/order_success.php',
        'review' => API_BASE_URL . '/orders/review_order.php'
    ],
    
    // Products Endpoints
    'products' => [
        'list' => API_BASE_URL . '/products/products.php',
        'details' => API_BASE_URL . '/products/product.php',
        'related' => API_BASE_URL . '/products/related_products.php',
        'rate' => API_BASE_URL . '/products/rate_products.php'
    ],
    
    // Chat Endpoints
    'chat' => [
        'main' => API_BASE_URL . '/chat/chat.php',
        'session' => API_BASE_URL . '/chat/chat_session.php',
        'start' => API_BASE_URL . '/chat/start_chat.php',
        'send_message' => API_BASE_URL . '/chat/send_message.php',
        'get_messages' => API_BASE_URL . '/chat/get_messages.php',
        'close' => API_BASE_URL . '/chat/close_chat.php'
    ],
    
    // User Endpoints
    'user' => [
        'profile' => API_BASE_URL . '/user/profile.php',
        'edit_profile' => API_BASE_URL . '/user/edit_profile.php',
        'change_password' => API_BASE_URL . '/user/change_password.php'
    ]
];

// Helper function to get API endpoint
function get_api_endpoint($category, $endpoint) {
    global $API_ENDPOINTS;
    return $API_ENDPOINTS[$category][$endpoint] ?? null;
}
?> 