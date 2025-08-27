<?php
if (session_status() === PHP_SESSION_NONE) { session_start(); }
$BASE = defined('BASE_URL') ? rtrim(BASE_URL, '/') : '';
$loggedIn = !empty($_SESSION['user_id']);
?>
<nav class="container navbar">
  <a href="<?= $BASE ?>/">Home</a>
  <?php if ($loggedIn): ?>
    <a href="<?= $BASE ?>/dashboard.php">Dashboard</a>
    <?php if (!empty($_SESSION['user_role']) && $_SESSION['user_role'] === 'admin'): ?>
      <a href="<?= $BASE ?>/admin/fartoy_admin.php">Administrer fartøyer</a>
    <?php endif; ?>
    <a href="<?= $BASE ?>/logout.php">Logg ut</a>
  <?php else: ?>
    <a href="<?= $BASE ?>/auth_login.php">Logg inn</a>
  <?php endif; ?>
</nav>
