<?php


define('DB_HOST', 'localhost');
define('DB_USER', 'root');       // XAMPP default — change on live host
define('DB_PASS', '');           // XAMPP default — change on live host
define('DB_NAME', 'drivepk');

$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

if ($conn->connect_error) {
    die(json_encode([
        'success' => false,
        'message' => 'Database connection failed. Please try again later.'
    ]));
}

$conn->set_charset('utf8mb4');

if (session_status() === PHP_SESSION_NONE) {
    $sessionPath = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'sessions';
    if (!is_dir($sessionPath)) {
        @mkdir($sessionPath, 0777, true);
    }
    if (!is_writable($sessionPath)) {
        $sessionPath = rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'drivepk_sessions';
        if (!is_dir($sessionPath)) {
            @mkdir($sessionPath, 0777, true);
        }
    }
    if (is_dir($sessionPath) && is_writable($sessionPath)) {
        session_save_path($sessionPath);
    }
    session_start();
}
?>
