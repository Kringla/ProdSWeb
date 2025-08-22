<?php
    // user/fartoydetaljer.php
    require_once __DIR__ . '/../includes/bootstrap.php';
    require_once __DIR__ . '/../includes/auth.php'; // ok for meny/rolle

    // Små helpers
    if (!function_exists('h')) {
        function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
    }
    function val($arr, $key, $def=''){ return isset($arr[$key]) ? $arr[$key] : $def; }

    // Parametre
    $obj_id  = isset($_GET['obj_id'])  ? (int)$_GET['obj_id']  : 0;
    $navn_id = isset($_GET['navn_id']) ? (int)$_GET['navn_id'] : 0;

    if ($obj_id <= 0 || $navn_id <= 0) {
        http_response_code(400);
        echo "Mangler eller ugyldige parametre: obj_id og navn_id må være > 0.";
        exit;
    }

    // --- Hent hovedrad (seneste) for valgt objekt+navn
    $main = null;
    $stmt = $conn->prepare(
        "SELECT t.*, fn.FartNavn,
                z.Nasjon AS NasjonNavn
         FROM tblfarttid t
         LEFT JOIN tblfartnavn fn ON fn.FartNavn_ID = t.FartNavn_ID
         LEFT JOIN tblznasjon z   ON z.Nasjon_ID    = t.Nasjon_ID
         WHERE t.FartObj_ID = ? AND t.FartNavn_ID = ?
         ORDER BY COALESCE(t.YearTid,0) DESC, COALESCE(t.MndTid,0) DESC, t.FartTid_ID DESC
         LIMIT 1"
    );
    $stmt->bind_param("ii", $obj_id, $navn_id);
    $stmt->execute();
    $res = $stmt->get_result();
    $main = $res->fetch_assoc();
    $stmt->close();

    if (!$main) {
        echo "Fant ingen detaljer for angitt objekt/navn.";
        exit;
    }

    // --- Hent TypeFork (fartøytypeforkortelse) tilhørende denne navneoppføringen via fartspes og farttype.
    $typeFork = '';
    $stmt = $conn->prepare(
        "SELECT zft.TypeFork
         FROM tblfarttid t
         LEFT JOIN tblfartspes fs ON fs.FartSpes_ID = t.FartSpes_ID
         LEFT JOIN tblzfarttype zft ON zft.FartType_ID = fs.FartType_ID
         WHERE t.FartObj_ID = ? AND t.FartNavn_ID = ?
         LIMIT 1"
    );
    if ($stmt) {
        $stmt->bind_param('ii', $obj_id, $navn_id);
        $stmt->execute();
        $resTF = $stmt->get_result();
        if ($resTF) {
            $tfRow = $resTF->fetch_assoc();
            if ($tfRow && isset($tfRow['TypeFork'])) {
                $typeFork = trim((string)$tfRow['TypeFork']);
            }
            $resTF->free();
        }
        $stmt->close();
    }

    // --- Hent bildet til valgt FartNavn_ID fra tblxnmmfoto
    // Standard fallback‑bilde dersom ingen oppføring finnes eller Bilde_Fil er tomt.
    // URL_Bane i tabellen peker til rot (typisk «/assets/img/skip/»), så vi kan bruke den direkte.
    $imageSrc = '/assets/img/skip/fartoydetaljer_1.jpg';
    $stmt = $conn->prepare(
        "SELECT URL_Bane, Bilde_Fil
         FROM tblxnmmfoto
         WHERE FartNavn_ID = ? AND COALESCE(Bilde_Fil,'') <> ''
         ORDER BY ID DESC
         LIMIT 1"
    );
    if ($stmt) {
        $stmt->bind_param('i', $navn_id);
        $stmt->execute();
        $resImg = $stmt->get_result();
        if ($resImg) {
            $imgRow = $resImg->fetch_assoc();
            if ($imgRow && isset($imgRow['Bilde_Fil']) && trim((string)$imgRow['Bilde_Fil']) !== '') {
                // Sørg for å fjerne/demme ekstra skråstreker og konstruere full URL.
                $base = rtrim((string)$imgRow['URL_Bane'], '/');
                $file = ltrim((string)$imgRow['Bilde_Fil'], '/');
                $imageSrc = $base . '/' . $file;
            }
            $resImg->free();
        }
        $stmt->close();
    }

    // Beregn relativ sti for bakgrunnsbildet. Siden denne filen ligger i /user,
    // må vi gå ett nivå opp dersom $imageSrc starter med '/'.
    $imageSrcRel = (substr($imageSrc, 0, 1) === '/') ? ('..' . $imageSrc) : $imageSrc;

    // --- DigitaltMuseum-lenker (ALLE for FartNavn_ID)
    $dimuList = [];
    $stmt = $conn->prepare(
        "SELECT DIMUkode, COALESCE(Motiv,'') AS Motiv
         FROM tblxdigmuseum
         WHERE FartNavn_ID = ? AND COALESCE(DIMUkode,'') <> ''
         ORDER BY ID DESC"
    );
    $stmt->bind_param("i", $navn_id);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) {
        $kode  = trim((string)$row['DIMUkode']);
        $motiv = (string)$row['Motiv'];
        $dimuList[] = [
            'kode'  => $kode,
            'motiv' => $motiv,
            'url'   => 'https://digitaltmuseum.no/' . $kode
        ];
    }
    $stmt->close();

    // --- Navnehistorikk (for hele objektet), med TypeFork + FartNavn og Year/Mnd (MM)
    $navnehist = [];
    $stmt = $conn->prepare(
        "SELECT t.FartTid_ID, t.YearTid, t.MndTid,
                fn.FartNavn,
                zft.TypeFork
         FROM tblfarttid t
         LEFT JOIN tblfartnavn   fn  ON fn.FartNavn_ID   = t.FartNavn_ID
         LEFT JOIN tblfartspes   fs  ON fs.FartSpes_ID   = t.FartSpes_ID
         LEFT JOIN tblzfarttype  zft ON zft.FartType_ID  = fs.FartType_ID
         WHERE t.FartObj_ID = ?
         ORDER BY COALESCE(t.YearTid,0), COALESCE(t.MndTid,0), t.FartTid_ID"
    );
    $stmt->bind_param("i", $obj_id);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($r = $res->fetch_assoc()) {
        $mm  = (int)$r['MndTid'];
        $mmS = $mm > 0 ? str_pad((string)$mm, 2, '0', STR_PAD_LEFT) : '00';
        $r['Tidspunkt'] = (string)val($r, 'YearTid', '') . '/' . $mmS;
        $prefix = trim((string)val($r,'TypeFork',''));
        $r['NavnKomp'] = ($prefix !== '' ? $prefix.' ' : '') . trim((string)val($r,'FartNavn',''));
        $navnehist[] = $r;
    }
    $stmt->close();

    // --- Øvrige lenker (tblxfartlink) via FartNavn_ID
    $fartLinks = [];
    $stmt = $conn->prepare(
        "SELECT COALESCE(LinkType,'') AS LinkType,
                COALESCE(LinkInnh,'') AS LinkInnh,
                Link,
                COALESCE(SerNo, 9999) AS SortNo
         FROM tblxfartlink
         WHERE FartNavn_ID = ? AND COALESCE(Link,'') <> ''
         ORDER BY SortNo, LinkType, LinkInnh"
    );
    $stmt->bind_param("i", $navn_id);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) {
        $fartLinks[] = $row;
    }
    $stmt->close();

