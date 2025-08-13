<?php
if (session_status() === PHP_SESSION_NONE) { session_start(); }

// Sikre BASE_URL (fall-back hvis constants.php mangler)
if (!defined('BASE_URL')) {
  $constPath = __DIR__ . '/../config/constants.php';
  if (is_file($constPath)) { require_once $constPath; }
  if (!defined('BASE_URL')) {
    $dir = rtrim(str_replace('\\','/', dirname($_SERVER['SCRIPT_NAME'] ?? '')), '/');
    define('BASE_URL', $dir === '/' ? '' : $dir);
  }
}
$BASE = rtrim(BASE_URL, '/');
$loggedIn = !empty($_SESSION['user_id']);
$isAdmin  = $loggedIn && (($_SESSION['user_role'] ?? '') === 'admin');
?>
<nav class="container navbar">
  <a href="<?= $BASE ?>/">Home</a>
  <a href="<?= $BASE ?>/user/fartoy_nat.php">Søk fartøy</a>
  <a href="<?= $BASE ?>/user/verft_sok.php">Søk verft</a>
  <a href="<?= $BASE ?>/user/rederi_sok.php">Søk rederi</a>
  <?php if ($isAdmin): ?>
    <a href="<?= $BASE ?>/admin/dashboard.php">Admin</a>
    <a href="<?= $BASE ?>/logout.php">Logg ut</a>
  <?php elseif ($loggedIn): ?>
    <a href="<?= $BASE ?>/logout.php">Logg ut</a>
  <?php else: ?>
    <a href="<?= $BASE ?>/login.php">Logg inn</a>
  <?php endif; ?>
</nav>

