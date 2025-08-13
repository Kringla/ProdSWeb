<?php
/**
 * user/rederi_sok.php
 * - Fritekstsøk mot rederi (tblFartTid.Rederi, fritekst)
 * - Valg av ett rederi (eksakt) snevrer inn listen
 * - Sortering (navn/byggeår, asc/desc), default: navn ASC
 * - Antall per side (20/50/100)
 * - Klikk på fartøy -> user/fartoydetaljer.php?id=<FartNavn_ID>
 * - CSV-eksport via ?export=csv (eksporterer alle treff, ignorerer paging)
 */
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/auth.php'; // ingen require_admin()

$q       = trim($_GET['q'] ?? '');
$sel     = trim($_GET['rederi'] ?? ''); // valgt rederi (eksakt match)

// sortering (default navn ASC)
$sort     = strtolower(trim($_GET['sort'] ?? 'navn'));
$dir      = strtolower(trim($_GET['dir'] ?? 'asc'));
$sortMap  = ['navn' => 'fn.FartNavn', 'bygget' => 'fo.Bygget'];
$col      = $sortMap[$sort] ?? $sortMap['navn'];
$direction= ($dir === 'desc') ? 'DESC' : 'ASC';
$orderBy  = $col . ' ' . $direction . ', fn.FartNavn ASC';

// per page
$ppAllowed = [20,50,100];
$perPage   = (int)($_GET['pp'] ?? 20);
if (!in_array($perPage, $ppAllowed, true)) $perPage = 20;

$page   = max(1, (int)($_GET['p'] ?? 1));
$offset = ($page - 1) * $perPage;

$rederiList = [];
$total = 0;
$rows  = [];

