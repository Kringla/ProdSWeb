<?php
// admin/param_table.php – manage a single parameter table
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/param_utils.php';

require_admin();
if (!function_exists('h')) { function h($s){return htmlspecialchars((string)$s,ENT_QUOTES,'UTF-8');} }

$table = isset($_GET['table']) ? (string)$_GET['table'] : '';
if (!param_allowed_table($table)) {
    http_response_code(400);
    echo 'Ugyldig tabellnavn';
    exit;
}

$pk = param_get_primary_key($table);
if (!$pk) {
    http_response_code(400);
    echo 'Tabellen har ikke en enkel primærnøkkel som støttes.';
    exit;
}

$columns = param_table_columns($table);

// Handle actions: create, update, delete
$action = $_POST['action'] ?? '';
$message = '';
$error = '';

try {
    if ($action === 'create') {
        $data = [];
        foreach ($columns as $c) {
            $name = $c['COLUMN_NAME'];
            if ($name === $pk) continue; // let DB assign or require manual? skip by default
            $data[$name] = isset($_POST[$name]) ? (string)$_POST[$name] : null;
        }
        // remove nulls for non-null fields to avoid explicit NULL if not provided
        $filtered = [];
        foreach ($columns as $c) {
            $n = $c['COLUMN_NAME'];
            if ($n === $pk) continue;
            if (array_key_exists($n, $data)) {
                $val = $data[$n];
                if ($val === '' && $c['IS_NULLABLE'] === 'YES') {
                    // skip setting to allow default NULL
                } else {
                    $filtered[$n] = $val;
                }
            }
        }
        $newId = param_insert($table, $filtered);
        $message = 'Opprettet rad med ID ' . h((string)$newId);
    } elseif ($action === 'update') {
        $id = $_POST['id'] ?? '';
        $data = [];
        foreach ($columns as $c) {
            $name = $c['COLUMN_NAME'];
            if ($name === $pk) continue;
            if (isset($_POST[$name])) {
                $val = (string)$_POST[$name];
                if ($val === '' && $c['IS_NULLABLE'] === 'YES') {
                    // set to NULL explicitly via skipping; but generic helper sets strings
                    // leave empty string if not nullable
                }
                $data[$name] = $val;
            }
        }
        if ($id === '') throw new RuntimeException('Mangler ID for oppdatering.');
        param_update($table, $pk, $id, $data);
        $message = 'Oppdatert rad ' . h((string)$id);
    } elseif ($action === 'delete') {
        $id = $_POST['id'] ?? '';
        if ($id === '') throw new RuntimeException('Mangler ID for sletting.');
        param_delete($table, $pk, $id);
        $message = 'Slettet rad ' . h((string)$id);
    }
} catch (Throwable $ex) {
    $error = $ex->getMessage();
}

// Fetch rows after action
$rows = param_fetch_all($table, $pk, 500);

$page_title = 'Param: ' . $table;
include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/menu.php';
?>
<div class="container mt-3">
  <h1><?= h($table) ?></h1>
  <p>Primærnøkkel: <code><?= h($pk) ?></code></p>

  <?php if ($message): ?><div class="alert success"><?= h($message) ?></div><?php endif; ?>
  <?php if ($error): ?><div class="alert danger"><?= h($error) ?></div><?php endif; ?>

  <p>
    <a class="btn" href="param_admin.php">Tilbake til oversikt</a>
  </p>

  <h2>Legg til ny rad</h2>
  <form method="post" class="form">
    <input type="hidden" name="action" value="create">
    <div class="two-col">
      <?php foreach (param_build_form_fields($columns, $pk) as $f): if ($f['is_pk']) continue; ?>
        <label>
          <span><?= h($f['name']) ?></span>
          <input type="text" name="<?= h($f['name']) ?>" value="" <?= $f['max'] ? 'maxlength="'.(int)$f['max'].'"' : '' ?>>
        </label>
      <?php endforeach; ?>
    </div>
    <button type="submit" class="btn primary">Opprett</button>
  </form>

  <h2 class="mt-3">Eksisterende rader</h2>
  <div class="table-responsive">
    <table class="table table-striped table-sm">
      <thead>
        <tr>
          <?php foreach ($columns as $c): ?>
            <th><?= h($c['COLUMN_NAME']) ?></th>
          <?php endforeach; ?>
          <th>Handlinger</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($rows as $r): ?>
          <tr>
            <?php foreach ($columns as $c): $n = $c['COLUMN_NAME']; ?>
              <td><?= h($r[$n] ?? '') ?></td>
            <?php endforeach; ?>
            <td>
              <details>
                <summary>Endre</summary>
                <form method="post" style="margin-top: .5rem;">
                  <input type="hidden" name="action" value="update">
                  <input type="hidden" name="id" value="<?= h((string)$r[$pk]) ?>">
                  <div class="two-col">
                    <?php foreach (param_build_form_fields($columns, $pk, $r) as $f): if ($f['is_pk']) continue; ?>
                      <label>
                        <span><?= h($f['name']) ?></span>
                        <input type="text" name="<?= h($f['name']) ?>" value="<?= h($f['value']) ?>" <?= $f['max'] ? 'maxlength="'.(int)$f['max'].'"' : '' ?>>
                      </label>
                    <?php endforeach; ?>
                  </div>
                  <button type="submit" class="btn-small">Lagre</button>
                </form>
                <form method="post" onsubmit="return confirm('Slette rad?');" style="margin-top:.25rem;">
                  <input type="hidden" name="action" value="delete">
                  <input type="hidden" name="id" value="<?= h((string)$r[$pk]) ?>">
                  <button type="submit" class="btn-small danger">Slett</button>
                </form>
              </details>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>

