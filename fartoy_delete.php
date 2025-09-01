<?php
// admin/fartoy_delete.php
// Side for å bekrefte og utføre sletting av fartøy. Kun admin-brukere.

require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/auth.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Tilgangssjekk
if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin') {
    http_response_code(403);
    $base = defined('BASE_URL') ? rtrim(BASE_URL, '/') : '';
    header('Location: ' . $base . '/');
    exit;
}

if (!function_exists('h')) {
    function h($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
}

// Parametre
$objId  = isset($_GET['obj_id'])  ? (int)$_GET['obj_id']  : 0;
$navnId = isset($_GET['navn_id']) ? (int)$_GET['navn_id'] : 0;
if ($objId <= 0 || $navnId <= 0) {
    http_response_code(400);
    echo "Ugyldige parametre.";
    exit;
}

// Hent rad for å avgjøre om dette er objektnavn
$stmt = $conn->prepare(
    "SELECT t.FartTid_ID, t.Objekt, t.FartNavn
     FROM tblfarttid t
     LEFT JOIN tblfartnavn fn ON t.FartNavn_ID = t.FartNavn_ID
     WHERE t.FartObj_ID = ? AND t.FartNavn_ID = ?
     ORDER BY COALESCE(t.YearTid,0) DESC, COALESCE(t.MndTid,0) DESC, t.FartTid_ID DESC
     LIMIT 1"
);
$stmt->bind_param('ii', $objId, $navnId);
$stmt->execute();
$res = $stmt->get_result();
$row = $res ? $res->fetch_assoc() : null;
$stmt->close();

if (!$row) {
    echo "Fant ingen data å slette.";
    exit;
}

$isObject = (int)$row['Objekt'] === 1;
$fartNavn = $row['FartNavn'];
$fartTidId = (int)$row['FartTid_ID'];

// Når POST med bekreftelse
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirm']) && $_POST['confirm'] === 'yes') {
    $conn->begin_transaction();
    try {
        if ($isObject) {
            // Slett alle navn og tidsrader for objektet
            $stmt = $conn->prepare("DELETE FROM tblfarttid WHERE FartObj_ID = ?");
            $stmt->bind_param('i', $objId);
            $stmt->execute();
            $stmt->close();

            $stmt = $conn->prepare("DELETE FROM tblfartnavn WHERE FartObj_ID = ?");
            $stmt->bind_param('i', $objId);
            $stmt->execute();
            $stmt->close();

            // Slett spesifikasjoner
            $stmt = $conn->prepare("DELETE FROM tblfartspes WHERE FartObj_ID = ?");
            $stmt->bind_param('i', $objId);
            $stmt->execute();
            $stmt->close();

            // Slett selve objektet
            $stmt = $conn->prepare("DELETE FROM tblfartobj WHERE FartObj_ID = ?");
            $stmt->bind_param('i', $objId);
            $stmt->execute();
            $stmt->close();
        } else {
            // Slett kun denne navne- og tidsoppføringen
            $stmt = $conn->prepare("DELETE FROM tblfarttid WHERE FartTid_ID = ?");
            $stmt->bind_param('i', $fartTidId);
            $stmt->execute();
            $stmt->close();

            $stmt = $conn->prepare("DELETE FROM tblfartnavn WHERE FartNavn_ID = ?");
            $stmt->bind_param('i', $navnId);
            $stmt->execute();
            $stmt->close();
        }
        $conn->commit();
    } catch (Exception $e) {
        $conn->rollback();
    }
    // Send tilbake til adminlisten
    $base = defined('BASE_URL') ? rtrim(BASE_URL, '/') : '';
    header('Location: ' . $base . '/admin/fartoy_admin.php?deleted=1');
    exit;
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
              $imgCandidate = '/assets/img/placeholder.jpg';
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
  <h1>Slett fartøy</h1>
  <div class="card" style="padding:1rem; max-width:600px; margin:0 auto;">
    <p>
      Du er i ferd med å slette fartøyet <strong><?= h($fartNavn) ?></strong> (Objekt-ID <?= $objId ?>).
      <?php if ($isObject): ?>
        Dette er et <em>objekt</em>, så alle tilhørende navn, historikk og spesifikasjoner vil også bli slettet.
      <?php else: ?>
        Dette er ikke et objekt. Kun denne navne- og tidsoppføringen vil bli slettet.
      <?php endif; ?>
    </p>
    <form method="post" style="margin-top:1rem; display:flex; gap:10px; justify-content:center;">
      <input type="hidden" name="confirm" value="yes">
      <button type="submit" class="btn primary">Bekreft sletting</button>
      <a href="fartoy_admin.php" class="btn">Avbryt</a>
    </form>
  </div>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>