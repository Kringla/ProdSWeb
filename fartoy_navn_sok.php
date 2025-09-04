<?php
    require_once __DIR__ . '/../includes/bootstrap.php';
    require_once __DIR__ . '/../includes/auth.php'; // ok å ha med for meny/rolle

    ini_set('display_errors', '1');
    error_reporting(E_ALL);

    // Små helpers
    if (!function_exists('h')) {
        function h($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
    }
    function val($arr, $key, $def='') { return isset($arr[$key]) ? $arr[$key] : $def; }

    $nasjonId = isset($_GET['nasjon_id']) ? (int)$_GET['nasjon_id'] : 0; // 0 = alle
    $q        = isset($_GET['q']) ? trim($_GET['q']) : '';

    // Nasjoner til dropdown (robust)
    $nasjoner = [];
    $sql = "
        SELECT Nasjon_ID, Nasjon
        FROM tblznasjon
        WHERE Nasjon IS NOT NULL AND Nasjon <> ''
        ORDER BY Nasjon
    ";
    if ($res = $conn->query($sql)) {
        while ($row = $res->fetch_assoc()) {
            $nasjoner[] = $row;
        }
        $res->free();
    }

    // Kjør søk bare når bruker har trykket Søk (eller sendt noen parametre)
    $doSearch = ($_GET !== []);

    // Resultater
    $rows = [];
    if ($doSearch) {
        // Bygg spørring. Vi henter seneste registrering per FartObj_ID og FartNavn direkte fra tblfarttid,
        // og joiner til parametertabeller for type, nasjon og objekt.
        $sql = "
        SELECT
          latest.FartTid_ID      AS FartTid_ID,
          latest.FartObj_ID      AS FartObj_ID,
          latest.FartNavn        AS FartNavn,
          latest.FartType_ID     AS FartType_ID,
          latest.PennantTiln     AS PennantTiln,
          ft.TypeFork,
          latest.YearTid,
          latest.MndTid,
          latest.RegHavn,
          latest.Kallesignal,
          latest.Nasjon_ID       AS TNat,
          n.Nasjon,
          CASE WHEN orig.FartTid_ID IS NOT NULL THEN 1 ELSE 0 END AS IsOriginalNow,
          o.Bygget               AS Bygget
        FROM (
            SELECT t.*
            FROM tblfarttid t
            INNER JOIN (
                SELECT FartObj_ID, FartNavn, MAX(FartTid_ID) AS max_id
                FROM tblfarttid
                WHERE COALESCE(FartNavn, '') <> ''
                GROUP BY FartObj_ID, FartNavn
            ) m ON m.FartObj_ID = t.FartObj_ID AND m.FartNavn = t.FartNavn AND m.max_id = t.FartTid_ID
        ) AS latest
        LEFT JOIN tblzfarttype AS ft ON ft.FartType_ID = latest.FartType_ID
        LEFT JOIN tblznasjon  AS n  ON n.Nasjon_ID  = latest.Nasjon_ID
        LEFT JOIN tblfartobj  AS o  ON o.FartObj_ID = latest.FartObj_ID
        LEFT JOIN (
            SELECT FartObj_ID, FartNavn, MAX(FartTid_ID) AS FartTid_ID
            FROM tblfarttid
            WHERE Objekt = 1
            GROUP BY FartObj_ID, FartNavn
        ) AS orig ON orig.FartObj_ID = latest.FartObj_ID AND orig.FartNavn = latest.FartNavn
        WHERE (? = 0 OR latest.Nasjon_ID = ?)
          AND (? = '' OR latest.FartNavn LIKE CONCAT('%', ?, '%'))
        ORDER BY latest.FartNavn ASC
        LIMIT 200
        ";

        $stmt = $conn->prepare($sql);
        if (!$stmt) { die('Prepare feilet: ' . $conn->error); }
        $stmt->bind_param('iiss', $nasjonId, $nasjonId, $q, $q);
        if (!$stmt->execute()) { die('Execute feilet: ' . $stmt->error); }
        $result = $stmt->get_result();
        if ($result) {
            $rows = $result->fetch_all(MYSQLI_ASSOC);
            $result->free();
        }
        $stmt->close();
    }
    ?>
    <?php include __DIR__ . '/../includes/header.php'; ?>
    <?php include __DIR__ . '/../includes/menu.php'; ?>

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
              $imgCandidate = '/assets/img/placeholder2.jpg';
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

    <div class="container">
      <h1>Fartøy i databasen</h1>
      <div style="margin:-0.25rem 0 0.75rem 0; font-size:0.95rem; color:#555;text-align: center;">
        <strong>Forklaring:</strong>
        <span title="Navnet tilhører opprinnelig fartøy" aria-hidden="true" style="font-size:1.1rem; vertical-align:baseline;">•</span>
        = navnet tilhører <em>opprinnelig</em> fartøy (Objekt = 1).
      </div>
      <form method="get" class="form-inline" style="margin-bottom:1rem;text-align: center;">
        <label for="q">Søk på del av fartøynavn:&nbsp;</label>
        <input type="text" id="q" name="q" value="<?= h($q) ?>" />
        &nbsp;&nbsp;
        <label for="nasjon_id">fra nasjon</label>
        <select name="nasjon_id" id="nasjon_id">
          <option value="0"<?= $nasjonId === 0 ? ' selected' : '' ?>>Alle nasjoner</option>
          <?php foreach ($nasjoner as $r): ?>
            <option value="<?= (int)$r['Nasjon_ID'] ?>"<?= $nasjonId === (int)$r['Nasjon_ID'] ? ' selected' : '' ?>>
              <?= h($r['Nasjon']) ?>
            </option>
          <?php endforeach; ?>
        </select>
        &nbsp;&nbsp;
        <button type="submit" class="btn">Søk</button>
      </form>

      <?php if ($doSearch): ?>
        <p>Antall funnet: <strong><?= count($rows) ?></strong></p>
      <?php endif; ?>

      <?php if ($rows): ?>
        <div class="table-wrap table-wrap--scroll">
          <table class="table table--compact table--zebra">
            <thead>
              <tr>
                <th>Type</th>
                <th>Navn</th>
                <th>Reg.havn</th>
                <th>Flaggstat</th>
                <th>Bygget</th>
                <th>Kallesignal</th>
                <th>Vis</th>
              </tr>
            </thead>
            <tbody>
            <?php foreach ($rows as $r): ?>
              <tr>
                <td><?= h(val($r,'TypeFork')) ?></td>
                <td>
                  <?= h(val($r,'FartNavn')) ?>
                  <?php if ((int)val($r,'IsOriginalNow',0) === 1): ?>
                    <span title="Navnet tilhører opprinnelig fartøy">•</span>
                  <?php endif; ?>
                </td>
                <td><?= h(val($r,'RegHavn')) ?></td>
                <td><?= h(val($r,'Nasjon')) ?></td>
                <td><?= h(val($r,'Bygget')) ?></td>
                <td><?= h(val($r,'Kallesignal')) ?></td>
                <td>
                  <?php $id = (int)val($r,'FartObj_ID',0); ?>
                  <?php if ($id > 0): ?>
                    <?php $tid = (int)val($r, 'FartTid_ID', 0); ?>
                    <a class="btn-small" href="fartoydetaljer.php?obj_id=<?= (int)$r['FartObj_ID'] ?>&tid_id=<?= $tid ?>">Vis</a>
                  <?php else: ?>
                    <span class="muted">–</span>
                  <?php endif; ?>
                </td>
              </tr>
            <?php endforeach; ?>
            </tbody>
          </table>
        </div> 
      <?php elseif ($doSearch): ?>
        <p>Ingen treff.</p>
      <?php else: ?>
        <p>Skriv del av navn for å søke. Du kan også bruke nasjon som filter.</p>
      <?php endif; ?>
    </div>

  <!-- Tilbake-knapp nederst: midtstilt -->
    <div class="actions" style="margin:1rem 0 2rem; text-align:center;">
      <a class="btn" href="#" onclick="if(history.length>1){history.back();return false;}" title="Tilbake">← Tilbake</a>
    </div>
    
    <?php include __DIR__ . '/../includes/footer.php'; ?>