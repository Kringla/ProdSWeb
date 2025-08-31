<?php
/*
 * admin/fartoy_nytt.php
 *
 * Dette scriptet håndterer opprettelse av nye fartøy (nye objekter,
 * endrede spesifikasjoner eller nye tidsrader) i henhold til
 * kravene i "CHANGE_REQUEST fartoy_nytt.php ver2". Fasenavigasjonen er
 * inspirert av flytdiagrammet i fartoy_new.doc【154275207487796†L0-L42】 og følger
 * retningslinjene beskrevet i SkipsWeb_PS v8 (layout og responsivitet)【644736552723675†L139-L148】.
 *
 * For å opprette et nytt fartøy må brukeren være innlogget som administrator.
 * Skjemaet er delt i to hovedfaser:
 *   1) Velge om det er et nytt objekt eller en oppdatering av et eksisterende.
 *      Hvis eksisterende, kan man søke etter og velge fartøy fra databasen.
 *   2) Registrere detaljer for objekt, spesifikasjon og tidsrad. Avhengig av
 *      valget i fase 1 vises relevante seksjoner for objekt og/eller
 *      spesifikasjon. Alle kolonner i tblfartobj, tblfartspes og tblfarttid kan
 *      settes, med unntak av feltet «Historie» som er fjernet fra schema.
 *
 * Skjemaet bruker prepared statements og transaksjoner for å sikre at hele
 * operasjonen rulles tilbake ved feil. Etter vellykket lagring vises en
 * suksessmelding og skjemaet tilbakestilles.
 */

require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/auth.php';

// Start session dersom den ikke allerede er startet
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Kun administratorer har lov til å opprette nye fartøyer
if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin') {
    $base = defined('BASE_URL') ? rtrim(BASE_URL, '/') : '';
    header('Location: ' . $base . '/');
    exit;
}

// Hjelpefunksjoner for sikker output og enkel tilgang til POST-verdier
if (!function_exists('h')) {
    /**
     * Escape-HTML for sikker visning
     *
     * @param string|null $s
     * @return string
     */
    function h($s) {
        return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('val')) {
    /**
     * Returner verdi fra array eller standardverdi dersom nøkkelen ikke finnes
     *
     * @param array  $arr
     * @param string $key
     * @param mixed  $def
     * @return mixed
     */
    function val(array $arr, string $key, $def = '') {
        return array_key_exists($key, $arr) ? $arr[$key] : $def;
    }
}

/**
 * Hent enkle referanselister fra parametertabeller. Tabellen må ha primærnøkkel
 * og en tekstkolonne som vises i dropdown.
 *
 * @param mysqli $conn    Databaseforbindelse
 * @param string $table   Tabellenavn
 * @param string $idField Primærnøkkel
 * @param string $nameField Felt som skal vises
 * @return array Liste over rader (associerte arrays)
 */
function getOptions(mysqli $conn, string $table, string $idField, string $nameField) : array {
    $opts = [];
    $sql  = "SELECT $idField AS id, $nameField AS name FROM {$table} ORDER BY name";
    if ($result = $conn->query($sql)) {
        while ($row = $result->fetch_assoc()) {
            $opts[] = $row;
        }
        $result->free();
    }
    return $opts;
}

// Hent referanselister som brukes i skjemaene
$nasjoner     = getOptions($conn, 'tblznasjon', 'Nasjon_ID', 'Nasjon');
$fartTyper    = getOptions($conn, 'tblzfarttype', 'FartType_ID', 'FartType');
$tonnEnheter  = getOptions($conn, 'tblztonnenh', 'TonnEnh_ID', 'TonnFork');
$drektEnheter = $tonnEnheter; // samme liste brukes for DrektEnh_ID【788094624802379†L48-L49】
$fartMat      = getOptions($conn, 'tblzfartmat', 'FartMat_ID', 'Materiale');
$fartFunk     = getOptions($conn, 'tblzfartfunk', 'FartFunk_ID', 'TypeFunksjon');
$fartSkrog    = getOptions($conn, 'tblzfartskrog', 'FartSkrog_ID', 'TypeSkrog');
$fartDrift    = getOptions($conn, 'tblzfartdrift', 'FartDrift_ID', 'DriftMiddel');
$fartKlasse   = getOptions($conn, 'tblzfartklasse', 'FartKlasse_ID', 'KlasseNavn');
$fartRigg     = getOptions($conn, 'tblzfartrigg', 'FartRigg_ID', 'RiggDetalj');
$fartMotor    = getOptions($conn, 'tblzfartmotor', 'FartMotor_ID', 'MotorDetalj');
$linkTyper    = getOptions($conn, 'tblzlinktype', 'LinkType_ID', 'LinkType');
$stroker      = getOptions($conn, 'tblzstroket', 'Stroket_ID', 'Strok');

// Hent verftliste (navn + sted i ett felt)
$verft = [];
$sqlVerft = "SELECT Verft_ID AS id, CONCAT_WS(', ', VerftNavn, Sted) AS name FROM tblverft ORDER BY name";
if ($res = $conn->query($sqlVerft)) {
    while ($row = $res->fetch_assoc()) {
        $verft[] = $row;
    }
    $res->free();
}

// Funksjon: Hent siste spesifikasjonsrad for et fartøysobjekt. Returnerer assoc array eller null
function getLastSpes(mysqli $conn, int $objId) {
    $sql = "SELECT * FROM tblfartspes WHERE FartObj_ID = ? ORDER BY YearSpes DESC, MndSpes DESC, FartSpes_ID DESC LIMIT 1";
    $stmt = $conn->prepare($sql);
    if ($stmt) {
        $stmt->bind_param('i', $objId);
        $stmt->execute();
        $res = $stmt->get_result();
        $row = $res->fetch_assoc();
        $res->free();
        $stmt->close();
        return $row ?: null;
    }
    return null;
}

// Funksjon: Hent siste tidsrad for et fartøysobjekt. Returnerer assoc array eller null
function getLastTid(mysqli $conn, int $objId) {
    $sql = "SELECT * FROM tblfarttid WHERE FartObj_ID = ? ORDER BY YearTid DESC, MndTid DESC, FartTid_ID DESC LIMIT 1";
    $stmt = $conn->prepare($sql);
    if ($stmt) {
        $stmt->bind_param('i', $objId);
        $stmt->execute();
        $res = $stmt->get_result();
        $row = $res->fetch_assoc();
        $res->free();
        $stmt->close();
        return $row ?: null;
    }
    return null;
}

// Hent liste over objekter for søk; brukes i "choose"-fasen. Resultatet begrenses hvis ikke søk.
function searchObjects(mysqli $conn, string $term = '', int $limit = 50) {
    $objs = [];
    if ($term === '') {
        // Standard – hent de nyeste tidsradene for de siste objektene
        $sql = "SELECT o.FartObj_ID, t.FartNavn
                FROM tblfartobj o
                JOIN (
                    SELECT FartObj_ID, MAX(FartTid_ID) AS max_id
                    FROM tblfarttid
                    GROUP BY FartObj_ID
                ) m ON m.FartObj_ID = o.FartObj_ID
                JOIN tblfarttid t ON t.FartObj_ID = o.FartObj_ID AND t.FartTid_ID = m.max_id
                ORDER BY t.FartNavn ASC
                LIMIT ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param('i', $limit);
    } else {
        // Søk – finn objekter hvor navnet matcher søketekst (delvis)
        // Vi søker kun i siste FartTid-rad for hvert objekt
        $sql = "SELECT DISTINCT o.FartObj_ID, t.FartNavn
                FROM tblfartobj o
                JOIN tblfarttid t ON t.FartObj_ID = o.FartObj_ID
                WHERE t.FartNavn LIKE CONCAT('%', ?, '%')
                ORDER BY t.FartNavn ASC
                LIMIT ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param('si', $term, $limit);
    }
    if ($stmt) {
        $stmt->execute();
        $res = $stmt->get_result();
        while ($row = $res->fetch_assoc()) {
            $objs[] = $row;
        }
        $res->free();
        $stmt->close();
    }
    return $objs;
}

// Hovedfase-variabler: hvilken fase vi er i og valg gjort i forrige steg
// Vi bruker faser: initial → select (for å velge eksisterende objekt) → create → save
$phase     = val($_POST, 'phase', 'initial');
// newObj: -1 = ikke valgt ennå; 1 = nytt objekt; 0 = eksisterende
$newObj    = isset($_POST['newObj']) ? (int)$_POST['newObj'] : -1;
$objId     = (int)val($_POST, 'objId', 0);
$changeSpec = (int)val($_POST, 'changeSpec', 0);
$searchTerm = trim((string)val($_POST, 'searchTerm', ''));

// Faseoverganger basert på POST-data
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($phase === 'initial') {
        // Etter at bruker har valgt nytt/eksisterende går vi videre
        if ($newObj === 1) {
            $phase = 'create';
        } elseif ($newObj === 0) {
            $phase = 'select';
        }
    } elseif ($phase === 'select') {
        // Etter at bruker har valgt fartøysobjekt i søket
        if ($objId > 0) {
            $phase = 'create';
        }
    }
}

