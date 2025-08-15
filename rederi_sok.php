<?php
// /user/rederi_sok.php — åpen søkeside (ingen auth-krav)

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

// 3) Søk (min. 2 tegn). Vi søker i tblfarttid.Rederi og henter navn via tblfartnavn
if ($q !== '' && mb_strlen($q) >= 2) {
    $sql = "
        SELECT 
            ft.FartObj_ID,
            ft.FartNavn_ID,
            fn.FartNavn,
            MIN(ft.YearTid) AS Fra,
            MAX(ft.YearTid) AS Til
        FROM tblfarttid ft
        JOIN tblfartnavn fn ON fn.FartNavn_ID = ft.FartNavn_ID
        WHERE ft.Rederi LIKE CONCAT('%', ?, '%')
        GROUP BY ft.FartObj_ID, ft.FartNavn_ID, fn.FartNavn
        ORDER BY fn.FartNavn
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
        $error = 'Kunne ikke forberede SQL for rederi-søk.';
    }
}
?>
<?php include __DIR__ . '/../includes/header.php'; ?>
<?php include __DIR__ . '/../includes/menu.php'; ?>

<section class="container">
  <h1>Søk rederi</h1>

  <form method="get" class="search-form" style="margin-bottom:1rem;">
    <label for="q">Rederinavn (min. 2 tegn)</label>
    <input type="text" id="q" name="q" value="<?= htmlspecialchars($q) ?>" placeholder="f.eks. Knutsen, Wilhelmsen ...">
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
            <th>Fartøy</th>
            <th>Periode (fra–til)</th>
            <th>Detaljer</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($results as $r): ?>
            <tr>
              <td><?= htmlspecialchars($r['FartNavn'] ?? '') ?></td>
              <td>
                <?php
                  $fra = isset($r['Fra']) ? (int)$r['Fra'] : 0;
                  $til = isset($r['Til']) ? (int)$r['Til'] : 0;
                  echo ($fra && $til) ? "$fra–$til" : ($fra ?: ($til ?: ''));
                ?>
              </td>
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
