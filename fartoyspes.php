<?php
    /**
     * /user/fartoy_spes.php
     * Viser tekniske spesifikasjoner for et fartøy (tblfartspes) basert på ?spes_id=...
     * Layout: moderat kompakt, grupper og rekkefølge styrt av $GROUPS nedenfor.
     */

    require_once __DIR__ . '/../includes/bootstrap.php';
    if (!isset($conn) && isset($mysqli) && $mysqli instanceof mysqli) { $conn = $mysqli; }
    require_once __DIR__ . '/../includes/auth.php'; // for meny/rolle

    // === Konfig: grupper og rekkefølge ===
    // Endre kun denne blokken om du vil justere rekkefølge/etiketter/grupper.
    // Hver post er [felt-nøkkel i $data => ['label' => 'Etikett som vises']]
    $GROUPS = [
      '-' => [
        'Aarmnd'     => ['label' => 'År/mnd for spes'],
        'ObjektBool' => ['label' => 'Objekt?'],
      ],
        'Hovedspesifikasjon' => [
        'Lengde'  => ['label' => 'Lengde (fot)'],
        'Bredde'  => ['label' => 'Bredde (fot)'],
        'Dypg'    => ['label' => 'Dypgående (fot)'],
        'TonnasjeFmt' => ['label' => 'Tonnasje'],
        'DrektFmt'    => ['label' => 'Drektighet'],
        'MaxFart'       => ['label' => 'Maks fart (knop)'],
      ],
      'Bygg & skrog' => [
        'Byggeverft' => ['label' => 'Byggeverft'],
        'Byggenr'    => ['label' => 'Byggenr'],
        'Skrogverft' => ['label' => 'Skrog verft'],
        'BnrSkrog'   => ['label' => 'Byggenr for verft'],
        'Materiale'  => ['label' => 'Materiale'],
        'Skrogtype'  => ['label' => 'Skrogtype'],
        'RiggDetalj'  => ['label' => 'Rigg'],
        'RiggFree'    => ['label' => 'Riggdetalj'],
        'KlasseNavn'  => ['label' => 'Klasse'],
        'Fartklasse'  => ['label' => 'Klassedetalj'],
      ],
      'Funksjon' => [
        'Funksjon'    => ['label' => 'Funksjon'],
        'FunkDetalj'  => ['label' => 'Funksjonsbeskrivelse'],
        'Kapasitet'  => ['label' => 'Kapasitet'],
      ],
      'Fremdrift' => [
        'DriftMiddel' => ['label' => 'Fremdriftsmiddel'],
        'Motortype'     => ['label' => 'Motortype'],
        'MotorDetalj'   => ['label' => 'Motordetalj'],
        'MotorEff'      => ['label' => 'Effekt (BHK)'],
      ],

    ];

    // Små helpers
    if (!function_exists('h')) { function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); } }
    function nonempty($v){ return isset($v) && $v !== '' && $v !== null; }

    // Parametre
    $spesId = isset($_GET['spes_id']) ? (int)$_GET['spes_id'] : 0;
    if ($spesId <= 0) {
      http_response_code(400);
      echo "<p>Mangler eller ugyldig parameter: spes_id må være &gt; 0.</p>";
      exit;
    }

    // Hent spes + oppslag + (seneste) navn
    $sql = "
    SELECT
      fs.FartSpes_ID, fs.FartObj_ID, fs.YearSpes, fs.MndSpes, fs.Byggenr, fs.BnrSkrog, fs.Kapasitet,
      fs.MotorDetalj AS MotorDetalj_free, fs.MotorEff, fs.MaxFart, fs.Lengde, fs.Bredde, fs.Dypg,
      fs.Tonnasje, fs.Drektigh, fs.Objekt,
      fs.FartType_ID AS FartTypeSpes_ID, fs.FunkDetalj, fs.Fartklasse, fs.Rigg,
      CONCAT_WS(', ', vb.VerftNavn, vb.Sted) AS Byggeverft,
      CONCAT_WS(', ', vs.VerftNavn, vs.Sted) AS Skrogverft,
      zmat.MatFork AS Materiale,
      zskrog.TypeSkrog AS Skrogtype,
      zd.DriftMiddel,
      zm.MotorDetalj AS Motortype,
      zr.RiggDetalj AS RiggDetalj,
      zf.TypeFunksjon AS Funksjon,
      zk.TypeKlasseNavn AS KlasseNavn,
      zt.TonnFork AS TonnEnh,
      fn.FartNavn,
      COALESCE(t.typefork, fs.FartType_ID) AS TypeForkOrID
    FROM tblfartspes fs
    LEFT JOIN tblverft vb            ON vb.Verft_ID         = fs.Verft_ID
    LEFT JOIN tblverft vs            ON vs.Verft_ID         = fs.SkrogID
    LEFT JOIN tblzfartmat zmat       ON zmat.FartMat_ID     = fs.FartMat_ID
    LEFT JOIN tblzfartskrog zskrog   ON zskrog.FartSkrog_ID = fs.FartSkrog_ID
    LEFT JOIN tblzfartdrift zd       ON zd.FartDrift_ID     = fs.FartDrift_ID
    LEFT JOIN tblzfartmotor zm       ON zm.FartMotor_ID     = fs.FartMotor_ID
    LEFT JOIN tblzfartrigg zr        ON zr.FartRigg_ID      = fs.FartRigg_ID
    LEFT JOIN tblzfartfunk zf        ON zf.FartFunk_ID      = fs.FartFunk_ID
    LEFT JOIN tblzfartklasse zk      ON zk.FartKlasse_ID    = fs.FartKlasse_ID
    LEFT JOIN tblztonnenh zt         ON zt.TonnEnh_ID       = fs.TonnEnh_ID
    LEFT JOIN (  -- seneste navn pr objekt
      SELECT fn.FartObj_ID, fn.FartNavn, fn.FartType_ID
      FROM tblfartnavn fn
      INNER JOIN (
        SELECT FartObj_ID, MAX(FartNavn_ID) AS max_id
        FROM tblfartnavn
        GROUP BY FartObj_ID
      ) m ON m.FartObj_ID = fn.FartObj_ID AND fn.FartNavn_ID = m.max_id
    ) fn ON fn.FartObj_ID = fs.FartObj_ID
    LEFT JOIN tblzfarttype t ON t.FartType_ID = COALESCE(fs.FartType_ID, fn.FartType_ID)
    WHERE fs.FartSpes_ID = ?
    LIMIT 1
    ";
    $stmt = $conn->prepare($sql);
    if (!$stmt) { http_response_code(500); echo "<p>DB-feil (prepare): ".h($conn->error)."</p>"; exit; }
    $stmt->bind_param('i', $spesId);
    $stmt->execute();
    $res  = $stmt->get_result();
    $row  = $res ? $res->fetch_assoc() : null;
    $stmt->close();

    if (!$row) {
      echo "<p>Fant ingen spesifikasjoner for spes_id." . h($spesId) . "</p>";
      exit;
    }

    // Bygg $data og $topLine med samme logikk som originalfil
    $data = [];
    $topLine = isset($row['FartNavn']) ? $row['FartNavn'] : '';
    // Bygg $data basert på $GROUPS og $row
    foreach ($GROUPS as $section => $fields) {
        foreach ($fields as $key => $_cfg) {
            // en litt spesialbehandling for felter som ikke finnes direkte
            if ($key === 'TonnasjeFmt') {
                $val  = $row['Tonnasje'] ?? null;
                $enh  = $row['TonnEnh'] ?? '';
                $data['TonnasjeFmt'] = ($val !== null && $val !== '') ? h($val) . ' ' . h($enh) : '';
                continue;
            }
            if ($key === 'DrektFmt') {
                $val = $row['Drektigh'] ?? null;
                $data['DrektFmt'] = ($val !== null && $val !== '') ? h($val) . ' reg.tonn' : '';
                continue;
            }
            if ($key === 'ObjektBool') {
                $obj  = $row['Objekt'] ?? null;
                $data['ObjektBool'] = ($obj && $obj !== '0') ? 'Ja' : 'Nei';
                continue;
            }
            // default: direkte kopier fra $row
            $data[$key] = $row[$key] ?? '';
        }
    }

