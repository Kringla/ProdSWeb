<?php
// /user/verft_sok.php — åpen søkeside (ingen auth-krav)

// 1) Bootstrap + DB
require_once __DIR__ . '/../includes/bootstrap.php';

// Dersom bootstrap ikke laget $conn, forsøk å koble via config
if (!isset($conn) || !($conn instanceof mysqli)) {
    $cfgFile = __DIR__ . '/../config/config.php';
    if (is_file($cfgFile)) {
        require_once $cfgFile;
    }
    if (!isset($conn) || !($conn instanceof mysqli)) {
        die('DB-tilkobling mangler.');
    }
}
$conn->set_charset('utf8mb4');

// 2) Input
$q = trim($_GET['q'] ?? '');
$results = [];
$error = null;

// 3) Søk (min. 2 tegn). Vi slår opp verft i tblverft og bygg i tblfartspes.
// For hvert FartObj_ID velger vi "seneste navn" = MAX(FartNavn_ID).
if ($q !== '' && mb_strlen($q) >= 2) {
    $sql = "
        SELECT 
            fs.FartObj_ID,
            n.FartNavn_ID,
            n.FartNavn,
            fs.YearSpes AS Byggeår,
            v.VerftNavn
        FROM tblverft v
        JOIN tblfartspes fs  ON fs.Verft_ID = v.Verft_ID
        JOIN (
            SELECT FartObj_ID, MAX(FartNavn_ID) AS FartNavn_ID
            FROM tblfartnavn
            GROUP BY FartObj_ID
        ) mx ON mx.FartObj_ID = fs.FartObj_ID
        JOIN tblfartnavn n ON n.FartNavn_ID = mx.FartNavn_ID
        WHERE v.VerftNavn LIKE CONCAT('%', ?, '%')
        ORDER BY fs.YearSpes, n.FartNavn
        LIMIT 500
    ";
    if ($stmt = $conn->prepare($sql)) {
        $stmt->bind_param('s', $q);
        $stmt->execute();
        $res = $stmt->get_result();
        while ($row = $res->fetch_assoc()) {
            $results[] = $row;
        }
        $stmt->close();
    } else {
        $error = 'Kunne ikke forberede SQL for verft-søk.';
    }
}
?>
<?php include __DIR__ . '/../includes/header.php'; ?>
<?php include __DIR__ . '/../includes/menu.php'; ?>

<section class="container">
  <h1>Søk verft</h1>

  <form method="get" class="search-form" style="margin-bottom:1rem;">
    <label for="q">Verftnavn (min. 2 tegn)</label>
    <input type="text" id="q" name="q" value="<?= htmlspecialchars($q) ?>" placeholder="f.eks. Aker, Ulstein ...">
    <button type="submit">Søk</button>
  </form>

  <?php if ($error): ?>
    <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
  <?php endif; ?>

  <?php if ($q !== '' && mb_strlen($q) < 2): ?>
    <p>Angi minst 2 tegn.</p>
  <?php endif; ?>

  <?php if ($q !== '' && mb_strlen($q) >= 2): ?>
    <h2>Treff (<?= count($results) ?>)</h2>
    <?php if (!count($results)): ?>
      <p>Ingen treff.</p>
    <?php else: ?>
      <table class="table">
        <thead>
          <tr>
            <th>Byggeår</th>
            <th>Fartøy</th>
            <th>Verft</th>
            <th>Detaljer</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($results as $r): ?>
            <tr>
              <td><?= isset($r['Byggeår']) ? (int)$r['Byggeår'] : '' ?></td>
              <td><?= htmlspecialchars($r['FartNavn'] ?? '') ?></td>
              <td><?= htmlspecialchars($r['VerftNavn'] ?? '') ?></td>
              <td>
                <a class="btn" href="fartoydetaljer.php?obj_id=<?= (int)($r['FartObj_ID'] ?? 0) ?>&navn_id=<?= (int)($r['FartNavn_ID'] ?? 0) ?>">
                  Åpne
                </a>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    <?php endif; ?>
  <?php endif; ?>
</section>

<?php include __DIR__ . '/../includes/footer.php'; ?>
