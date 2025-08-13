<?php
$host   = 'localhost';
$port   = 3306;
$dbname = 'skipsdb';
$user   = 'root';
$pass   = '';

// Establish connection
$conn = new mysqli($host, $user, $pass, $dbname, $port);

// Check connection status
if ($conn->connect_error) {
    // Connection failed – output the error message
    die("Tilkobling feilet: " . $conn->connect_error);
}

// If we reach here, connection is successful
echo "Tilkobling OK";

// Close the connection (optional, since script ends)
$conn->close();
?>