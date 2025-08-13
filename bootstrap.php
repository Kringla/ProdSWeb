<?php
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../config/config.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// MySQLi-tilkobling med feilhåndtering
mysqli_report(MYSQLI_REPORT_OFF);
$conn = @new mysqli(DB_HOST, DB_USER, DB_PASSWORD, DB_NAME);
if ($conn->connect_errno) {
    error_log("Database connection failed: " . $conn->connect_error);
    http_response_code(500);
    exit('Internal Server Error');
}
$conn->set_charset('utf8mb4');

// Hjelpefunksjoner
require_once __DIR__ . '/functions.php';
