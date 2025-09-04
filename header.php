<?php require_once __DIR__ . '/bootstrap.php'; ?>
<?php
// V2 aktiv hvis konstanten er true ELLER hvis du legger til ?theme=v2 i URL
$themeV2 = SKIPSWEB_THEME_V2 || (isset($_GET['theme']) && $_GET['theme'] === 'v2');
?>

<?php

if (session_status() === PHP_SESSION_NONE) { session_start(); }

$role_class = 'role-guest';
if (!empty($_SESSION['user_role'])) {
    $role_class = ($_SESSION['user_role'] === 'admin') ? 'role-admin' : 'role-user';
}

$page_class = isset($page_class) && is_string($page_class) ? $page_class : 'page';
$body_class = trim($role_class . ' ' . $page_class);
$page_title = isset($page_title) && is_string($page_title) ? $page_title : 'SkipsWeb';

$BASE = defined('BASE_URL') ? rtrim(BASE_URL, '/') : '';
?>


<!DOCTYPE html>
<html lang="no">
  <head>
    <!-- Alltid dagens stil -->
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/app.css">
    <?php if ($themeV2): ?>
      <!-- Ny v2-stil lastes KUN når flagget er aktivt -->
      <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/v2/base.css">
      <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/v2/components.css">
      <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/v2/compat.css">
    <?php endif; ?>
  </head>
<body class="<?= $themeV2 ? 'theme-v2' : '' ?>">

<header class="site-header">
  <div class="container">
    <a class="brand" href="<?php echo $BASE; ?>/">
      <img
        src="<?php echo $BASE; ?>/assets/img/skipsweb-logo@2x.jpg"
        srcset="<?php echo $BASE; ?>/assets/img/skipsweb-logo.jpg 1x,
                 <?php echo $BASE; ?>/assets/img/skipsweb-logo@2x.jpg 2x"
        alt="SkipsWeb" class="logo">
    </a>
  </div>
</header>
