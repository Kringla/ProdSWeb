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
    http_response_code(403);
    $base = defined('BASE_URL') ? rtrim(BASE_URL, '/') : '';
    header('Location: ' . $base . '/');
    exit;
}

if (!function_exists('h')) {
    function h($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
}
function val($arr, $key, $def = '') { return isset($arr[$key]) ? $arr[$key] : $def; }

// Hent nasjoner for dropdown
$nasjoner = [];
$sqlN = "SELECT Nasjon_ID, Nasjon FROM tblznasjon WHERE Nasjon IS NOT NULL AND Nasjon <> '' ORDER BY Nasjon";
if ($resN = $conn->query($sqlN)) {
    while ($row = $resN->fetch_assoc()) {
        $nasjoner[] = $row;
    }
    $resN->free();
}

// Håndter POST for opprettelse
$errorMsg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $mode = $_POST['mode'] ?? '';
    $mode = $mode === 'alias' ? 'alias' : 'object';
    // Felles felter
    $fartNavn   = trim((string)($_POST['FartNavn'] ?? ''));
    $rederi     = trim((string)($_POST['Rederi'] ?? ''));
    $regHavn    = trim((string)($_POST['RegHavn'] ?? ''));
    $nasjon_id  = (int)($_POST['Nasjon_ID'] ?? 0);
    $kallesignal= trim((string)($_POST['Kallesignal'] ?? ''));
    $mmsi       = trim((string)($_POST['MMSI'] ?? ''));
    $fiskerinr  = trim((string)($_POST['Fiskerinr'] ?? ''));
    $yearTid    = isset($_POST['YearTid']) && $_POST['YearTid'] !== '' ? (int)$_POST['YearTid'] : null;
    $mndTid     = isset($_POST['MndTid'])  && $_POST['MndTid']  !== '' ? (int)$_POST['MndTid']  : null;

    if ($fartNavn === '') {
        $errorMsg = 'Fartøynavn må fylles ut.';
    } else {
        $conn->begin_transaction();
        try {
            if ($mode === 'object') {
                // Opprett nytt objekt
                $bygget = isset($_POST['Bygget']) && $_POST['Bygget'] !== '' ? (int)$_POST['Bygget'] : null;
                $stmt = $conn->prepare("INSERT INTO tblfartobj (Bygget) VALUES (?)");
                $stmt->bind_param('i', $bygget);
                $stmt->execute();
                $stmt->close();
                $newObjId = (int)$conn->insert_id;

                // Opprett nytt fartnavn
                $stmt = $conn->prepare("INSERT INTO tblfartnavn (FartObj_ID, FartNavn) VALUES (?, ?)");
                $stmt->bind_param('is', $newObjId, $fartNavn);
                $stmt->execute();
                $stmt->close();
                $newNavnId = (int)$conn->insert_id;

                // Opprett farttid
                $stmt = $conn->prepare(
                    "INSERT INTO tblfarttid (FartObj_ID, FartNavn_ID, Rederi, RegHavn, Nasjon_ID, Kallesignal, MMSI, Fiskerinr, YearTid, MndTid, Objekt)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1)"
                );
                $stmt->bind_param(
                    'iisssssiii',
                    $newObjId,
                    $newNavnId,
                    $rederi,
                    $regHavn,
                    $nasjon_id,
                    $kallesignal,
                    $mmsi,
                    $fiskerinr,
                    $yearTid,
                    $mndTid
                );
                $stmt->execute();
                $stmt->close();

                // Opprett fartspes (valgfritt – fyll inn hvis felt er angitt)
                $specFields = [
                    'Lengde'    => 'd',
                    'Bredde'    => 'd',
                    'Dypg'      => 'd',
                    'Tonnasje'  => 'd',
                    'Drektigh'  => 'd',
                    'MaxFart'   => 'd',
                    'Byggenr'   => 's',
                    'BnrSkrog'  => 's',
                    'Kapasitet' => 's',
                    'MotorDetalj' => 's',
                    'MotorEff'    => 'd'
                ];
                $hasSpec = false;
                $values  = [$newObjId];
                $types   = 'i';
                $columns = [];
                foreach ($specFields as $field => $type) {
                    $valPost = $_POST[$field] ?? null;
                    if ($valPost !== null && $valPost !== '') {
                        $hasSpec = true;
                        $columns[] = $field;
                        if ($type === 'd') {
                            $values[] = (float)$valPost;
                            $types .= 'd';
                        } else {
                            $values[] = trim((string)$valPost);
                            $types .= 's';
                        }
                    }
                }
                if ($hasSpec) {
                    $colList = implode(', ', $columns);
                    $placeholders = implode(', ', array_fill(0, count($columns), '?'));
                    $sqlIns = "INSERT INTO tblfartspes (FartObj_ID, $colList) VALUES (?,$placeholders)";
                    $stmt = $conn->prepare($sqlIns);
                    $stmt->bind_param($types, ...$values);
                    $stmt->execute();
                    $stmt->close();
                }
            } else {
                // alias
                $targetObjId = (int)($_POST['TargetObj_ID'] ?? 0);
                if ($targetObjId <= 0) {
                    throw new Exception('Ugyldig objekt-ID.');
                }
                // Opprett nytt fartnavn
                $stmt = $conn->prepare("INSERT INTO tblfartnavn (FartObj_ID, FartNavn) VALUES (?, ?)");
                $stmt->bind_param('is', $targetObjId, $fartNavn);
                $stmt->execute();
                $stmt->close();
                $newNavnId = (int)$conn->insert_id;
                // Opprett farttid
                $stmt = $conn->prepare(
                    "INSERT INTO tblfarttid (FartObj_ID, FartNavn_ID, Rederi, RegHavn, Nasjon_ID, Kallesignal, MMSI, Fiskerinr, YearTid, MndTid, Objekt)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 0)"
                );
                $stmt->bind_param(
                    'iisssssiii',
                    $targetObjId,
                    $newNavnId,
                    $rederi,
                    $regHavn,
                    $nasjon_id,
                    $kallesignal,
                    $mmsi,
                    $fiskerinr,
                    $yearTid,
                    $mndTid
                );
                $stmt->execute();
                $stmt->close();
            }
            $conn->commit();
            // Ferdig – send tilbake til oversikten
            $base = defined('BASE_URL') ? rtrim(BASE_URL, '/') : '';
            header('Location: ' . $base . '/admin/fartoy_admin.php?created=1');
            exit;
        } catch (Exception $e) {
            $conn->rollback();
            $errorMsg = 'Kunne ikke lagre endringene: ' . $e->getMessage();
        }
    }
}

