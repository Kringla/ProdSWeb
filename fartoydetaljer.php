<?php
// /user/fartoydetaljer.php — detaljside uten auth-krav

// 1) Bootstrap + DB
require_once __DIR__ . '/../includes/bootstrap.php';

// Aksepter både $conn og $mysqli fra config/boot
if (!isset($conn) && isset($mysqli) && ($mysqli instanceof mysqli)) {
    $conn = $mysqli;
}
if (!isset($conn) || !($conn instanceof mysqli)) {
    // Fallback dersom bootstrap ikke opprettet tilkobling
    $cfg = __DIR__ . '/../config/config.php';
    if (is_file($cfg)) {
        require_once $cfg;
        if (!isset($conn) && isset($mysqli) && ($mysqli instanceof mysqli)) {
            $conn = $mysqli;
        }
    }
}
if (!isset($conn) || !($conn instanceof mysqli)) {
    http_response_code(500);
    die('DB-tilkobling mangler.');
}
$conn->set_charset('utf8mb4');

// 2) Parametere
$obj_id  = isset($_GET['obj_id'])  ? (int)$_GET['obj_id']  : 0;
$navn_id = isset($_GET['navn_id']) ? (int)$_GET['navn_id'] : 0;

$err = null;
if ($obj_id <= 0 || $navn_id <= 0) {
    http_response_code(400);
    $err = 'Mangler eller ugyldige parametre: obj_id og navn_id må være > 0.';
}

// 3) Spørringer
$main = null;
$history = [];

if (!$err) {
    // Hovedvisning: “seneste” rad for gitt (obj,navn)
    $sql = "
        SELECT 
            ft.*, 
            fn.FartNavn,
            fs.FartSpes_ID,
            v.VerftNavn
        FROM tblfarttid ft
        JOIN tblfartnavn fn ON fn.FartNavn_ID = ft.FartNavn_ID
        LEFT JOIN tblfartspes fs ON fs.FartSpes_ID = ft.FartSpes_ID
        LEFT JOIN tblverft v ON v.Verft_ID = fs.Verft_ID
        WHERE ft.FartObj_ID = ? AND ft.FartNavn_ID = ?
        ORDER BY ft.YearTid DESC
        LIMIT 1
    ";
    if ($stmt = $conn->prepare($sql)) {
        $stmt->bind_param('ii', $obj_id, $navn_id);
        $stmt->execute();
        $res = $stmt->get_result();
        $main = $res->fetch_assoc();
        $stmt->close();
    } else {
        $err = 'Kunne ikke forberede hovedspørring.';
    }

    // Historikk for objektet (lettvektsliste)
    if (!$err) {
        $sqlHist = "
            SELECT ft.YearTid, fn.FartNavn, ft.Rederi
            FROM tblfarttid ft
            JOIN tblfartnavn fn ON fn.FartNavn_ID = ft.FartNavn_ID
            WHERE ft.FartObj_ID = ?
            ORDER BY ft.YearTid
        ";
        if ($stmt = $conn->prepare($sqlHist)) {
            $stmt->bind_param('i', $obj_id);
            $stmt->execute();
            $res = $stmt->get_result();
            while ($row = $res->fetch_assoc()) {
                $history[] = $row;
            }
            $stmt->close();
        }
    }
}

include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/menu.php';
?>

<section class="container">
  <h1>Fartøydetaljer</h1>

  <?php if ($err): ?>
    <div class="alert alert-error"><?= htmlspecialchars($err) ?></div>
  <?php elseif (!$main): ?>
    <div class="alert">Ingen detaljer funnet for valgt kombinasjon.</div>
  <?php else: ?>
    <div class="card" style="margin-bottom:1rem;">
      <div class="card-content">
        <h2 style="margin:0 0 .5rem 0;"><?= htmlspecialchars($main['FartNavn'] ?? '') ?></h2>
        <div class="grid" style="display:grid; grid-template-columns:1fr 1fr; gap:.75rem;">
          <div><strong>Objekt-ID:</strong> <?= (int)$obj_id ?></div>
          <div><strong>Navn-ID:</strong> <?= (int)$navn_id ?></div>
          <div><strong>Rederi (seneste rad):</strong> <?= htmlspecialchars($main['Rederi'] ?? '') ?></div>
          <div><strong>Verft:</strong> <?= htmlspecialchars($main['VerftNavn'] ?? '') ?></div>
          <div><strong>År (seneste rad):</strong> <?= isset($main['YearTid']) ? (int)$main['YearTid'] : '' ?></div>
        </div>

        <?php if (!empty($main['FartSpes_ID'])): ?>
          <div style="margin-top:1rem;">
            <a class="btn" href="fartoyspes.php?spes_id=<?= (int)$main['FartSpes_ID'] ?>">Tekniske data</a>
          </div>
        <?php endif; ?>
      </div>
    </div>

    <h3>Historikk</h3>
    <?php if (!count($history)): ?>
      <p>Ingen historikk registrert.</p>
    <?php else: ?>
      <table class="table">
        <thead>
          <tr>
            <th>År</th>
            <th>Navn</th>
            <th>Rederi</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($history as $h): ?>
            <tr>
              <td><?= isset($h['YearTid']) ? (int)$h['YearTid'] : '' ?></td>
              <td><?= htmlspecialchars($h['FartNavn'] ?? '') ?></td>
              <td><?= htmlspecialchars($h['Rederi'] ?? '') ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    <?php endif; ?>
  <?php endif; ?>
</section>

<?php include __DIR__ . '/../includes/footer.php'; ?>
