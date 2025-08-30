<?php
// admin/fartoy_edit.php
// Rediger eksisterende fartøyinformasjon. Krever admin-rolle.

require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/auth.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Sjekk tilgang
if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin') {
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
$objId = isset($_GET['obj_id']) ? (int)$_GET['obj_id'] : 0;
$tidId = isset($_GET['tid_id']) ? (int)$_GET['tid_id'] : 0;

if ($tidId <= 0 && $objId <= 0) {
    http_response_code(400);
    echo "Ugyldige parametre.";
    exit;
}

// Hent nasjoner for dropdown
$nasjoner = [];
$sqlN = "SELECT Nasjon_ID, Nasjon
         FROM tblznasjon
         WHERE Nasjon IS NOT NULL AND Nasjon <> ''
         ORDER BY Nasjon";
if ($resN = $conn->query($sqlN)) {
    while ($row = $resN->fetch_assoc()) {
        $nasjoner[] = $row;
    }
    $resN->free();
}

// Hent aktuell FartTid-rad (og avled obj_id hvis nødvendig)
if ($tidId > 0) {
    $stmt = $conn->prepare("SELECT * FROM tblfarttid WHERE FartTid_ID = ? LIMIT 1");
    $stmt->bind_param('i', $tidId);
    $stmt->execute();
    $res  = $stmt->get_result();
    $main = $res ? $res->fetch_assoc() : null;
    $stmt->close();
    if (!$main) { echo "Fant ingen oppføringer."; exit; }
    if ($objId <= 0) { $objId = (int)$main['FartObj_ID']; }
} else {
    // Finn siste FartTid for gitt objekt
    $stmt = $conn->prepare("
        SELECT *
        FROM tblfarttid t
        WHERE t.FartObj_ID = ?
        ORDER BY COALESCE(t.YearTid,0) DESC, COALESCE(t.MndTid,0) DESC, t.FartTid_ID DESC
        LIMIT 1
    ");
    $stmt->bind_param('i', $objId);
    $stmt->execute();
    $res = $stmt->get_result();
    $main = $res ? $res->fetch_assoc() : null;
    $stmt->close();
    if (!$main) { echo "Fant ingen oppføringer."; exit; }
    $tidId = (int)$main['FartTid_ID'];
}

// Finn gjeldende fartspes (seneste for objektet)
$spec = null;
$stmt = $conn->prepare("SELECT * FROM tblfartspes WHERE FartObj_ID = ? ORDER BY FartSpes_ID DESC LIMIT 1");
if ($stmt) {
    $stmt->bind_param('i', $objId);
    $stmt->execute();
    $resSpec = $stmt->get_result();
    if ($resSpec) {
        $spec = $resSpec->fetch_assoc();
        $resSpec->free();
    }
    $stmt->close();
}

// LAGRE
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Les og normaliser POST-verdier
    $fartnavn    = trim((string)($_POST['FartNavn']    ?? $main['FartNavn']));
    $rederi      = trim((string)($_POST['Rederi']      ?? $main['Rederi']));
    $reghavn     = trim((string)($_POST['RegHavn']     ?? $main['RegHavn']));
    $nasjon_id   = isset($_POST['Nasjon_ID']) && $_POST['Nasjon_ID'] !== '' ? (int)$_POST['Nasjon_ID'] : (int)$main['Nasjon_ID'];
    $kallesignal = trim((string)($_POST['Kallesignal'] ?? $main['Kallesignal']));
    $mmsi        = trim((string)($_POST['MMSI']        ?? $main['MMSI']));
    $fiskerinr   = trim((string)($_POST['Fiskerinr']   ?? $main['Fiskerinr']));
    $yeartid     = isset($_POST['YearTid']) && $_POST['YearTid'] !== '' ? (int)$_POST['YearTid'] : null;
    $mndtid      = isset($_POST['MndTid'])  && $_POST['MndTid']  !== '' ? (int)$_POST['MndTid']  : null;

    // Oppdater FartTid (inkl. navn som nå ligger her)
    if ($stmt = $conn->prepare("
         UPDATE tblfarttid
            SET FartNavn = ?,
                Rederi = ?,
                RegHavn = ?,
                Nasjon_ID = ?,
                Kallesignal = ?,
                MMSI = ?,
                Fiskerinr = ?,
                YearTid = ?,
                MndTid = ?
          WHERE FartTid_ID = ?")) {
        $stmt->bind_param(
            'sssissiiii',
            $fartnavn,
            $rederi,
            $reghavn,
            $nasjon_id,
            $kallesignal,
            $mmsi,
            $fiskerinr,
            $yeartid,
            $mndtid,
            $tidId
        );
        $stmt->execute();
        $stmt->close();
    }

    // Oppdater tekniske spesifikasjoner (dersom finnes)
    if ($spec) {
        $specFields = [
            'Lengde'      => 'd',
            'Bredde'      => 'd',
            'Dypg'        => 'd',
            'Tonnasje'    => 'd',
            'Drektigh'    => 'd',
            'MaxFart'     => 'd',
            'Byggenr'     => 's',
            'BnrSkrog'    => 's',
            'Kapasitet'   => 's',
            'MotorDetalj' => 's',
            'MotorEff'    => 'd'
        ];
        $updates = [];
        $types   = '';
        $values  = [];
        foreach ($specFields as $field => $type) {
            if (array_key_exists($field, $_POST)) {
                $raw = trim((string)$_POST[$field]);
                if ($type === 'd') {
                    $val = ($raw === '') ? null : (float)$raw;
                } else {
                    $val = $raw;
                }
                $updates[] = "$field = ?";
                $types    .= $type;
                $values[]  = $val;
            }
        }
        if ($updates) {
            $sqlUpd = "UPDATE tblfartspes SET " . implode(', ', $updates) . " WHERE FartSpes_ID = ?";
            $types .= 'i';
            $values[] = (int)$spec['FartSpes_ID'];
            if ($stmt = $conn->prepare($sqlUpd)) {
                $stmt->bind_param($types, ...$values);
                $stmt->execute();
                $stmt->close();
            }
        }
    }

    // Tilbake til admin-oversikten
    $base = defined('BASE_URL') ? rtrim(BASE_URL, '/') : '';
    header('Location: ' . $base . '/admin/fartoy_admin.php?updated=1');
    exit;
}

include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/menu.php';
?>
<style>
.form-grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: .75rem 1rem;
}
.form-grid label { font-weight: 600; }
.form-grid input, .form-grid select {
  max-width: 100%;
  width: 100%;
  padding: .45rem .6rem;
}
@media (max-width: 720px) { .form-grid { grid-template-columns: 1fr; } }
.field-span-2 { grid-column: span 2; }
</style>

