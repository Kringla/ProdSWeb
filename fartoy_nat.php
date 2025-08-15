<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/auth.php'; // ok å ha med for meny/rolle

ini_set('display_errors', '1');
error_reporting(E_ALL);

// Små helpers
if (!function_exists('h')) {
    function h($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
}
function val($arr, $key, $def='') { return isset($arr[$key]) ? $arr[$key] : $def; }

$nasjonId = isset($_GET['nasjon_id']) ? (int)$_GET['nasjon_id'] : 0; // 0 = alle
$q        = isset($_GET['q']) ? trim($_GET['q']) : '';

// Nasjoner til dropdown (robust)
$nasjoner = [];
$sql = "
    SELECT Nasjon_ID, Nasjon
    FROM tblznasjon
    WHERE Nasjon IS NOT NULL AND Nasjon <> ''
    ORDER BY Nasjon
";
if ($res = $conn->query($sql)) {
    while ($row = $res->fetch_assoc()) {
        $nasjoner[] = $row;
    }
    $res->free();
}

// Kjør søk bare når bruker har trykket Søk (eller sendt noen parametre)
$doSearch = ($_GET !== []);

// Resultater
$rows = [];
if ($doSearch) {
    $sql = "
    SELECT
      fn.FartNavn_ID,
      ft.TypeFork,
      fn.FartNavn,
      fn.FartType_ID,
      fn.PennantTiln,
      curr.FartTid_ID,
      curr.FartObj_ID              AS FartObj_ID,
      curr.YearTid,
      curr.MndTid,
      curr.Rederi,
      curr.RegHavn,
      curr.Kallesignal,
      curr.Nasjon_ID               AS TNat,
      n.Nasjon,
      curr.Objekt                  AS IsOriginalNow,
      o.Bygget                     AS Bygget
    FROM tblfartnavn AS fn
    LEFT JOIN tblzfarttype AS ft
      ON ft.FartType_ID = fn.FartType_ID
    LEFT JOIN tblfarttid AS curr
      ON curr.FartTid_ID = (
         SELECT t2.FartTid_ID
         FROM tblfarttid t2
         WHERE t2.FartNavn_ID = fn.FartNavn_ID
         ORDER BY t2.YearTid DESC, t2.MndTid DESC, t2.FartTid_ID DESC
         LIMIT 1
      )
    LEFT JOIN tblznasjon AS n
      ON n.Nasjon_ID = curr.Nasjon_ID
    LEFT JOIN tblfarttid AS ot
      ON ot.FartNavn_ID = fn.FartNavn_ID AND ot.Objekt = 1
    LEFT JOIN tblfartobj AS o
      ON o.FartObj_ID = ot.FartObj_ID
    WHERE curr.FartTid_ID IS NOT NULL
      AND (? = 0 OR curr.Nasjon_ID = ?)
      AND (? = '' OR fn.FartNavn LIKE CONCAT('%', ?, '%'))
    ORDER BY fn.FartNavn ASC
    LIMIT 200
    ";

    $stmt = $conn->prepare($sql);
    if (!$stmt) { die('Prepare feilet: ' . $conn->error); }
    $stmt->bind_param('iiss', $nasjonId, $nasjonId, $q, $q);
    if (!$stmt->execute()) { die('Execute feilet: ' . $stmt->error); }
    $result = $stmt->get_result();
    if ($result) {
        $rows = $result->fetch_all(MYSQLI_ASSOC);
        $result->free();
    }
    $stmt->close();
}
?>
<?php include __DIR__ . '/../includes/header.php'; ?>
<?php include __DIR__ . '/../includes/menu.php'; ?>

<div class="container">
  <h1>Fartøy i databasen</h1>
  <div style="margin:-0.25rem 0 0.75rem 0; font-size:0.95rem; color:#555;">
    <strong>Forklaring:</strong>
    <span title="Navnet tilhører opprinnelig fartøy" aria-hidden="true" style="font-size:1.1rem; vertical-align:baseline;">•</span>
    = navnet tilhører <em>opprinnelig</em> fartøy (Objekt = 1).
  </div>
  <form method="get" class="form-inline" style="margin-bottom:1rem">
    <label for="q">Søk på del av fartøynamn:&nbsp;</label>
    <input type="text" id="q" name="q" value="<?= h($q) ?>" />
    &nbsp;&nbsp;
    <label for="nasjon_id">Nasjon</label>
    <select name="nasjon_id" id="nasjon_id">
      <option value="0"<?= $nasjonId === 0 ? ' selected' : '' ?>>Alle nasjoner</option>
      <?php foreach ($nasjoner as $r): ?>
        <option value="<?= (int)$r['Nasjon_ID'] ?>"<?= $nasjonId === (int)$r['Nasjon_ID'] ? ' selected' : '' ?>>
          <?= h($r['Nasjon']) ?>
        </option>
      <?php endforeach; ?>
    </select>
    &nbsp;&nbsp;
    <button type="submit">Søk</button>
  </form>

  <?php if ($doSearch): ?>
    <p>Antall funnet: <strong><?= count($rows) ?></strong></p>
  <?php endif; ?>

  <?php if ($rows): ?>
  <table class="table table-striped table-sm" border="1" cellspacing="0" cellpadding="4">
    <thead>
      <tr>
        <th>Type</th>
        <th>Navn</th>
        <th>Reg.havn</th>
        <th>Flaggstat</th>
        <th>Bygget</th>
        <th>Kallesignal</th>
        <th>Rederi/Eier</th>
        <th>Vis</th>
      </tr>
    </thead>
    <tbody>
    <?php foreach ($rows as $r): ?>
      <tr>
        <td><?= h(val($r,'TypeFork')) ?></td>
        <td>
          <?= h(val($r,'FartNavn')) ?>
          <?php if ((int)val($r,'IsOriginalNow',0) === 1): ?>
            <span title="Navnet tilhører opprinnelig fartøy">•</span>
          <?php endif; ?>
        </td>
        <td><?= h(val($r,'RegHavn')) ?></td>
        <td><?= h(val($r,'Nasjon')) ?></td>
        <td><?= h(val($r,'Bygget')) ?></td>
        <td><?= h(val($r,'Kallesignal')) ?></td>
        <td><?= h(val($r,'Rederi')) ?></td>
        <td>
          <?php $id = (int)val($r,'FartObj_ID',0); ?>
          <?php if ($id > 0): ?>
            <a href="fartoydetaljer.php?obj_id=<?= (int)$r['FartObj_ID'] ?>&navn_id=<?= (int)$r['FartNavn_ID'] ?>">Vis</a>
          <?php else: ?>
            <span class="muted">–</span>
          <?php endif; ?>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  <?php elseif ($doSearch): ?>
    <p>Ingen treff.</p>
  <?php else: ?>
    <p>Velg nasjon og/eller skriv del av navn for å søke.</p>
  <?php endif; ?>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