// Successtekst etter lagring
$successMsg = '';

// Hvis brukeren lagrer data i create-fasen
if ($phase === 'save' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    // På dette tidspunktet skal vi ha alle felter i POST. Start transaksjon
    $conn->begin_transaction();
    try {
        $createdObjId  = $objId;
        $createdSpesId = null;
        $createdTidId  = null;

        /*
         * Opprett nytt verft hvis bruker har fylt ut nye verftsdetaljer.
         * Dette gjøres før objekts- eller spesifikasjonsinnsetting, slik at
         * de nye IDene kan brukes. Vi støtter separate nye verft for objekt
         * (LeverID/SkrogID) og for spesifikasjon (Verft_ID).
         */
        $newVerftIds = [];
        // Funksjon for å sette inn verft
        $insertVerft = function($prefix) use ($conn, &$newVerftIds) {
            $navn = trim((string)val($_POST, $prefix . 'VerftNavn', ''));
            $sted = trim((string)val($_POST, $prefix . 'VerftSted', ''));
            $nasj = val($_POST, $prefix . 'VerftNasjon', '');
            if ($navn !== '') {
                $nasjonId = $nasj !== '' ? (int)$nasj : null;
                $sql = "INSERT INTO tblverft (VerftNavn, Sted, Nasjon_ID) VALUES (?,?,?)";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param('ssi', $navn, $sted, $nasjonId);
                $stmt->execute();
                $verftId = (int)$conn->insert_id;
                $stmt->close();
                $newVerftIds[$prefix] = $verftId;
                return $verftId;
            }
            return null;
        };

        // Sett inn nytt verft for objekt (lever/skrog) om nødvendig
        $objVerftId = $insertVerft('Obj');
        // Sett inn nytt verft for spesifikasjon om nødvendig
        $spesVerftId = $insertVerft('Spes');

        /* === (a1) Nytt objekt === */
        if ($newObj === 1) {
            // Les alle felt fra skjema for tblfartobj
            $NavnObj      = trim((string)val($_POST, 'NavnObj', ''));
            $FartTypeObj  = (int)val($_POST, 'FartTypeObj', 1);
            $IMO          = (val($_POST, 'IMO', '') !== '' ? (int)val($_POST, 'IMO') : null);
            $Kontrahert   = trim((string)val($_POST, 'Kontrahert', ''));
            $Kjolstrukket = trim((string)val($_POST, 'Kjolstrukket', ''));
            $Sjosatt      = trim((string)val($_POST, 'Sjosatt', ''));
            $Levert       = trim((string)val($_POST, 'Levert', ''));
            $Bygget       = trim((string)val($_POST, 'Bygget', '')); // kun år som tekst【788094624802379†L41-L47】
            // LeverID og SkrogID – bruk nytt verft hvis valgt, ellers verdier fra skjema
            $LeverID      = null;
            if ($objVerftId) {
                $LeverID = $objVerftId;
            } else {
                $LeverID = (val($_POST, 'LeverID', '') !== '' ? (int)val($_POST, 'LeverID') : null);
            }
            $Byggenr      = trim((string)val($_POST, 'Byggenr', ''));
            $SkrogID      = null;
            // Hvis bruker har valgt eget skrogverft
            if ($objVerftId && val($_POST, 'ObjSkrogEget', '') === '1') {
                // Bruk skrog-verft ID fra nytt/verft-liste
                $SkrogID = (val($_POST, 'SkrogID', '') !== '' ? (int)val($_POST, 'SkrogID') : null);
            } else {
                // Hvis SkrogID ikke er satt, settes lik LeverID
                $SkrogID = (val($_POST, 'SkrogID', '') !== '' ? (int)val($_POST, 'SkrogID') : $LeverID);
            }
            $BnrSkrog     = trim((string)val($_POST, 'BnrSkrog', ''));
            if ($BnrSkrog === '') {
                // Hvis BnrSkrog ikke spesifisert, bruk Byggenr for skrog også
                $BnrSkrog = $Byggenr;
            }
            $StroketYear  = (val($_POST, 'StroketYear', '') !== '' ? (int)val($_POST, 'StroketYear') : null);
            $StroketID    = (val($_POST, 'StroketID', '') !== '' ? (int)val($_POST, 'StroketID') : null);
            $Historikk    = trim((string)val($_POST, 'Historikk', ''));
            $ObjNotater   = trim((string)val($_POST, 'ObjNotater', ''));
            $IngenData    = isset($_POST['IngenData']) ? 1 : null;

            // Sett inn i tblfartobj
            $sqlObj = "INSERT INTO tblfartobj (
                NavnObj, FartType_ID, IMO, Kontrahert, Kjolstrukket, Sjosatt,
                Levert, Bygget, LeverID, ByggeNr, SkrogID, BnrSkrog,
                StroketYear, StroketID, Historikk, ObjNotater, IngenData
            ) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)";
            $stmtObj = $conn->prepare($sqlObj);
            if (!$stmtObj) {
                throw new Exception('Feil ved forberedelse av INSERT i tblfartobj: ' . $conn->error);
            }
            $stmtObj->bind_param(
                'siisssssisisiissi',
                $NavnObj,
                $FartTypeObj,
                $IMO,
                $Kontrahert,
                $Kjolstrukket,
                $Sjosatt,
                $Levert,
                $Bygget,
                $LeverID,
                $Byggenr,
                $SkrogID,
                $BnrSkrog,
                $StroketYear,
                $StroketID,
                $Historikk,
                $ObjNotater,
                $IngenData
            );
            $stmtObj->execute();
            $stmtObj->close();
            $createdObjId = (int)$conn->insert_id;
        }

        /* === (a2) Endre spesifikasjon eller (a1) Ny spes for nytt objekt === */
        if ($newObj === 1 || $changeSpec === 1) {
            // Start med å kopiere eksisterende verdier hvis vi endrer spesifikasjon på et eksisterende objekt
            $spes = [];
            if ($newObj !== 1) {
                $spes = getLastSpes($conn, $objId) ?: [];
            }
            // Hent felter fra skjema – hvis feltet ikke er oppgitt, bruk eksisterende verdi
            $YearSpes  = (val($_POST, 'YearSpes', '') !== '' ? (int)val($_POST, 'YearSpes') : (isset($spes['YearSpes']) ? (int)$spes['YearSpes'] : null));
            $MndSpes   = (val($_POST, 'MndSpes', '') !== '' ? (int)val($_POST, 'MndSpes') : (isset($spes['MndSpes']) ? (int)$spes['MndSpes'] : null));
            // Verft_ID: bruk nytt verft hvis oppgitt, ellers feltet fra skjema, ellers eksisterende verdi
            if ($spesVerftId) {
                $Verft_ID = $spesVerftId;
            } else {
                $Verft_ID = (val($_POST, 'Verft_ID', '') !== '' ? (int)val($_POST, 'Verft_ID') : (isset($spes['Verft_ID']) ? (int)$spes['Verft_ID'] : null));
            }
            $ByggenrSpes = trim((string)val($_POST, 'SpesByggenr', isset($spes['Byggenr']) ? $spes['Byggenr'] : ''));
            $Materiale = trim((string)val($_POST, 'Materiale', isset($spes['Materiale']) ? $spes['Materiale'] : ''));
            $FartMat_ID  = (val($_POST, 'FartMat_ID', '') !== '' ? (int)val($_POST, 'FartMat_ID') : (isset($spes['FartMat_ID']) ? (int)$spes['FartMat_ID'] : null));
            $FartTypeSpes = (val($_POST, 'FartTypeSpes', '') !== '' ? (int)val($_POST, 'FartTypeSpes') : (isset($spes['FartType_ID']) ? (int)$spes['FartType_ID'] : null));
            $FartFunk_ID = (val($_POST, 'FartFunk_ID', '') !== '' ? (int)val($_POST, 'FartFunk_ID') : (isset($spes['FartFunk_ID']) ? (int)$spes['FartFunk_ID'] : null));
            $FartSkrog_ID = (val($_POST, 'FartSkrog_ID', '') !== '' ? (int)val($_POST, 'FartSkrog_ID') : (isset($spes['FartSkrog_ID']) ? (int)$spes['FartSkrog_ID'] : null));
            $FartDrift_ID = (val($_POST, 'FartDrift_ID', '') !== '' ? (int)val($_POST, 'FartDrift_ID') : (isset($spes['FartDrift_ID']) ? (int)$spes['FartDrift_ID'] : null));
            $FunkDetalj = trim((string)val($_POST, 'FunkDetalj', isset($spes['FunkDetalj']) ? $spes['FunkDetalj'] : ''));
            $TeknDetalj = trim((string)val($_POST, 'TeknDetalj', isset($spes['TeknDetalj']) ? $spes['TeknDetalj'] : ''));
            $FartKlasse_ID = (val($_POST, 'FartKlasse_ID', '') !== '' ? (int)val($_POST, 'FartKlasse_ID') : (isset($spes['FartKlasse_ID']) ? (int)$spes['FartKlasse_ID'] : null));
            $FartKlasse   = trim((string)val($_POST, 'FartKlasse', isset($spes['Fartklasse']) ? $spes['Fartklasse'] : ''));
            $Kapasitet    = trim((string)val($_POST, 'Kapasitet', isset($spes['Kapasitet']) ? $spes['Kapasitet'] : ''));
            $Rigg         = trim((string)val($_POST, 'Rigg', isset($spes['Rigg']) ? $spes['Rigg'] : ''));
            $FartRigg_ID  = (val($_POST, 'FartRigg_ID', '') !== '' ? (int)val($_POST, 'FartRigg_ID') : (isset($spes['FartRigg_ID']) ? (int)$spes['FartRigg_ID'] : null));
            $FartMotor_ID = (val($_POST, 'FartMotor_ID', '') !== '' ? (int)val($_POST, 'FartMotor_ID') : (isset($spes['FartMotor_ID']) ? (int)$spes['FartMotor_ID'] : null));
            $MotorDetalj  = trim((string)val($_POST, 'MotorDetalj', isset($spes['MotorDetalj']) ? $spes['MotorDetalj'] : ''));
            $MotorEff     = trim((string)val($_POST, 'MotorEff', isset($spes['MotorEff']) ? $spes['MotorEff'] : ''));
            $MaxFart      = (val($_POST, 'MaxFart', '') !== '' ? (int)val($_POST, 'MaxFart') : (isset($spes['MaxFart']) ? (int)$spes['MaxFart'] : null));
            $Lengde       = (val($_POST, 'Lengde', '') !== '' ? (int)val($_POST, 'Lengde') : (isset($spes['Lengde']) ? (int)$spes['Lengde'] : null));
            $Bredde       = (val($_POST, 'Bredde', '') !== '' ? (int)val($_POST, 'Bredde') : (isset($spes['Bredde']) ? (int)$spes['Bredde'] : null));
            $Dypg         = (val($_POST, 'Dypg', '') !== '' ? (int)val($_POST, 'Dypg') : (isset($spes['Dypg']) ? (int)$spes['Dypg'] : null));
            $Tonnasje     = trim((string)val($_POST, 'Tonnasje', isset($spes['Tonnasje']) ? $spes['Tonnasje'] : ''));
            $TonnEnh_ID   = (val($_POST, 'TonnEnh_ID', '') !== '' ? (int)val($_POST, 'TonnEnh_ID') : (isset($spes['TonnEnh_ID']) ? (int)$spes['TonnEnh_ID'] : null));
            $Drektigh     = trim((string)val($_POST, 'Drektigh', isset($spes['Drektigh']) ? $spes['Drektigh'] : ''));
            $DrektEnh_ID  = (val($_POST, 'DrektEnh_ID', '') !== '' ? (int)val($_POST, 'DrektEnh_ID') : (isset($spes['DrektEnh_ID']) ? (int)$spes['DrektEnh_ID'] : null));
            $ObjektFlag   = ($newObj === 1 ? 1 : 0);

            // Sett inn ny spesifikasjonsrad
            $sqlSpes = "INSERT INTO tblfartspes (
                FartObj_ID, YearSpes, MndSpes, Verft_ID, Byggenr, Materiale,
                FartMat_ID, FartType_ID, FartFunk_ID, FartSkrog_ID, FartDrift_ID,
                FunkDetalj, TeknDetalj, FartKlasse_ID, Fartklasse, Kapasitet,
                Rigg, FartRigg_ID, FartMotor_ID, MotorDetalj, MotorEff, MaxFart,
                Lengde, Bredde, Dypg, Tonnasje, TonnEnh_ID, Drektigh, DrektEnh_ID,
                Objekt
            ) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)";
            $stmtSpes = $conn->prepare($sqlSpes);
            if (!$stmtSpes) {
                throw new Exception('Feil ved forberedelse av INSERT i tblfartspes: ' . $conn->error);
            }
            /*
             * MySQL krever en typeindikator per parameter i bind_param. Siden
             * flere felt kan være null og/eller numeriske, bruker vi her
             * strenger for alle parametre (30 stk). MySQL vil automatisk
             * konvertere strenger til passende typer. Hvis du ønsker mer
             * presis binding, kan du erstatte 's' med 'i' for entydige
             * heltallfelt, men det gir ingen funksjonell forskjell i denne
             * sammenhengen.
             */
            $stmtSpes->bind_param(
                str_repeat('s', 30),
                $newObj === 1 ? $createdObjId : $objId,
                $YearSpes,
                $MndSpes,
                $Verft_ID,
                $ByggenrSpes,
                $Materiale,
                $FartMat_ID,
                $FartTypeSpes,
                $FartFunk_ID,
                $FartSkrog_ID,
                $FartDrift_ID,
                $FunkDetalj,
                $TeknDetalj,
                $FartKlasse_ID,
                $FartKlasse,
                $Kapasitet,
                $Rigg,
                $FartRigg_ID,
                $FartMotor_ID,
                $MotorDetalj,
                $MotorEff,
                $MaxFart,
                $Lengde,
                $Bredde,
                $Dypg,
                $Tonnasje,
                $TonnEnh_ID,
                $Drektigh,
                $DrektEnh_ID,
                $ObjektFlag
            );
            $stmtSpes->execute();
            $stmtSpes->close();
            $createdSpesId = (int)$conn->insert_id;
        }

        /* === (a3) eller generell tidsrad === */
        // Finn FartSpes_ID for tidsraden: hvis ny spesifikasjon ble satt inn, bruk den; ellers hent siste spesifikasjon
        if ($newObj === 1) {
            $FartSpesRef = $createdSpesId;
        } elseif ($changeSpec === 1) {
            $FartSpesRef = $createdSpesId;
        } else {
            // hent siste spes
            $lastSpes = getLastSpes($conn, $objId);
            $FartSpesRef = $lastSpes ? (int)$lastSpes['FartSpes_ID'] : null;
        }

        // Les tidsfelter fra skjema
        $YearTid  = (val($_POST, 'YearTid', '') !== '' ? (int)val($_POST, 'YearTid') : null);
        $MndTid   = (val($_POST, 'MndTid', '') !== '' ? (int)val($_POST, 'MndTid') : null);
        $FartNavn = trim((string)val($_POST, 'FartNavn', ''));
        $FartTypeTid = (int)val($_POST, 'FartTypeTid', 1);
        $PennantTiln = trim((string)val($_POST, 'PennantTiln', ''));
        $Rederi     = trim((string)val($_POST, 'Rederi', ''));
        $Nasjon_ID  = (val($_POST, 'Nasjon_ID', '') !== '' ? (int)val($_POST, 'Nasjon_ID') : null);
        $RegHavn    = trim((string)val($_POST, 'RegHavn', ''));
        $MMSI       = trim((string)val($_POST, 'MMSI', ''));
        $Kallesignal = trim((string)val($_POST, 'Kallesignal', ''));
        $Fiskerinr  = trim((string)val($_POST, 'Fiskerinr', ''));
        // Navning, Eierskifte, Annet (boolean flagg)
        $Navning    = isset($_POST['Navning']) ? 1 : 0;
        $Eierskifte = isset($_POST['Eierskifte']) ? 1 : 0;
        $Annet      = isset($_POST['Annet']) ? 1 : 0;
        $Hendelse   = trim((string)val($_POST, 'Hendelse', ''));
        $ObjektFlag = ($newObj === 1 ? 1 : 0);
        // Default: hvis nytt fartøy settes Navning/Eierskifte automatisk til 1 jf. CR【788094624802379†L41-L47】
        if ($newObj === 1) {
            $Navning    = 1;
            $Eierskifte = 1;
        }
        // Sett inn ny tidsrad
        $sqlTid = "INSERT INTO tblfarttid (
            YearTid, MndTid, FartObj_ID, FartSpes_ID, FartNavn, FartType_ID,
            PennantTiln, Objekt, Rederi, Nasjon_ID, RegHavn, MMSI, Kallesignal,
            Fiskerinr, Navning, Eierskifte, Annet, Hendelse
        ) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)";
        $stmtTid = $conn->prepare($sqlTid);
        if (!$stmtTid) {
            throw new Exception('Feil ved forberedelse av INSERT i tblfarttid: ' . $conn->error);
        }
        /*
         * Bruk streng-typer for alle parametere i tidsinnsettingen. Antall
         * parametre er 18, og MySQL konverterer ved behov. Dette gir oss
         * en fleksibel binding når verdier kan være null eller tekst.
         */
        $stmtTid->bind_param(
            str_repeat('s', 18),
            $YearTid,
            $MndTid,
            ($newObj === 1 ? $createdObjId : $objId),
            $FartSpesRef,
            $FartNavn,
            $FartTypeTid,
            $PennantTiln,
            $ObjektFlag,
            $Rederi,
            $Nasjon_ID,
            $RegHavn,
            $MMSI,
            $Kallesignal,
            $Fiskerinr,
            $Navning,
            $Eierskifte,
            $Annet,
            $Hendelse
        );
        $stmtTid->execute();
        $stmtTid->close();
        $createdTidId = (int)$conn->insert_id;

        /* === Lenker (tblxfartlink) === */
        // Flere lenker kan registreres. Vi forventer arrays av like lengde
        if (isset($_POST['LinkType_ID']) && is_array($_POST['LinkType_ID'])) {
            $linkTypeArr = $_POST['LinkType_ID'];
            $linkInnhArr = $_POST['LinkInnh'];
            $linkUrlArr  = $_POST['Link'];
            $serialNo    = 1;
            for ($i = 0; $i < count($linkTypeArr); $i++) {
                $ltId   = $linkTypeArr[$i] !== '' ? (int)$linkTypeArr[$i] : null;
                $ltInnh = trim((string)$linkInnhArr[$i]);
                $ltLink = trim((string)$linkUrlArr[$i]);
                if ($ltInnh !== '' || $ltLink !== '') {
                    $sqlLink = "INSERT INTO tblxfartlink (
                        FartTid_ID, LinkType_ID, LinkInnh, Link, SerNo
                    ) VALUES (?,?,?,?,?)";
                    $stmtL = $conn->prepare($sqlLink);
                    $stmtL->bind_param('iisis', $createdTidId, $ltId, $ltInnh, $ltLink, $serialNo);
                    $stmtL->execute();
                    $stmtL->close();
                    $serialNo++;
                }
            }
        }

        // Commit alt
        $conn->commit();
        $successMsg = 'Fartøyet ble registrert.';
        // Tilbakestill til første fase etter lagring
        // Etter lagring, start på nytt valg-fase
        $phase = 'initial';
        // Tilbakestill valg slik at radio ikke vises feil
        $newObj = -1;
        $objId  = 0;
        $changeSpec = 0;
        $searchTerm = '';
    } catch (Exception $ex) {
        $conn->rollback();
        $successMsg = 'Feil oppstod under lagring: ' . h($ex->getMessage());
    }
}

