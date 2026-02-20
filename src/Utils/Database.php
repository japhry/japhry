<?php
namespace App\Utils;

use PDO;
use PDOException;

class Database {
    private static $instance = null;
    private $pdo;

    private function __construct() {
        $config = require __DIR__ . '/../../config/database.php';

        try {
            $this->pdo = new PDO(
                "mysql:host={$config['host']};dbname={$config['dbname']};charset={$config['charset']}",
                $config['user'],
                $config['password'],
                $config['options']
            );
        } catch (PDOException $e) {
            // In a real application, log this error and show a user-friendly message.
            // Do not expose detailed error messages in production.
            error_log("Database Connection Error: " . $e->getMessage());
            // For development, it's okay to die here. For production, handle more gracefully.
            die("Database connection failed. Check application logs. We are working to resolve the issue.");
        }
    }

    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new Database();
        }
        return self::$instance;
    }

    public function getConnection() {
        return $this->pdo;
    }

    // Prevent cloning and unserialization
    private function __clone() {}
    public function __wakeup() {}
}
?>
