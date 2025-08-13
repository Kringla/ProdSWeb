<?php
require_once __DIR__ . '/../includes/bootstrap.php'; // sørger for session og $mysqli
require_once __DIR__ . '/../includes/auth.php'; // ok å ha med for meny/rolle

function fetch_one($sql, $params = []) {
    global $mysqli;
    $stmt = $mysqli->prepare($sql);
    if ($params) {
        // alle parametrene her er int
        $types = str_repeat('i', count($params));
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    return $stmt->get_result()->fetch_assoc();
}

$obj_id  = filter_input(INPUT_GET, 'obj_id',  FILTER_VALIDATE_INT) ?: 0;
$navn_id = filter_input(INPUT_GET, 'navn_id', FILTER_VALIDATE_INT) ?: 0;

// Hvis vi kun fikk navn_id: finn tilhørende obj_id
if ($obj_id <= 0 && $navn_id > 0) {
    if ($row = fetch_one("SELECT FartObj_ID FROM tblFartNavn WHERE FartNavn_ID = ?", [$navn_id])) {
        $obj_id = (int)$row['FartObj_ID'];
    }
}

// Hvis vi kun fikk obj_id: velg et fornuftig navn (siste registrerte navn)
if ($obj_id > 0 && $navn_id <= 0) {
    if ($row = fetch_one("
        SELECT FartNavn_ID
        FROM tblFartNavn
        WHERE FartObj_ID = ?
        ORDER BY FartNavn_ID DESC
        LIMIT 1
    ", [$obj_id])) {
        $navn_id = (int)$row['FartNavn_ID'];
    }
}

// Hvis fortsatt mangler noen av dem, feilmelding
if ($obj_id <= 0 || $navn_id <= 0) {
    http_response_code(400);
    echo "Mangler eller ugyldige parametre: obj_id og navn_id må være > 0.";
    exit;
}

// Hent 'hovedraden' i tidslinjen for dette navnet/objektet (nyeste først)
$main = fetch_one("
    SELECT ft.*, fn.FartNavn, fo.NavnObj, ft.FartSpes_ID
    FROM tblFartTid ft
    JOIN tblFartNavn fn ON fn.FartNavn_ID = ft.FartNavn_ID
    JOIN tblFartObj  fo ON fo.FartObj_ID  = ft.FartObj_ID
    WHERE ft.FartObj_ID = ? AND ft.FartNavn_ID = ?
    ORDER BY COALESCE(ft.YearTid, 0) DESC, COALESCE(ft.MndTid, 0) DESC
    LIMIT 1
", [$obj_id, $navn_id]);

if (!$main) {
    http_response_code(404);
    echo "Fant ingen detaljer for valgt fartøy.";
    exit;
}

// ...resten av visningen din, inkl. lenke til tekniske data:
?>
<a class="btn right" href="fartoyspes.php?spes_id=<?= (int)$main['FartSpes_ID'] ?>">Tekniske data</a>

ini_set('display_errors', '1');
error_reporting(E_ALL);

if (!function_exists('h')) {
  function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
}
function get_int($name){ return isset($_GET[$name]) ? (int)$_GET[$name] : 0; }

$objId  = get_int('obj_id');
$navnId = get_int('navn_id');

if ($objId <= 0 || $navnId <= 0) {
  http_response_code(400);
  die('Mangler eller ugyldige parametre: obj_id og navn_id må være > 0.');
}

/* 1) HENT «GJELDENDE» RAD FOR DETTE NAVNET (SISTE ÅR/MND) + BYGGET FRA OPPRINNELIG OBJEKT */
$sqlMain = "
SELECT
  t.FartTid_ID, t.YearTid, t.MndTid, t.FartObj_ID, t.FartNavn_ID, t.FartSpes_ID,
  t.Objekt, t.Rederi, t.Nasjon_ID, t.RegHavn, t.Kallesignal, t.MMSI, t.Fiskerinr, t.Historie,
  fn.FartNavn, fn.PennantTiln, ft.TypeFork, n.Nasjon,
  o.Bygget,
  o.Historikk AS ObjHistorikk
FROM tblFartTid t
JOIN tblFartNavn       fn ON fn.FartNavn_ID    = t.FartNavn_ID
LEFT JOIN tblzFartType ft ON ft.FartType_ID    = fn.FartType_ID
LEFT JOIN tblzNasjon   n  ON n.Nasjon_ID       = t.Nasjon_ID
LEFT JOIN tblFartTid   ot ON ot.FartNavn_ID    = t.FartNavn_ID AND ot.Objekt = 1
LEFT JOIN tblFartObj   o  ON o.FartObj_ID      = ot.FartObj_ID
WHERE t.FartObj_ID = ? AND t.FartNavn_ID = ?
ORDER BY t.YearTid DESC, t.MndTid DESC, t.FartTid_ID DESC
LIMIT 1
";
$stmt = $conn->prepare($sqlMain) or die('Prepare (main) feilet: ' . $conn->error);
$stmt->bind_param('ii', $objId, $navnId);
$stmt->execute() or die('Execute (main) feilet: ' . $stmt->error);
$main = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$main) {
  http_response_code(404);
  die('Ingen data funnet for valgt navn/objekt.');
}

/* 2) TEKNISKE SPESIFIKASJONER FRA tblFartSpes (+ oppslagstabeller) */
$spes = [];
if (!empty($main['FartSpes_ID'])) {
  $sqlSpes = "
    SELECT s.*,
           v.VerftNavn AS VerftNavn,    v.Sted AS VerftSted,
           sv.VerftNavn AS SkrogVerftNavn, sv.Sted AS SkrogVerftSted,
           sk.TypeSkrog,
           rigg.RiggFork, rigg.RiggDetalj,
           mot.MotorFork, mot.MotorDetalj,
           kl.TypeKlasseNavn,
           mat.Materiale
    FROM tblFartSpes s
    LEFT JOIN tblVerft       v   ON v.Verft_ID         = s.Verft_ID
    LEFT JOIN tblVerft       sv  ON sv.Verft_ID        = s.SkrogID
    LEFT JOIN tblzFartSkrog  sk  ON sk.FartSkrog_ID    = s.FartSkrog_ID
    LEFT JOIN tblzFartRigg   rigg ON rigg.FartRigg_ID  = s.FartRigg_ID
    LEFT JOIN tblzFartMotor  mot  ON mot.FartMotor_ID  = s.FartMotor_ID
    LEFT JOIN tblzFartKlasse kl  ON kl.FartKlasse_ID   = s.FartKlasse_ID
    LEFT JOIN tblzFartMat    mat ON mat.FartMat_ID     = s.FartMat_ID
    WHERE s.FartSpes_ID = ?
  ";
  $p = $conn->prepare($sqlSpes) or die('Prepare (spes) feilet: ' . $conn->error);
  $p->bind_param('i', $main['FartSpes_ID']);
  $p->execute() or die('Execute (spes) feilet: ' . $p->error);
  $spes = $p->get_result()->fetch_assoc() ?: [];
  $p->close();
}

/* 3) REGISTRERINGS-/NAVNEHISTORIKK (ALLE ØVRIGE RADER FOR SAMME OBJEKT) + TYPE/NAVN/PENNANT */
$sqlHist = "
SELECT
  t.YearTid, t.MndTid,
  ft.TypeFork,
  fn.FartNavn, fn.PennantTiln,
  t.RegHavn, n.Nasjon, t.Kallesignal, t.Rederi
FROM tblFartTid t
JOIN tblFartNavn       fn ON fn.FartNavn_ID = t.FartNavn_ID
LEFT JOIN tblzFartType ft ON ft.FartType_ID = fn.FartType_ID
LEFT JOIN tblzNasjon   n  ON n.Nasjon_ID    = t.Nasjon_ID
WHERE t.FartObj_ID = ?
ORDER BY t.YearTid DESC, t.MndTid DESC, t.FartTid_ID DESC
LIMIT 200
";
$h = $conn->prepare($sqlHist) or die('Prepare (hist) feilet: ' . $conn->error);
$h->bind_param('i', $objId);
$h->execute() or die('Execute (hist) feilet: ' . $h->error);
$hist = $h->get_result()->fetch_all(MYSQLI_ASSOC);
$h->close();

/* --- VISNING --- */
include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/menu.php';
?>
<style>
  .wrap{max-width:1100px;margin:0 auto;padding:12px;}
  .muted{color:#666;}
  .grid{display:grid;grid-template-columns:1fr 1fr;gap:16px;}
  @media (max-width:900px){.grid{grid-template-columns:1fr;}}
  .card{background:#fff;border:1px solid #ddd;border-radius:12px;padding:14px;box-shadow:0 1px 2px rgba(0,0,0,.04);}
  .card h2{margin:.25rem 0 1rem 0;font-size:1.1rem}
  table.meta th{white-space:nowrap;text-align:left;padding:.25rem .5rem .25rem 0;color:#444;vertical-align:top}
  table.meta td{padding:.25rem 0}
  table.table{width:100%;border-collapse:collapse}
  table.table th,table.table td{border:1px solid #ddd;padding:.35rem .5rem}
  table.table th{background:#f7f7f7;text-align:left}
</style>

<div class="wrap">
  <h1 style="margin-bottom:.25rem;">
    <?php if (!empty($main['TypeFork'])): ?>
      <span><?= h($main['TypeFork']) ?></span>
    <?php endif; ?>
    <?= ' ' . h($main['FartNavn']) ?>
    <?php if ((int)$main['Objekt'] === 1): ?>
      <span title="Navnet tilhører opprinnelig fartøy (Objekt = 1)">•</span>
    <?php endif; ?>
  </h1>
  <div class="muted" style="margin:0 0 .75rem 0;">
    <?= h($main['Nasjon'] ?: '') ?>
  </div>

  <div class="grid">
    <!-- Grunndata -->
    <div class="card">
      <h2>Grunndata</h2>
      <table class="meta">
        <tr><th>Bygget</th><td><?= h($main['Bygget'] ?? '') ?></td></tr>
        <tr><th>Reg.havn</th><td><?= h($main['RegHavn'] ?? '') ?></td></tr>
        <tr><th>Kallesignal</th><td><?= h($main['Kallesignal'] ?? '') ?></td></tr>
        <tr><th>Rederi/Eier</th><td><?= h($main['Rederi'] ?? '') ?></td></tr>
        <?php if (!empty($main['MMSI'])): ?>
          <tr><th>MMSI</th><td><?= h($main['MMSI']) ?></td></tr>
        <?php endif; ?>
        <?php if (!empty($main['Fiskerinr'])): ?>
          <tr><th>Fiskerinr</th><td><?= h($main['Fiskerinr']) ?></td></tr>
        <?php endif; ?>
        <tr><th>Gjeldende pr.</th>
          <td>
            <?php
              $ym = [];
              if (!empty($main['YearTid'])) $ym[] = (int)$main['YearTid'];
              if (!empty($main['MndTid']))  $ym[] = str_pad((int)$main['MndTid'],2,'0',STR_PAD_LEFT);
              echo h(implode('-', $ym));
            ?>
          </td>
        </tr>
      </table>
    </div>

    <!-- Tekniske data -->
    <div class="card">
      <h2>Tekniske data</h2>
      <div style="display:flex;align-items:center;justify-content:space-between;">
        <h2 style="margin:0;">Tekniske data</h2>
        <?php if (!empty($main['FartSpes_ID'])): ?>
          <a href="fartoyspes.php?spes_id=<?= (int)$main['FartSpes_ID'] ?>" style="font-size:.95rem;">Full spesifikasjon →</a>
        <?php endif; ?>
      </div>
      <?php if ($spes): ?>
        <table class="meta">
          <?php if (!empty($spes['VerftNavn'])): ?>
            <tr><th>Verft</th><td><?= h($spes['VerftNavn']) ?><?= !empty($spes['VerftSted']) ? ', '.h($spes['VerftSted']) : '' ?></td></tr>
          <?php endif; ?>
          <?php if (!empty($spes['SkrogVerftNavn'])): ?>
            <tr><th>Skrog/verft</th><td><?= h($spes['SkrogVerftNavn']) ?><?= !empty($spes['SkrogVerftSted']) ? ', '.h($spes['SkrogVerftSted']) : '' ?></td></tr>
          <?php endif; ?>
          <?php if (!empty($spes['Byggenr'])): ?>
            <tr><th>Byggenr</th><td><?= h($spes['Byggenr']) ?></td></tr>
          <?php endif; ?>
          <?php if (!empty($spes['Materiale'])): ?>
            <tr><th>Materiale</th><td><?= h($spes['Materiale']) ?></td></tr>
          <?php endif; ?>
          <?php if (!empty($spes['TypeSkrog'])): ?>
            <tr><th>Skrogtype</th><td><?= h($spes['TypeSkrog']) ?></td></tr>
          <?php endif; ?>
          <?php if (!empty($spes['TypeKlasseNavn'])): ?>
            <tr><th>Klasse</th><td><?= h($spes['TypeKlasseNavn']) ?></td></tr>
          <?php endif; ?>
          <?php if (!empty($spes['RiggFork']) || !empty($spes['RiggDetalj'])): ?>
            <tr><th>Rigg</th><td><?= h(trim(($spes['RiggFork'] ?? '').' '.($spes['RiggDetalj'] ?? ''))) ?></td></tr>
          <?php endif; ?>
          <?php if (!empty($spes['MotorFork']) || !empty($spes['MotorDetalj'])): ?>
            <tr><th>Motor</th><td><?= h(trim(($spes['MotorFork'] ?? '').' '.($spes['MotorDetalj'] ?? ''))) ?></td></tr>
          <?php endif; ?>
          <?php if (!empty($spes['Lengde']) || !empty($spes['Bredde']) || !empty($spes['Dypg'])): ?>
            <tr><th>Dimensjoner</th>
                <td>
                  <?php
                    $dim = [];
                    if (!empty($spes['Lengde'])) $dim[] = 'L: '.(int)$spes['Lengde'].' m';
                    if (!empty($spes['Bredde'])) $dim[] = 'B: '.(int)$spes['Bredde'].' m';
                    if (!empty($spes['Dypg']))   $dim[] = 'D: '.(int)$spes['Dypg'].' m';
                    echo h(implode('  |  ', $dim));
                  ?>
                </td></tr>
          <?php endif; ?>
          <?php if (!empty($spes['Tonnasje'])): ?>
            <tr><th>Tonnasje</th><td><?= h($spes['Tonnasje']) ?></td></tr>
          <?php endif; ?>
          <?php if (!empty($spes['Drektigh'])): ?>
            <tr><th>Drektighet</th><td><?= h($spes['Drektigh']) ?></td></tr>
          <?php endif; ?>
        </table>
      <?php else: ?>
        <div class="muted">Ingen registrerte spesifikasjoner.</div>
      <?php endif; ?>
    </div>
  </div>

  <!-- Samlet registrerings- og navnehistorikk for objektet -->
  <div class="card" style="margin-top:16px;">
    <h2>Registrerings- og navnehistorikk</h2>
    <?php if ($hist): ?>
      <table class="table">
        <thead>
          <tr>
            <th>År</th>
            <th>Type</th>
            <th>Navn</th>
            <th>Pennant</th>
            <th>Reg.havn</th>
            <th>Flaggstat</th>
            <th>Kallesignal</th>
            <th>Rederi/Eier</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($hist as $row): ?>
            <tr>
              <td>
                <?php
                  $ym = [];
                  if (!empty($row['YearTid'])) $ym[] = (int)$row['YearTid'];
                  if (!empty($row['MndTid']))  $ym[] = str_pad((int)$row['MndTid'],2,'0',STR_PAD_LEFT);
                  echo h(implode('-', $ym));
                ?>
              </td>
              <td><?= h($row['TypeFork'] ?? '') ?></td>
              <td><?= h($row['FartNavn'] ?? '') ?></td>
              <td><?= h($row['PennantTiln'] ?? '') ?></td>
              <td><?= h($row['RegHavn'] ?? '') ?></td>
              <td><?= h($row['Nasjon'] ?? '') ?></td>
              <td><?= h($row['Kallesignal'] ?? '') ?></td>
              <td><?= h($row['Rederi'] ?? '') ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    <?php else: ?>
      <div class="muted">Ingen historikk funnet.</div>
    <?php endif; ?>
  </div>

  
  <?php if (!empty($main['ObjHistorikk'])): ?>
  <div class="card" style="margin-top:16px;">
    <h2>Objekthistorikk</h2>
    <div><?= nl2br(h($main['ObjHistorikk'])) ?></div>
  </div>
  <?php endif; ?>

<p style="margin-top:10px;"><a href="javascript:history.back()">← Tilbake</a></p>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