/* 1) Foreslå rederinavn (distinct) ved fritekst */
if ($q !== '') {
    $stmt = $conn->prepare("
        SELECT DISTINCT TRIM(Rederi) AS Rederi
        FROM tblFartTid
        WHERE Rederi IS NOT NULL AND Rederi <> '' AND Rederi LIKE ?
        ORDER BY Rederi
        LIMIT 200
    ");
    $like = '%'.$q.'%';
    $stmt->bind_param('s', $like);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($r = $res->fetch_assoc()) { $rederiList[] = $r['Rederi']; }
    $stmt->close();
}

/* 2) Filter-subquery for FartObj_ID basert på valgt/tekst */
$filterSQL  = '';
$filterType = '';
$filterVal  = null;

if ($sel !== '') {
    $filterSQL  = "SELECT DISTINCT FartObj_ID FROM tblFartTid WHERE Rederi = ?";
    $filterType = 's';
    $filterVal  = $sel;
} elseif ($q !== '') {
    $filterSQL  = "SELECT DISTINCT FartObj_ID FROM tblFartTid WHERE Rederi LIKE ?";
    $filterType = 's';
    $filterVal  = '%'.$q.'%';
}

/* 3) CSV-eksport (tidlig exit, respekterer sortering) */
if (isset($_GET['export']) && $_GET['export'] === 'csv' && $filterSQL !== '') {
    $exportSql = "
        SELECT fo.FartObj_ID,
               fn.FartNavn,
               zt.TypeFork,
               fo.Bygget,
               lft.RegHavn,
               zn.Nasjon,
               lft.Kallesignal,
               lft.Rederi,
               fs.FartSpes_ID,
               fn.FartNavn_ID
        FROM tblFartObj fo
        JOIN ($filterSQL) f ON f.FartObj_ID = fo.FartObj_ID
        LEFT JOIN tblFartSpes fs ON fs.FartObj_ID = fo.FartObj_ID
        LEFT JOIN tblFartNavn fn ON fn.FartObj_ID = fo.FartObj_ID
        LEFT JOIN tblzFartType zt ON zt.FartType_ID = fn.FartType_ID
        LEFT JOIN (
            SELECT ft.*
            FROM tblFartTid ft
            JOIN (
               SELECT FartObj_ID, MAX(FartTid_ID) AS maxid
               FROM tblFartTid GROUP BY FartObj_ID
            ) m ON m.FartObj_ID = ft.FartObj_ID AND m.maxid = ft.FartTid_ID
        ) lft ON lft.FartObj_ID = fo.FartObj_ID
        LEFT JOIN tblzNasjon zn ON zn.Nasjon_ID = lft.Nasjon_ID
        ORDER BY $orderBy
    ";
    $stmt = $conn->prepare($exportSql);
    $stmt->bind_param($filterType, $filterVal);
    $stmt->execute();
    $res = $stmt->get_result();

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="rederi_sok.csv"');

    $out = fopen('php://output', 'w');
    fputcsv($out, ['FartObj_ID','FartNavn','Type','Bygget','RegHavn','Nasjon','Kallesignal','Rederi','FartSpes_ID','FartNavn_ID']);
    while ($r = $res->fetch_assoc()) {
        fputcsv($out, [
            $r['FartObj_ID'], $r['FartNavn'], $r['TypeFork'], $r['Bygget'],
            $r['RegHavn'], $r['Nasjon'], $r['Kallesignal'], $r['Rederi'],
            $r['FartSpes_ID'], $r['FartNavn_ID']
        ]);
    }
    fclose($out);
    exit;
}

/* 4) Tell og hent rader når vi har et filter */
if ($filterSQL !== '') {
    // COUNT
    $countSql = "
        SELECT COUNT(*) AS c FROM (
            SELECT DISTINCT fo.FartObj_ID
            FROM tblFartObj fo
            JOIN ($filterSQL) f ON f.FartObj_ID = fo.FartObj_ID
        ) x";
    $stmt = $conn->prepare($countSql);
    $stmt->bind_param($filterType, $filterVal);
    $stmt->execute();
    $total = (int)($stmt->get_result()->fetch_assoc()['c'] ?? 0);
    $stmt->close();

    // ROWS
    $rowsSql = "
        SELECT fo.FartObj_ID,
               fn.FartNavn,
               zt.TypeFork,
               fo.Bygget,
               lft.RegHavn,
               zn.Nasjon,
               lft.Kallesignal,
               lft.Rederi,
               fs.FartSpes_ID,
               fn.FartNavn_ID
        FROM tblFartObj fo
        JOIN ($filterSQL) f ON f.FartObj_ID = fo.FartObj_ID
        LEFT JOIN tblFartSpes fs ON fs.FartObj_ID = fo.FartObj_ID
        LEFT JOIN tblFartNavn fn ON fn.FartObj_ID = fo.FartObj_ID
        LEFT JOIN tblzFartType zt ON zt.FartType_ID = fn.FartType_ID
        LEFT JOIN (
            SELECT ft.*
            FROM tblFartTid ft
            JOIN (
               SELECT FartObj_ID, MAX(FartTid_ID) AS maxid
               FROM tblFartTid
               GROUP BY FartObj_ID
            ) m ON m.FartObj_ID = ft.FartObj_ID AND m.maxid = ft.FartTid_ID
        ) lft ON lft.FartObj_ID = fo.FartObj_ID
        LEFT JOIN tblzNasjon zn ON zn.Nasjon_ID = lft.Nasjon_ID
        ORDER BY $orderBy
        LIMIT ? OFFSET ?";
    $stmt = $conn->prepare($rowsSql);
    $stmt->bind_param($filterType.'ii', $filterVal, $perPage, $offset);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($r = $res->fetch_assoc()) { $rows[] = $r; }
    $stmt->close();
}

include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/menu.php';
?>
<div class="container mt-4">
  <h1>Fartøy hos rederi</h1>

  <form method="get" class="form">
    <label for="rederi">Rederi (del av navn):</label>
    <input type="text" id="rederi" name="rederi" value="<?= htmlspecialchars($rederi ?? '') ?>" />
    <button type="submit">Søk</button>
  </form>

  <?php
  // === START: NY SQL-BLOKK FOR REDERI-SØK ===
  $rederi = $rederi ?? (isset($_GET['rederi']) ? trim((string)$_GET['rederi']) : '');
  $rows = [];
  if ($rederi !== '') {
      $like = "%{$rederi}%";
      $sql = "
          SELECT DISTINCT
              ft.FartObj_ID AS obj_id,
              ft.FartNavn_ID AS navn_id,
              fn.FartNavn
          FROM tblFartTid ft
          JOIN tblFartNavn fn ON fn.FartNavn_ID = ft.FartNavn_ID
          WHERE ft.Rederi LIKE ?
          ORDER BY fn.FartNavn
      ";
      $stmt = $mysqli->prepare($sql);
      $stmt->bind_param('s', $like);
      $stmt->execute();
      $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
      $stmt->close();
  }
  // === SLUTT: NY SQL-BLOKK FOR REDERI-SØK ===
  ?>

  <?php if (!empty($rows)): ?>
    <p><?= count($rows) ?> treff.</p>
    <ul class="result-list">
      <?php foreach ($rows as $row): ?>
        <li>
          <a href="fartoydetaljer.php?obj_id=<?= (int)$row['obj_id'] ?>&navn_id=<?= (int)$row['navn_id'] ?>">
            <?= htmlspecialchars($row['FartNavn']) ?>
          </a>
        </li>
      <?php endforeach; ?>
    </ul>
  <?php elseif (($rederi ?? '') !== ''): ?>
    <p>Ingen treff.</p>
  <?php else: ?>
    <p>Oppgi rederinavn eller del av navn.</p>
  <?php endif; ?>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
