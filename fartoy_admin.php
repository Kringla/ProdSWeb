<?php
// admin/fartoy_admin.php
// Denne siden gir administrator mulighet til å søke etter fartøyer og utføre redigering, sletting
// eller oppretting av nye fartøyoppføringer. Siden er kun tilgjengelig for brukere med admin-rolle.

require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/auth.php';

// Start session hvis ikke allerede startet
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Sjekk tilgang: kun admin-brukere har lov å bruke denne siden
if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin') {
    /*
     * Ikke send statuskode 403 her. Dersom vi sender en 4xx-kode samtidig som vi gjør en
     * redirect, kan enkelte nettlesere ignorere redirecten og vise en blank side. I stedet
     * sender vi bare en vanlig 302-redirect til forsiden. Login/kontroll av rolle håndteres
     * av auth.php.
     */
    $base = defined('BASE_URL') ? rtrim(BASE_URL, '/') : '';
    header('Location: ' . $base . '/');
    exit;
}

// Hjelpefunksjoner
if (!function_exists('h')) {
    function h($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
}
function val($arr, $key, $def = '') { return isset($arr[$key]) ? $arr[$key] : $def; }

// Parametre
$nasjonId = isset($_GET['nasjon_id']) ? (int)$_GET['nasjon_id'] : 0;
$q        = isset($_GET['q']) ? trim((string)$_GET['q']) : '';

// Hent nasjoner til dropdown
$nasjoner = [];
$sqlN = "SELECT Nasjon_ID, Nasjon FROM tblznasjon WHERE Nasjon IS NOT NULL AND Nasjon <> '' ORDER BY Nasjon";
if ($resN = $conn->query($sqlN)) {
    while ($row = $resN->fetch_assoc()) {
        $nasjoner[] = $row;
    }
    $resN->free();
}

// Søk? Bare når det er sendt parametre
$doSearch = ($_GET !== []);
$rows = [];
if ($doSearch) {
    $sql = "
        SELECT
          fn.FartNavn_ID,
          ft.TypeFork,
          fn.FartNavn,
          fn.FartType_ID,
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
        LIMIT 200";

    $stmt = $conn->prepare($sql);
    if ($stmt) {
        $stmt->bind_param('iiss', $nasjonId, $nasjonId, $q, $q);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result) {
            $rows = $result->fetch_all(MYSQLI_ASSOC);
            $result->free();
        }
        $stmt->close();
    }
}

include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/menu.php';
?>

<div class="container mt-3">
  <h1>Administrer fartøyer</h1>
  <p class="muted" style="text-align:center;">Søk etter fartøynavn for å redigere eller slette eksisterende fartøyer, eller opprett et nytt fartøy.</p>

  <form method="get" class="search-form" style="margin-bottom:1rem;">
    <label for="q">Søk på del av navn:&nbsp;</label>
    <input type="text" id="q" name="q" value="<?= h($q) ?>" />
    <label for="nasjon_id">fra nasjon</label>
    <select name="nasjon_id" id="nasjon_id">
      <option value="0"<?= $nasjonId === 0 ? ' selected' : '' ?>>Alle nasjoner</option>
      <?php foreach ($nasjoner as $r): ?>
        <option value="<?= (int)$r['Nasjon_ID'] ?>"<?= $nasjonId === (int)$r['Nasjon_ID'] ? ' selected' : '' ?>>
          <?= h($r['Nasjon']) ?>
        </option>
      <?php endforeach; ?>
    </select>
    <button type="submit" class="btn">Søk</button>
    <a class="btn" href="fartoy_new.php">Legg til nytt fartøy</a>
  </form>

  <?php if ($doSearch): ?>
    <p>Antall funnet: <strong><?= count($rows) ?></strong></p>
  <?php endif; ?>

  <?php if ($rows): ?>
    <div class="table-wrap outline-brand">
      <table class="table tight fit">
        <thead>
          <tr>
            <th>Type</th>
            <th>Navn</th>
            <th>Reg.havn</th>
            <th>Flaggstat</th>
            <th>Bygget</th>
            <th>Kallesignal</th>
            <th>Rederi/Eier</th>
            <th>Handlinger</th>
          </tr>
        </thead>
        <tbody>
        <?php foreach ($rows as $r): ?>
          <tr>
            <td><?= h(val($r, 'TypeFork')) ?></td>
            <td>
              <?= h(val($r, 'FartNavn')) ?>
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
              <?php $objId = (int)val($r,'FartObj_ID',0); $navnId = (int)val($r,'FartNavn_ID',0); ?>
              <?php if ($objId > 0 && $navnId > 0): ?>
                <a class="btn-small" href="fartoy_edit.php?obj_id=<?= $objId ?>&navn_id=<?= $navnId ?>">Rediger</a>
                <a class="btn-small" href="fartoy_delete.php?obj_id=<?= $objId ?>&navn_id=<?= $navnId ?>" onclick="return confirm('Er du sikker på at du vil slette dette fartøyet?');">Slett</a>
              <?php else: ?>
                <span class="muted">–</span>
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php elseif ($doSearch): ?>
    <p>Ingen treff.</p>
  <?php else: ?>
    <p>Skriv inn del av navn for å søke etter fartøy.</p>
  <?php endif; ?>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>