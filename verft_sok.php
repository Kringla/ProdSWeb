<?php
/**
 * user/verft_sok.php
 * - Fritekstsøk mot verft (navn/sted) -> list fartøy bygget ved treffene
 * - Valg av ett verft snevrer inn listen
 * - Sortering (navn/byggeår, asc/desc), default: byggeår DESC
 * - Antall per side (20/50/100)
 * - Klikk på fartøy -> user/fartoydetaljer.php?id=<FartNavn_ID>
 * - CSV-eksport via ?export=csv (eksporterer alle treff, ignorerer paging)
 */
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/auth.php'; // ingen require_admin()

$q        = trim($_GET['q'] ?? '');
$verft_id = isset($_GET['verft_id']) ? (int)$_GET['verft_id'] : 0;

// sortering (default byggeår DESC)
$sort     = strtolower(trim($_GET['sort'] ?? 'bygget'));
$dir      = strtolower(trim($_GET['dir'] ?? 'desc'));
$sortMap  = ['navn' => 'fn.FartNavn', 'bygget' => 'fo.Bygget'];
$col      = $sortMap[$sort] ?? $sortMap['bygget'];
$direction= ($dir === 'desc') ? 'DESC' : 'ASC';
$orderBy  = $col . ' ' . $direction . ', fn.FartNavn ASC'; // sekundær for stabilitet

// per page
$ppAllowed = [20,50,100];
$perPage   = (int)($_GET['pp'] ?? 20);
if (!in_array($perPage, $ppAllowed, true)) $perPage = 20;

$page   = max(1, (int)($_GET['p'] ?? 1));
$offset = ($page - 1) * $perPage;

$verftList = [];
$verftIDs  = [];

/* 1) Hent verft-treff (for drop-down og chips) */
if ($q !== '' || $verft_id > 0) {
    $sql = "SELECT v.Verft_ID, v.VerftNavn, v.Sted, zn.Nasjon
            FROM tblVerft v
            LEFT JOIN tblzNasjon zn ON zn.Nasjon_ID = v.Nasjon_ID
            WHERE 1=1 ";
    $params = [];
    $types  = '';

    if ($q !== '') {
        $sql .= "AND (v.VerftNavn LIKE ? OR v.Sted LIKE ?) ";
        $like = '%'.$q.'%';
        $params[] = $like; $params[] = $like; $types .= 'ss';
    }
    if ($verft_id > 0) {
        $sql .= "AND v.Verft_ID = ? ";
        $params[] = $verft_id; $types .= 'i';
    }
    $sql .= "ORDER BY v.VerftNavn LIMIT 200";

    $stmt = $conn->prepare($sql);
    if ($types !== '') $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($r = $res->fetch_assoc()) {
        $verftList[] = $r;
        $verftIDs[]  = (int)$r['Verft_ID'];
    }
    $stmt->close();
}

/* 2) Bygg IN-liste for verft-IDer */
$inList = '';
if ($verft_id > 0) {
    $inList = (string)$verft_id;
} elseif (!empty($verftIDs)) {
    $inList = implode(',', array_map('intval', $verftIDs));
}

/* 3) CSV-eksport (tidlig exit, respekterer sortering) */
if (isset($_GET['export']) && $_GET['export'] === 'csv' && $inList !== '') {
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
        FROM tblFartSpes fs
        JOIN tblFartObj fo ON fo.FartObj_ID = fs.FartObj_ID
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
        WHERE fs.Verft_ID IN ($inList)
        ORDER BY $orderBy
    ";
    $res = $conn->query($exportSql);

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="verft_sok.csv"');

    $out = fopen('php://output', 'w');
    fputcsv($out, ['FartObj_ID','FartNavn','Type','Bygget','RegHavn','Nasjon','Kallesignal','Rederi','FartSpes_ID','FartNavn_ID']);
    if ($res) {
        while ($r = $res->fetch_assoc()) {
            fputcsv($out, [
                $r['FartObj_ID'], $r['FartNavn'], $r['TypeFork'], $r['Bygget'],
                $r['RegHavn'], $r['Nasjon'], $r['Kallesignal'], $r['Rederi'],
                $r['FartSpes_ID'], $r['FartNavn_ID']
            ]);
        }
    }
    fclose($out);
    exit;
}

/* 4) Paging + resultater */
$total = 0;
$rows  = [];

