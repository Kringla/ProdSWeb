<?php
// user/fartoydetaljer.php

require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/auth.php'; // for meny/rolle

// helpers
if (!function_exists('h')) {
    function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
}
function val($arr, $key, $def=''){ return isset($arr[$key]) ? $arr[$key] : $def; }

// Parametre
$obj_id  = isset($_GET['obj_id']) ? (int)$_GET['obj_id'] : 0;
// Nytt schema bruker FartTid_ID i lenker/bilder; støtt både tid_id og historisk navn_id
$tid_id = 0;
if (isset($_GET['tid_id'])) {
    $tid_id = (int)$_GET['tid_id'];
} elseif (isset($_GET['navn_id'])) {
    $tid_id = (int)$_GET['navn_id'];
}

if ($obj_id <= 0 || $tid_id <= 0) {
    http_response_code(400);
    echo "Mangler eller ugyldige parametre: obj_id og tid_id må være > 0.";
    exit;
}

// Hent valgt navneoppføring (tblFartTid) + typeforkortelse og nasjonsnavn
$main = null;
$stmt = $conn->prepare(
    "SELECT t.*,
            zft.TypeFork,
            zn.Nasjon AS NasjonNavn
     FROM tblfarttid t
     LEFT JOIN tblzfarttype zft ON zft.FartType_ID = t.FartType_ID
     LEFT JOIN tblznasjon   zn  ON zn.Nasjon_ID    = t.Nasjon_ID
     WHERE t.FartTid_ID = ?
     LIMIT 1"
);
if ($stmt) {
    $stmt->bind_param("i", $tid_id);
    $stmt->execute();
    $res = $stmt->get_result();
    $main = $res->fetch_assoc();
    $stmt->close();
}
if (!$main) {
    echo "Fant ingen detaljer for angitt objekt/tid.";
    exit;
}

// Hent TypeFork (ev. via spesifikasjon om den mangler direkte)
$typeFork = '';
if (isset($main['TypeFork'])) {
    $typeFork = trim((string)$main['TypeFork']);
}
if ($typeFork === '') {
    $stmt = $conn->prepare(
        "SELECT zft.TypeFork
         FROM tblfarttid t
         LEFT JOIN tblfartspes fs ON fs.FartSpes_ID = t.FartSpes_ID
         LEFT JOIN tblzfarttype zft ON zft.FartType_ID = fs.FartType_ID
         WHERE t.FartTid_ID = ?
         LIMIT 1"
    );
    if ($stmt) {
        $stmt->bind_param('i', $tid_id);
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
}

// Bilde for denne navneoppføringen (tblxNMMFoto) – koblet på FartTid_ID i nytt schema
$imageSrc = '/assets/img/skip/fartoydetaljer_1.jpg';
$stmt = $conn->prepare(
    "SELECT URL_Bane, Bilde_Fil
     FROM tblxnmmfoto
     WHERE FartTid_ID = ? AND COALESCE(Bilde_Fil,'') <> ''
     ORDER BY ID DESC
     LIMIT 1"
);
if ($stmt) {
    $stmt->bind_param('i', $tid_id);
    $stmt->execute();
    $resImg = $stmt->get_result();
    if ($resImg) {
        $imgRow = $resImg->fetch_assoc();
        if ($imgRow && trim((string)$imgRow['Bilde_Fil']) !== '') {
            $base = rtrim((string)$imgRow['URL_Bane'], '/');
            $file = ltrim((string)$imgRow['Bilde_Fil'], '/');
            $imageSrc = $base . '/' . $file;
        }
        $resImg->free();
    }
    $stmt->close();
}
// relativ sti for hero-bakgrunn (fila ligger i /user)
$imageSrcRel = (substr($imageSrc, 0, 1) === '/') ? ('..' . $imageSrc) : $imageSrc;

// DigitaltMuseum-lenker (tblxDigMuseum) for denne navneoppføringen
$dimuList = [];
$stmt = $conn->prepare(
    "SELECT DIMUkode, COALESCE(Motiv,'') AS Motiv
     FROM tblxdigmuseum
     WHERE FartTid_ID = ? AND COALESCE(DIMUkode,'') <> ''
     ORDER BY ID DESC"
);
if ($stmt) {
    $stmt->bind_param("i", $tid_id);
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
}

// Navnehistorikk for hele objektet
$navnehist = [];
$stmt = $conn->prepare(
    "SELECT t.FartTid_ID,
            t.YearTid,
            t.MndTid,
            t.FartNavn,
            COALESCE(t.FartType_ID, fs.FartType_ID) AS FartType_ID,
            zft.TypeFork,
            t.Rederi,
            t.RegHavn,
            zn.Nasjon
     FROM tblfarttid t
     LEFT JOIN tblfartspes  fs  ON fs.FartSpes_ID  = t.FartSpes_ID
     LEFT JOIN tblzfarttype zft ON zft.FartType_ID = COALESCE(t.FartType_ID, fs.FartType_ID)
     LEFT JOIN tblznasjon   zn  ON zn.Nasjon_ID    = t.Nasjon_ID
     WHERE t.FartObj_ID = ?
     ORDER BY COALESCE(t.YearTid,0),
              COALESCE(t.MndTid,0),
              t.FartTid_ID"
);
if ($stmt) {
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
}

// Øvrige lenker (tblxFartLink) for denne navneoppføringen
$fartLinks = [];
$stmt = $conn->prepare(
    "SELECT COALESCE(LinkType,'') AS LinkType,
            COALESCE(LinkInnh,'') AS LinkInnh,
            Link,
            COALESCE(SerNo, 9999) AS SortNo
     FROM tblxfartlink
     WHERE FartTid_ID = ? AND COALESCE(Link,'') <> ''
     ORDER BY SortNo, LinkType, LinkInnh"
);
if ($stmt) {
    $stmt->bind_param("i", $tid_id);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) {
        $fartLinks[] = $row;
    }
    $stmt->close();
}

