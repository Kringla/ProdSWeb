<?php
/**
 * /user/fartoydetaljer.php
 * Viser detaljer for valgt fartøy basert på ?obj_id=...&navn_id=...
 * Endringer:
 *  - "Tekniske detaljer": peker til /user/fartoyspes.php?spes_id=... (via tblFartTid for valgt navn_id)
 *  - Navnehistorikk: viser TypeFork (tekst) i stedet for FartType-ID
 *  - "Hoveddetaljer" fjernet; "Lenker" flyttes øverst (på samme område)
 *  - Lenker-blokk: robust filtrering + fallback-filter i PHP
 */

require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/auth.php'; // for meny/rolle

// Helpers
if (!function_exists('h')) { function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); } }
function nonempty($v){ return isset($v) && $v !== '' && $v !== null; }

// Parametre
$objId  = isset($_GET['obj_id'])  ? (int)$_GET['obj_id']  : 0;
$navnId = isset($_GET['navn_id']) ? (int)$_GET['navn_id'] : 0;
if ($objId <= 0 || $navnId <= 0) {
  http_response_code(400);
  echo "<p>Mangler eller ugyldige parametre: obj_id og navn_id må være &gt; 0.</p>";
  exit;
}

// Hovedrad (navn, type, basis)
$sqlMain = "
SELECT
  fn.FartNavn_ID, fn.FartObj_ID, fn.FartNavn, fn.FartType_ID,
  t.typefork,
  fo.IMO, fo.Kontrahert, fo.Kjolstrukket, fo.Sjosatt, fo.Levert
FROM tblfartnavn fn
JOIN tblfartobj fo       ON fo.FartObj_ID = fn.FartObj_ID
LEFT JOIN tblzfarttype t ON t.FartType_ID = COALESCE(fn.FartType_ID, fo.FartType_ID)
WHERE fn.FartObj_ID = ? AND fn.FartNavn_ID = ?
LIMIT 1";
if (!$stmt = $conn->prepare($sqlMain)) { http_response_code(500); echo "DB-feil (prepare main): ".h($conn->error); exit; }
$stmt->bind_param('ii', $objId, $navnId);
$stmt->execute();
$main = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$main) {
  http_response_code(404);
  echo "<p>Fant ingen detaljer for obj_id=".h($objId)." / navn_id=".h($navnId).".</p>";
  exit;
}