<div class="container mt-3">
  <h1>Rediger fartøy</h1>

  <form method="post" class="card" style="padding:1rem; max-width:900px; margin:0 auto;">
    <h2>Generelle opplysninger</h2>
    <div class="form-grid">
      <label for="FartNavn" class="field-span-2">Navn</label>
      <input type="text" name="FartNavn" id="FartNavn" value="<?= h($main['FartNavn']) ?>" class="field-span-2" required>

      <label for="Rederi">Rederi / eier</label>
      <input type="text" name="Rederi" id="Rederi" value="<?= h(val($main,'Rederi','')) ?>">

      <label for="RegHavn">Registreringshavn</label>
      <input type="text" name="RegHavn" id="RegHavn" value="<?= h(val($main,'RegHavn','')) ?>">

      <label for="Nasjon_ID">Nasjon</label>
      <select name="Nasjon_ID" id="Nasjon_ID">
        <option value="0">– Velg –</option>
        <?php foreach ($nasjoner as $r): ?>
          <option value="<?= (int)$r['Nasjon_ID'] ?>"<?= ((int)$main['Nasjon_ID'] === (int)$r['Nasjon_ID']) ? ' selected' : '' ?>>
            <?= h($r['Nasjon']) ?>
          </option>
        <?php endforeach; ?>
      </select>

      <label for="Kallesignal">Kallesignal</label>
      <input type="text" name="Kallesignal" id="Kallesignal" value="<?= h(val($main,'Kallesignal','')) ?>">

      <label for="MMSI">MMSI</label>
      <input type="text" name="MMSI" id="MMSI" value="<?= h(val($main,'MMSI','')) ?>">

      <label for="Fiskerinr">Fiskerinr</label>
      <input type="text" name="Fiskerinr" id="Fiskerinr" value="<?= h(val($main,'Fiskerinr','')) ?>">

      <label for="YearTid">År (navnstart)</label>
      <input type="number" name="YearTid" id="YearTid" value="<?= h(val($main,'YearTid','')) ?>" min="0">

      <label for="MndTid">Mnd (1–12)</label>
      <input type="number" name="MndTid" id="MndTid" value="<?= h(val($main,'MndTid','')) ?>" min="0" max="12">
    </div>

    <?php if ($spec): ?>
      <h2 style="margin-top:1.25rem;">Tekniske data</h2>
      <div class="form-grid">
        <label for="Lengde">Lengde</label>
        <input type="number" step="0.01" name="Lengde" id="Lengde" value="<?= h(val($spec,'Lengde','')) ?>">

        <label for="Bredde">Bredde</label>
        <input type="number" step="0.01" name="Bredde" id="Bredde" value="<?= h(val($spec,'Bredde','')) ?>">

        <label for="Dypg">Dypgående</label>
        <input type="number" step="0.01" name="Dypg" id="Dypg" value="<?= h(val($spec,'Dypg','')) ?>">

        <label for="Tonnasje">Tonnasje</label>
        <input type="number" step="0.01" name="Tonnasje" id="Tonnasje" value="<?= h(val($spec,'Tonnasje','')) ?>">

        <label for="Drektigh">Drektighet</label>
        <input type="number" step="0.01" name="Drektigh" id="Drektigh" value="<?= h(val($spec,'Drektigh','')) ?>">

        <label for="MaxFart">Maksimal fart</label>
        <input type="number" step="0.1" name="MaxFart" id="MaxFart" value="<?= h(val($spec,'MaxFart','')) ?>">

        <label for="Byggenr">Byggenummer</label>
        <input type="text" name="Byggenr" id="Byggenr" value="<?= h(val($spec,'Byggenr','')) ?>">

        <label for="BnrSkrog">Skrog byggenummer</label>
        <input type="text" name="BnrSkrog" id="BnrSkrog" value="<?= h(val($spec,'BnrSkrog','')) ?>">

        <label for="Kapasitet">Kapasitet</label>
        <input type="text" name="Kapasitet" id="Kapasitet" value="<?= h(val($spec,'Kapasitet','')) ?>">

        <label for="MotorDetalj" class="field-span-2">Motor detalj</label>
        <input type="text" name="MotorDetalj" id="MotorDetalj" value="<?= h(val($spec,'MotorDetalj','')) ?>" class="field-span-2">

        <label for="MotorEff">Motoreffekt</label>
        <input type="number" step="0.1" name="MotorEff" id="MotorEff" value="<?= h(val($spec,'MotorEff','')) ?>">
      </div>
    <?php endif; ?>

    <div style="margin-top:1.25rem; display:flex; gap:.75rem; justify-content:center;">
      <button type="submit" class="btn primary">Lagre endringer</button>
      <?php $base = defined('BASE_URL') ? rtrim(BASE_URL, '/') : ''; ?>
      <a href="<?= h($base . '/admin/fartoy_admin.php') ?>" class="btn">Avbryt</a>
    </div>
  </form>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
