<?php
// /user/rederi_sok.php — åpen søkeside (ingen auth‑krav)

/*
 * Denne siden lar brukeren søke etter rederier (skipseiere) og viser en
 * rederiliste med alle rederi som matcher søkekriteriet. Når et rederi
 * velges, vises en liste over fartøyer som eies/har vært eid av det valgte
 * rederiet. Søkeordet hentes fra ?q og det valgte rederinavnet fra ?rederi.
 */

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
$q           = isset($_GET['q']) ? trim((string)$_GET['q']) : '';
$selRederi   = isset($_GET['rederi']) ? trim((string)$_GET['rederi']) : '';
$rederiList  = [];
$fartoyListe = [];
$error       = null;
// SQL uttrykk for 'trimmet rederinavn' iht. CR: behold alt t.o.m. siste ')', eller t.o.m. tegnet før siste ','.
$REDE_TI_TRIM_T = "
RTRIM(
  SUBSTRING(
    t.Rederi,
    1,
    GREATEST(
      CHAR_LENGTH(t.Rederi) - LOCATE(')', REVERSE(t.Rederi)) + 1,
      (CHAR_LENGTH(t.Rederi) - LOCATE(',', REVERSE(t.Rederi)) + 1) - 1
    )
  )
)
";
$REDE_TI_TRIM_FT = "
RTRIM(
  SUBSTRING(
    ft.Rederi,
    1,
    GREATEST(
      CHAR_LENGTH(ft.Rederi) - LOCATE(')', REVERSE(ft.Rederi)) + 1,
      (CHAR_LENGTH(ft.Rederi) - LOCATE(',', REVERSE(ft.Rederi)) + 1) - 1
    )
  )
)
";


// 1) Søk etter rederier (min. 2 tegn). Basert på tblfarttid.Rederi.
if ($q !== '' && mb_strlen($q) >= 2) {
    $sql = "
        SELECT DISTINCT $REDE_TI_TRIM_T AS Rederi
        FROM tblfarttid t
        WHERE $REDE_TI_TRIM_T LIKE CONCAT('%', ?, '%')
          AND t.Rederi IS NOT NULL AND t.Rederi <> ''
        ORDER BY Rederi
        LIMIT 500
    ";
    if ($stmt = $conn->prepare($sql)) {
        $stmt->bind_param('s', $q);
        $stmt->execute();
        $res = $stmt->get_result();
        while ($row = $res->fetch_assoc()) {
            $rederiList[] = $row;
        }
        $stmt->close();
    } else {
        $error = 'Kunne ikke forberede SQL for rederi‑søk.';
    }

    // 2) Dersom vi har rederiresultater, bestem hvilket rederi som er valgt
    if (!$error && count($rederiList) > 0) {
        // Finn valgt rederi i resultatlisten; ellers bruk første
        $selectedName = null;
        if ($selRederi !== '') {
            foreach ($rederiList as $r) {
                if ((string)$r['Rederi'] === $selRederi) {
                    $selectedName = $r['Rederi'];
                    break;
                }
            }
        }
        if (!$selectedName) {
            $selectedName = $rederiList[0]['Rederi'];
        }

        // 3) Fartøyliste: alle fartøyer eid/har vært eid av valgt rederi
        $sqlFart = "
            SELECT
                ft.FartTid_ID,
                ft.FartObj_ID,
                ft.FartType_ID,
                ft.FartNavn,
                ft.YearTid,
                ft.MndTid,
                ft.RegHavn,
                ft.Rederi,
                ft.Objekt,
                zt.TypeFork
            FROM tblfarttid ft
            LEFT JOIN tblzfarttype zt ON zt.FartType_ID = ft.FartType_ID
            WHERE $REDE_TI_TRIM_FT = ?
            ORDER BY ft.YearTid, ft.MndTid, ft.FartNavn
            LIMIT 500
        ";
        if ($stmt = $conn->prepare($sqlFart)) {
            $stmt->bind_param('s', $selectedName);
            $stmt->execute();
            $res = $stmt->get_result();
            while ($row = $res->fetch_assoc()) {
                $fartoyListe[] = $row;
            }
            $stmt->close();
        }
    }
}
?>
<?php include __DIR__ . '/../includes/header.php'; ?>
<?php include __DIR__ . '/../includes/menu.php'; ?>

