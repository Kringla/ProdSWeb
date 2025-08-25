<?php
// /user/verft_sok.php — åpen søkeside (ingen auth‑krav)

/*
 * Denne siden lar brukeren søke etter verft (skipverft) og viser resultatene
 * i tre separate lister:
 *   1) En verftliste over alle verft som matcher søkekriteriet
 *   2) En leveranseliste med fartøyer levert av valgt verft
 *   3) En skrogbyggliste med fartøyer hvor valgt verft stod for skroget
 *
 * Søkeordet hentes fra ?q og det valgte verftets ID fra ?verft_id. For å
 * unngå SQL‑injeksjon brukes forberedte statements. Alle output verdier
 * escapes med htmlspecialchars() via helperfunksjonen h().
 */

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/../includes/bootstrap.php';

// Dersom bootstrap ikke laget $conn, forsøk å koble via config
if (!isset($conn) || !($conn instanceof mysqli)) {
    $cfgFile = __DIR__ . '/../config/config.php';
    if (is_file($cfgFile)) {
        require_once $cfgFile;
    }
    if (!isset($conn) || !($conn instanceof mysqli)) {
        die('DB‑tilkobling mangler.');
    }
}
$conn->set_charset('utf8mb4');

// En enkel helper for HTML escaping
if (!function_exists('h')) {
    function h($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
}

// Input
$q       = isset($_GET['q']) ? trim((string)$_GET['q']) : '';
$verftId = isset($_GET['verft_id']) ? (int)$_GET['verft_id'] : 0;

// Resultatlister
$verftList     = [];
$leveranseList = [];
$skrogList     = [];
$error         = null;

// 1) Søk etter verft (min. 2 tegn). Basert på tblverft.
if ($q !== '' && mb_strlen($q) >= 2) {
    $sql = "
        SELECT v.Verft_ID, v.VerftNavn, v.Sted, n.Nasjon
        FROM tblverft v
        LEFT JOIN tblznasjon n ON n.Nasjon_ID = v.Nasjon_ID
        WHERE v.VerftNavn LIKE CONCAT('%', ?, '%')
        ORDER BY v.VerftNavn
        LIMIT 500
    ";
    if ($stmt = $conn->prepare($sql)) {
        $stmt->bind_param('s', $q);
        $stmt->execute();
        $res = $stmt->get_result();
        while ($row = $res->fetch_assoc()) {
            $verftList[] = $row;
        }
        $stmt->close();
    } else {
        $error = 'Kunne ikke forberede SQL for verft‑søk.';
    }

    // 2) Dersom vi har verftresultater, bestem hvilket verft som er valgt
    if (!$error && count($verftList) > 0) {
        // Finn valgt verft i resultatlisten; ellers bruk første
        $selected = null;
        if ($verftId > 0) {
            foreach ($verftList as $v) {
                if ((int)$v['Verft_ID'] === $verftId) {
                    $selected = $v;
                    break;
                }
            }
        }
        if (!$selected) {
            $selected = $verftList[0];
            $verftId  = (int)$selected['Verft_ID'];
        }

        // 3) Leveranseliste: fartøyer levert av valgt verft
        $sqlLev = "
            SELECT
                fs.FartSpes_ID,
                fs.FartObj_ID,
                fs.Byggenr,
                fs.YearSpes,
                fs.MndSpes,
                fn.FartNavn_ID,
                fn.FartNavn,
                zt.TypeFork,
                tid.RegHavn,
                tid.Rederi,
                tid.Objekt
            FROM tblfartspes fs
            /* Finn siste tidsrad per spesifikasjon */
            LEFT JOIN (
                SELECT t1.*
                FROM tblfarttid t1
                JOIN (
                    SELECT FartSpes_ID, MAX(FartTid_ID) AS FartTid_ID
                    FROM tblfarttid
                    GROUP BY FartSpes_ID
                ) t2 ON t2.FartSpes_ID = t1.FartSpes_ID AND t2.FartTid_ID = t1.FartTid_ID
            ) tid ON tid.FartSpes_ID = fs.FartSpes_ID

            /* Bind navnet entydig til tidsradens FartNavn_ID */
            LEFT JOIN tblfartnavn  fn ON fn.FartNavn_ID = tid.FartNavn_ID
            LEFT JOIN tblzfarttype zt ON zt.FartType_ID = fn.FartType_ID

            WHERE fs.Verft_ID = ?
            ORDER BY fs.YearSpes, fs.MndSpes, fn.FartNavn
            LIMIT 500
        ";
        if ($stmt = $conn->prepare($sqlLev)) {
            $stmt->bind_param('i', $verftId);
            $stmt->execute();
            $res = $stmt->get_result();
            while ($row = $res->fetch_assoc()) {
                $leveranseList[] = $row;
            }
            $stmt->close();
        }

        // 4) Skrogbyggliste: fartøyer hvor valgt verft bygget skroget, men ikke leveransen
        $sqlSkrog = "
            SELECT
              fs.FartSpes_ID,
              fs.FartObj_ID,
              fs.BnrSkrog AS Byggenr,
              fs.YearSpes,
              fs.MndSpes,
              fn.FartNavn_ID,
              fn.FartNavn,
              zt.TypeFork,
              tid.RegHavn,
              tid.Rederi,
              tid.Objekt
          FROM tblfartspes fs
          /* Finn siste tidsrad per spesifikasjon */
          LEFT JOIN (
              SELECT t1.*
              FROM tblfarttid t1
              JOIN (
                  SELECT FartSpes_ID, MAX(FartTid_ID) AS FartTid_ID
                  FROM tblfarttid
                  GROUP BY FartSpes_ID
              ) t2 ON t2.FartSpes_ID = t1.FartSpes_ID AND t2.FartTid_ID = t1.FartTid_ID
          ) tid ON tid.FartSpes_ID = fs.FartSpes_ID

          /* Bind navnet entydig til tidsradens FartNavn_ID */
          LEFT JOIN tblfartnavn  fn ON fn.FartNavn_ID = tid.FartNavn_ID
          LEFT JOIN tblzfarttype zt ON zt.FartType_ID = fn.FartType_ID

          WHERE fs.SkrogID = ?
            AND fs.SkrogID IS NOT NULL
            AND (fs.Verft_ID IS NULL OR fs.SkrogID <> fs.Verft_ID)

          ORDER BY fs.YearSpes, fs.MndSpes, fn.FartNavn
          LIMIT 500
        ";
        if ($stmt = $conn->prepare($sqlSkrog)) {
            $stmt->bind_param('i', $verftId);
            $stmt->execute();
            $res = $stmt->get_result();
            while ($row = $res->fetch_assoc()) {
                $skrogList[] = $row;
            }
            $stmt->close();
        }
    }
}
?>
<?php include __DIR__ . '/../includes/header.php'; ?>
<?php include __DIR__ . '/../includes/menu.php'; ?>

<!-- Hero image for verft‑søk page -->
<div class="container">
    <section class="hero" style="background-image:url('../assets/img/verft_sok_1.jpg'); background-size:cover; background-position:center;">
        <div class="hero-overlay"></div>
    </section>
</div>

<section class="container">
  <h1>Søk verft</h1>

  <form method="get" class="search-form" style="margin-bottom:1rem;">
    <label for="q">Verftnavn (min. 2 tegn)</label>
    <input type="text" id="q" name="q" value="<?= h($q) ?>" placeholder="f.eks. Aker, Ulstein ...">
    <button type="submit" class="btn">Søk</button>
  </form>

  <?php if ($error): ?>
    <div class="alert alert-error"><?= h($error) ?></div>
  <?php endif; ?>

  <?php if ($q !== '' && mb_strlen($q) < 2): ?>
    <p>Angi minst 2 tegn.</p>
  <?php endif; ?>

  <?php if ($q !== '' && mb_strlen($q) >= 2): ?>
    <h2>Verft funnet (<?= count($verftList) ?>)</h2>
    <?php if (!count($verftList)): ?>
      <p>Ingen treff.</p>
    <?php else: ?>
      <div id="verftliste" class="card centered-card" style="overflow-x:auto;">
          <div class="table-wrap center">
            <div class="table-wrap outline-brand">
            <table class="table tight fit">
            <thead>
              <tr>
                <th>Verft</th>
                <th>Sted</th>
                <th>Nasjon</th>
                <th>Velg</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($verftList as $v): ?>
                <tr <?php if ((int)$v['Verft_ID'] === $verftId): ?> style="background:var(--accent);"<?php endif; ?>>
                  <td><?= h($v['VerftNavn'] ?? '') ?></td>
                  <td><?= h($v['Sted'] ?? '') ?></td>
                  <td><?= h($v['Nasjon'] ?? '') ?></td>
                  <td>
                    <a class="btn-small" href="?q=<?= urlencode($q) ?>&verft_id=<?= (int)$v['Verft_ID'] ?>#verftliste" title="Velg">Vis</a>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
</div>
        </div>
      </div>

      <!-- Leveranseliste -->
      <h3 style="margin-top:1.5rem;">Leveranser fra det valgte verftet</h3>
      <?php if (count($leveranseList) === 0): ?>
        <p>Ingen registrerte leveranser for dette verftet.</p>
      <?php else: ?>
        <div id="leveranser" class="card centered-card" style="overflow-x:auto;">
          <div class="table-wrap center">
            <div class="table-wrap outline-brand"><table class="table tight fit">
              <thead>
                <tr>
                  <th>Byggenr</th>
                  <th>Bygd År/mnd</th>
                  <th>Navn</th>
                  <th>Reg. havn</th>
                  <th>Eier</th>
                  <th>Objekt</th>
                  <th>Vis</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($leveranseList as $r): ?>
                  <?php
                    $year  = isset($r['YearSpes']) ? (int)$r['YearSpes'] : 0;
                    $month = isset($r['MndSpes']) ? (int)$r['MndSpes'] : 0;
                    $ym    = $year ? ($year . '/' . ($month ? str_pad((string)$month, 2, '0', STR_PAD_LEFT) : '')) : '';
                    $navn  = trim((string)($r['TypeFork'] ?? ''));
                    $navn  = $navn !== '' ? ($navn . ' ') : '';
                    $navn .= (string)($r['FartNavn'] ?? '');
                  ?>
                  <tr>
                    <td><?= h($r['Byggenr'] ?? '') ?></td>
                    <td><?= h($ym) ?></td>
                    <td><?= h($navn) ?></td>
                    <td><?= h($r['RegHavn'] ?? '') ?></td>
                    <td><?= h($r['Rederi'] ?? '') ?></td>
                    <td>
                      <?php if (isset($r['Objekt']) && (int)$r['Objekt'] === 1): ?>
                        <span title="Navnet tilhører opprinnelig fartøy" aria-hidden="true">•</span>
                      <?php endif; ?>
                    </td>
                    <td>
                      <?php
                        $objId  = isset($r['FartObj_ID']) ? (int)$r['FartObj_ID'] : 0;
                        $navnId = isset($r['FartNavn_ID']) ? (int)$r['FartNavn_ID'] : 0;
                      ?>
                      <?php if ($objId > 0 && $navnId > 0): ?>
                        <a class="btn-small" href="fartoydetaljer.php?obj_id=<?= $objId ?>&navn_id=<?= $navnId ?>">Vis</a>
                        
                      <?php else: ?>
                        <span class="muted">–</span>
                      <?php endif; ?>
                    </td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
</div>
          </div>
        </div>
      <?php endif; ?>

      <!-- Skrogbyggliste -->
      <h3 style="margin-top:1.5rem;">Skrogleveranser fra det valgte verftet</h3>
      <?php if (count($skrogList) === 0): ?>
        <p>Ingen registrerte skrogbygg for dette verftet.</p>
      <?php else: ?>
        <div id="skrogleveranser" class="card centered-card" style="overflow-x:auto;">
          <div class="table-wrap center">
            <div class="table-wrap outline-brand"><table class="table tight fit">
              <thead>
                <tr>
                  <th>Byggenr</th>
                  <th>Bygd År/mnd</th>
                  <th>Navn</th>
                  <th>Reg. havn</th>
                  <th>Eier</th>
                  <th>Objekt</th>
                  <th>Vis</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($skrogList as $r): ?>
                  <?php
                    $year  = isset($r['YearSpes']) ? (int)$r['YearSpes'] : 0;
                    $month = isset($r['MndSpes']) ? (int)$r['MndSpes'] : 0;
                    $ym    = $year ? ($year . '/' . ($month ? str_pad((string)$month, 2, '0', STR_PAD_LEFT) : '')) : '';
                    $navn  = trim((string)($r['TypeFork'] ?? ''));
                    $navn  = $navn !== '' ? ($navn . ' ') : '';
                    $navn .= (string)($r['FartNavn'] ?? '');
                  ?>
                  <tr>
                    <td><?= h($r['Byggenr'] ?? '') ?></td>
                    <td><?= h($ym) ?></td>
                    <td><?= h($navn) ?></td>
                    <td><?= h($r['RegHavn'] ?? '') ?></td>
                    <td><?= h($r['Rederi'] ?? '') ?></td>
                    <td>
                      <?php if (isset($r['Objekt']) && (int)$r['Objekt'] === 1): ?>
                        <span title="Navnet tilhører opprinnelig fartøy" aria-hidden="true">•</span>
                      <?php endif; ?>
                    </td>
                    <td>
                      <?php
                        $objId  = isset($r['FartObj_ID']) ? (int)$r['FartObj_ID'] : 0;
                        $navnId = isset($r['FartNavn_ID']) ? (int)$r['FartNavn_ID'] : 0;
                      ?>
                      <?php if ($objId > 0 && $navnId > 0): ?>
                        <a class="btn-small" href="fartoydetaljer.php?obj_id=<?= $objId ?>&navn_id=<?= $navnId ?>">Vis</a>
                      <?php else: ?>
                        <span class="muted">–</span>
                      <?php endif; ?>
                    </td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
</div>
          </div>
        </div>
      <?php endif; ?>
    <?php endif; ?>
  <?php endif; ?>
</section>

<!-- Tilbake-knapp nederst: midtstilt -->
<div class="actions" style="margin:1rem 0 2rem; text-align:center;">
  <a class="btn" href="#" onclick="if(history.length>1){history.back();return false;}" title="Tilbake">← Tilbake</a>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>