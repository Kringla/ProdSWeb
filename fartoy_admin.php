<?php
// admin/fartoy_admin.php
// Administrasjon: søk, list og håndter fartøyer. Kun for admin-brukere.

require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/auth.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Tilgangskontroll
if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin') {
    $base = defined('BASE_URL') ? rtrim(BASE_URL, '/') : '';
    header('Location: ' . $base . '/');
    exit;
}

// Hjelpere
if (!function_exists('h')) {
    function h($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
}
function val($arr, $key, $def = '') { return isset($arr[$key]) ? $arr[$key] : $def; }

// Parametre
$nasjonId = isset($_GET['nasjon_id']) ? (int)$_GET['nasjon_id'] : 0;
$q        = isset($_GET['q']) ? trim((string)$_GET['q']) : '';
$doSearch = ($q !== '' || $nasjonId !== 0);

// Hent nasjoner for filter
$nasjoner = [];
$sqlN = "SELECT Nasjon_ID, Nasjon FROM tblznasjon WHERE Nasjon IS NOT NULL AND Nasjon <> '' ORDER BY Nasjon";
if ($resN = $conn->query($sqlN)) {
    while ($row = $resN->fetch_assoc()) { $nasjoner[] = $row; }
    $resN->free();
}

// Søk
$rows = [];
if ($doSearch) {
    $sql = "
        SELECT
            curr.FartTid_ID,
            curr.FartObj_ID,
            curr.FartNavn,
            curr.RegHavn,
            curr.Kallesignal,
            curr.Nasjon_ID   AS TNat,
            n.Nasjon,
            curr.Objekt      AS IsOriginalNow,
            o.Bygget,
            zft.TypeFork
        FROM tblfarttid AS curr
        LEFT JOIN tblfartobj   AS o   ON o.FartObj_ID  = curr.FartObj_ID
        LEFT JOIN tblfartspes  AS fs  ON fs.FartObj_ID = curr.FartObj_ID
        LEFT JOIN tblzfarttype AS zft ON zft.FartType_ID = fs.FartType_ID
        LEFT JOIN tblznasjon   AS n   ON n.Nasjon_ID   = curr.Nasjon_ID
        WHERE curr.FartTid_ID = (
            SELECT t2.FartTid_ID
            FROM tblfarttid t2
            WHERE t2.FartObj_ID = curr.FartObj_ID
            ORDER BY COALESCE(t2.YearTid,0) DESC, COALESCE(t2.MndTid,0) DESC, t2.FartTid_ID DESC
            LIMIT 1
        )
          AND (? = 0 OR curr.Nasjon_ID = ?)
          AND (? = '' OR curr.FartNavn LIKE CONCAT('%', ?, '%'))
        ORDER BY curr.FartNavn ASC
        LIMIT 200
    ";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('iiss', $nasjonId, $nasjonId, $q, $q);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($res) { while ($r = $res->fetch_assoc()) { $rows[] = $r; } $res->free(); }
    $stmt->close();
}

include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/menu.php';
?>
<!-- Responsive image box (contain, no crop) -->
    <div class="container" style="display:flex; justify-content:center;">
      <div class="image-box">
        <?php
          // 1) Velg tryggt bilde (DB -> fallback). Bruk basename()+h() for sikkerhet.
          $imgCandidate = null;

          // Hvis du i denne siden har en $imgRow fra tblxnmmfoto:
          if (isset($imgRow) && is_array($imgRow) && !empty($imgRow['Bilde_Fil'])) {
              $base = rtrim((string)($imgRow['URL_Bane'] ?? '/assets/img'), '/');
              $file = basename((string)$imgRow['Bilde_Fil']); // dropp path-fragmenter
              $imgCandidate = $base . '/' . $file;
          }
          // Alternativ kilde: $main['Bilde_Fil'] dersom du bruker den i siden:
          elseif (!empty($main['Bilde_Fil'])) {
              $imgCandidate = '/assets/img/' . basename((string)$main['Bilde_Fil']);
          }
          // 2) Garantert fallback:
          if (!$imgCandidate) {
              $imgCandidate = '/assets/img/placeholder2.jpg';
          }

          // 3) Relativ URL hvis siden ligger i /user/
          $imgRel = (substr($imgCandidate, 0, 1) === '/') ? ('..' . $imgCandidate) : $imgCandidate;

          // 4) Alt‑tekst (prøv å bruke type + navn hvis det finnes)
          $altText = trim(
            (string)($main['TypeFork'] ?? ($main['FartType'] ?? '')) . ' ' .
            (string)($main['FartNavn'] ?? 'Fartøy')
          );
          if ($altText === '') { $altText = 'Fartøybilde'; }
        ?>
        <img src="<?= h($imgRel) ?>" alt="<?= h($altText) ?>">
      </div>
    </div>
<div class="container mt-3">
  <h1>Administrasjon av fartøyer og parametre</h1>
  <h2>Endre, slett, lag nytt fartøy eller tabellparametre</h2>
  <p style="text-align:center; margin-top:1rem;">
    <a class="btn" href="param_admin.php">Administrer parametertabeller</a>
  </p>
  <p style="text-align:center; margin-top:1rem;">
    <a href="<?= h($base . '/admin/fartoy_nytt.php') ?>" class="btn">Opprett nytt fartøy</a>
  </p>
  <p class="text-small">Skriv inn del av navn  og/eller velg nasjon for å søke etter fartøy å endre.</p>
  <form method="get" class="search-form" style="margin-bottom:1rem; text-align:center;">
    <label for="q">Søk på del av navn:&nbsp;</label>
    <input type="text" id="q" name="q" value="<?= h($q) ?>" />
    <label for="nasjon_id">fra nasjon</label>
    <select name="nasjon_id" id="nasjon_id">
      <option value="0"<?= $nasjonId === 0 ? ' selected' : '' ?>>Alle nasjoner</option>
      <?php foreach ($nasjoner as $r): ?>
        <option value="<?= (int)$r['Nasjon_ID'] ?>"<?= $nasjonId === (int)$r['Nasjon_ID'] ? ' selected' : '' ?>><?= h($r['Nasjon']) ?></option>
      <?php endforeach; ?>
    </select>
    <button type="submit" class="btn">Søk</button>
    <?php $base = defined('BASE_URL') ? rtrim(BASE_URL, '/') : ''; ?>
    
  </form>

  <?php if ($doSearch && $rows): ?>
    <div class="table-responsive">
      <table class="table table-striped table-sm">
        <thead>
          <tr>
            <th>Type</th>
            <th>Navn</th>
            <th>Reg.havn</th>
            <th>Flaggstat</th>
            <th>Bygget</th>
            <th>Kallesignal</th>
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
            <td>
              <?php $objId = (int)val($r,'FartObj_ID',0); $tidId = (int)val($r,'FartTid_ID',0); ?>
              <?php if ($objId > 0 && $tidId > 0): ?>
                <a class="btn-small" href="fartoy_edit.php?obj_id=<?= $objId ?>&tid_id=<?= $tidId ?>">Rediger</a>
                <a class="btn-small" href="fartoy_delete.php?obj_id=<?= $objId ?>" onclick="return confirm('Er du sikker på at du vil slette dette fartøyet?');">Slett</a>
              <?php else: ?>
                <span class="muted">—</span>
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
  <?php endif; ?>
  
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
