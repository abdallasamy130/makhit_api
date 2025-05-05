# API Structure Documentation

This directory contains all the API endpoints organized by category.

## Directory Structure

```
api/
├── auth/           # Authentication related endpoints
├── cart/           # Shopping cart related endpoints
├── orders/         # Order management endpoints
├── products/       # Product related endpoints
├── chat/           # Chat system endpoints
├── user/           # User profile management endpoints
└── config.php      # API configuration and endpoint definitions
```

## API Categories

### Authentication API (`/api/auth/`)
- `login.php` - User login
- `register.php` - User registration
- `logout.php` - User logout
- `forgot-password.php` - Password recovery request
- `reset-password.php` - Password reset

### Cart API (`/api/cart/`)
- `add_to_cart.php` - Add item to cart
- `remove_from_cart.php` - Remove item from cart
- `update_cart.php` - Update cart item quantity
- `get_cart_items.php` - Get cart contents
- `get_cart_count.php` - Get cart item count
- `setup_cart.php` - Initialize cart

### Orders API (`/api/orders/`)
- `process_order.php` - Process new order
- `order_confirmation.php` - Order confirmation
- `order_details.php` - Get order details
- `orders.php` - List user orders
- `order_success.php` - Order success page
- `review_order.php` - Review order

### Products API (`/api/products/`)
- `products.php` - List products
- `product.php` - Get product details
- `related_products.php` - Get related products
- `rate_products.php` - Rate products

### Chat API (`/api/chat/`)
- `chat.php` - Main chat interface
- `chat_session.php` - Chat session management
- `start_chat.php` - Start new chat
- `send_message.php` - Send chat message
- `get_messages.php` - Get chat messages
- `close_chat.php` - Close chat session

### User API (`/api/user/`)
- `profile.php` - User profile
- `edit_profile.php` - Edit user profile
- `change_password.php` - Change user password

## Usage

To use the API endpoints in your code, include the config file and use the helper function:

```php
require_once 'api/config.php';

// Get login endpoint
$login_endpoint = get_api_endpoint('auth', 'login');

// Get cart items endpoint
$cart_items_endpoint = get_api_endpoint('cart', 'get_items');
```

## Configuration

The `config.php` file contains all API endpoint definitions and can be modified to change endpoint paths or add new endpoints. 