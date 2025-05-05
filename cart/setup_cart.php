<?php
require_once 'includes/db.php';

try {
    // Read and execute the SQL file
    $sql = file_get_contents('create_cart_table.sql');
    $pdo->exec($sql);
    echo "Cart table created successfully!\n";
} catch (PDOException $e) {
    echo "Error creating cart table: " . $e->getMessage() . "\n";
} 