if ($inList !== '') {
    // Tell antall distinkte fartøy
    $countSql = "SELECT COUNT(DISTINCT fo.FartObj_ID) AS c
                 FROM tblFartSpes fs
                 JOIN tblFartObj fo ON fo.FartObj_ID = fs.FartObj_ID
                 WHERE fs.Verft_ID IN ($inList)";
    $countRes = $conn->query($countSql);
    $total = (int)($countRes->fetch_assoc()['c'] ?? 0);

    // Hent rader
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
        FROM tblFartSpes fs
        JOIN tblFartObj fo ON fo.FartObj_ID = fs.FartObj_ID
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
        WHERE fs.Verft_ID IN ($inList)
        ORDER BY $orderBy
        LIMIT ? OFFSET ?";
    $stmt = $conn->prepare($rowsSql);
    $stmt->bind_param('ii', $perPage, $offset);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($r = $res->fetch_assoc()) { $rows[] = $r; }
    $stmt->close();
}

include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/menu.php';
$BASE = defined('BASE_URL') ? rtrim(BASE_URL, '/') : '';
?>
<div class="container mt-4">
  <h1>Fartøy bygget ved verft</h1>

  <form method="get" class="form">
    <label for="q">Verftsnavn:</label>
    <input type="text" id="q" name="q" value="<?= htmlspecialchars($q ?? '') ?>" />
    <button type="submit">Søk</button>
  </form>

  <?php
  // === START: NY SQL-BLOKK FOR VERFTSØK ===
  $verftId = $verftId ?? filter_input(INPUT_GET, 'verft_id', FILTER_VALIDATE_INT);
  $q       = $q       ?? (isset($_GET['q']) ? trim((string)$_GET['q']) : '');

  $lastNameSub = "
      SELECT n.FartObj_ID, n.FartNavn_ID, n.FartNavn
      FROM tblFartNavn n
      JOIN (
          SELECT FartObj_ID, MAX(FartNavn_ID) AS max_id
          FROM tblFartNavn
          GROUP BY FartObj_ID
      ) mx ON mx.FartObj_ID = n.FartObj_ID AND mx.max_id = n.FartNavn_ID
  ";

  $rows = [];
  if ($verftId && $verftId > 0) {
      $sql = "
          SELECT DISTINCT
              fo.FartObj_ID    AS obj_id,
              ln.FartNavn_ID   AS navn_id,
              COALESCE(ln.FartNavn, fo.NavnObj) AS visningsnavn,
              v.VerftNavn,
              fs.Byggenr
          FROM tblFartSpes fs
          JOIN tblVerft v    ON v.Verft_ID   = fs.Verft_ID
          JOIN tblFartObj fo ON fo.FartObj_ID = fs.FartObj_ID
          LEFT JOIN ($lastNameSub) ln ON ln.FartObj_ID = fo.FartObj_ID
          WHERE fs.Verft_ID = ?
          ORDER BY visningsnavn
      ";
      $stmt = $mysqli->prepare($sql);
      $stmt->bind_param('i', $verftId);
      $stmt->execute();
      $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
      $stmt->close();
  } elseif ($q !== '') {
      $like = "%{$q}%";
      $sql = "
          SELECT DISTINCT
              fo.FartObj_ID    AS obj_id,
              ln.FartNavn_ID   AS navn_id,
              COALESCE(ln.FartNavn, fo.NavnObj) AS visningsnavn,
              v.VerftNavn,
              fs.Byggenr
          FROM tblFartSpes fs
          JOIN tblVerft v    ON v.Verft_ID   = fs.Verft_ID
          JOIN tblFartObj fo ON fo.FartObj_ID = fs.FartObj_ID
          LEFT JOIN ($lastNameSub) ln ON ln.FartObj_ID = fo.FartObj_ID
          WHERE v.VerftNavn LIKE ?
          ORDER BY visningsnavn
      ";
      $stmt = $mysqli->prepare($sql);
      $stmt->bind_param('s', $like);
      $stmt->execute();
      $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
      $stmt->close();
  }
  // === SLUTT: NY SQL-BLOKK FOR VERFTSØK ===
  ?>

  <?php if (!empty($rows)): ?>
    <p><?= count($rows) ?> treff.</p>
    <ul class="result-list">
      <?php foreach ($rows as $row): ?>
        <li>
          <a href="fartoydetaljer.php?obj_id=<?= (int)$row['obj_id'] ?>&navn_id=<?= (int)$row['navn_id'] ?>">
            <?= htmlspecialchars($row['visningsnavn']) ?>
          </a>
          <?php if (!empty($row['VerftNavn'])): ?>
            <small>— <?= htmlspecialchars($row['VerftNavn']) ?><?= $row['Byggenr'] ? ' (byggenr: '.htmlspecialchars($row['Byggenr']).')' : '' ?></small>
          <?php endif; ?>
        </li>
      <?php endforeach; ?>
    </ul>
  <?php elseif (($verftId && $verftId > 0) || ($q ?? '') !== ''): ?>
    <p>Ingen treff.</p>
  <?php else: ?>
    <p>Oppgi enten <code>?verft_id=</code> eller søk på navn.</p>
  <?php endif; ?>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
