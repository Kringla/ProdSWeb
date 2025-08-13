<?php
define('DB_HOST', 'localhost');
define('DB_NAME', 'skipsdb'); // navnet du valgte i phpMyAdmin
define('DB_USER', 'root');     // standardbruker i XAMPP
define('DB_PASSWORD', '');     // XAMPP root har vanligvis ingen passord

$conn = new mysqli(DB_HOST, DB_USER, DB_PASSWORD, DB_NAME);
if ($conn->connect_error) {
    die("Tilkobling mislyktes: " . $conn->connect_error);
}
$conn->set_charset('utf8mb4');
?>