<!-- Hero image for rederi‑søk page -->
<div class="container">
    <section class="hero" style="background-image:url('../assets/img/rederi_sok_1.jpg'); background-size:cover; background-position:center;">
        <div class="hero-overlay"></div>
    </section>
</div>

<section class="container">
  <h1>Søk rederi</h1>

  <form method="get" class="search-form" style="margin-bottom:1rem;">
    <label for="q">Rederinavn (min. 2 tegn)</label>
    <input type="text" id="q" name="q" value="<?= h($q) ?>" placeholder="f.eks. Knutsen, Wilhelmsen ...">
    <button type="submit" class="btn">Søk</button>
  </form>

  <?php if ($error): ?>
    <div class="alert alert-error"><?= h($error) ?></div>
  <?php endif; ?>

  <?php if ($q !== '' && mb_strlen($q) < 2): ?>
    <p>Angi minst 2 tegn.</p>
  <?php endif; ?>

  <?php if ($q !== '' && mb_strlen($q) >= 2): ?>
    <h2>Rederier funnet (<?= count($rederiList) ?>)</h2>
    <?php if (!count($rederiList)): ?>
      <p>Ingen treff.</p>
    <?php else: ?>
      <div id="rederiliste" class="card centered-card" style="overflow-x:auto;">
          <div class="table-wrap center">
            <div class="table-wrap outline-brand"><table class="table tight fit">
            <thead>
              <tr>
                <th>Rederi</th>
                <th>Velg</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($rederiList as $r): ?>
                <?php $navn = (string)$r['Rederi']; ?>
                <tr <?php if (isset($selectedName) && $navn === $selectedName): ?> style="background:var(--accent);"<?php endif; ?>>
                  <td><?= h($navn) ?></td>
                  <td>
                    <a class="btn-small" href="?q=<?= urlencode($q) ?>&rederi=<?= urlencode($navn) ?>#rederiliste" title="Velg">Vis</a>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
</div>
        </div>
      </div>

      <!-- Fartøyliste for valgt rederi -->
      <h3 style="margin-top:1.5rem;">Fartøyer eid av valgt rederi</h3>
      <?php if (count($fartoyListe) === 0): ?>
        <p>Ingen registrerte fartøyer for dette rederiet.</p>
      <?php else: ?>
        <div id="fartoyer" class="card centered-card" style="overflow-x:auto;">
          <div class="table-wrap center">
            <div class="table-wrap outline-brand">
              <table class="table tight fit">
              <thead>
                <tr>
                  <th>Navn</th>
                  <th>Reg. havn</th>
                  <th>Fra År/mnd</th>
                  <th>Objekt</th>
                  <th>Vis</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($fartoyListe as $r): ?>
                  <?php
                    $year  = isset($r['YearTid']) ? (int)$r['YearTid'] : 0;
                    $month = isset($r['MndTid']) ? (int)$r['MndTid'] : 0;
                    $ym    = $year ? ($year . '/' . ($month ? str_pad((string)$month, 2, '0', STR_PAD_LEFT) : '')) : '';
                    $navn  = trim((string)($r['TypeFork'] ?? ''));
                    $navn  = $navn !== '' ? ($navn . ' ') : '';
                    $navn .= (string)($r['FartNavn'] ?? '');
                  ?>
                  <tr>
                    <td><?= h($navn) ?></td>
                    <td><?= h($r['RegHavn'] ?? '') ?></td>
                    <td><?= h($ym) ?></td>
                    <td>
                      <?php if (isset($r['Objekt']) && (int)$r['Objekt'] === 1): ?>
                        <span title="Navnet tilhører opprinnelig fartøy" aria-hidden="true">•</span>
                      <?php endif; ?>
                    </td>
                    <td>
                      <?php
                        $objId  = isset($r['FartObj_ID']) ? (int)$r['FartObj_ID'] : 0;
                        $navnId = isset($r['FartTid_ID'])  ? (int)$r['FartTid_ID']  : 0;
                      ?>
                      <?php if ($objId > 0 && $navnId > 0): ?>
                        <a class="btn-small" href="fartoydetaljer.php?obj_id=<?= $objId ?>&navn_id=<?= $navnId ?>#fartoyliste" title="Velg">Velg</a>
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