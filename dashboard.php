<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/auth.php';
require_admin();

include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/menu.php';
?>
<div class="container mt-4">
  <h1>Admin</h1>
  <p>Admin-område. Her kommer tabellredigering og brukeradministrasjon.</p>

  <div class="grid cols-2 mt-3">
    <div class="card">
      <h3>Data</h3>
      <ul>
        <li><a href="<?= url('user/fartoy_nat.php') ?>">Søk fartøy (offentlig)</a></li>
        <li><a href="<?= url('user/verft_sok.php') ?>">Søk verft (offentlig)</a></li>
        <li><a href="<?= url('user/rederi_sok.php') ?>">Søk rederi (offentlig)</a></li>
      </ul>
    </div>
    <div class="card">
      <h3>Administrasjon</h3>
      <ul>
        <li>Brukeradministrasjon (kommer)</li>
        <li>Redigering av tabeller (kommer)</li>
      </ul>
    </div>
  </div>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
