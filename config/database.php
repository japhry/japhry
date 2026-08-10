<?php
// Database Configuration
// PLEASE DON'T COMMIT ACTUAL CREDENTIALS TO A PUBLIC REPOSITORY
// Use environment variables or a secure configuration management system in production.

return [
    'host' => '127.0.0.1', // Or your MySQL server host
    'dbname' => 'garage_management_system',
    'user' => 'root', // Your MySQL username
    'password' => '', // Your MySQL password - CHANGE THIS
    'charset' => 'utf8mb4',
    'options' => [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ],
];

// Instructions for setting up the database:
// 1. Create a MySQL database named 'garage_management_system'.
//    Example SQL: CREATE DATABASE garage_management_system CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
// 2. Create a MySQL user with appropriate permissions for this database.
//    Example SQL (replace 'your_user' and 'your_password' with strong credentials):
//    CREATE USER 'garagesys_user'@'localhost' IDENTIFIED BY 'secure_password_here';
//    GRANT ALL PRIVILEGES ON garage_management_system.* TO 'garagesys_user'@'localhost';
//    FLUSH PRIVILEGES;
// 3. Update the 'user' and 'password' fields above with the credentials you created.
//
// For development, you might use 'root' with an empty password or a common development password,
// but ensure this is changed for any staging or production environment.
?>