// HTML Output starter
include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/menu.php';
?>
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
<div class="container mt-3">
    <h1 class="text-center">Opprett nytt fartøy</h1>
    <?php if ($successMsg): ?>
        <div class="alert alert-info"><?= h($successMsg) ?></div>
    <?php endif; ?>
    <?php if ($phase === 'initial' || $phase === 'select'): ?>
        <!-- Fase 1: Velg nytt/eksisterende, og eventuelt søk opp eksisterende -->
        <form method="post" class="mt-3">
            <?php if ($phase === 'initial'): ?>
                <input type="hidden" name="phase" value="initial">
                <div class="mb-3">
                    <label class="form-label">Er dette et nytt fartøysobjekt?</label><br><br>
                <!-- Plasser radio-knappene over sine respektive valgknapper -->
                <div class="d-flex gap-4">
                    <div class="text-center">
                        <input type="radio" name="newObj" id="newObj1" value="1" <?= $newObj === 1 ? 'checked' : '' ?>>
                        <br>
                        <label class="btn btn-outline-primary mt-1" for="newObj1">Ja</label>
                    </div>
                    <div class="text-center">
                        <input type="radio" name="newObj" id="newObj0" value="0" <?= $newObj === 0 ? 'checked' : '' ?>>
                        <br>
                        <label class="btn btn-outline-primary mt-1" for="newObj0">Nei</label>
                    </div>
                </div>
                </div>
                <button type="submit" class="btn btn-primary">Fortsett</button>
            <?php elseif ($phase === 'select'): ?>
                <input type="hidden" name="phase" value="select">
                <input type="hidden" name="newObj" value="0">
                <div class="mb-3">
                    <label for="searchTerm" class="form-label">Søk etter fartøysnavn:</label>
                    <input type="text" name="searchTerm" id="searchTerm" class="form-control" value="<?= h($searchTerm) ?>" placeholder="Søk på navn...">
                </div>
                <?php
                    $results = searchObjects($conn, $searchTerm);
                    if (!empty($results)) {
                        echo '<div class="table-responsive" style="max-height:300px; overflow:auto;">';
                        echo '<table class="table table-sm table-hover">';
                        echo '<thead><tr><th>Velg</th><th>FartObj_ID</th><th>FartNavn</th></tr></thead><tbody>';
                        foreach ($results as $row) {
                            $checked = ($objId == $row['FartObj_ID']) ? 'checked' : '';
                            echo '<tr>';
                            echo '<td><input type="radio" name="objId" value="' . h($row['FartObj_ID']) . '" ' . $checked . '></td>';
                            echo '<td>' . h($row['FartObj_ID']) . '</td>';
                            echo '<td>' . h($row['FartNavn']) . '</td>';
                            echo '</tr>';
                        }
                        echo '</tbody></table>';
                        echo '</div>';
                    } elseif ($searchTerm !== '') {
                        echo '<p>Ingen treff.</p>';
                    }
                ?>
                <button type="submit" class="btn btn-primary">Fortsett</button>
            <?php endif; ?>
        </form>
    <?php endif; ?>
    <?php if ($phase === 'create'): ?>
        <!-- Fase 2: Registrering av detaljer -->
        <form method="post" class="mt-3">
            <input type="hidden" name="phase" value="save">
            <input type="hidden" name="newObj" value="<?= $newObj ?>">
            <input type="hidden" name="objId" value="<?= h($objId) ?>">
            <!-- Hvis eksisterende objekt: valg om vi skal endre spesifikasjon -->
            <?php if ($newObj == 0): ?>
                <div class="mb-3">
                    <label class="form-label">Skal du endre teknisk spesifikasjon?</label><br>
                    <!-- Vis radioer over sine knapper for å tydeliggjøre valget -->
                    <div class="d-flex gap-4">
                        <div class="text-center">
                            <input type="radio" name="changeSpec" id="changeSpec1" value="1" <?= $changeSpec === 1 ? 'checked' : '' ?>>
                            <br>
                            <label class="btn btn-outline-primary mt-1" for="changeSpec1">Ja</label>
                        </div>
                        <div class="text-center">
                            <input type="radio" name="changeSpec" id="changeSpec0" value="0" <?= $changeSpec === 0 ? 'checked' : '' ?>>
                            <br>
                            <label class="btn btn-outline-primary mt-1" for="changeSpec0">Nei</label>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
            <!-- (a1) Skjema for nytt objekt -->
            <?php if ($newObj == 1): ?>
            <h2 class="h4 mt-4">Data for nytt fartøysobjekt</h2>
            <table class="table table-sm table-borderless align-middle">
                <tbody>
                    <tr>
                        <th class="text-end" style="width:30%">Navn gitt ved bygging</th>
                        <td><input type="text" class="form-control" id="NavnObj" name="NavnObj" value="<?= h(val($_POST, 'NavnObj', '')) ?>"></td>
                    </tr>
                    <tr>
                        <th class="text-end">Fartøystype</th>
                        <td>
                            <select class="form-select" id="FartTypeObj" name="FartTypeObj">
                                <?php foreach ($fartTyper as $ft): ?>
                                    <option value="<?= $ft['id'] ?>" <?= (int)val($_POST, 'FartTypeObj', 1) === (int)$ft['id'] ? 'selected' : '' ?>><?= h($ft['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th class="text-end">IMO</th>
                        <td><input type="number" class="form-control" id="IMO" name="IMO" value="<?= h(val($_POST, 'IMO', '')) ?>"></td>
                    </tr>
                    <tr>
                        <th class="text-end">Kontrahert</th>
                        <td><input type="text" class="form-control" id="Kontrahert" name="Kontrahert" value="<?= h(val($_POST, 'Kontrahert', '')) ?>"></td>
                    </tr>
                    <tr>
                        <th class="text-end">Kjølstrukket</th>
                        <td><input type="text" class="form-control" id="Kjolstrukket" name="Kjolstrukket" value="<?= h(val($_POST, 'Kjolstrukket', '')) ?>"></td>
                    </tr>
                    <tr>
                        <th class="text-end">Sjøsatt</th>
                        <td><input type="text" class="form-control" id="Sjosatt" name="Sjosatt" value="<?= h(val($_POST, 'Sjosatt', '')) ?>"></td>
                    </tr>
                    <tr>
                        <th class="text-end">Levert</th>
                        <td><input type="text" class="form-control" id="Levert" name="Levert" value="<?= h(val($_POST, 'Levert', '')) ?>"></td>
                    </tr>
                    <tr>
                        <th class="text-end">Bygget (år)</th>
                        <td><input type="number" class="form-control" id="Bygget" name="Bygget" value="<?= h(val($_POST, 'Bygget', '')) ?>"></td>
                    </tr>
                    <tr>
                        <th class="text-end">Leverende verft</th>
                        <td>
                            <select class="form-select" id="LeverID" name="LeverID">
                                <option value="">-- Velg --</option>
                                <?php foreach ($verft as $v): ?>
                                    <option value="<?= $v['id'] ?>" <?= (int)val($_POST, 'LeverID') === (int)$v['id'] ? 'selected' : '' ?>><?= h($v['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <small class="text-muted">Velg verft eller legg til nytt under.</small>
                        </td>
                    </tr>
                    <tr>
                        <th class="text-end">Byggenummer</th>
                        <td><input type="text" class="form-control" id="Byggenr" name="Byggenr" value="<?= h(val($_POST, 'Byggenr', '')) ?>"></td>
                    </tr>
                    <tr>
                        <th class="text-end">Skrogverft</th>
                        <td>
                            <select class="form-select" id="SkrogID" name="SkrogID">
                                <option value="">Samme som leverende verft</option>
                                <?php foreach ($verft as $v): ?>
                                    <option value="<?= $v['id'] ?>" <?= (int)val($_POST, 'SkrogID') === (int)$v['id'] ? 'selected' : '' ?>><?= h($v['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th class="text-end">Byggenummer, skrog</th>
                        <td><input type="text" class="form-control" id="BnrSkrog" name="BnrSkrog" value="<?= h(val($_POST, 'BnrSkrog', '')) ?>" placeholder="Defaults to Byggenr hvis tomt"></td>
                    </tr>
                    <tr>
                        <th class="text-end">Strøket år</th>
                        <td><input type="number" class="form-control" id="StroketYear" name="StroketYear" value="<?= h(val($_POST, 'StroketYear', '')) ?>"></td>
                    </tr>
                    <tr>
                        <th class="text-end">Strøket ID</th>
                        <td>
                            <select class="form-select" id="StroketID" name="StroketID">
                                <option value="">-- Velg --</option>
                                <?php foreach ($stroker as $s): ?>
                                    <option value="<?= $s['id'] ?>" <?= (int)val($_POST, 'StroketID') === (int)$s['id'] ? 'selected' : '' ?>><?= h($s['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th class="text-end">Historikk</th>
                        <td><textarea class="form-control" id="Historikk" name="Historikk" rows="3" style="width:100%"><?= h(val($_POST, 'Historikk', '')) ?></textarea></td>
                    </tr>
                    <tr>
                        <th class="text-end">Objektnotater</th>
                        <td><textarea class="form-control" id="ObjNotater" name="ObjNotater" rows="3" style="width:100%"><?= h(val($_POST, 'ObjNotater', '')) ?></textarea></td>
                    </tr>
                    <tr>
                        <th class="text-end">Ingen data</th>
                        <td>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="IngenData" name="IngenData" <?= isset($_POST['IngenData']) ? 'checked' : '' ?>>
                                <label class="form-check-label" for="IngenData">Ingen data</label>
                            </div>
                        </td>
                    </tr>
                    <!-- Nytt verft for objekt (lever/skrog) -->
                    <tr><th class="text-end" colspan="2"><h6 class="mt-4">Legg til nytt verft (frivillig)</h6></th></tr>
                    <tr>
                        <th class="text-end">Verftnavn</th>
                        <td><input type="text" class="form-control" id="ObjVerftNavn" name="ObjVerftNavn" value="<?= h(val($_POST, 'ObjVerftNavn', '')) ?>" style="width:100%"></td>
                    </tr>
                    <tr>
                        <th class="text-end">Sted</th>
                        <td><input type="text" class="form-control" id="ObjVerftSted" name="ObjVerftSted" value="<?= h(val($_POST, 'ObjVerftSted', '')) ?>" style="width:100%"></td>
                    </tr>
                    <tr>
                        <th class="text-end">Nasjon</th>
                        <td>
                            <select class="form-select" id="ObjVerftNasjon" name="ObjVerftNasjon">
                                <option value="">-- Velg --</option>
                                <?php foreach ($nasjoner as $n): ?>
                                    <option value="<?= $n['id'] ?>" <?= (int)val($_POST, 'ObjVerftNasjon') === (int)$n['id'] ? 'selected' : '' ?>><?= h($n['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                    </tr>
                </tbody>
            </table>
            <?php endif; ?>
            <!-- (a2) og (a1) Skjema for spesifikasjon hvis nytt objekt eller spesifikasjonsendring -->
            <?php if ($newObj == 1 || $changeSpec == 1): ?>
            <h2 class="h4 mt-5">Teknisk spesifikasjon</h2>
            <?php
                // Hvis endring av spes, hent siste spes og bruk som defaultverdier
                $defaultSpes = [];
                if ($newObj == 0 && $changeSpec == 1 && $objId) {
                    $defaultSpes = getLastSpes($conn, $objId) ?: [];
                }
            ?>
            <div id="spec-container" <?= ($newObj == 1 || $changeSpec == 1) ? '' : 'style="display:none"' ?> >
                <table class="table table-sm table-borderless align-middle">
                    <tbody>
                        <tr>
                            <th class="text-end" style="width:30%">År spes.</th>
                            <td style="width:70%"><input type="number" class="form-control" id="YearSpes" name="YearSpes" value="<?= h(val($_POST, 'YearSpes', val($defaultSpes, 'YearSpes', ''))) ?>"></td>
                        </tr>
                        <tr>
                            <th class="text-end">Måned spes.</th>
                            <td><input type="number" class="form-control" id="MndSpes" name="MndSpes" value="<?= h(val($_POST, 'MndSpes', val($defaultSpes, 'MndSpes', ''))) ?>" min="1" max="12"></td>
                        </tr>
                        <tr>
                            <th class="text-end">Verft</th>
                            <td>
                                <select class="form-select" id="Verft_ID" name="Verft_ID">
                                    <option value="">-- Velg --</option>
                                    <?php foreach ($verft as $v): ?>
                                        <option value="<?= $v['id'] ?>" <?= (int)val($_POST, 'Verft_ID', val($defaultSpes, 'Verft_ID', '')) === (int)$v['id'] ? 'selected' : '' ?>><?= h($v['name']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <div class="form-text">Velg verft eller legg til nytt under.</div>
                            </td>
                        </tr>
                        <tr>
                            <th class="text-end">Byggenummer</th>
                            <td><input type="text" class="form-control" id="SpesByggenr" name="SpesByggenr" value="<?= h(val($_POST, 'SpesByggenr', val($defaultSpes, 'Byggenr', ''))) ?>"></td>
                        </tr>
                        <tr>
                            <th class="text-end">Materiale</th>
                            <td><input type="text" class="form-control" id="Materiale" name="Materiale" value="<?= h(val($_POST, 'Materiale', val($defaultSpes, 'Materiale', ''))) ?>"></td>
                        </tr>
                        <tr>
                            <th class="text-end">Materiale (kode)</th>
                            <td>
                                <select class="form-select" id="FartMat_ID" name="FartMat_ID">
                                    <option value="">-- Velg --</option>
                                    <?php foreach ($fartMat as $m): ?>
                                        <option value="<?= $m['id'] ?>" <?= (int)val($_POST, 'FartMat_ID', val($defaultSpes, 'FartMat_ID', '')) === (int)$m['id'] ? 'selected' : '' ?>><?= h($m['name']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </td>
                        </tr>
                        <tr>
                            <th class="text-end">Fartøystype</th>
                            <td>
                                <select class="form-select" id="FartTypeSpes" name="FartTypeSpes">
                                    <option value="">-- Velg --</option>
                                    <?php foreach ($fartTyper as $ft): ?>
                                        <option value="<?= $ft['id'] ?>" <?= (int)val($_POST, 'FartTypeSpes', val($defaultSpes, 'FartType_ID', '')) === (int)$ft['id'] ? 'selected' : '' ?>><?= h($ft['name']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </td>
                        </tr>
                        <tr>
                            <th class="text-end">Hovedfunksjon</th>
                            <td>
                                <select class="form-select" id="FartFunk_ID" name="FartFunk_ID">
                                    <option value="">-- Velg --</option>
                                    <?php foreach ($fartFunk as $f): ?>
                                        <option value="<?= $f['id'] ?>" <?= (int)val($_POST, 'FartFunk_ID', val($defaultSpes, 'FartFunk_ID', '')) === (int)$f['id'] ? 'selected' : '' ?>><?= h($f['name']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </td>
                        </tr>
                        <tr>
                            <th class="text-end">Skrogtype</th>
                            <td>
                                <select class="form-select" id="FartSkrog_ID" name="FartSkrog_ID">
                                    <option value="">-- Velg --</option>
                                    <?php foreach ($fartSkrog as $sk): ?>
                                        <option value="<?= $sk['id'] ?>" <?= (int)val($_POST, 'FartSkrog_ID', val($defaultSpes, 'FartSkrog_ID', '')) === (int)$sk['id'] ? 'selected' : '' ?>><?= h($sk['name']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </td>
                        </tr>
                        <tr>
                            <th class="text-end">Driftsform</th>
                            <td>
                                <select class="form-select" id="FartDrift_ID" name="FartDrift_ID">
                                    <option value="">-- Velg --</option>
                                    <?php foreach ($fartDrift as $dr): ?>
                                        <option value="<?= $dr['id'] ?>" <?= (int)val($_POST, 'FartDrift_ID', val($defaultSpes, 'FartDrift_ID', '')) === (int)$dr['id'] ? 'selected' : '' ?>><?= h($dr['name']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </td>
                        </tr>
                        <tr>
                            <th class="text-end">Funksjonsdetalj</th>
                            <td><input type="text" class="form-control" id="FunkDetalj" name="FunkDetalj" value="<?= h(val($_POST, 'FunkDetalj', val($defaultSpes, 'FunkDetalj', ''))) ?>"></td>
                        </tr>
                        <tr>
                            <th class="text-end">Tekniske detaljer</th>
                            <td><input type="text" class="form-control" id="TeknDetalj" name="TeknDetalj" value="<?= h(val($_POST, 'TeknDetalj', val($defaultSpes, 'TeknDetalj', ''))) ?>"></td>
                        </tr>
                        <tr>
                            <th class="text-end">Klassekode</th>
                            <td>
                                <select class="form-select" id="FartKlasse_ID" name="FartKlasse_ID">
                                    <option value="">-- Velg --</option>
                                    <?php foreach ($fartKlasse as $fk): ?>
                                        <option value="<?= $fk['id'] ?>" <?= (int)val($_POST, 'FartKlasse_ID', val($defaultSpes, 'FartKlasse_ID', '')) === (int)$fk['id'] ? 'selected' : '' ?>><?= h($fk['name']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </td>
                        </tr>
                        <tr>
                            <th class="text-end">Klassenavn</th>
                            <td><input type="text" class="form-control" id="FartKlasse" name="FartKlasse" value="<?= h(val($_POST, 'FartKlasse', val($defaultSpes, 'Fartklasse', ''))) ?>"></td>
                        </tr>
                        <tr>
                            <th class="text-end">Kapasitet</th>
                            <td><input type="text" class="form-control" id="Kapasitet" name="Kapasitet" value="<?= h(val($_POST, 'Kapasitet', val($defaultSpes, 'Kapasitet', ''))) ?>"></td>
                        </tr>
                        <tr>
                            <th class="text-end">Rigg</th>
                            <td><input type="text" class="form-control" id="Rigg" name="Rigg" value="<?= h(val($_POST, 'Rigg', val($defaultSpes, 'Rigg', ''))) ?>"></td>
                        </tr>
                        <tr>
                            <th class="text-end">Riggkode</th>
                            <td>
                                <select class="form-select" id="FartRigg_ID" name="FartRigg_ID">
                                    <option value="">-- Velg --</option>
                                    <?php foreach ($fartRigg as $fr): ?>
                                        <option value="<?= $fr['id'] ?>" <?= (int)val($_POST, 'FartRigg_ID', val($defaultSpes, 'FartRigg_ID', '')) === (int)$fr['id'] ? 'selected' : '' ?>><?= h($fr['name']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </td>
                        </tr>
                        <tr>
                            <th class="text-end">Motorkode</th>
                            <td>
                                <select class="form-select" id="FartMotor_ID" name="FartMotor_ID">
                                    <option value="">-- Velg --</option>
                                    <?php foreach ($fartMotor as $fm): ?>
                                        <option value="<?= $fm['id'] ?>" <?= (int)val($_POST, 'FartMotor_ID', val($defaultSpes, 'FartMotor_ID', '')) === (int)$fm['id'] ? 'selected' : '' ?>><?= h($fm['name']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </td>
                        </tr>
                        <tr>
                            <th class="text-end">Motordetalj</th>
                            <td><input type="text" class="form-control" id="MotorDetalj" name="MotorDetalj" value="<?= h(val($_POST, 'MotorDetalj', val($defaultSpes, 'MotorDetalj', ''))) ?>"></td>
                        </tr>
                        <tr>
                            <th class="text-end">Motoreffekt</th>
                            <td><input type="text" class="form-control" id="MotorEff" name="MotorEff" value="<?= h(val($_POST, 'MotorEff', val($defaultSpes, 'MotorEff', ''))) ?>"></td>
                        </tr>
                        <tr>
                            <th class="text-end">Max fart</th>
                            <td><input type="number" class="form-control" id="MaxFart" name="MaxFart" value="<?= h(val($_POST, 'MaxFart', val($defaultSpes, 'MaxFart', ''))) ?>"></td>
                        </tr>
                        <tr>
                            <th class="text-end">Lengde</th>
                            <td><input type="number" class="form-control" id="Lengde" name="Lengde" value="<?= h(val($_POST, 'Lengde', val($defaultSpes, 'Lengde', ''))) ?>"></td>
                        </tr>
                        <tr>
                            <th class="text-end">Bredde</th>
                            <td><input type="number" class="form-control" id="Bredde" name="Bredde" value="<?= h(val($_POST, 'Bredde', val($defaultSpes, 'Bredde', ''))) ?>"></td>
                        </tr>
                        <tr>
                            <th class="text-end">Dypgående</th>
                            <td><input type="number" class="form-control" id="Dypg" name="Dypg" value="<?= h(val($_POST, 'Dypg', val($defaultSpes, 'Dypg', ''))) ?>"></td>
                        </tr>
                        <tr>
                            <th class="text-end">Tonnasje / enhet</th>
                            <td>
                                <div class="row g-2">
                                    <div class="col-md-6">
                                        <input type="text" class="form-control" id="Tonnasje" name="Tonnasje" value="<?= h(val($_POST, 'Tonnasje', val($defaultSpes, 'Tonnasje', ''))) ?>">
                                    </div>
                                    <div class="col-md-6">
                                        <select class="form-select" id="TonnEnh_ID" name="TonnEnh_ID">
                                            <option value="">-- Velg --</option>
                                            <?php foreach ($tonnEnheter as $te): ?>
                                                <option value="<?= $te['id'] ?>" <?= (int)val($_POST, 'TonnEnh_ID', val($defaultSpes, 'TonnEnh_ID', '')) === (int)$te['id'] ? 'selected' : '' ?>><?= h($te['name']) ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                </div>
                            </td>
                        </tr>
                        <tr>
                            <th class="text-end">Drektighet / enhet</th>
                            <td>
                                <div class="row g-2">
                                    <div class="col-md-6">
                                        <input type="text" class="form-control" id="Drektigh" name="Drektigh" value="<?= h(val($_POST, 'Drektigh', val($defaultSpes, 'Drektigh', ''))) ?>">
                                    </div>
                                    <div class="col-md-6">
                                        <select class="form-select" id="DrektEnh_ID" name="DrektEnh_ID">
                                            <option value="">-- Velg --</option>
                                            <?php foreach ($drektEnheter as $de): ?>
                                                <option value="<?= $de['id'] ?>" <?= (int)val($_POST, 'DrektEnh_ID', val($defaultSpes, 'DrektEnh_ID', '')) === (int)$de['id'] ? 'selected' : '' ?>><?= h($de['name']) ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                </div>
                            </td>
                        </tr>
                        <tr>
                            <th class="text-end" colspan="2"><h6 class="mt-4">Legg til nytt verft for spesifikasjon (frivillig)</h6></th>
                        </tr>
                        <tr>
                            <th class="text-end">Verftnavn</th>
                            <td><input type="text" class="form-control" id="SpesVerftNavn" name="SpesVerftNavn" value="<?= h(val($_POST, 'SpesVerftNavn', '')) ?>" style="width:100%"></td>
                        </tr>
                        <tr>
                            <th class="text-end">Sted</th>
                            <td><input type="text" class="form-control" id="SpesVerftSted" name="SpesVerftSted" value="<?= h(val($_POST, 'SpesVerftSted', '')) ?>" style="width:100%"></td>
                        </tr>
                        <tr>
                            <th class="text-end">Nasjon</th>
                            <td>
                                <select class="form-select" id="SpesVerftNasjon" name="SpesVerftNasjon" style="width:100%">
                                    <option value="">-- Velg --</option>
                                    <?php foreach ($nasjoner as $n): ?>
                                        <option value="<?= $n['id'] ?>" <?= (int)val($_POST, 'SpesVerftNasjon') === (int)$n['id'] ? 'selected' : '' ?>><?= h($n['name']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
            <!-- Skjema for FartTid (generelle opplysninger) -->
            <h2 class="h4 mt-5">Navn, eier og øvrige opplysninger</h2>
            <table class="table table-sm table-bordered align-middle">
                <tbody>
                    <tr>
                        <th class="text-end" style="width:30%">År</th>
                        <td style="width:70%"><input type="number" class="form-control" id="YearTid" name="YearTid" value="<?= h(val($_POST, 'YearTid', '')) ?>"></td>
                    </tr>
                    <tr>
                        <th class="text-end">Måned</th>
                        <td><input type="number" class="form-control" id="MndTid" name="MndTid" value="<?= h(val($_POST, 'MndTid', '')) ?>" min="1" max="12"></td>
                    </tr>
                    <tr>
                        <th class="text-end">Fartøysnavn</th>
                        <td><input type="text" class="form-control" id="FartNavn" name="FartNavn" value="<?= h(val($_POST, 'FartNavn', '')) ?>"></td>
                    </tr>
                    <tr>
                        <th class="text-end">Fartøystype</th>
                        <td>
                            <select class="form-select" id="FartTypeTid" name="FartTypeTid">
                                <?php foreach ($fartTyper as $ft): ?>
                                    <option value="<?= $ft['id'] ?>" <?= (int)val($_POST, 'FartTypeTid', 1) === (int)$ft['id'] ? 'selected' : '' ?>><?= h($ft['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th class="text-end">Pennant/Tilnavn</th>
                        <td><input type="text" class="form-control" id="PennantTiln" name="PennantTiln" value="<?= h(val($_POST, 'PennantTiln', '')) ?>"></td>
                    </tr>
                    <tr>
                        <th class="text-end">Rederi</th>
                        <td><input type="text" class="form-control" id="Rederi" name="Rederi" value="<?= h(val($_POST, 'Rederi', '')) ?>"></td>
                    </tr>
                    <tr>
                        <th class="text-end">Flaggstat</th>
                        <td>
                            <select class="form-select" id="Nasjon_ID" name="Nasjon_ID">
                                <option value="">-- Velg --</option>
                                <?php foreach ($nasjoner as $n): ?>
                                    <option value="<?= $n['id'] ?>" <?= (int)val($_POST, 'Nasjon_ID') === (int)$n['id'] ? 'selected' : '' ?>><?= h($n['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th class="text-end">Registreringshavn</th>
                        <td><input type="text" class="form-control" id="RegHavn" name="RegHavn" value="<?= h(val($_POST, 'RegHavn', '')) ?>"></td>
                    </tr>
                    <tr>
                        <th class="text-end">MMSI</th>
                        <td><input type="text" class="form-control" id="MMSI" name="MMSI" value="<?= h(val($_POST, 'MMSI', '')) ?>"></td>
                    </tr>
                    <tr>
                        <th class="text-end">Kallesignal</th>
                        <td><input type="text" class="form-control" id="Kallesignal" name="Kallesignal" value="<?= h(val($_POST, 'Kallesignal', '')) ?>"></td>
                    </tr>
                    <tr>
                        <th class="text-end">Fiskerinr</th>
                        <td><input type="text" class="form-control" id="Fiskerinr" name="Fiskerinr" value="<?= h(val($_POST, 'Fiskerinr', '')) ?>"></td>
                    </tr>
                    <tr>
                        <th class="text-end">Navneskifte/Eierskifte/Annet</th>
                        <td>
                            <div class="form-check form-check-inline">
                                <input class="form-check-input" type="checkbox" id="Navning" name="Navning" <?= ($newObj === 1 || isset($_POST['Navning'])) ? 'checked' : '' ?>>
                                <label class="form-check-label" for="Navning">Navneskifte</label>
                            </div>
                            <div class="form-check form-check-inline">
                                <input class="form-check-input" type="checkbox" id="Eierskifte" name="Eierskifte" <?= ($newObj === 1 || isset($_POST['Eierskifte'])) ? 'checked' : '' ?>>
                                <label class="form-check-label" for="Eierskifte">Eierskifte</label>
                            </div>
                            <div class="form-check form-check-inline">
                                <input class="form-check-input" type="checkbox" id="Annet" name="Annet" <?= isset($_POST['Annet']) ? 'checked' : '' ?>>
                                <label class="form-check-label" for="Annet">Annet</label>
                            </div>
                        </td>
                    </tr>
                    <tr>
                        <th class="text-end">Hendelse (optional)</th>
                        <td><input type="text" class="form-control" id="Hendelse" name="Hendelse" value="<?= h(val($_POST, 'Hendelse', '')) ?>"></td>
                    </tr>
                </tbody>
            </table>
            <!-- Lenker -->
            <h2 class="h4 mt-5">Lenker</h2>
            <!-- Inline CSS to center and align link fields -->
            <style>
            .link-row {
                display: flex;
                justify-content: center;
                flex-wrap: wrap;
                gap: 0.5rem;
            }
            .link-row > div {
                flex: 0 0 auto;
            }
            .link-row select,
            .link-row input {
                min-width: 150px;
            }
            </style>
            <div id="links-section">
                <?php
                // Vis eventuelle eksisterende lenker fra POST
                $linkCount = max(1, isset($_POST['LinkType_ID']) && is_array($_POST['LinkType_ID']) ? count($_POST['LinkType_ID']) : 1);
                for ($i = 0; $i < $linkCount; $i++):
                    $ltId   = isset($_POST['LinkType_ID'][$i]) ? $_POST['LinkType_ID'][$i] : '';
                    $ltInnh = isset($_POST['LinkInnh'][$i]) ? $_POST['LinkInnh'][$i] : '';
                    $ltLink = isset($_POST['Link'][$i]) ? $_POST['Link'][$i] : '';
                ?>
                <div class="link-row mb-2">
                    <div>
                        <label class="form-label">Type</label>
                        <select class="form-select" name="LinkType_ID[]">
                            <option value="">-- Velg --</option>
                            <?php foreach ($linkTyper as $lt): ?>
                                <option value="<?= $lt['id'] ?>" <?= (int)$ltId === (int)$lt['id'] ? 'selected' : '' ?>><?= h($lt['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label class="form-label">Innhold</label>
                        <input type="text" class="form-control" name="LinkInnh[]" value="<?= h($ltInnh) ?>">
                    </div>
                    <div>
                        <label class="form-label">URL</label>
                        <input type="url" class="form-control" name="Link[]" value="<?= h($ltLink) ?>">
                    </div>
                    <div class="d-flex align-items-end">
                        <button type="button" class="btn btn-outline-danger remove-link">Fjern</button>
                    </div>
                </div>
                <?php endfor; ?>
            </div>
            <button type="button" id="add-link" class="btn btn-outline-secondary mt-3 mb-3">Legg til lenke</button>
            <!-- Lagre knapp -->
            <div class="mt-4">
                <button type="submit" class="btn btn-primary float-end">Lagre</button>
            </div>
        </form>
        <!-- Enkel JavaScript for å legge til/fjerne lenker -->
        <script>
        document.getElementById('add-link').addEventListener('click', function() {
            const linksSection = document.getElementById('links-section');
            const row = document.createElement('div');
            row.className = 'link-row mb-2';
            row.innerHTML = `
                <div>
                    <label class="form-label">Type</label>
                    <select class="form-select" name="LinkType_ID[]">
                        <option value="">-- Velg --</option>
                        <?php foreach ($linkTyper as $lt): ?>
                            <option value="<?= $lt['id'] ?>"><?= h($lt['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="form-label">Innhold</label>
                    <input type="text" class="form-control" name="LinkInnh[]">
                </div>
                <div>
                    <label class="form-label">URL</label>
                    <input type="url" class="form-control" name="Link[]">
                </div>
                <div class="d-flex align-items-end">
                    <button type="button" class="btn btn-outline-danger remove-link">Fjern</button>
                </div>`;
            linksSection.appendChild(row);
        });
        // Event delegation for remove buttons
        document.addEventListener('click', function(e) {
            if (e.target && e.target.classList.contains('remove-link')) {
                const row = e.target.closest('.link-row');
                if (row) row.remove();
            }
        });
        // Dynamisk visning av spesifikasjonsskjema
        const specContainer = document.getElementById('spec-container');
        const changeSpecRadios = document.querySelectorAll('input[name="changeSpec"]');
        changeSpecRadios.forEach(function(radio) {
            radio.addEventListener('change', function() {
                if (!specContainer) return;
                if (this.value === '1') {
                    specContainer.style.display = '';
                } else {
                    specContainer.style.display = 'none';
                }
            });
        });
        </script>
    <?php endif; ?>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>