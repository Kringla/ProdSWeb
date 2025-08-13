<?php
require_once __DIR__ . '/includes/bootstrap.php';

$count = $conn->query("SELECT COUNT(*) FROM users")->fetch_row()[0];
if ($count > 0) {
    header("Location: " . BASE_URL . "/login.php");
    exit;
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = $_POST["email"];
    $password = password_hash($_POST["password"], PASSWORD_DEFAULT);
    $role = "admin";

    $stmt = $conn->prepare("INSERT INTO users (email, password, role) VALUES (?, ?, ?)");
    $stmt->bind_param("sss", $email, $password, $role);
    $stmt->execute();

    header("Location: " . BASE_URL . "/login.php");
    exit;
}
?>

<form method="post" action="">
    <input type="email" name="email" required placeholder="E-post" class="input">
    <input type="password" name="password" required placeholder="Passord" class="input">
    <button type="submit" class="btn primary">Registrer administrator</button>
</form>