// Hent objektdata (tblFartObj) med verftnavn for Leverandør og Skrogbygger,
// og STROK-navn fra tblzStroket (via StroketID)
$objRow = null;
$objStmt = $conn->prepare(
    "SELECT o.*,
            CONCAT_WS(', ', v1.VerftNavn, v1.Sted) AS LeverandorNavn,
            CONCAT_WS(', ', v2.VerftNavn, v2.Sted) AS SkrogbyggerNavn,
            zs.Strok AS StroketNavn
     FROM tblfartobj o
     LEFT JOIN tblverft    v1 ON v1.Verft_ID     = o.LeverID
     LEFT JOIN tblverft    v2 ON v2.Verft_ID     = o.SkrogID
     LEFT JOIN tblzstroket zs ON zs.Stroket_ID   = o.StroketID
     WHERE o.FartObj_ID = ?
     LIMIT 1"
);
if ($objStmt) {
    $objStmt->bind_param('i', $obj_id);
    $objStmt->execute();
    $objRes = $objStmt->get_result();
    if ($objRes) { $objRow = $objRes->fetch_assoc(); $objRes->free(); }
    $objStmt->close();
}

// Utled visningsfelter
$mmCur   = (int)val($main,'MndTid',0);
$mmCurS  = $mmCur > 0 ? str_pad((string)$mmCur, 2, '0', STR_PAD_LEFT) : '00';
$tidStr  = (string)val($main,'YearTid','') . '/' . $mmCurS;

$navn    = val($main,'FartNavn','(ukjent navn)');
$visNavn = $typeFork !== '' ? trim($typeFork.' '.$navn) : $navn;

?>
<?php include __DIR__ . '/../includes/header.php'; ?>
<?php include __DIR__ . '/../includes/menu.php'; ?>

<!-- Hero image -->
<div class="container">
    <section class="hero" style="background-image:url('<?= h($imageSrcRel) ?>'); background-size:cover; background-position:center;">
        <div class="hero-overlay"></div>
    </section>
</div>