?>
<?php include __DIR__ . '/../includes/header.php'; ?>
<?php include __DIR__ . '/../includes/menu.php'; ?>

    <!-- Hero image for fartøys­spesifikasjon page -->
    <div class="container">
        <section class="hero" style="background-image:url('../assets/img/fartoy_spes_1.jpg'); background-size:cover; background-position:center;">
            <div class="hero-overlay"></div>
        </section>
    </div>

    <style>
    /* Moderat kompakt spesifikasjonslayout */
    .spec-wrap { max-width: 980px; margin: 0 auto; padding: 8px 12px; }
    .spec-head { text-align: center; margin: 4px 0 10px; }
    .spec-head h1 { margin: 0; font-size: 1.4rem; }
    .spec-head .sub { margin-top: 4px; font-size: 1.25rem; font-weight: 600; line-height: 1.25; opacity: 0.9; }
    .spec-actions { display:flex; justify-content: space-between; align-items:center; margin: 6px 0 8px; }
    .spec-actions .left { display:flex; gap:8px; align-items:center; }
    .btn-back { display:inline-block; padding:6px 10px; border:1px solid #ccc; border-radius:8px; text-decoration:none; font-size:0.92rem; }
    .spec-id { font-size: 0.78rem; opacity: 0.8; }
    .spec-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 6px 18px; }
    .spec-group { grid-column: 1 / -1; margin-top: 8px; font-weight: 600; border-top: 1px solid #ddd; padding-top: 6px; }
    .spec-row { display: grid; grid-template-columns: 200px 1fr; align-items: start; gap: 8px; font-size: 0.95rem; }
    .spec-row .label { color: #333; opacity: 0.9; }
    .spec-row .value { color: #111; }
    @media (max-width: 700px) {
      .spec-grid { grid-template-columns: 1fr; }
    }
    </style>

    <div class="spec-wrap">
      <div class="spec-head">
        <h1>Fartøysspesifikasjoner</h1>
        <h2 class="sub"><?= h($topLine) ?></h2>
      </div>

      <div class="spec-actions">
        <div class="left">
          <!-- Tilbake-knapp: prioriterer history.back(); fallback-lenke under -->
          <a href="#" class="btn" onclick="if(history.length>1){history.back();return false;}" title="Tilbake">← Tilbake</a>
          <span class="spec-id">Spes ID: <?= (int)$data['FartSpes_ID'] ?></span>
        </div>
        <!-- Fallback: direkte lenke til detaljer (om du har en fast URL-struktur). Justér ved behov. -->
        <a class="btn" href="<?= BASE_URL ?>/user/fartoydetaljer.php?obj_id=<?= (int)$data['FartObj_ID'] ?>">Detaljside</a>
      </div>

      <div class="spec-grid">
        <?php foreach ($GROUPS as $groupTitle => $fields): ?>
          <div class="spec-group"><?= h($groupTitle) ?></div>
          <?php foreach ($fields as $key => $cfg): ?>
            <?php $val = $data[$key] ?? ''; if (!nonempty($val)) continue; ?>
            <div class="spec-row">
              <div class="label"><?= h($cfg['label']) ?></div>
              <div class="value"><?= h($val) ?></div>
            </div>
          <?php endforeach; ?>
        <?php endforeach; ?>
      </div>
    </div>

<?php include __DIR__ . '/../includes/footer.php'; ?>