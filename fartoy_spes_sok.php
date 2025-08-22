<?php
    // New search page for vessel specifications (tblFartSpes)
    // Based on user/fartoy_nat.php but expanded to support multiple filters.

    /**
     * Denne filen lar en bruker søke i spesifikasjonstabellen tblFartSpes på ett eller flere
     * kriterier. Hver av søkefeltene knyttet til parametertabeller viser en lesbar
     * tekst (for eksempel TypeFunksjon, DriftMiddel, MotorDetalj etc.) i nedtrekkslisten,
     * men lagrer ID‑verdien som parameter. FartObj_ID og FartSpes_ID kan filtreres via
     * numeriske felt. Resultatlisten gir en oversikt over matchende spesifikasjoner
     * sammen med den tilhørende fartøynavnet, og lenker inn til fartøydetaljer via
     * fartoydetaljer.php.
     */

    require_once __DIR__ . '/../includes/bootstrap.php';
    require_once __DIR__ . '/../includes/auth.php'; // for meny/rolle

    // Hjelpefunksjoner (disse er definert i andre filer men dupliseres her som fall‑back)
    if (!function_exists('h')) {
        /**
         * HTML‑escape av streng.
         *
         * @param mixed $s
         * @return string
         */
        function h($s) {
            return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
        }
    }
    if (!function_exists('val')) {
        /**
         * Returner verdi fra array hvis den finnes, ellers default.
         *
         * @param array  $arr
         * @param string $key
         * @param mixed  $def
         * @return mixed
         */
        function val($arr, $key, $def = '') {
            return isset($arr[$key]) ? $arr[$key] : $def;
        }
    }

    // --- Hent inn filtreringsparametere fra URL (0 betyr "ingen valgt")
    $fartMatId   = isset($_GET['fartmat_id'])   ? (int)$_GET['fartmat_id']   : 0;
    $fartTypeId  = isset($_GET['farttype_id'])  ? (int)$_GET['farttype_id']  : 0;
    $fartFunkId  = isset($_GET['fartfunk_id'])  ? (int)$_GET['fartfunk_id']  : 0;
    $fartSkrogId = isset($_GET['fartskrog_id']) ? (int)$_GET['fartskrog_id'] : 0;
    $fartDriftId = isset($_GET['fartdrift_id']) ? (int)$_GET['fartdrift_id'] : 0;
    $fartMotorId = isset($_GET['fartmotor_id']) ? (int)$_GET['fartmotor_id'] : 0;
    $fartRiggId  = isset($_GET['fartrigg_id'])  ? (int)$_GET['fartrigg_id']  : 0;
    $fartKlasseId= isset($_GET['fartklasse_id'])? (int)$_GET['fartklasse_id']: 0;
    // FartObj_ID og FartSpes_ID skal ikke benyttes som søkekriterier og beholdes derfor
    // kun internt til lenkegenerering via SQL‑spørringen. De leses ikke fra URL.

    // Kjør søk bare hvis bruker har angitt noen parametre (tom GET betyr ingen søk)
    $doSearch = ($_GET !== []);

    // --- Forhåndslast alle parametere til nedtrekkslister
    /**
     * Hent parametre fra en tabell med id og tekst.
     * @param mysqli $conn    Databasetilkobling
     * @param string $table   Tabellnavn
     * @param string $idCol   Navn på ID‑kolonne
     * @param string $txtCol  Navn på tekstkolonne
     * @return array<int,array<string,mixed>>
     */
    function getParamList($conn, $table, $idCol, $txtCol) {
        $list = [];
        $sql = "SELECT $idCol AS id, $txtCol AS txt FROM $table WHERE $txtCol IS NOT NULL AND $txtCol <> '' ORDER BY $txtCol";
        if ($res = $conn->query($sql)) {
            while ($row = $res->fetch_assoc()) {
                $list[] = $row;
            }
            $res->free();
        }
        return $list;
    }

    // I henhold til PKD v6 bruker vi bestemte feltnavn fra parametertabellene
    // Hent lister fra parametertabellene. Kolonnenavn må reflektere schema v6.
    $listFartMat    = getParamList($conn, 'tblzfartmat',    'FartMat_ID',    'MatFork');     // Materiale/MatFork
    $listFartType   = getParamList($conn, 'tblzfarttype',   'FartType_ID',   'FartType');    // FartType, ikke 'type'
    $listFartFunk   = getParamList($conn, 'tblzfartfunk',   'FartFunk_ID',   'TypeFunksjon');
    $listFartSkrog  = getParamList($conn, 'tblzfartskrog',  'FartSkrog_ID',  'TypeSkrog');
    $listFartDrift  = getParamList($conn, 'tblzfartdrift',  'FartDrift_ID',  'DriftMiddel');
    $listFartMotor  = getParamList($conn, 'tblzfartmotor',  'FartMotor_ID',  'MotorDetalj');
    $listFartRigg   = getParamList($conn, 'tblzfartrigg',   'FartRigg_ID',   'RiggDetalj');
    $listFartKlasse = getParamList($conn, 'tblzfartklasse', 'FartKlasse_ID', 'KlasseNavn');  // bruker KlasseNavn fra schema v6

    // --- Kjør søk ved behov
    $rows = [];
    if ($doSearch) {
        // Bygg SQL med korrelert subquery for å hente seneste FartTid for hver spes
        $sql = "
            SELECT
              fs.FartSpes_ID,
              fs.FartObj_ID,
              fs.Lengde,
              fs.Bredde,
              fs.Dypg,
              fn.FartNavn,
              curr.FartNavn_ID AS FartNavn_ID,
              zft.FartType     AS FartType,
              zff.TypeFunksjon AS FartFunk,
              zfs.TypeSkrog    AS FartSkrog,
              zfd.DriftMiddel  AS FartDrift,
              zfm.MotorDetalj  AS FartMotor,
              zfr.RiggDetalj   AS FartRigg,
              zfk.KlasseNavn   AS FartKlasse,
              COALESCE(fs.MotorDetalj, zfm.MotorDetalj) AS MotorDetalj
            FROM tblfartspes AS fs
            LEFT JOIN tblzfartmat    AS zfm2 ON zfm2.FartMat_ID    = fs.FartMat_ID
            LEFT JOIN tblzfarttype   AS zft  ON zft.FartType_ID    = fs.FartType_ID
            LEFT JOIN tblzfartfunk   AS zff  ON zff.FartFunk_ID    = fs.FartFunk_ID
            LEFT JOIN tblzfartskrog  AS zfs  ON zfs.FartSkrog_ID   = fs.FartSkrog_ID
            LEFT JOIN tblzfartdrift  AS zfd  ON zfd.FartDrift_ID   = fs.FartDrift_ID
            LEFT JOIN tblzfartmotor  AS zfm  ON zfm.FartMotor_ID   = fs.FartMotor_ID
            LEFT JOIN tblzfartrigg   AS zfr  ON zfr.FartRigg_ID    = fs.FartRigg_ID
            LEFT JOIN tblzfartklasse AS zfk  ON zfk.FartKlasse_ID  = fs.FartKlasse_ID
            LEFT JOIN tblfarttid AS curr ON curr.FartTid_ID = (
                SELECT t2.FartTid_ID
                FROM tblfarttid t2
                WHERE t2.FartSpes_ID = fs.FartSpes_ID
                ORDER BY COALESCE(t2.YearTid,0) DESC,
                         COALESCE(t2.MndTid,0) DESC,
                         t2.FartTid_ID DESC
                LIMIT 1
            )
            LEFT JOIN tblfartnavn AS fn ON fn.FartNavn_ID = curr.FartNavn_ID
            WHERE 1=1
              AND (? = 0 OR fs.FartMat_ID    = ?)
              AND (? = 0 OR fs.FartType_ID   = ?)
              AND (? = 0 OR fs.FartFunk_ID   = ?)
              AND (? = 0 OR fs.FartSkrog_ID  = ?)
              AND (? = 0 OR fs.FartDrift_ID  = ?)
              AND (? = 0 OR fs.FartMotor_ID  = ?)
              AND (? = 0 OR fs.FartRigg_ID   = ?)
              AND (? = 0 OR fs.FartKlasse_ID = ?)
            ORDER BY fn.FartNavn ASC, fs.FartSpes_ID ASC
            LIMIT 200
        ";
        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            die('Prepare feilet: ' . $conn->error);
        }
        // Bind parametre i samme rekkefølge som de forekommer i SQL
        $stmt->bind_param(
            // 16 heltall: 2 for hver av de 8 filtrene
            'iiiiiiiiiiiiiiii',
            $fartMatId,   $fartMatId,
            $fartTypeId,  $fartTypeId,
            $fartFunkId,  $fartFunkId,
            $fartSkrogId, $fartSkrogId,
            $fartDriftId, $fartDriftId,
            $fartMotorId, $fartMotorId,
            $fartRiggId,  $fartRiggId,
            $fartKlasseId,$fartKlasseId
        );
        if (!$stmt->execute()) {
            die('Execute feilet: ' . $stmt->error);
        }
        $result = $stmt->get_result();
        if ($result) {
            $rows = $result->fetch_all(MYSQLI_ASSOC);
            $result->free();
        }
        $stmt->close();
    }

    // Angi navn på denne filen for tilbakestilling av filter (brukes i frontend)
    $currentFile = basename(__FILE__);
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
                $base = rtrim((string)($imgRow['URL_Bane'] ?? '/assets/img/skip'), '/');
                $file = basename((string)$imgRow['Bilde_Fil']); // dropp path-fragmenter
                $imgCandidate = $base . '/' . $file;
            }
            // Alternativ kilde: $main['Bilde_Fil'] dersom du bruker den i siden:
            elseif (!empty($main['Bilde_Fil'])) {
                $imgCandidate = '/assets/img/skip/' . basename((string)$main['Bilde_Fil']);
            }
            // 2) Garantert fallback:
            if (!$imgCandidate) {
                $imgCandidate = '/assets/img/skip/placeholder.jpg';
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
        <h1 style="text-align:center;">Søk fartøyspesifikasjoner</h1>
        <form method="get" class="form-inline" style="margin-bottom:1rem; display:flex; flex-wrap:wrap; gap:0.5rem; justify-content:flex-start;">
            <!-- FartMat (Materiale) -->
            <label for="fartmat_id">Materiale</label>
            <select id="fartmat_id" name="fartmat_id">
                <option value="0"<?= $fartMatId === 0 ? ' selected' : '' ?>>Alle</option>
                <?php foreach ($listFartMat as $r): ?>
                    <option value="<?= (int)$r['id'] ?>"<?= $fartMatId === (int)$r['id'] ? ' selected' : '' ?>><?= h($r['txt']) ?></option>
                <?php endforeach; ?>
            </select>
            <!-- FartType (Type) -->
            <label for="farttype_id">Type</label>
            <select id="farttype_id" name="farttype_id">
                <option value="0"<?= $fartTypeId === 0 ? ' selected' : '' ?>>Alle</option>
                <?php foreach ($listFartType as $r): ?>
                    <option value="<?= (int)$r['id'] ?>"<?= $fartTypeId === (int)$r['id'] ? ' selected' : '' ?>><?= h($r['txt']) ?></option>
                <?php endforeach; ?>
            </select>
            <!-- FartFunk (Funksjon) -->
            <label for="fartfunk_id">Funksjon</label>
            <select id="fartfunk_id" name="fartfunk_id">
                <option value="0"<?= $fartFunkId === 0 ? ' selected' : '' ?>>Alle</option>
                <?php foreach ($listFartFunk as $r): ?>
                    <option value="<?= (int)$r['id'] ?>"<?= $fartFunkId === (int)$r['id'] ? ' selected' : '' ?>><?= h($r['txt']) ?></option>
                <?php endforeach; ?>
            </select>
            <!-- FartSkrog -->
            <label for="fartskrog_id">Skrog</label>
            <select id="fartskrog_id" name="fartskrog_id">
                <option value="0"<?= $fartSkrogId === 0 ? ' selected' : '' ?>>Alle</option>
                <?php foreach ($listFartSkrog as $r): ?>
                    <option value="<?= (int)$r['id'] ?>"<?= $fartSkrogId === (int)$r['id'] ? ' selected' : '' ?>><?= h($r['txt']) ?></option>
                <?php endforeach; ?>
            </select>
            <!-- FartDrift -->
            <label for="fartdrift_id">Driftmiddel</label>
            <select id="fartdrift_id" name="fartdrift_id">
                <option value="0"<?= $fartDriftId === 0 ? ' selected' : '' ?>>Alle</option>
                <?php foreach ($listFartDrift as $r): ?>
                    <option value="<?= (int)$r['id'] ?>"<?= $fartDriftId === (int)$r['id'] ? ' selected' : '' ?>><?= h($r['txt']) ?></option>
                <?php endforeach; ?>
            </select>
            <!-- FartMotor -->
            <label for="fartmotor_id">Motor</label>
            <select id="fartmotor_id" name="fartmotor_id">
                <option value="0"<?= $fartMotorId === 0 ? ' selected' : '' ?>>Alle</option>
                <?php foreach ($listFartMotor as $r): ?>
                    <option value="<?= (int)$r['id'] ?>"<?= $fartMotorId === (int)$r['id'] ? ' selected' : '' ?>><?= h($r['txt']) ?></option>
                <?php endforeach; ?>
            </select>
            <!-- FartRigg -->
            <label for="fartrigg_id">Rigg</label>
            <select id="fartrigg_id" name="fartrigg_id">
                <option value="0"<?= $fartRiggId === 0 ? ' selected' : '' ?>>Alle</option>
                <?php foreach ($listFartRigg as $r): ?>
                    <option value="<?= (int)$r['id'] ?>"<?= $fartRiggId === (int)$r['id'] ? ' selected' : '' ?>><?= h($r['txt']) ?></option>
                <?php endforeach; ?>
            </select>
            <!-- FartKlasse -->
            <label for="fartklasse_id">Klasse</label>
            <select id="fartklasse_id" name="fartklasse_id">
                <option value="0"<?= $fartKlasseId === 0 ? ' selected' : '' ?>>Alle</option>
                <?php foreach ($listFartKlasse as $r): ?>
                    <option value="<?= (int)$r['id'] ?>"<?= $fartKlasseId === (int)$r['id'] ? ' selected' : '' ?>><?= h($r['txt']) ?></option>
                <?php endforeach; ?>
            </select>
            <!-- FartObj_ID og FartSpes_ID er ikke søkbare og vises derfor ikke. -->
            <!-- Avsluttende knapper -->
            <div style="flex-basis:100%; text-align:center; margin-top:0.75rem;">
                <button type="submit" class="btn" style="margin-right:1rem;">Søk</button>
                <button type="button" class="btn" onclick="window.location.href='<?= h($currentFile) ?>';">Nullstill filtre</button>
            </div>
        </form>

        <?php if ($doSearch): ?>
            <p>Antall funnet: <strong><?= count($rows) ?></strong></p>
        <?php endif; ?>

        <?php if ($rows): ?>
        <table class="table table-striped table-sm" border="1" cellspacing="0" cellpadding="4" style="width:100%;">
            <thead>
                <tr>
                    <th>Navn</th>
                    <th>Type</th>
                    <th>Funksjon</th>
                    <th>Skrog</th>
                    <th>Drift</th>
                    <th>Motor</th>
                    <th>Rigg</th>
                    <th>Lengde</th>
                    <th>Bredde</th>
                    <th>Dypg</th>
                    <th>Vis</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($rows as $r): ?>
                <tr>
                    <td><?= h(val($r,'FartNavn','')) ?></td>
                    <td><?= h(val($r,'FartType','')) ?></td>
                    <td><?= h(val($r,'FartFunk','')) ?></td>
                    <td><?= h(val($r,'FartSkrog','')) ?></td>
                    <td><?= h(val($r,'FartDrift','')) ?></td>
                    <td><?= h(val($r,'FartMotor','')) ?></td>
                    <td><?= h(val($r,'FartRigg','')) ?></td>
                    <td><?= h(val($r,'Lengde','')) ?></td>
                    <td><?= h(val($r,'Bredde','')) ?></td>
                    <td><?= h(val($r,'Dypg','')) ?></td>
                    <td>
                        <?php $objId  = (int)val($r,'FartObj_ID',0); ?>
                        <?php $navnId = (int)val($r,'FartNavn_ID',0); ?>
                        <?php if ($objId > 0 && $navnId > 0): ?>
                            <a class="btn" href="fartoydetaljer.php?obj_id=<?= $objId ?>&navn_id=<?= $navnId ?>">Vis</a>
                        <?php else: ?>
                            <span class="muted">–</span>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php elseif ($doSearch): ?>
            <p>Ingen treff.</p>
        <?php else: ?>
            <p>Velg ett eller flere filtre for å søke i spesifikasjonene.</p>
        <?php endif; ?>
    </div>

<?php include __DIR__ . '/../includes/footer.php'; ?>