// FartSpes_ID for valgt navn_id (tblFartTid)
$spesIdForNavn = null;
if ($stmt = $conn->prepare("
  SELECT ft.FartSpes_ID
  FROM tblfarttid ft
  WHERE ft.FartNavn_ID = ? AND ft.FartSpes_ID IS NOT NULL
  ORDER BY ft.FartTid_ID DESC
  LIMIT 1
")) {
  $stmt->bind_param('i', $navnId);
  if ($stmt->execute()) {
    $r = $stmt->get_result()->fetch_assoc();
    if ($r) $spesIdForNavn = (int)$r['FartSpes_ID'];
  }
  $stmt->close();
}

// Nasjon_ID for valgt navn_id (tblFartTid)
$nasjonIdFromTid = null;
if ($stmt = $conn->prepare("
  SELECT ft.Nasjon_ID
  FROM tblfarttid ft
  WHERE ft.FartNavn_ID = ? AND ft.Nasjon_ID IS NOT NULL
  ORDER BY ft.FartTid_ID DESC
  LIMIT 1
")) {
  $stmt->bind_param('i', $navnId);
  if ($stmt->execute()) {
    $r = $stmt->get_result()->fetch_assoc();
    if ($r) $nasjonIdFromTid = (int)$r['Nasjon_ID'];
  }
  $stmt->close();
}

// Navnehistorikk: Navn + YearTid + MndTid + TypeFork (tekst)
$navnehistorikk = [];
if ($stmt = $conn->prepare("
  SELECT
    fn.FartNavn,
    ft.YearTid,
    ft.MndTid,
    tz.typefork AS TypeFork
  FROM tblfarttid ft
  JOIN tblfartnavn fn ON fn.FartNavn_ID = ft.FartNavn_ID
  LEFT JOIN tblfartobj fo ON fo.FartObj_ID = fn.FartObj_ID
  LEFT JOIN tblzfarttype tz ON tz.FartType_ID = COALESCE(fn.FartType_ID, fo.FartType_ID)
  WHERE fn.FartObj_ID = ?
  ORDER BY ft.YearTid, ft.MndTid, ft.FartTid_ID
")) {
  $stmt->bind_param('i', $objId);
  if ($stmt->execute()) {
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) { $navnehistorikk[] = $row; }
  }
  $stmt->close();
}

// LENKER — robust: prøv filtrert spørring først, fall tilbake til PHP-filter
$links = [];
$cols = [];
if ($resCols = @$conn->query("SELECT * FROM tblxfartlink LIMIT 0")) {
  foreach ($resCols->fetch_fields() as $f) { $cols[] = $f->name; }
  $resCols->close();
}

// 1) Forsøk filtrert spørring på sannsynlige nøkkelkolonner
$candidatesNavn = ['FartNavn_ID','FartNavnID','Navn_ID','NavnId','FNavn_ID','FNavnID'];
$candidatesObj  = ['FartObj_ID','FartObjID','Obj_ID','Objekt_ID','ObjId','ObjektId','FartObj','FartObjid','FartObjId','FartID','Fart_ID','FartId'];
$linkKeyCol = null;
$linkKeyVal = null;

foreach ($candidatesNavn as $c) {
  if (in_array($c, $cols, true)) { $linkKeyCol = $c; $linkKeyVal = $navnId; break; }
}
if ($linkKeyCol === null) {
  foreach ($candidatesObj as $c) {
    if (in_array($c, $cols, true)) { $linkKeyCol = $c; $linkKeyVal = $objId; break; }
  }
}

if ($linkKeyCol !== null) {
  $sqlLinks = "SELECT * FROM tblxfartlink WHERE {$linkKeyCol} = ? ORDER BY 1";
  if ($stmt = $conn->prepare($sqlLinks)) {
    $stmt->bind_param('i', $linkKeyVal);
    if ($stmt->execute()) {
      $res = $stmt->get_result();
      while ($row = $res->fetch_assoc()) {
        $url   = $row['Link'] ?? $row['LinkURL'] ?? $row['URL'] ?? $row['Url'] ?? $row['Adresse'] ?? $row['Href'] ?? '';
        $title = $row['LinkInnh'] ?? $row['LinkType'] ?? $row['Tittel'] ?? $row['Title'] ?? $row['Tekst'] ?? '';
        $kilde = $row['Kilde'] ?? $row['Source'] ?? '';
        $note  = $row['Merknad'] ?? $row['Note'] ?? $row['Beskrivelse'] ?? '';
        if ($url !== '') $links[] = ['url'=>$url,'title'=>$title,'kilde'=>$kilde,'note'=>$note];
      }
    }
    $stmt->close();
  }
}

// 2) Fallback: Hvis ingen lenker funnet, hent alle og filtrer i PHP på kjente ID-felt
if (empty($links)) {
  if ($res = @$conn->query("SELECT * FROM tblxfartlink")) {
    while ($row = $res->fetch_assoc()) {
      $idMatches = false;
      foreach (array_merge($candidatesNavn, $candidatesObj) as $c) {
        if (array_key_exists($c, $row)) {
          $val = (string)$row[$c];
          if ($val !== '' && (int)$val === $navnId) { $idMatches = true; break; }
          if ($val !== '' && (int)$val === $objId)  { $idMatches = true; break; }
        }
      }
      if ($idMatches) {
        $url   = $row['Link'] ?? $row['LinkURL'] ?? $row['URL'] ?? $row['Url'] ?? $row['Adresse'] ?? $row['Href'] ?? '';
        $title = $row['LinkInnh'] ?? $row['LinkType'] ?? $row['Tittel'] ?? $row['Title'] ?? $row['Tekst'] ?? '';
        $kilde = $row['Kilde'] ?? $row['Source'] ?? '';
        $note  = $row['Merknad'] ?? $row['Note'] ?? $row['Beskrivelse'] ?? '';
        if ($url !== '') $links[] = ['url'=>$url,'title'=>$title,'kilde'=>$kilde,'note'=>$note];
      }
    }
    $res->close();
  }
}

// Topp-linje (type + navn)
$topType = $main['typefork'] ?? $main['FartType_ID'] ?? '';
$topName = $main['FartNavn']   ?? '';
$topLine = trim(($topType ? $topType.' ' : '').$topName);

// Grupper (kun "Bygg & status" igjen – Hoveddetaljer er sløyfet)
$GROUPS = [
  'Bygg & status' => [
    'Kontrahert'    => ['label' => 'Kontrahert',    'val' => $main['Kontrahert'] ?? ''],
    'Kjolstrukket'  => ['label' => 'Kjølstrukket',  'val' => $main['Kjolstrukket'] ?? ''],
    'Sjosatt'       => ['label' => 'Sjøsatt',       'val' => $main['Sjosatt'] ?? ''],
    'Levert'        => ['label' => 'Levert',        'val' => $main['Levert'] ?? ''],
  ],
];

// Hjelper
function group_has_values(array $fields): bool {
  foreach ($fields as $f) if (nonempty($f['val'])) return true;
  return false;
}
?>
<?php include __DIR__ . '/../includes/header.php'; ?>
<?php include __DIR__ . '/../includes/menu.php'; ?>

<style>
.detail-wrap { max-width: 980px; margin: 0 auto; padding: 8px 12px; }
.detail-head { text-align: center; margin: 4px 0 8px; }
.detail-head h1 { margin: 0; font-size: 1.4rem; }
.detail-head h2.sub { margin-top: 4px; font-size: 1.25rem; font-weight: 600; line-height: 1.25; opacity: 0.9; }

.detail-ids { font-size: 0.78rem; opacity: 0.8; text-align: center; margin: 4px 0 10px; }

.detail-actions { display: flex; justify-content: space-between; align-items: center; margin: 6px 0 10px; }
.detail-actions .left { display: flex; gap: 8px; align-items: center; }

.btn-slim { display: inline-block; padding: 6px 10px; border: 1px solid #ccc; border-radius: 8px; text-decoration: none; font-size: 0.92rem; }

.detail-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 6px 18px; }
.detail-group { grid-column: 1 / -1; margin-top: 8px; font-weight: 600; border-top: 1px solid #ddd; padding-top: 6px; }
.detail-row { display: grid; grid-template-columns: 200px 1fr; align-items: start; gap: 8px; font-size: 0.95rem; }
.detail-row .label { color: #333; opacity: 0.9; }
.detail-row .value { color: #111; white-space: pre-line; }

.section { margin-top: 14px; }
.section h3 { margin: 6px 0; font-size: 1.05rem; }

.tbl { width: 100%; border-collapse: collapse; font-size: 0.95rem; }
.tbl th, .tbl td { padding: 6px 8px; border-bottom: 1px solid #eee; text-align: left; line-height: 1.2; }

.links-list { list-style: none; padding-left: 0; margin: 0; }
.links-list li { margin: 4px 0; }
.links-list a { text-decoration: none; border-bottom: 1px dotted #999; }
.links-list small { opacity: 0.8; }

@media (max-width: 700px) {
  .detail-grid { grid-template-columns: 1fr; }
  .detail-row { grid-template-columns: 160px 1fr; }
}

/* --- Navnehistorikk: presise mellomrom --- */
/* Tabellen må ha class="tbl tbl-navnehist" og kolonner: TypeFork, FartNavn, År, Mnd */
.tbl.tbl-navnehist th, .tbl.tbl-navnehist td { padding: 4px 6px; } /* kompakt base */

.tbl.tbl-navnehist th:nth-child(1),
.tbl.tbl-navnehist td:nth-child(1) { padding-right: 3ch !important; } /* mellom TypeFork → FartNavn */

.tbl.tbl-navnehist th:nth-child(3),
.tbl.tbl-navnehist td:nth-child(3) { padding-right: 3ch !important; } /* mellom År → Mnd */
</style>

<div class="detail-wrap">
  <div class="detail-head">
    <h1>Fartøydetaljer</h1>
    <h2 class="sub"><?= h($topLine) ?></h2>
  </div>

  <div class="detail-ids">
    Objekt ID: <?= (int)$main['FartObj_ID'] ?>
    <?php if (nonempty($nasjonIdFromTid)): ?> • Nasjon ID: <?= (int)$nasjonIdFromTid ?><?php endif; ?>
  </div>

  <div class="detail-actions">
    <div class="left">
      <a href="#" class="btn-slim" onclick="if(history.length>1){history.back();return false;}" title="Tilbake">← Tilbake</a>
      <?php if ($spesIdForNavn): ?>
        <a class="btn-slim" href="<?= BASE_URL ?>/user/fartoyspes.php?spes_id=<?= (int)$spesIdForNavn ?>">Tekniske detaljer</a>
      <?php endif; ?>
    </div>
    <div></div>
  </div>

  <!-- LENKER flyttet hit (på plassen til "Hoveddetaljer") -->
  <?php if (!empty($links)): ?>
    <div class="detail-grid">
      <div class="detail-group">Lenker</div>
      <div class="detail-row" style="grid-column: 1 / -1;">
        <ul class="links-list">
          <?php foreach ($links as $lnk): ?>
            <li>
              <a href="<?= h($lnk['url']) ?>" target="_blank" rel="noopener">
                <?= h($lnk['title'] ?: $lnk['url']) ?>
              </a>
              <?php if ($lnk['kilde'] || $lnk['note']): ?>
                <br><small>
                  <?php if ($lnk['kilde']): ?>Kilde: <?= h($lnk['kilde']) ?><?php endif; ?>
                  <?php if ($lnk['kilde'] && $lnk['note']): ?> • <?php endif; ?>
                  <?php if ($lnk['note']): ?><?= h($lnk['note']) ?><?php endif; ?>
                </small>
              <?php endif; ?>
            </li>
          <?php endforeach; ?>
        </ul>
      </div>
    </div>
  <?php endif; ?>

  <div class="detail-grid">
    <?php foreach ($GROUPS as $groupTitle => $fields): ?>
      <?php if (!group_has_values($fields)) continue; ?>
      <div class="detail-group"><?= h($groupTitle) ?></div>
      <?php foreach ($fields as $key => $cfg): ?>
        <?php $val = $cfg['val']; if (!nonempty($val)) continue; ?>
        <div class="detail-row">
          <div class="label"><?= h($cfg['label']) ?></div>
          <div class="value"><?= h($val) ?></div>
        </div>
      <?php endforeach; ?>
    <?php endforeach; ?>
  </div>

  <?php if (!empty($navnehistorikk)): ?>
  <div class="section">
    <h3>Navnehistorikk</h3>
    <table class="tbl tbl-navnehist">
      <thead>
        <tr>
          <th>Navn</th>
          <th>Tidspunkt</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($navnehistorikk as $n): ?>
          <?php
            // Navn = TypeFork + " " + FartNavn (uten dobbel-space)
            $type = trim((string)($n['TypeFork'] ?? ''));
            $name = trim((string)($n['FartNavn'] ?? ''));
            $navnConcat = trim($type . ($type && $name ? ' ' : '') . $name);

            // Tidspunkt = YearTid + "/" + MndTid (00-pad på måned)
            $year  = isset($n['YearTid']) ? (int)$n['YearTid'] : 0;
            $month = isset($n['MndTid'])  ? (int)$n['MndTid']  : 0;
            if ($year > 0 && $month > 0) {
              $tidspunkt = $year . '/' . sprintf('%02d', $month);
            } elseif ($year > 0) {
              $tidspunkt = (string)$year;
            } else {
              $tidspunkt = '';
            }
          ?>
          <tr>
            <td><?= h($navnConcat) ?></td>
            <td><?= h($tidspunkt) ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
<?php endif; ?>

</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
