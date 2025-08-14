<?php
if (session_status() === PHP_SESSION_NONE) { session_start(); }
$BASE = defined('BASE_URL') ? rtrim(BASE_URL, '/') : '';
$loggedIn = !empty($_SESSION['user_id']);
?>
<nav class="container navbar">
  <a href="<?= $BASE ?>/">Home</a>
  <?php if ($loggedIn): ?>
    <a href="<?= $BASE ?>/dashboard.php">Dashboard</a>
    <a href="<?= $BASE ?>/logout.php">Logg ut</a>
  <?php else: ?>
    <a href="<?= $BASE ?>/login.php">Logg inn</a>
  <?php endif; ?>
</nav>
