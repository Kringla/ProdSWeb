<?php
// includes/menu.php
require_once __DIR__ . '/bootstrap.php'; // session + BASE_URL

$isLoggedIn = isset($_SESSION['user_id']);
$isAdmin    = $isLoggedIn && isset($_SESSION['role']) && $_SESSION['role'] === 'admin';
?>
<nav class="topmenu" aria-label="Hovedmeny">
  <div class="topmenu-links">
    <?php if ($isAdmin): ?>
      <a href="<?= BASE_URL ?>/admin/dashboard.php">Dashboard</a>
      <a href="<?= BASE_URL ?>/admin/fartoy_admin.php">Parametre</a>
      <a href="<?= BASE_URL ?>/admin/users.php">Brukere</a>
    <?php endif; ?>

    <?php if ($isLoggedIn): ?>
      <a href="<?= BASE_URL ?>/logout.php">Logg ut</a>
    <?php else: ?>
      <a href="<?= BASE_URL ?>/login.php">Logg inn</a>
    <?php endif; ?>
  </div>
</nav>

<style>
/* Minimal, isolert styling for menyen – påvirker ikke tabeller eller sticky */
.topmenu {
  width: 100%;
  background: transparent;   /* behold blå stripe fra header.php urørt */
}
.topmenu-links {
  display: flex;
  justify-content: flex-end;
  align-items: center;
  gap: 1rem;
  padding: .5rem 1rem;
}
.topmenu a {
  text-decoration: none;
  font-weight: 500;
}
.topmenu a:hover {
  text-decoration: underline;
}
</style>
