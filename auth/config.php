<?php
require_once __DIR__ . '/../config.php';

// Database connection
require_once __DIR__ . '/../../config/database.php';

// Common functions
require_once __DIR__ . '/../../includes/functions.php';

// Session start if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
?> 