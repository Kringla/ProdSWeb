<?php require_once __DIR__ . '/bootstrap.php'; ?>
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
<!doctype html>
<html lang="no">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title><?php echo htmlspecialchars($page_title, ENT_QUOTES, 'UTF-8'); ?></title>

  <link rel="stylesheet" href="<?php echo $BASE; ?>/assets/css/app.css">
  <script defer src="<?php echo $BASE; ?>/assets/js/hero-rotator.js"></script>
</head>
<body class="<?php echo htmlspecialchars($body_class, ENT_QUOTES, 'UTF-8'); ?>">

<header class="site-header">
  <div class="container">
    <a class="brand" href="<?php echo $BASE; ?>/">SkipsWeb</a>
  </div>
</header>
