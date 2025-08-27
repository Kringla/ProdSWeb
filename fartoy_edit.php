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
    http_response_code(403);
    $base = defined('BASE_URL') ? rtrim(BASE_URL, '/') : '';
    header('Location: ' . $base . '/');
    exit;
}

if (!function_exists('h')) {
    function h($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
}
function val($arr, $key, $def = '') { return isset($arr[$key]) ? $arr[$key] : $def; }

// Parametre
$objId  = isset($_GET['obj_id'])  ? (int)$_GET['obj_id']  : 0;
$navnId = isset($_GET['navn_id']) ? (int)$_GET['navn_id'] : 0;
if ($objId <= 0 || $navnId <= 0) {
    http_response_code(400);
    echo "Ugyldige parametre.";
    exit;
}

// Hent nasjoner for dropdown
$nasjoner = [];
$sqlN = "SELECT Nasjon_ID, Nasjon FROM tblznasjon WHERE Nasjon IS NOT NULL AND Nasjon <> '' ORDER BY Nasjon";
if ($resN = $conn->query($sqlN)) {
    while ($row = $resN->fetch_assoc()) {
        $nasjoner[] = $row;
    }
    $resN->free();
}

// Hent hovedrad fra tblfarttid for dette objektet/navnet (seneste)
$stmt = $conn->prepare(
    "SELECT t.*, fn.FartNavn
     FROM tblfarttid t
     LEFT JOIN tblfartnavn fn ON fn.FartNavn_ID = t.FartNavn_ID
     WHERE t.FartObj_ID = ? AND t.FartNavn_ID = ?
     ORDER BY COALESCE(t.YearTid,0) DESC, COALESCE(t.MndTid,0) DESC, t.FartTid_ID DESC
     LIMIT 1"
);
$stmt->bind_param('ii', $objId, $navnId);
$stmt->execute();
$res = $stmt->get_result();
$main = $res ? $res->fetch_assoc() : null;
$stmt->close();

if (!$main) {
    echo "Fant ingen oppføringer.";
    exit;
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

// Ved POST: oppdater data
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Hent felter fra POST og trim
    $fartnavn   = trim((string)($_POST['FartNavn']   ?? $main['FartNavn']));
    $rederi     = trim((string)($_POST['Rederi']     ?? $main['Rederi']));
    $reghavn    = trim((string)($_POST['RegHavn']    ?? $main['RegHavn']));
    $nasjon_id  = (int)($_POST['Nasjon_ID'] ?? $main['Nasjon_ID']);
    $kallesignal= trim((string)($_POST['Kallesignal']?? $main['Kallesignal']));
    $mmsi       = trim((string)($_POST['MMSI']       ?? $main['MMSI']));
    $fiskerinr  = trim((string)($_POST['Fiskerinr']  ?? $main['Fiskerinr']));
    $yeartid    = isset($_POST['YearTid']) && $_POST['YearTid'] !== '' ? (int)$_POST['YearTid'] : null;
    $mndtid     = isset($_POST['MndTid'])  && $_POST['MndTid']  !== '' ? (int)$_POST['MndTid']  : null;

    // Oppdater navn
    $stmt = $conn->prepare("UPDATE tblfartnavn SET FartNavn = ? WHERE FartNavn_ID = ?");
    if ($stmt) {
        $stmt->bind_param('si', $fartnavn, $navnId);
        $stmt->execute();
        $stmt->close();
    }

    // Oppdater farttid
    $stmt = $conn->prepare(
        "UPDATE tblfarttid
         SET Rederi = ?, RegHavn = ?, Kallesignal = ?, MMSI = ?, Fiskerinr = ?, Nasjon_ID = ?, YearTid = ?, MndTid = ?
         WHERE FartTid_ID = ?"
    );
    if ($stmt) {
        // Merk: bruker i stedet for NULL for year/mnd; bind param tar int eller null med 'i'
        $stmt->bind_param(
            'ssssssiii',
            $rederi,
            $reghavn,
            $kallesignal,
            $mmsi,
            $fiskerinr,
            $nasjon_id,
            $yeartid,
            $mndtid,
            $main['FartTid_ID']
        );
        $stmt->execute();
        $stmt->close();
    }

    // Oppdater spesifikasjoner hvis de finnes
    if ($spec) {
        // Hent verdier fra POST, bruk eksisterende ved tomt input
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
        $updates = [];
        $types   = '';
        $values  = [];
        foreach ($specFields as $field => $type) {
            $valPost = $_POST[$field] ?? null;
            if ($valPost !== null) {
                // Tom streng => null eller oppretthold eksisterende verdi
                $value = trim((string)$valPost);
                // Behold numeriske tom som null
                if ($type === 'd') {
                    $value = ($value === '') ? null : (float)$value;
                }
                $updates[] = "$field = ?";
                $types .= $type;
                $values[] = $value;
            }
        }
        if ($updates) {
            $sqlUpd = "UPDATE tblfartspes SET " . implode(', ', $updates) . " WHERE FartSpes_ID = ?";
            $types .= 'i';
            $values[] = $spec['FartSpes_ID'];
            $stmt = $conn->prepare($sqlUpd);
            if ($stmt) {
                $stmt->bind_param($types, ...$values);
                $stmt->execute();
                $stmt->close();
            }
        }
    }

    // Etter lagring, send tilbake til adminlisten med suksessbeskjed
    $base = defined('BASE_URL') ? rtrim(BASE_URL, '/') : '';
    header('Location: ' . $base . '/admin/fartoy_admin.php?updated=1');
    exit;
}

include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/menu.php';
?>
<div class="container mt-3">
  <h1>Rediger fartøy</h1>
  <form method="post" class="card" style="padding:1rem; max-width:800px; margin:0 auto;">
    <h2>Generelle opplysninger</h2>
    <label for="FartNavn">Navn</label>
    <input type="text" name="FartNavn" id="FartNavn" value="<?= h($main['FartNavn']) ?>" class="input" required>

    <label for="Rederi">Rederi / eier</label>
    <input type="text" name="Rederi" id="Rederi" value="<?= h(val($main,'Rederi','')) ?>" class="input">

    <label for="RegHavn">Registreringshavn</label>
    <input type="text" name="RegHavn" id="RegHavn" value="<?= h(val($main,'RegHavn','')) ?>" class="input">

    <label for="Nasjon_ID">Nasjon</label>
    <select name="Nasjon_ID" id="Nasjon_ID" class="input">
      <option value="0">– Velg –</option>
      <?php foreach ($nasjoner as $r): ?>
        <option value="<?= (int)$r['Nasjon_ID'] ?>"<?= ((int)$main['Nasjon_ID'] === (int)$r['Nasjon_ID']) ? ' selected' : '' ?>><?= h($r['Nasjon']) ?></option>
      <?php endforeach; ?>
    </select>

    <label for="Kallesignal">Kallesignal</label>
    <input type="text" name="Kallesignal" id="Kallesignal" value="<?= h(val($main,'Kallesignal','')) ?>" class="input">

    <label for="MMSI">MMSI</label>
    <input type="text" name="MMSI" id="MMSI" value="<?= h(val($main,'MMSI','')) ?>" class="input">

    <label for="Fiskerinr">Fiskerinr</label>
    <input type="text" name="Fiskerinr" id="Fiskerinr" value="<?= h(val($main,'Fiskerinr','')) ?>" class="input">

    <label for="YearTid">År (navnstart)</label>
    <input type="number" name="YearTid" id="YearTid" value="<?= h(val($main,'YearTid','')) ?>" class="input" min="0">

    <label for="MndTid">Måned (navnstart, 1–12)</label>
    <input type="number" name="MndTid" id="MndTid" value="<?= h(val($main,'MndTid','')) ?>" class="input" min="0" max="12">

    <?php if ($spec): ?>
      <h2 style="margin-top:1.5rem;">Tekniske data</h2>
      <label for="Lengde">Lengde</label>
      <input type="number" step="0.01" name="Lengde" id="Lengde" value="<?= h(val($spec,'Lengde','')) ?>" class="input">

      <label for="Bredde">Bredde</label>
      <input type="number" step="0.01" name="Bredde" id="Bredde" value="<?= h(val($spec,'Bredde','')) ?>" class="input">

      <label for="Dypg">Dypgående</label>
      <input type="number" step="0.01" name="Dypg" id="Dypg" value="<?= h(val($spec,'Dypg','')) ?>" class="input">

      <label for="Tonnasje">Tonnasje</label>
      <input type="number" step="0.01" name="Tonnasje" id="Tonnasje" value="<?= h(val($spec,'Tonnasje','')) ?>" class="input">

      <label for="Drektigh">Drektighet</label>
      <input type="number" step="0.01" name="Drektigh" id="Drektigh" value="<?= h(val($spec,'Drektigh','')) ?>" class="input">

      <label for="MaxFart">Maksimal fart</label>
      <input type="number" step="0.1" name="MaxFart" id="MaxFart" value="<?= h(val($spec,'MaxFart','')) ?>" class="input">

      <label for="Byggenr">Byggenummer</label>
      <input type="text" name="Byggenr" id="Byggenr" value="<?= h(val($spec,'Byggenr','')) ?>" class="input">

      <label for="BnrSkrog">Skrog byggenummer</label>
      <input type="text" name="BnrSkrog" id="BnrSkrog" value="<?= h(val($spec,'BnrSkrog','')) ?>" class="input">

      <label for="Kapasitet">Kapasitet</label>
      <input type="text" name="Kapasitet" id="Kapasitet" value="<?= h(val($spec,'Kapasitet','')) ?>" class="input">

      <label for="MotorDetalj">Motor detalj</label>
      <input type="text" name="MotorDetalj" id="MotorDetalj" value="<?= h(val($spec,'MotorDetalj','')) ?>" class="input">

      <label for="MotorEff">Motoreffekt</label>
      <input type="number" step="0.1" name="MotorEff" id="MotorEff" value="<?= h(val($spec,'MotorEff','')) ?>" class="input">
    <?php endif; ?>

    <div style="margin-top:1.5rem; display:flex; gap:10px; justify-content:center;">
      <button type="submit" class="btn primary">Lagre endringer</button>
      <a href="fartoy_admin.php" class="btn">Avbryt</a>
    </div>
  </form>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>