<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/auth.php'; // ok å ha med for meny/rolle

ini_set('display_errors', '1');
error_reporting(E_ALL);

if (!function_exists('h')) {
  function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
}
function intval_or_zero($v){ return isset($v) ? (int)$v : 0; }

$spesId = isset($_GET['spes_id']) ? (int)$_GET['spes_id'] : 0;
$objId  = isset($_GET['obj_id'])  ? (int)$_GET['obj_id']  : 0;
$navnId = isset($_GET['navn_id']) ? (int)$_GET['navn_id'] : 0;

/* Hvis spes_id ikke er gitt, men obj_id & navn_id er gitt:
   finn siste (nyeste) tidslinjerad for dette navnet/objektet og ta dens FartSpes_ID */
if ($spesId <= 0 && $objId > 0 && $navnId > 0) {
  $sqlFind = "
    SELECT t.FartSpes_ID
    FROM tblfarttid t
    WHERE t.FartObj_ID = ? AND t.FartNavn_ID = ?
    ORDER BY t.YearTid DESC, t.MndTid DESC, t.FartTid_ID DESC
    LIMIT 1
  ";
  $p = $conn->prepare($sqlFind) or die('Prepare (find) feilet: ' . $conn->error);
  $p->bind_param('ii', $objId, $navnId);
  $p->execute() or die('Execute (find) feilet: ' . $p->error);
  $res = $p->get_result()->fetch_assoc();
  $p->close();
  if (!empty($res['FartSpes_ID'])) {
    $spesId = (int)$res['FartSpes_ID'];
  }
}

$spes = [];
if ($spesId > 0) {
  $sql = "
    SELECT s.*,
           v.VerftNavn AS VerftNavn, v.Sted AS VerftSted,
           sv.VerftNavn AS SkrogVerftNavn, sv.Sted AS SkrogVerftSted,
           sk.TypeSkrog,
           rigg.RiggFork, rigg.RiggDetalj,
           mot.MotorFork, mot.MotorDetalj,
           kl.TypeKlasseNavn,
           mat.Materiale
    FROM tblfartspes s
    LEFT JOIN tblverft       v   ON v.Verft_ID        = s.Verft_ID
    LEFT JOIN tblverft       sv  ON sv.Verft_ID       = s.SkrogID
    LEFT JOIN tblzfartskrog  sk  ON sk.FartSkrog_ID   = s.FartSkrog_ID
    LEFT JOIN tblzfartrigg   rigg ON rigg.FartRigg_ID = s.FartRigg_ID
    LEFT JOIN tblzfartmotor  mot  ON mot.FartMotor_ID = s.FartMotor_ID
    LEFT JOIN tblzfartklasse kl  ON kl.FartKlasse_ID  = s.FartKlasse_ID
    LEFT JOIN tblzfartmat    mat ON mat.FartMat_ID    = s.FartMat_ID
    WHERE s.FartSpes_ID = ?
  ";
  $stmt = $conn->prepare($sql) or die('Prepare feilet: ' . $conn->error);
  $stmt->bind_param('i', $spesId);
  $stmt->execute() or die('Execute feilet: ' . $stmt->error);
  $spes = $stmt->get_result()->fetch_assoc() ?: [];
  $stmt->close();
}

include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/menu.php';
?>
<style>
  .wrap{max-width:1100px;margin:0 auto;padding:12px}
  .card{background:#fff;border:1px solid #ddd;border-radius:12px;padding:14px;box-shadow:0 1px 2px rgba(0,0,0,.04);margin-bottom:14px}
  table.meta{border-collapse:collapse}
  table.meta th{white-space:nowrap;text-align:left;padding:.3rem .6rem .3rem 0;color:#444;vertical-align:top}
  table.meta td{padding:.3rem 0}
  .muted{color:#666}
  .grid{display:grid;grid-template-columns:1fr 1fr;gap:18px}
  @media (max-width:900px){.grid{grid-template-columns:1fr}}
  .code{font-family:ui-monospace,SFMono-Regular,Menlo,Monaco,Consolas,"Liberation Mono","Courier New",monospace;font-size:.92rem;background:#f8f8f8;border:1px solid #eee;border-radius:8px;padding:.5rem .6rem}
</style>

<div class="wrap">
  <h1>Fartøy – full spesifikasjon</h1>

  <form method="get" class="card" style="display:flex;gap:10px;align-items:flex-end;flex-wrap:wrap">
    <div>
      <label for="spes_id">FartSpes&nbsp;ID</label><br>
      <input type="number" id="spes_id" name="spes_id" value="<?= $spesId ?: '' ?>" min="1">
    </div>
    <div class="muted">eller</div>
    <div>
      <label for="obj_id">Objekt&nbsp;ID</label><br>
      <input type="number" id="obj_id" name="obj_id" value="<?= $objId ?: '' ?>" min="1">
    </div>
    <div>
      <label for="navn_id">Navn&nbsp;ID</label><br>
      <input type="number" id="navn_id" name="navn_id" value="<?= $navnId ?: '' ?>" min="1">
    </div>
    <div>
      <button type="submit">Hent</button>
    </div>
    <div class="muted" style="margin-left:auto">
      Tips: Fra detaljsiden kan du lenke hit med <span class="code">fartoyspes.php?spes_id=&lt;FartSpes_ID&gt;</span>.
    </div>
  </form>

  <?php if ($spesId > 0 && !$spes): ?>
    <div class="card">Fant ingen spesifikasjon for ID <?= (int)$spesId ?>.</div>
  <?php endif; ?>

  <?php if ($spes): ?>
    <div class="grid">
      <div class="card">
        <h2>Oppslåtte verdier (fra parameter-tabeller)</h2>
        <table class="meta">
          <?php if (!empty($spes['VerftNavn'])): ?>
            <tr><th>Verft</th><td><?= h($spes['VerftNavn']) ?><?= !empty($spes['VerftSted'])?', '.h($spes['VerftSted']):'' ?></td></tr>
          <?php endif; ?>
          <?php if (!empty($spes['SkrogVerftNavn'])): ?>
            <tr><th>Skrog/verft</th><td><?= h($spes['SkrogVerftNavn']) ?><?= !empty($spes['SkrogVerftSted'])?', '.h($spes['SkrogVerftSted']):'' ?></td></tr>
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
          <?php if (!empty($spes['Materiale'])): ?>
            <tr><th>Materiale</th><td><?= h($spes['Materiale']) ?></td></tr>
          <?php endif; ?>
        </table>
      </div>

      <div class="card">
        <h2>Kjernefelt fra tblfartspes</h2>
        <table class="meta">
          <?php
            // Vis ALT fra s.* på en grei måte:
            foreach ($spes as $k => $v) {
              // hopp over de oppslåtte aliasene for å ikke duplisere
              if (in_array($k, ['VerftNavn','VerftSted','SkrogVerftNavn','SkrogVerftSted','TypeSkrog','RiggFork','RiggDetalj','MotorFork','MotorDetalj','TypeKlasseNavn','Materiale'], true)) {
                continue;
              }
              echo '<tr><th>'.h($k).'</th><td>'.h($v).'</td></tr>';
            }
          ?>
        </table>
      </div>
    </div>
  <?php else: ?>
    <div class="card">Oppgi enten <strong>FartSpes_ID</strong> eller kombinasjonen <strong>Objekt&nbsp;ID + Navn&nbsp;ID</strong>, og trykk «Hent».</div>
  <?php endif; ?>

  <p style="margin-top:10px;"><a href="javascript:history.back()">← Tilbake</a></p>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