<div class="container">
    <h1 style="text-align:center;">Fartøydetaljer</h1>

    <!-- Hovedinfo-boks -->
    <div class="card mb-3" style="padding:1rem;">
      <h2 class="h4">Objektinformasjon</h2>
      <table class="table compact table-sm table-borderless align-middle">
        <tbody>
          <?php if (!empty($objRow['Bygget'])): ?>
          <tr>
            <th class="text-end" style="width:30%">Bygget (år)</th>
            <td><?= h($objRow['Bygget']) ?></td>
          </tr>
          <?php endif; ?>

          <tr>
            <th class="text-end" style="width:30%">Navn gitt ved bygging</th>
            <td><?= h($objRow['NavnObj'] ?? '') ?></td>
          </tr>

          <?php if ($typeFork !== ''): ?>
          <tr>
            <th class="text-end">Fartøystype</th>
            <td><?= h($typeFork) ?></td>
          </tr>
          <?php endif; ?>

          <?php if (!empty($objRow['IMO'])): ?>
          <tr>
            <th class="text-end">IMO</th>
            <td><?= h($objRow['IMO']) ?></td>
          </tr>
          <?php endif; ?>

          <?php if (!empty($objRow['Kontrahert'])): ?>
          <tr>
            <th class="text-end">Kontrahert</th>
            <td><?= h($objRow['Kontrahert']) ?></td>
          </tr>
          <?php endif; ?>

          <?php if (!empty($objRow['Kjolstrukket'])): ?>
          <tr>
            <th class="text-end">Kjølstrukket</th>
            <td><?= h($objRow['Kjolstrukket']) ?></td>
          </tr>
          <?php endif; ?>

          <?php if (!empty($objRow['Sjosatt'])): ?>
          <tr>
            <th class="text-end">Sjøsatt</th>
            <td><?= h($objRow['Sjosatt']) ?></td>
          </tr>
          <?php endif; ?>

          <?php if (!empty($objRow['Levert'])): ?>
          <tr>
            <th class="text-end">Levert</th>
            <td><?= h($objRow['Levert']) ?></td>
          </tr>
          <?php endif; ?>

          <?php
            $levNavn = trim((string)val($objRow,'LeverandorNavn',''));
            $levID   = trim((string)val($objRow,'LeverID',''));
          ?>
          <?php if ($levNavn !== '' || $levID !== ''): ?>
          <tr>
            <th class="text-end">Leverandør</th>
            <td>
              <?= h($levNavn !== '' ? $levNavn : '') ?>
              <?php if ($levNavn === '' && $levID !== ''): ?>
                (ID: <?= h($levID) ?>)
              <?php endif; ?>
            </td>
          </tr>
          <?php endif; ?>

          <?php if (!empty($objRow['ByggeNr'])): ?>
          <tr>
            <th class="text-end">Byggenr</th>
            <td><?= h($objRow['ByggeNr']) ?></td>
          </tr>
          <?php endif; ?>

          <?php
            $skrogNavn = trim((string)val($objRow,'SkrogbyggerNavn',''));
            $skrogID   = trim((string)val($objRow,'SkrogID',''));
          ?>
          <?php if ($skrogNavn !== '' || $skrogID !== ''): ?>
          <tr>
            <th class="text-end">Skrogbygger</th>
            <td>
              <?= h($skrogNavn !== '' ? $skrogNavn : '') ?>
              <?php if ($skrogNavn === '' && $skrogID !== ''): ?>
                (ID: <?= h($skrogID) ?>)
              <?php endif; ?>
            </td>
          </tr>
          <?php endif; ?>

          <?php if (!empty($objRow['BnrSkrog'])): ?>
          <tr>
            <th class="text-end">BnrSkrog</th>
            <td><?= h($objRow['BnrSkrog']) ?></td>
          </tr>
          <?php endif; ?>

          <?php if (!empty($objRow['StroketYear'])): ?>
          <tr>
            <th class="text-end">StrøketYear</th>
            <td><?= h($objRow['StroketYear']) ?></td>
          </tr>
          <?php endif; ?>

          <?php
            $strokNavn = trim((string)val($objRow,'StroketNavn',''));
            $strokID   = trim((string)val($objRow,'StroketID',''));
          ?>
          <?php if ($strokNavn !== '' || $strokID !== ''): ?>
          <tr>
            <th class="text-end">Strøket grunnet</th>
            <td>
              <?= h($strokNavn !== '' ? $strokNavn : '') ?>
              <?php if ($strokNavn === '' && $strokID !== ''): ?>
                (ID: <?= h($strokID) ?>)
              <?php endif; ?>
            </td>
          </tr>
          <?php endif; ?>

          <?php if (!empty($objRow['Historikk'])): ?>
          <tr>
            <th class="text-end">Historikk</th>
            <td><?= nl2br(h($objRow['Historikk'])) ?></td>
          </tr>
          <?php endif; ?>

          <?php if (!empty($objRow['ObjNotater'])): ?>
          <tr>
            <th class="text-end">ObjNotater</th>
            <td><?= nl2br(h($objRow['ObjNotater'])) ?></td>
          </tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>

    <div class="card mb-3" style="padding:1rem;">
      <h2 class="h4">Navneoppføring</h2>
      <table class="table compact table-sm table-borderless align-middle">
        <tbody>
          <tr>
            <th class="text-end" style="width:30%">Navn</th>
            <td><?= h($visNavn) ?></td>
          </tr>
          <tr>
            <th class="text-end">Tidspunkt</th>
            <td><?= h($tidStr) ?></td>
          </tr>

          <?php if (val($main,'NasjonNavn','') !== ''): ?>
          <tr>
            <th class="text-end">Nasjon</th>
            <td><?= h($main['NasjonNavn']) ?></td>
          </tr>
          <?php endif; ?>

          <?php if (val($main,'RegHavn','') !== ''): ?>
          <tr>
            <th class="text-end">Reg.havn</th>
            <td><?= h($main['RegHavn']) ?></td>
          </tr>
          <?php endif; ?>

          <?php if (val($main,'Rederi','') !== ''): ?>
          <tr>
            <th class="text-end">Rederi</th>
            <td><?= h($main['Rederi']) ?></td>
          </tr>
          <?php endif; ?>

          <?php if (val($main,'Kallesignal','') !== ''): ?>
          <tr>
            <th class="text-end">Kallesignal</th>
            <td><?= h($main['Kallesignal']) ?></td>
          </tr>
          <?php endif; ?>

          <?php if (val($main,'PennantTiln','') !== ''): ?>
          <tr>
            <th class="text-end">Tilnavn/Pennant nr</th>
            <td><?= h($main['PennantTiln']) ?></td>
          </tr>
          <?php endif; ?>

          <?php if (val($main,'MMSI','') !== ''): ?>
          <tr>
            <th class="text-end">MMSI</th>
            <td><?= h($main['MMSI']) ?></td>
          </tr>
          <?php endif; ?>

          <?php if (val($main,'Fiskerinr','') !== ''): ?>
          <tr>
            <th class="text-end">Fiskerinr</th>
            <td><?= h($main['Fiskerinr']) ?></td>
          </tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
    <!-- Navnehistorikk-boks -->

    <h3 style="margin:.25rem 0 0.5rem;">Navnehistorikk (for objektet)</h3>
    <div class="card centered-card" style="padding:1rem;">
      <div class="table-wrap center outline-brand">
        <table class="table fit tight">
          <thead>
            <tr>
              <th style="padding:.35rem .5rem;">År/Mnd</th>
              <th style="padding:.35rem .5rem;">Navn</th>
              <th style="padding:.35rem .5rem;">Nasjon</th>
              <th style="padding:.35rem .5rem;">Reg.havn</th>
              <th style="padding:.35rem .5rem;">Rederi</th>
              <th style="padding:.35rem .5rem;">Vis</th>
            </tr>
          </thead>
          <tbody>
          <?php foreach ($navnehist as $n): ?>
            <tr>
              <td style="padding:.35rem .5rem;"><?= h($n['Tidspunkt']) ?></td>
              <td style="padding:.35rem .5rem;"><?= h($n['NavnKomp']) ?></td>
              <td style="padding:.35rem .5rem;"><?= h($n['Nasjon'] ?? '') ?></td>
              <td style="padding:.35rem .5rem;"><?= h($n['RegHavn'] ?? '') ?></td>
              <td style="padding:.35rem .5rem;"><?= h($n['Rederi'] ?? '') ?></td>
              <td style="padding:.35rem .5rem;">
                <a class="btn-small" href="<?= h(BASE_URL) ?>/user/fartoydetaljer.php?obj_id=<?= (int)$obj_id ?>&tid_id=<?= (int)$n['FartTid_ID'] ?>">Vis</a>
              </td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>

    <h3 style="margin:1.25rem 0 0;">DigitaltMuseum</h3>
    <div class="table-wrap outline-brand">
      <table class="table" style="width:100%; border-collapse:collapse;">
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
              <a class="btn" href="<?= h($dm['url']) ?>" target="_blank" rel="noopener noreferrer">Åpne bilde med DM koden</a>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>

    <h3 style="margin:2rem 0 0;">Andre lenker</h3>
    <div class="table-wrap outline-brand">
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

    <!-- Tekniske data-knapp -->
    <div class="actions" style="margin:1rem 0 2rem; display:flex; justify-content:center;">
      <?php if (!empty($main['FartSpes_ID'])): ?>
        <a class="btn" href="<?= h(BASE_URL) ?>/user/fartoyspes.php?spes_id=<?= (int)$main['FartSpes_ID'] ?>">Tekniske data</a>
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
// Dobbeltklikk: åpne lenke ved dblclick på rad
document.querySelectorAll('tbody.dm-links tr[data-open], .table-wrap tbody tr[data-open]').forEach(function(tr){
  tr.addEventListener('dblclick', function(){
    var url = tr.getAttribute('data-open');
    if (url) window.open(url, '_blank', 'noopener');
  });
});
</script>