?>
<?php include __DIR__ . '/../includes/header.php'; ?>
<?php include __DIR__ . '/../includes/menu.php'; ?>

    <!-- Hero image for fartøydetaljer page -->
    <div class="container">
        <section class="hero" style="background-image:url('<?= h($imageSrcRel) ?>'); background-size:cover; background-position:center;">
            <div class="hero-overlay"></div>
        </section>
    </div>

    <div class="container">
        <h1 style="text-align:center;">Fartøydetaljer</h1>

        <!-- Hovedinfo-boks -->
        <div class="card centered-card" style="padding:1rem; margin-bottom:1rem; text-align:center;">
            <?php
                // Kombiner TypeFork og FartNavn for visningen (TypeFork + ' ' + navn)
                $navn = val($main, 'FartNavn', '(ukjent navn)');
                $displayName = $navn;
                if ($typeFork !== '') {
                    $displayName = trim($typeFork . ' ' . $navn);
                }
            ?>
            <div style="display:flex; justify-content:center;">
                <h2 style="margin-top:0; font-size:1.8rem; font-weight:600;">
                    <?= h($displayName) ?>
                </h2>
            </div>

            <!-- Merk: .meta er flex; text-align påvirker ikke plasseringen av barna.
                Vi må derfor sentrere selve flex-linjen med justify-content:center og 
                gjerne align-items:center for vertikal justering. -->
            <div class="meta"
                style="display:flex; gap:1.5rem; flex-wrap:wrap; justify-content:center; align-items:center;">

                <?php if (val($main,'NasjonNavn','') !== ''): ?>
                    <div style="text-align:center;"><strong>Nasjon:</strong> <?= h($main['NasjonNavn']) ?></div>
                <?php endif; ?>

                <?php if (val($main,'RegHavn','') !== ''): ?>
                    <div style="text-align:center;"><strong>Reg.havn:</strong> <?= h($main['RegHavn']) ?></div>
                <?php endif; ?>

                <?php if (val($main,'Rederi','') !== ''): ?>
                    <div style="text-align:center;"><strong>Rederi:</strong> <?= h($main['Rederi']) ?></div>
                <?php endif; ?>

                <?php if (val($main,'Kallesignal','') !== ''): ?>
                    <div style="text-align:center;"><strong>Kallesignal:</strong> <?= h($main['Kallesignal']) ?></div>
                <?php endif; ?>

                <?php if (val($main,'MMSI','') !== ''): ?>
                    <div style="text-align:center;"><strong>MMSI:</strong> <?= h($main['MMSI']) ?></div>
                <?php endif; ?>

                <?php if (val($main,'Fiskerinr','') !== ''): ?>
                    <div style="text-align:center;"><strong>Fiskerinr:</strong> <?= h($main['Fiskerinr']) ?></div>
                <?php endif; ?>

                <div style="text-align:center; font-size: 9px">Objekt-ID:<?= (int)$main['FartObj_ID'] ?></div>
            </div>
        </div>

        <!-- Lenkelister-boks (alltid viser overskriftene) -->
        <div class="card" style="padding:1rem; margin-bottom:1rem;">
            <h3 style="margin:.25rem 0;">Digitalt Museum</h3>
    <table class="table" style="width:100%; border-collapse:collapse; margin-bottom:.75rem;">
        <thead>
            <tr>
                <th style="text-align:left; padding:.35rem .5rem;">Kode</th>
                <th style="text-align:left; padding:.35rem .5rem;">Motiv</th>
                <th style="text-align:left; padding:.35rem .5rem; width:1%;">Åpne</th>
            </tr>
        </thead>
        <tbody class="dm-links">
        <?php foreach ($dimuList as $dm): ?>
            <tr data-open="<?= h($dm['url']) ?>" style="cursor:pointer;">
                <td style="padding:.35rem .5rem; border-top:1px solid #ddd;"><?= h($dm['kode']) ?></td>
                <td style="padding:.35rem .5rem; border-top:1px solid #ddd;"><?= h($dm['motiv']) ?></td>
                <td style="padding:.35rem .5rem; border-top:1px solid #ddd;">
                    <a class="btn" href="<?= h($dm['url']) ?>" target="_blank" rel="noopener noreferrer">
                        Åpne bilde med DM koden
                    </a>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>

            <h3 style="margin:.25rem 0;">Andre lenker</h3>
            <table class="table" style="width:100%; border-collapse:collapse;">
                <thead>
                    <tr>
                        <th style="text-align:left; padding:.35rem .5rem;">Type / innhold</th>
                        <th style="text-align:left; padding:.35rem .5rem; width:1%;">Åpne</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($fartLinks as $lk): ?>
                    <?php
                        $label = ($lk['LinkType'] !== '' ? $lk['LinkType'] : 'Lenke')
                               . ($lk['LinkInnh'] !== '' ? ': ' . $lk['LinkInnh'] : '');
                    ?>
                    <tr data-open="<?= h($lk['Link']) ?>" style="cursor:pointer;">
                        <td style="padding:.35rem .5rem; border-top:1px solid #ddd;"><?= h($label) ?></td>
                        <td style="padding:.35rem .5rem; border-top:1px solid #ddd;">
                            <a class="btn" href="<?= h($lk['Link']) ?>" target="_blank" rel="noopener noreferrer">Åpne</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <!-- Navnehistorikk-boks -->
        <div class="card" style="padding:1rem; margin-bottom:1rem;">
            <h3 style="text-align:center; margin-top:0;">Navnehistorikk</h3>
            <table class="table" style="width:100%; border-collapse:collapse;">
                <thead>
                    <tr>
                        <th style="text-align:left; padding:.35rem .5rem;">Navn</th>
                        <th style="text-align:left; padding:.35rem .5rem;">Tidspunkt</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($navnehist as $row): ?>
                    <tr>
                        <td style="padding:.35rem .5rem; border-top:1px solid #ddd;"><?= h($row['NavnKomp']) ?></td>
                        <td style="padding:.35rem .5rem; border-top:1px solid #ddd;"><?= h($row['Tidspunkt']) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <!-- Tekniske data: UNDER Navnehistorikk, midtstilt, med BASE_URL -->
        <div class="actions" style="margin:1rem 0 2rem; display:flex; justify-content:center;">
            <?php if (!empty($main['FartSpes_ID'])): ?>
                <a class="btn" href="<?= h(BASE_URL) ?>/user/fartoyspes.php?spes_id=<?= (int)$main['FartSpes_ID'] ?>">
                    Tekniske data
                </a>
            <?php else: ?>
                <span class="btn" style="opacity:.5; pointer-events:none;">Tekniske data (mangler)</span>
            <?php endif; ?>
        </div>
    </div>

    <div class="actions" style="margin:1rem 0 2rem;display:flex; justify-content:center;">
        <a class="btn" href="<?= h(BASE_URL) ?>/user/fartoy_navn_sok.php">← Tilbake</a>
    </div>

<?php include __DIR__ . '/../includes/footer.php'; ?>

    <script>
    // Dobbelklikk: åpne lenke ved dblclick på tabellradene
    document.querySelectorAll('tbody.dm-links tr[data-open], div.card tbody tr[data-open]').forEach(function(tr){
        tr.addEventListener('dblclick', function(){
            var url = tr.getAttribute('data-open');
            if (url) window.open(url, '_blank', 'noopener');
        });
    });
    </script>