<?php
// admin/param_admin.php – overview of parameter tables
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/param_utils.php';

require_admin();

$tables = param_list_tables();

$page_title = 'Parameter-tabeller';
include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/menu.php';
if (!function_exists('h')) { function h($s){return htmlspecialchars((string)$s,ENT_QUOTES,'UTF-8');} }
?>
<div class="container mt-3">
  <h1>Parameter-tabeller</h1>
  <p>Her kan du liste, opprette, endre og slette rader i parametertabeller (<code>tblz*</code>).</p>

  <?php if (!$tables): ?>
    <p>Fant ingen tabeller som starter med <code>tblz</code>.</p>
  <?php else: ?>
    <div class="table-responsive">
      <table class="table table-striped table-sm">
        <thead>
          <tr>
            <th>Tabell</th>
            <th>Handling</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($tables as $t): ?>
            <tr>
              <td><?= h($t) ?></td>
              <td>
                <a class="btn-small" href="param_table.php?table=<?= urlencode($t) ?>">Administrer</a>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>

