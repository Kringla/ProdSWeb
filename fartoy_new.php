<?php
// admin/fartoy_new.php
// Skjema for å legge til nye fartøy eller nye navn/eiere på eksisterende objekter. Kun for admin.

require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/auth.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Sjekk adminrolle
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

// Hent nasjoner for dropdown
$nasjoner = [];
$sqlN = "SELECT Nasjon_ID, Nasjon
         FROM tblznasjon
         WHERE Nasjon IS NOT NULL AND Nasjon <> ''
         ORDER BY Nasjon";
if ($resN = $conn->query($sqlN)) {
    while ($row = $resN->fetch_assoc()) { $nasjoner[] = $row; }
    $resN->free();
}

// Håndter POST for opprettelse
$errorMsg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $mode = ($_POST['mode'] ?? '') === 'alias' ? 'alias' : 'object';

    // Felles felter
    $fartNavn    = trim((string)($_POST['FartNavn'] ?? ''));
    $rederi      = trim((string)($_POST['Rederi'] ?? ''));
    $regHavn     = trim((string)($_POST['RegHavn'] ?? ''));
    $nasjon_id   = (int)($_POST['Nasjon_ID'] ?? 0);
    $kallesignal = trim((string)($_POST['Kallesignal'] ?? ''));
    $mmsi        = trim((string)($_POST['MMSI'] ?? ''));
    $fiskerinr   = trim((string)($_POST['Fiskerinr'] ?? ''));
    $yearTid     = isset($_POST['YearTid']) && $_POST['YearTid'] !== '' ? (int)$_POST['YearTid'] : null;
    $mndTid      = isset($_POST['MndTid'])  && $_POST['MndTid']  !== '' ? (int)$_POST['MndTid']  : null;

    if ($fartNavn === '') {
        $errorMsg = 'Fartøynavn må fylles ut.';
    } else {
        $conn->begin_transaction();
        try {
            if ($mode === 'object') {
                // 0) Opprett nytt objekt
                $bygget = isset($_POST['Bygget']) && $_POST['Bygget'] !== '' ? (int)$_POST['Bygget'] : null;
                $stmt = $conn->prepare("INSERT INTO tblfartobj (Bygget) VALUES (?)");
                $stmt->bind_param('i', $bygget);
                $stmt->execute();
                $stmt->close();
                $newObjId = (int)$conn->insert_id;

                // 1) Første navns-/tidsrad (navn ligger i tblfarttid.FartNavn)
                $stmt = $conn->prepare("
                    INSERT INTO tblfarttid
                      (FartObj_ID, FartNavn, Rederi, RegHavn, Nasjon_ID, Kallesignal, MMSI, Fiskerinr, YearTid, MndTid, Objekt)
                    VALUES (?,?,?,?,?,?,?,?,?,?,1)
                ");
                $stmt->bind_param(
                    'isssissiii',
                    $newObjId, $fartNavn, $rederi, $regHavn, $nasjon_id, $kallesignal, $mmsi, $fiskerinr, $yearTid, $mndTid
                );
                $stmt->execute();
                $stmt->close();

                // 2) OPPRETT ALLTID en ny spes-rad for objektet (obligatorisk)
                //    Fyll inn de feltene som er sendt; hvis ingen felt er angitt, lag en minimal rad med kun FartObj_ID.
                $specMap = [
                    'FartType_ID' => 'i',
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
                $columns = [];
                $types   = 'i';       // starter med FartObj_ID
                $values  = [$newObjId];

                foreach ($specMap as $col => $tp) {
                    if (isset($_POST[$col]) && $_POST[$col] !== '') {
                        $columns[] = $col;
                        if ($tp === 'd') { $values[] = (float)$_POST[$col]; $types .= 'd'; }
                        elseif ($tp === 'i') { $values[] = (int)$_POST[$col]; $types .= 'i'; }
                        else { $values[] = trim((string)$_POST[$col]); $types .= 's'; }
                    }
                }

                if ($columns) {
                    $colList = implode(', ', $columns);
                    $placeholders = implode(', ', array_fill(0, count($columns), '?'));
                    $sqlIns = "INSERT INTO tblfartspes (FartObj_ID, $colList) VALUES (?,$placeholders)";
                    $stmt = $conn->prepare($sqlIns);
                    $stmt->bind_param($types, ...$values);
                    $stmt->execute();
                    $stmt->close();
                } else {
                    // Ingen felter sendt – opprett minimal spes-rad
                    $stmt = $conn->prepare("INSERT INTO tblfartspes (FartObj_ID) VALUES (?)");
                    $stmt->bind_param('i', $newObjId);
                    $stmt->execute();
                    $stmt->close();
                }

            } else {
                // alias (ikke-objekt): ny navnsrad på eksisterende objekt
                $targetObjId = (int)($_POST['TargetObj_ID'] ?? 0);
                if ($targetObjId <= 0) {
                    throw new Exception('Ugyldig objekt-ID.');
                }
                $stmt = $conn->prepare("
                    INSERT INTO tblfarttid
                      (FartObj_ID, FartNavn, Rederi, RegHavn, Nasjon_ID, Kallesignal, MMSI, Fiskerinr, YearTid, MndTid, Objekt)
                    VALUES (?,?,?,?,?,?,?,?,?,?,0)
                ");
                $stmt->bind_param(
                    'isssissiii',
                    $targetObjId, $fartNavn, $rederi, $regHavn, $nasjon_id, $kallesignal, $mmsi, $fiskerinr, $yearTid, $mndTid
                );
                $stmt->execute();
                $stmt->close();
                // Merk: Spesifikasjoner følger objektet. Vi oppretter ikke ny spes her automatisk.
            }

            $conn->commit();
            $base = defined('BASE_URL') ? rtrim(BASE_URL, '/') : '';
            header('Location: ' . $base . '/admin/fartoy_admin.php?created=1');
            exit;

        } catch (Exception $ex) {
            $conn->rollback();
            $errorMsg = 'Kunne ikke opprette: ' . $ex->getMessage();
        }
    }
}

include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/menu.php';
?>
<style>
.form-grid { display:grid; grid-template-columns: 1fr 1fr; gap:.75rem 1rem; }
.form-grid label { font-weight:600; }
.form-grid input, .form-grid select { width:100%; padding:.45rem .6rem; }
@media (max-width:720px){ .form-grid{ grid-template-columns:1fr; } }
.field-span-2 { grid-column: span 2; }
</style>

<div class="container mt-3">
  <h1>Nytt fartøy / nytt navn</h1>

  <?php if ($errorMsg): ?>
    <div class="alert alert-danger"><?= h($errorMsg) ?></div>
  <?php endif; ?>

  <form method="post" class="card" style="padding:1rem; max-width:900px; margin:0 auto;">
    <div style="text-align:center; margin-bottom:1rem;">
      <label><input type="radio" name="mode" value="object" <?= (!isset($_POST['mode']) || $_POST['mode']!=='alias') ? 'checked' : '' ?>> Nytt objekt</label>
      &nbsp;&nbsp;
      <label><input type="radio" name="mode" value="alias" <?= (isset($_POST['mode']) && $_POST['mode']==='alias') ? 'checked' : '' ?>> Nytt navn på eksisterende objekt</label>
    </div>

    <div id="object-fields" style="<?= (!isset($_POST['mode']) || $_POST['mode']!=='alias') ? '' : 'display:none;' ?>">
      <h2>Objekt</h2>
      <div class="form-grid">
        <label for="Bygget">Byggeår</label>
        <input type="number" name="Bygget" id="Bygget" value="<?= h($_POST['Bygget'] ?? '') ?>">
      </div>
    </div>

    <div id="alias-fields" style="<?= (isset($_POST['mode']) && $_POST['mode']==='alias') ? '' : 'display:none;' ?>">
      <h2>Målobjekt</h2>
      <div class="form-grid">
        <label for="TargetObj_ID">Objekt-ID</label>
        <input type="number" name="TargetObj_ID" id="TargetObj_ID" value="<?= h($_POST['TargetObj_ID'] ?? '') ?>">
      </div>
    </div>

    <h2>Navn og status</h2>
    <div class="form-grid">
      <label for="FartNavn" class="field-span-2">Navn</label>
      <input type="text" name="FartNavn" id="FartNavn" value="<?= h($_POST['FartNavn'] ?? '') ?>" class="field-span-2" required>

      <label for="Rederi">Rederi / eier</label>
      <input type="text" name="Rederi" id="Rederi" value="<?= h($_POST['Rederi'] ?? '') ?>">

      <label for="RegHavn">Registreringshavn</label>
      <input type="text" name="RegHavn" id="RegHavn" value="<?= h($_POST['RegHavn'] ?? '') ?>">

      <label for="Nasjon_ID">Nasjon</label>
      <select name="Nasjon_ID" id="Nasjon_ID">
        <option value="0">– Velg –</option>
        <?php foreach ($nasjoner as $r): ?>
          <option value="<?= (int)$r['Nasjon_ID'] ?>"<?= (isset($_POST['Nasjon_ID']) && (int)$_POST['Nasjon_ID']===(int)$r['Nasjon_ID']) ? ' selected' : '' ?>>
            <?= h($r['Nasjon']) ?>
          </option>
        <?php endforeach; ?>
      </select>

      <label for="Kallesignal">Kallesignal</label>
      <input type="text" name="Kallesignal" id="Kallesignal" value="<?= h($_POST['Kallesignal'] ?? '') ?>">

      <label for="MMSI">MMSI</label>
      <input type="text" name="MMSI" id="MMSI" value="<?= h($_POST['MMSI'] ?? '') ?>">

      <label for="Fiskerinr">Fiskerinr</label>
      <input type="text" name="Fiskerinr" id="Fiskerinr" value="<?= h($_POST['Fiskerinr'] ?? '') ?>">

      <label for="YearTid">År (navnstart)</label>
      <input type="number" name="YearTid" id="YearTid" value="<?= h($_POST['YearTid'] ?? '') ?>">

      <label for="MndTid">Mnd (1–12)</label>
      <input type="number" name="MndTid" id="MndTid" value="<?= h($_POST['MndTid'] ?? '') ?>">
    </div>

    <h2>Tekniske data (opprettes alltid ved nytt objekt)</h2>
    <div class="form-grid">
      <label for="FartType_ID">Fartøytype (ID)</label>
      <input type="number" name="FartType_ID" id="FartType_ID" value="<?= h($_POST['FartType_ID'] ?? '') ?>">

      <label for="Lengde">Lengde</label>
      <input type="number" step="0.01" name="Lengde" id="Lengde" value="<?= h($_POST['Lengde'] ?? '') ?>">

      <label for="Bredde">Bredde</label>
      <input type="number" step="0.01" name="Bredde" id="Bredde" value="<?= h($_POST['Bredde'] ?? '') ?>">

      <label for="Dypg">Dypgående</label>
      <input type="number" step="0.01" name="Dypg" id="Dypg" value="<?= h($_POST['Dypg'] ?? '') ?>">

      <label for="Tonnasje">Tonnasje</label>
      <input type="number" step="0.01" name="Tonnasje" id="Tonnasje" value="<?= h($_POST['Tonnasje'] ?? '') ?>">

      <label for="Drektigh">Drektighet</label>
      <input type="number" step="0.01" name="Drektigh" id="Drektigh" value="<?= h($_POST['Drektigh'] ?? '') ?>">

      <label for="MaxFart">Maksimal fart</label>
      <input type="number" step="0.1" name="MaxFart" id="MaxFart" value="<?= h($_POST['MaxFart'] ?? '') ?>">

      <label for="Byggenr">Byggenummer</label>
      <input type="text" name="Byggenr" id="Byggenr" value="<?= h($_POST['Byggenr'] ?? '') ?>">

      <label for="BnrSkrog">Skrog byggenummer</label>
      <input type="text" name="BnrSkrog" id="BnrSkrog" value="<?= h($_POST['BnrSkrog'] ?? '') ?>">

      <label for="Kapasitet">Kapasitet</label>
      <input type="text" name="Kapasitet" id="Kapasitet" value="<?= h($_POST['Kapasitet'] ?? '') ?>">

      <label for="MotorDetalj" class="field-span-2">Motor detalj</label>
      <input type="text" name="MotorDetalj" id="MotorDetalj" value="<?= h($_POST['MotorDetalj'] ?? '') ?>" class="field-span-2">

      <label for="MotorEff">Motoreffekt</label>
      <input type="number" step="0.1" name="MotorEff" id="MotorEff" value="<?= h($_POST['MotorEff'] ?? '') ?>">
    </div>

    <div style="margin-top:1.25rem; display:flex; gap:.75rem; justify-content:center;">
      <button type="submit" class="btn primary">Opprett</button>
      <?php $base = defined('BASE_URL') ? rtrim(BASE_URL, '/') : ''; ?>
      <a href="<?= h($base . '/admin/fartoy_admin.php') ?>" class="btn">Avbryt</a>
    </div>
  </form>
</div>

<script>
document.addEventListener('DOMContentLoaded', function(){
  function toggle() {
    var isAlias = document.querySelector('input[name="mode"][value="alias"]').checked;
    document.getElementById('object-fields').style.display = isAlias ? 'none' : '';
    document.getElementById('alias-fields').style.display  = isAlias ? '' : 'none';
  }
  document.querySelectorAll('input[name="mode"]').forEach(function(r){ r.addEventListener('change', toggle); });
  toggle();
});
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