include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/menu.php';
?>
<div class="container mt-3">
  <h1>Legg til fartøy</h1>
  <?php if ($errorMsg): ?>
    <div class="alert alert-error"><?= h($errorMsg) ?></div>
  <?php endif; ?>
  <form method="post" class="card" style="padding:1rem; max-width:800px; margin:0 auto;">
    <label><strong>Type oppføring:</strong></label><br>
    <?php
      $modeSel = $_POST['mode'] ?? 'object';
    ?>
    <label><input type="radio" name="mode" value="object" <?= $modeSel === 'object' ? 'checked' : '' ?>> Nytt objekt (nybygg)</label><br>
    <label><input type="radio" name="mode" value="alias"  <?= $modeSel === 'alias'  ? 'checked' : '' ?>> Nytt navn/eier på eksisterende objekt</label>

    <hr style="margin:1rem 0;">

    <div id="common-fields">
      <h2>Felles felter</h2>
      <label for="FartNavn">Navn</label>
      <input type="text" name="FartNavn" id="FartNavn" value="<?= h($_POST['FartNavn'] ?? '') ?>" class="input" required>

      <label for="Rederi">Rederi / eier</label>
      <input type="text" name="Rederi" id="Rederi" value="<?= h($_POST['Rederi'] ?? '') ?>" class="input">

      <label for="RegHavn">Registreringshavn</label>
      <input type="text" name="RegHavn" id="RegHavn" value="<?= h($_POST['RegHavn'] ?? '') ?>" class="input">

      <label for="Nasjon_ID">Nasjon</label>
      <select name="Nasjon_ID" id="Nasjon_ID" class="input">
        <option value="0">– Velg –</option>
        <?php foreach ($nasjoner as $r): ?>
          <option value="<?= (int)$r['Nasjon_ID'] ?>"<?= ((int)($_POST['Nasjon_ID'] ?? 0) === (int)$r['Nasjon_ID']) ? ' selected' : '' ?>><?= h($r['Nasjon']) ?></option>
        <?php endforeach; ?>
      </select>

      <label for="Kallesignal">Kallesignal</label>
      <input type="text" name="Kallesignal" id="Kallesignal" value="<?= h($_POST['Kallesignal'] ?? '') ?>" class="input">

      <label for="MMSI">MMSI</label>
      <input type="text" name="MMSI" id="MMSI" value="<?= h($_POST['MMSI'] ?? '') ?>" class="input">

      <label for="Fiskerinr">Fiskerinr</label>
      <input type="text" name="Fiskerinr" id="Fiskerinr" value="<?= h($_POST['Fiskerinr'] ?? '') ?>" class="input">

      <label for="YearTid">År (navnstart)</label>
      <input type="number" name="YearTid" id="YearTid" value="<?= h($_POST['YearTid'] ?? '') ?>" class="input" min="0">

      <label for="MndTid">Måned (navnstart, 1–12)</label>
      <input type="number" name="MndTid" id="MndTid" value="<?= h($_POST['MndTid'] ?? '') ?>" class="input" min="0" max="12">
    </div>

    <div id="object-fields" style="margin-top:1rem;<?= $modeSel === 'object' ? '' : ' display:none;' ?>">
      <h2>Nytt objekt</h2>
      <label for="Bygget">Byggeår</label>
      <input type="number" name="Bygget" id="Bygget" value="<?= h($_POST['Bygget'] ?? '') ?>" class="input" min="0">

      <h3>Tekniske data (valgfritt)</h3>
      <label for="Lengde">Lengde</label>
      <input type="number" step="0.01" name="Lengde" id="Lengde" value="<?= h($_POST['Lengde'] ?? '') ?>" class="input">

      <label for="Bredde">Bredde</label>
      <input type="number" step="0.01" name="Bredde" id="Bredde" value="<?= h($_POST['Bredde'] ?? '') ?>" class="input">

      <label for="Dypg">Dypgående</label>
      <input type="number" step="0.01" name="Dypg" id="Dypg" value="<?= h($_POST['Dypg'] ?? '') ?>" class="input">

      <label for="Tonnasje">Tonnasje</label>
      <input type="number" step="0.01" name="Tonnasje" id="Tonnasje" value="<?= h($_POST['Tonnasje'] ?? '') ?>" class="input">

      <label for="Drektigh">Drektighet</label>
      <input type="number" step="0.01" name="Drektigh" id="Drektigh" value="<?= h($_POST['Drektigh'] ?? '') ?>" class="input">

      <label for="MaxFart">Maksimal fart</label>
      <input type="number" step="0.1" name="MaxFart" id="MaxFart" value="<?= h($_POST['MaxFart'] ?? '') ?>" class="input">

      <label for="Byggenr">Byggenummer</label>
      <input type="text" name="Byggenr" id="Byggenr" value="<?= h($_POST['Byggenr'] ?? '') ?>" class="input">

      <label for="BnrSkrog">Skrog byggenummer</label>
      <input type="text" name="BnrSkrog" id="BnrSkrog" value="<?= h($_POST['BnrSkrog'] ?? '') ?>" class="input">

      <label for="Kapasitet">Kapasitet</label>
      <input type="text" name="Kapasitet" id="Kapasitet" value="<?= h($_POST['Kapasitet'] ?? '') ?>" class="input">

      <label for="MotorDetalj">Motor detalj</label>
      <input type="text" name="MotorDetalj" id="MotorDetalj" value="<?= h($_POST['MotorDetalj'] ?? '') ?>" class="input">

      <label for="MotorEff">Motoreffekt</label>
      <input type="number" step="0.1" name="MotorEff" id="MotorEff" value="<?= h($_POST['MotorEff'] ?? '') ?>" class="input">
    </div>

    <div id="alias-fields" style="margin-top:1rem;<?= $modeSel === 'alias' ? '' : ' display:none;' ?>">
      <h2>Nytt navn/eier</h2>
      <label for="TargetObj_ID">Objekt-ID det skal knyttes til</label>
      <input type="number" name="TargetObj_ID" id="TargetObj_ID" value="<?= h($_POST['TargetObj_ID'] ?? '') ?>" class="input" min="1" required>
      <p class="muted" style="font-size:0.85rem;">Du finner Objekt-ID i adminoversikten.</p>
    </div>

    <div style="margin-top:1.5rem; display:flex; gap:10px; justify-content:center;">
      <button type="submit" class="btn primary">Opprett</button>
      <a href="fartoy_admin.php" class="btn">Avbryt</a>
    </div>
  </form>
</div>

<script>
// Vis/skjul felt basert på valgt modus
document.querySelectorAll('input[name="mode"]').forEach(function(radio){
  radio.addEventListener('change', function(){
    var objectFields = document.getElementById('object-fields');
    var aliasFields  = document.getElementById('alias-fields');
    if (this.value === 'object') {
      objectFields.style.display = '';
      aliasFields.style.display  = 'none';
    } else {
      objectFields.style.display = 'none';
      aliasFields.style.display  = '';
    }
  });
});
</script>
<?php include __DIR__ . '/../includes/footer.php'; ?>