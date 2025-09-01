<?php
    /*
     * admin/fartoy_edit.php
     *
     * Dette scriptet lar en administrator redigere et eksisterende fartøy.
     * Både navne/tids‑rad (tblfarttid), teknisk spesifikasjon (tblfartspes)
     * og objektdata (tblfartobj) kan oppdateres. Lenker til fartøyet i
     * tblxfartlink håndteres også. Siden baserer seg på utseendet og
     * funksjonaliteten fra fartoy_nytt.php, men jobber kun i én fase
     * (redigering av eksisterende data).
     *
     * Parametere:
     *   GET obj_id (int)  – ID til objektet som eies av tidsraden
     *   GET tid_id (int)  – ID til tidsraden som skal redigeres
     *
     * Siden forventer at brukeren er innlogget som administrator og
     * avviser forespørsler fra andre roller. Alle databaseoperasjoner
     * bruker prepared statements og transaksjoner for å sikre at
     * oppdateringer er konsistente. Hvis tidsraden ikke flagges som
     * «Objekt» vil objektfeltene vises som lesbare, men ikke være
     * redigerbare.
     */

    require_once __DIR__ . '/../includes/bootstrap.php';
    require_once __DIR__ . '/../includes/auth.php';

    // Start session dersom den ikke allerede er startet
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    // Kun administratorer har lov til å redigere fartøyer
    if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin') {
        $base = defined('BASE_URL') ? rtrim(BASE_URL, '/') : '';
        header('Location: ' . $base . '/');
        exit;
    }

    // Enkle hjelpefunksjoner hvis de ikke allerede er definert
    if (!function_exists('h')) {
        /**
         * HTML‑escape for sikker visning
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
     * Hent referanselister fra parametertabeller for dropdowns.
     * Tabellen må ha primærnøkkel og et feltnavn som skal vises.
     *
     * @param mysqli $conn
     * @param string $table
     * @param string $idField
     * @param string $nameField
     * @return array
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

    // Hent GET‑parametre og valider
    $objIdParam = isset($_GET['obj_id']) ? (int)$_GET['obj_id'] : 0;
    $tidIdParam = isset($_GET['tid_id']) ? (int)$_GET['tid_id'] : 0;
    if ($objIdParam <= 0 || $tidIdParam <= 0) {
        http_response_code(400);
        echo 'Ugyldige parametre.';
        exit;
    }

    // Hent referanselister som brukes i skjemaene
    $nasjoner     = getOptions($conn, 'tblznasjon',    'Nasjon_ID',   'Nasjon');
    $fartTyper    = getOptions($conn, 'tblzfarttype',  'FartType_ID', 'FartType');
    $tonnEnheter  = getOptions($conn, 'tblztonnenh',   'TonnEnh_ID',  'TonnFork');
    $drektEnheter = $tonnEnheter; // samme liste brukes for DrektEnh_ID
    $fartMat      = getOptions($conn, 'tblzfartmat',   'FartMat_ID',  'Materiale');
    $fartFunk     = getOptions($conn, 'tblzfartfunk',  'FartFunk_ID', 'TypeFunksjon');
    $fartSkrog    = getOptions($conn, 'tblzfartskrog', 'FartSkrog_ID','TypeSkrog');
    $fartDrift    = getOptions($conn, 'tblzfartdrift','FartDrift_ID','DriftMiddel');
    $fartKlasse   = getOptions($conn, 'tblzfartklasse','FartKlasse_ID','KlasseNavn');
    $fartRigg     = getOptions($conn, 'tblzfartrigg', 'FartRigg_ID', 'RiggDetalj');
    $fartMotor    = getOptions($conn, 'tblzfartmotor','FartMotor_ID','MotorDetalj');
    $linkTyper    = getOptions($conn, 'tblzlinktype', 'LinkType_ID', 'LinkType');
    $stroker      = getOptions($conn, 'tblzstroket',  'Stroket_ID', 'Strok');

    // Hent verftliste (navn + sted i ett felt)
    $verft = [];
    $sqlVerft = "SELECT Verft_ID AS id, CONCAT_WS(', ', VerftNavn, Sted) AS name FROM tblverft ORDER BY name";
    if ($res = $conn->query($sqlVerft)) {
        while ($row = $res->fetch_assoc()) {
            $verft[] = $row;
        }
        $res->free();
    }

    // Hent tidsrad (tblfarttid) med tilknyttede rader
    $tidRow  = null;
    $stmtTid = $conn->prepare('SELECT * FROM tblfarttid WHERE FartTid_ID = ?');
    $stmtTid->bind_param('i', $tidIdParam);
    $stmtTid->execute();
    $resTid  = $stmtTid->get_result();
    if ($resTid) {
        $tidRow = $resTid->fetch_assoc();
        $resTid->free();
    }
    $stmtTid->close();
    if (!$tidRow) {
        http_response_code(404);
        echo 'Fant ingen tidsrad.';
        exit;
    }

    // Overstyr objekt‑ID dersom GET ikke samsvarer med databasen
    $objId = (int)$tidRow['FartObj_ID'];
    if ($objId <= 0) {
        http_response_code(404);
        echo 'Denne tidsraden er ikke knyttet til et fartøysobjekt.';
        exit;
    }

    // Hent objektdata (tblfartobj)
    $objRow = null;
    $stmtObj = $conn->prepare('SELECT * FROM tblfartobj WHERE FartObj_ID = ?');
    $stmtObj->bind_param('i', $objId);
    $stmtObj->execute();
    $resObj = $stmtObj->get_result();
    if ($resObj) {
        $objRow = $resObj->fetch_assoc();
        $resObj->free();
    }
    $stmtObj->close();

    // Hent spesifikasjon (tblfartspes) hvis tidsraden refererer til en spesifikasjon
    $spesRow = null;
    $spesId  = isset($tidRow['FartSpes_ID']) ? (int)$tidRow['FartSpes_ID'] : 0;
    if ($spesId > 0) {
        $stmtSp = $conn->prepare('SELECT * FROM tblfartspes WHERE FartSpes_ID = ?');
        $stmtSp->bind_param('i', $spesId);
        $stmtSp->execute();
        $resSp = $stmtSp->get_result();
        if ($resSp) {
            $spesRow = $resSp->fetch_assoc();
            $resSp->free();
        }
        $stmtSp->close();
    }

    // Hent lenker (tblxfartlink)
    $linkRows = [];
    $stmtLk = $conn->prepare('SELECT * FROM tblxfartlink WHERE FartTid_ID = ? ORDER BY SerNo ASC, FartLk_ID ASC');
    $stmtLk->bind_param('i', $tidIdParam);
    $stmtLk->execute();
    $resLk = $stmtLk->get_result();
    if ($resLk) {
        while ($row = $resLk->fetch_assoc()) {
            $linkRows[] = $row;
        }
        $resLk->free();
    }
    $stmtLk->close();

    // Flag som indikerer at tidsraden representerer selve objektet
    $isObjectFlag = (int)($tidRow['Objekt'] ?? 0) === 1;

    $successMsg = '';

    // Ved POST: oppdater data
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        // Samle inn og sanitere input for tidsrad
        $YearTid   = (val($_POST, 'YearTid', '') !== '' ? (int)val($_POST, 'YearTid') : null);
        $MndTid    = (val($_POST, 'MndTid', '')  !== '' ? (int)val($_POST, 'MndTid')  : null);
        $FartNavn  = trim((string)val($_POST, 'FartNavn', ''));
        $FartTypeTid = (int)val($_POST, 'FartTypeTid', (int)$tidRow['FartType_ID']);
        $PennantTiln = trim((string)val($_POST, 'PennantTiln', ''));
        // behold objektflagget som det er
        $Rederi   = trim((string)val($_POST, 'Rederi', ''));
        $Nasjon_ID = (val($_POST, 'Nasjon_ID', '') !== '' ? (int)val($_POST, 'Nasjon_ID') : null);
        $RegHavn  = trim((string)val($_POST, 'RegHavn', ''));
        $MMSI     = trim((string)val($_POST, 'MMSI', ''));
        $Kallesignal = trim((string)val($_POST, 'Kallesignal', ''));
        $Fiskerinr   = trim((string)val($_POST, 'Fiskerinr', ''));
        $Navning  = isset($_POST['Navning'])  ? 1 : null;
        $Eierskifte = isset($_POST['Eierskifte']) ? 1 : null;
        $Annet    = isset($_POST['Annet'])    ? 1 : null;
        $Hendelse = trim((string)val($_POST, 'Hendelse', ''));

        // Samle inn input for spesifikasjon (hvis det finnes en spesifikasjonsrad)
        $specData = [];
        if ($spesRow) {
            $specData = [
                'YearSpes'     => (val($_POST, 'YearSpes', '') !== '' ? (int)val($_POST, 'YearSpes') : null),
                'MndSpes'      => (val($_POST, 'MndSpes', '')  !== '' ? (int)val($_POST, 'MndSpes')  : null),
                'Verft_ID'     => (val($_POST, 'Verft_ID', '') !== '' ? (int)val($_POST, 'Verft_ID') : null),
                // Byggenr refers to the build number of the specific specification record. Use a distinct field name in the form to avoid clashing
                // with the object’s build number input. If the posted field is not set, default to empty string.
                'Byggenr'      => trim((string)val($_POST, 'SpecByggenr', val($_POST, 'Byggenr', ''))),
                'Materiale'    => trim((string)val($_POST, 'Materiale', '')),
                'FartMat_ID'   => (val($_POST, 'FartMat_ID', '') !== '' ? (int)val($_POST, 'FartMat_ID') : null),
                'FartType_ID'  => (val($_POST, 'FartType_ID', '') !== '' ? (int)val($_POST, 'FartType_ID') : null),
                'FartFunk_ID'  => (val($_POST, 'FartFunk_ID', '') !== '' ? (int)val($_POST, 'FartFunk_ID') : null),
                'FartSkrog_ID' => (val($_POST, 'FartSkrog_ID', '') !== '' ? (int)val($_POST, 'FartSkrog_ID') : null),
                'FartDrift_ID' => (val($_POST, 'FartDrift_ID', '') !== '' ? (int)val($_POST, 'FartDrift_ID') : null),
                'FunkDetalj'   => trim((string)val($_POST, 'FunkDetalj', '')),
                'TeknDetalj'   => trim((string)val($_POST, 'TeknDetalj', '')),
                'FartKlasse_ID'=> (val($_POST, 'FartKlasse_ID', '') !== '' ? (int)val($_POST, 'FartKlasse_ID') : null),
                'Fartklasse'   => trim((string)val($_POST, 'Fartklasse', '')),
                'Kapasitet'    => trim((string)val($_POST, 'Kapasitet', '')),
                'Rigg'         => trim((string)val($_POST, 'Rigg', '')),
                'FartRigg_ID'  => (val($_POST, 'FartRigg_ID', '') !== '' ? (int)val($_POST, 'FartRigg_ID') : null),
                'FartMotor_ID' => (val($_POST, 'FartMotor_ID', '') !== '' ? (int)val($_POST, 'FartMotor_ID') : null),
                'MotorDetalj'  => trim((string)val($_POST, 'MotorDetalj', '')),
                'MotorEff'     => trim((string)val($_POST, 'MotorEff', '')),
                'MaxFart'      => (val($_POST, 'MaxFart', '') !== '' ? (int)val($_POST, 'MaxFart') : null),
                'Lengde'       => (val($_POST, 'Lengde', '')   !== '' ? (int)val($_POST, 'Lengde')   : null),
                'Bredde'       => (val($_POST, 'Bredde', '')   !== '' ? (int)val($_POST, 'Bredde')   : null),
                'Dypg'         => (val($_POST, 'Dypg', '')     !== '' ? (int)val($_POST, 'Dypg')     : null),
                'Tonnasje'     => trim((string)val($_POST, 'Tonnasje', '')),
                'TonnEnh_ID'   => (val($_POST, 'TonnEnh_ID', '') !== '' ? (int)val($_POST, 'TonnEnh_ID') : null),
                'Drektigh'     => trim((string)val($_POST, 'Drektigh', '')),
                'DrektEnh_ID'  => (val($_POST, 'DrektEnh_ID', '') !== '' ? (int)val($_POST, 'DrektEnh_ID') : null)
            ];
        }

        // Samle inn input for objekt (hvis denne tidsraden representerer objektet)
        $objData = [];
        if ($isObjectFlag && $objRow) {
            $objData = [
                'NavnObj'      => trim((string)val($_POST, 'NavnObj', $objRow['NavnObj'] ?? '')),
                'FartType_ID'  => (val($_POST, 'FartTypeObj', '') !== '' ? (int)val($_POST, 'FartTypeObj') : ($objRow['FartType_ID'] ?? null)),
                'IMO'          => (val($_POST, 'IMO', '') !== '' ? (int)val($_POST, 'IMO') : null),
                'Kontrahert'   => trim((string)val($_POST, 'Kontrahert', $objRow['Kontrahert'] ?? '')),
                'Kjolstrukket' => trim((string)val($_POST, 'Kjolstrukket', $objRow['Kjolstrukket'] ?? '')),
                'Sjosatt'      => trim((string)val($_POST, 'Sjosatt', $objRow['Sjosatt'] ?? '')),
                'Levert'       => trim((string)val($_POST, 'Levert', $objRow['Levert'] ?? '')),
                'Bygget'       => (val($_POST, 'Bygget', '') !== '' ? (int)val($_POST, 'Bygget') : null),
                'LeverID'      => (val($_POST, 'LeverID', '') !== '' ? (int)val($_POST, 'LeverID') : null),
                // Use a dedicated field name for the object’s build number to avoid conflict with the specification’s build number
                'ByggeNr'      => trim((string)val($_POST, 'ObjByggeNr', $objRow['ByggeNr'] ?? '')),
                'SkrogID'      => (val($_POST, 'SkrogID', '') !== '' ? (int)val($_POST, 'SkrogID') : null),
                'BnrSkrog'     => trim((string)val($_POST, 'BnrSkrog', $objRow['BnrSkrog'] ?? '')),
                'StroketYear'  => (val($_POST, 'StroketYear', '') !== '' ? (int)val($_POST, 'StroketYear') : null),
                'StroketID'    => (val($_POST, 'StroketID', '') !== '' ? (int)val($_POST, 'StroketID') : null),
                'Historikk'    => trim((string)val($_POST, 'Historikk', $objRow['Historikk'] ?? '')),
                'ObjNotater'   => trim((string)val($_POST, 'ObjNotater', $objRow['ObjNotater'] ?? '')),
                'IngenData'    => isset($_POST['IngenData']) ? 1 : null
            ];
        }

        // Hent lenker fra POST (arrays med samme indeks)
        $postLinkTypes  = isset($_POST['LinkType_ID']) && is_array($_POST['LinkType_ID']) ? $_POST['LinkType_ID'] : [];
        $postLinkInnh   = isset($_POST['LinkInnh'])   && is_array($_POST['LinkInnh'])   ? $_POST['LinkInnh']   : [];
        $postLinkUrls   = isset($_POST['Link'])       && is_array($_POST['Link'])       ? $_POST['Link']       : [];

        // Start transaksjon
        $conn->begin_transaction();
        try {
            // Oppdater tidsrad ved hjelp av dynamisk typebinding
            $sqlTid = "UPDATE tblfarttid
                        SET YearTid = ?, MndTid = ?, FartNavn = ?, FartType_ID = ?, PennantTiln = ?,
                            Rederi = ?, Nasjon_ID = ?, RegHavn = ?, MMSI = ?, Kallesignal = ?,
                            Fiskerinr = ?, Navning = ?, Eierskifte = ?, Annet = ?, Hendelse = ?
                        WHERE FartTid_ID = ?";
            $stmt = $conn->prepare($sqlTid);
            if (!$stmt) {
                throw new Exception('Kunne ikke forberede tidsoppdatering.');
            }
            $tidValues = [
                $YearTid,
                $MndTid,
                $FartNavn,
                $FartTypeTid,
                $PennantTiln,
                $Rederi,
                $Nasjon_ID,
                $RegHavn,
                $MMSI,
                $Kallesignal,
                $Fiskerinr,
                $Navning,
                $Eierskifte,
                $Annet,
                $Hendelse,
                $tidIdParam
            ];
            $types = '';
            foreach ($tidValues as $valTmp) {
                // Null behandles som i (heltal) her; database konverterer tomt til NULL
                if (is_int($valTmp) || is_null($valTmp)) {
                    $types .= 'i';
                } else {
                    $types .= 's';
                }
            }
            $stmt->bind_param($types, ...$tidValues);
            $stmt->execute();
            $stmt->close();

            // Oppdater spesifikasjon dersom rad finnes
            if ($spesRow) {
                $sqlSpes = "UPDATE tblfartspes SET
                        YearSpes = ?, MndSpes = ?, Verft_ID = ?, Byggenr = ?, Materiale = ?,
                        FartMat_ID = ?, FartType_ID = ?, FartFunk_ID = ?, FartSkrog_ID = ?,
                        FartDrift_ID = ?, FunkDetalj = ?, TeknDetalj = ?, FartKlasse_ID = ?,
                        Fartklasse = ?, Kapasitet = ?, Rigg = ?, FartRigg_ID = ?,
                        FartMotor_ID = ?, MotorDetalj = ?, MotorEff = ?, MaxFart = ?,
                        Lengde = ?, Bredde = ?, Dypg = ?, Tonnasje = ?, TonnEnh_ID = ?,
                        Drektigh = ?, DrektEnh_ID = ?
                        WHERE FartSpes_ID = ?";
                $stmtSpes = $conn->prepare($sqlSpes);
                if (!$stmtSpes) {
                    throw new Exception('Kunne ikke forberede spesifikasjonsoppdatering.');
                }
                // Sett sammen verdier i riktig rekkefølge
                $spesValues = [
                    $specData['YearSpes'],
                    $specData['MndSpes'],
                    $specData['Verft_ID'],
                    $specData['Byggenr'],
                    $specData['Materiale'],
                    $specData['FartMat_ID'],
                    $specData['FartType_ID'],
                    $specData['FartFunk_ID'],
                    $specData['FartSkrog_ID'],
                    $specData['FartDrift_ID'],
                    $specData['FunkDetalj'],
                    $specData['TeknDetalj'],
                    $specData['FartKlasse_ID'],
                    $specData['Fartklasse'],
                    $specData['Kapasitet'],
                    $specData['Rigg'],
                    $specData['FartRigg_ID'],
                    $specData['FartMotor_ID'],
                    $specData['MotorDetalj'],
                    $specData['MotorEff'],
                    $specData['MaxFart'],
                    $specData['Lengde'],
                    $specData['Bredde'],
                    $specData['Dypg'],
                    $specData['Tonnasje'],
                    $specData['TonnEnh_ID'],
                    $specData['Drektigh'],
                    $specData['DrektEnh_ID'],
                    $spesId
                ];
                // Lag type‑streng basert på hvert element
                $typesSpes = '';
                foreach ($spesValues as $valTmp) {
                    $typesSpes .= (is_int($valTmp) || is_null($valTmp)) ? 'i' : 's';
                }
                $stmtSpes->bind_param($typesSpes, ...$spesValues);
                $stmtSpes->execute();
                $stmtSpes->close();
            }

            // Oppdater objekt hvis relevant
            if ($isObjectFlag && $objRow) {
                $sqlObj = "UPDATE tblfartobj SET
                        NavnObj = ?, FartType_ID = ?, IMO = ?, Kontrahert = ?,
                        Kjolstrukket = ?, Sjosatt = ?, Levert = ?, Bygget = ?,
                        LeverID = ?, ByggeNr = ?, SkrogID = ?, BnrSkrog = ?,
                        StroketYear = ?, StroketID = ?, Historikk = ?, ObjNotater = ?,
                        IngenData = ?
                        WHERE FartObj_ID = ?";
                $stmtObjUpd = $conn->prepare($sqlObj);
                if (!$stmtObjUpd) {
                    throw new Exception('Kunne ikke forberede objektoppdatering.');
                }
                $objValues = [
                    $objData['NavnObj'],
                    $objData['FartType_ID'],
                    $objData['IMO'],
                    $objData['Kontrahert'],
                    $objData['Kjolstrukket'],
                    $objData['Sjosatt'],
                    $objData['Levert'],
                    $objData['Bygget'],
                    $objData['LeverID'],
                    $objData['ByggeNr'],
                    $objData['SkrogID'],
                    $objData['BnrSkrog'],
                    $objData['StroketYear'],
                    $objData['StroketID'],
                    $objData['Historikk'],
                    $objData['ObjNotater'],
                    $objData['IngenData'],
                    $objId
                ];
                $typesObj = '';
                foreach ($objValues as $valTmp) {
                    $typesObj .= (is_int($valTmp) || is_null($valTmp)) ? 'i' : 's';
                }
                $stmtObjUpd->bind_param($typesObj, ...$objValues);
                $stmtObjUpd->execute();
                $stmtObjUpd->close();
            }

            // Oppdater lenker: først slett eksisterende, deretter legg til nye
            $stmtDel = $conn->prepare('DELETE FROM tblxfartlink WHERE FartTid_ID = ?');
            $stmtDel->bind_param('i', $tidIdParam);
            $stmtDel->execute();
            $stmtDel->close();

            // Sett inn nye lenker
            if (!empty($postLinkTypes) && !empty($postLinkUrls)) {
                $sqlLk = "INSERT INTO tblxfartlink (FartTid_ID, LinkType_ID, LinkType, LinkInnh, Link, SerNo) VALUES (?, ?, ?, ?, ?, ?)";
                $stmtLkIns = $conn->prepare($sqlLk);
                if (!$stmtLkIns) {
                    throw new Exception('Kunne ikke forberede lenkeinnsetting.');
                }
                $serial = 1;
                $count = max(count($postLinkTypes), count($postLinkUrls));
                for ($i = 0; $i < $count; $i++) {
                    $ltId  = isset($postLinkTypes[$i]) && $postLinkTypes[$i] !== '' ? (int)$postLinkTypes[$i] : null;
                    $ltRow = null;
                    // Finn linktype tekst for å lagre LinkType
                    if ($ltId !== null) {
                        foreach ($linkTyper as $lt) {
                            if ((int)$lt['id'] === $ltId) { $ltRow = $lt; break; }
                        }
                    }
                    $ltName = $ltRow ? $ltRow['name'] : null;
                    $ltInnh = isset($postLinkInnh[$i]) ? trim((string)$postLinkInnh[$i]) : '';
                    $ltUrl  = isset($postLinkUrls[$i]) ? trim((string)$postLinkUrls[$i]) : '';
                    if ($ltId === null && $ltUrl === '') {
                        // hopp over tomme rader
                        continue;
                    }
                    $stmtLkIns->bind_param('iiissi', $tidIdParam, $ltId, $ltName, $ltInnh, $ltUrl, $serial);
                    $stmtLkIns->execute();
                    $serial++;
                }
                $stmtLkIns->close();
            }

            // Commit transaksjonen
            $conn->commit();
            $successMsg = 'Endringene ble lagret.';
            // Refetch data fra database for å vise oppdaterte verdier
            // NB: Kun oppdater refresh hvis alt gikk bra
            // Hent tidsrad på nytt
            $stmtTid = $conn->prepare('SELECT * FROM tblfarttid WHERE FartTid_ID = ?');
            $stmtTid->bind_param('i', $tidIdParam);
            $stmtTid->execute();
            $resTid = $stmtTid->get_result();
            if ($resTid) { $tidRow = $resTid->fetch_assoc(); $resTid->free(); }
            $stmtTid->close();
            // Spesifikasjon
            if ($spesId) {
                $stmtSp = $conn->prepare('SELECT * FROM tblfartspes WHERE FartSpes_ID = ?');
                $stmtSp->bind_param('i', $spesId);
                $stmtSp->execute();
                $resSp = $stmtSp->get_result();
                if ($resSp) { $spesRow = $resSp->fetch_assoc(); $resSp->free(); }
                $stmtSp->close();
            }
            // Objekt
            if ($objId) {
                $stmtObj = $conn->prepare('SELECT * FROM tblfartobj WHERE FartObj_ID = ?');
                $stmtObj->bind_param('i', $objId);
                $stmtObj->execute();
                $resObj = $stmtObj->get_result();
                if ($resObj) { $objRow = $resObj->fetch_assoc(); $resObj->free(); }
                $stmtObj->close();
            }
            // Lenker
            $linkRows = [];
            $stmtLk = $conn->prepare('SELECT * FROM tblxfartlink WHERE FartTid_ID = ? ORDER BY SerNo ASC, FartLk_ID ASC');
            $stmtLk->bind_param('i', $tidIdParam);
            $stmtLk->execute();
            $resLk = $stmtLk->get_result();
            if ($resLk) {
                while ($row = $resLk->fetch_assoc()) { $linkRows[] = $row; }
                $resLk->free();
            }
            $stmtLk->close();
        } catch (Exception $ex) {
            $conn->rollback();
            $successMsg = 'Feil oppstod under lagring: ' . h($ex->getMessage());
        }
    }

    // Vis skjema
    include __DIR__ . '/../includes/header.php';
    include __DIR__ . '/../includes/menu.php';
    ?>
    <div class="container mt-3">
      <h1 class="text-center">Rediger fartøy</h1>
      <?php if ($successMsg): ?>
        <div class="alert alert-info"><?= h($successMsg) ?></div>
      <?php endif; ?>
      <!-- Vis objektinformasjon øverst -->
      <div class="card mb-4" style="padding:1rem;">
        <h2 class="h4">Objektinformasjon</h2>
        <?php if ($objRow): ?>
        <table class="table compact table-sm table-borderless align-middle">
          <tbody>
            <tr>
              <th class="text-end" style="width:30%">Navn gitt ved bygging</th>
              <td>
                <?php if ($isObjectFlag): ?>
                  <input type="text" class="form-control" name="NavnObj" form="editForm" value="<?= h($objRow['NavnObj'] ?? '') ?>">
                <?php else: ?>
                  <span><?= h($objRow['NavnObj'] ?? '') ?></span>
                <?php endif; ?>
              </td>
            </tr>
            <tr>
              <th class="text-end">Fartøystype</th>
              <td>
                <?php if ($isObjectFlag): ?>
                  <select class="form-select" name="FartTypeObj" form="editForm">
                    <?php foreach ($fartTyper as $ft): ?>
                      <option value="<?= $ft['id'] ?>" <?= (int)($objRow['FartType_ID'] ?? 1) === (int)$ft['id'] ? 'selected' : '' ?>><?= h($ft['name']) ?></option>
                    <?php endforeach; ?>
                  </select>
                <?php else: ?>
                  <?php
                    $typeName = '';
                    foreach ($fartTyper as $ft) {
                        if ((int)$ft['id'] === (int)($objRow['FartType_ID'] ?? 0)) { $typeName = $ft['name']; break; }
                    }
                  ?>
                  <span><?= h($typeName) ?></span>
                <?php endif; ?>
              </td>
            </tr>
            <tr>
              <th class="text-end">IMO</th>
              <td>
                <?php if ($isObjectFlag): ?>
                  <input type="number" class="form-control" name="IMO" form="editForm" value="<?= h($objRow['IMO'] ?? '') ?>">
                <?php else: ?>
                  <span><?= h($objRow['IMO'] ?? '') ?></span>
                <?php endif; ?>
              </td>
            </tr>
            <tr>
              <th class="text-end">Kontrahert</th>
              <td>
                <?php if ($isObjectFlag): ?>
                  <input type="text" class="form-control" name="Kontrahert" form="editForm" value="<?= h($objRow['Kontrahert'] ?? '') ?>">
                <?php else: ?>
                  <span><?= h($objRow['Kontrahert'] ?? '') ?></span>
                <?php endif; ?>
              </td>
            </tr>
            <tr>
              <th class="text-end">Kjølstrukket</th>
              <td>
                <?php if ($isObjectFlag): ?>
                  <input type="text" class="form-control" name="Kjolstrukket" form="editForm" value="<?= h($objRow['Kjolstrukket'] ?? '') ?>">
                <?php else: ?>
                  <span><?= h($objRow['Kjolstrukket'] ?? '') ?></span>
                <?php endif; ?>
              </td>
            </tr>
            <tr>
              <th class="text-end">Sjøsatt</th>
              <td>
                <?php if ($isObjectFlag): ?>
                  <input type="text" class="form-control" name="Sjosatt" form="editForm" value="<?= h($objRow['Sjosatt'] ?? '') ?>">
                <?php else: ?>
                  <span><?= h($objRow['Sjosatt'] ?? '') ?></span>
                <?php endif; ?>
              </td>
            </tr>
            <tr>
              <th class="text-end">Levert</th>
              <td>
                <?php if ($isObjectFlag): ?>
                  <input type="text" class="form-control" name="Levert" form="editForm" value="<?= h($objRow['Levert'] ?? '') ?>">
                <?php else: ?>
                  <span><?= h($objRow['Levert'] ?? '') ?></span>
                <?php endif; ?>
              </td>
            </tr>
            <tr>
              <th class="text-end">Bygget (år)</th>
              <td>
                <?php if ($isObjectFlag): ?>
                  <input type="number" class="form-control" name="Bygget" form="editForm" value="<?= h($objRow['Bygget'] ?? '') ?>">
                <?php else: ?>
                  <span><?= h($objRow['Bygget'] ?? '') ?></span>
                <?php endif; ?>
              </td>
            </tr>
            <tr>
              <th class="text-end">Leverende verft</th>
              <td>
                <?php if ($isObjectFlag): ?>
                  <select class="form-select" name="LeverID" form="editForm">
                    <option value="">-- Velg --</option>
                    <?php foreach ($verft as $v): ?>
                      <option value="<?= $v['id'] ?>" <?= (int)($objRow['LeverID'] ?? 0) === (int)$v['id'] ? 'selected' : '' ?>><?= h($v['name']) ?></option>
                    <?php endforeach; ?>
                  </select>
                <?php else: ?>
                  <?php
                    $leverNavn = '';
                    foreach ($verft as $v) {
                        if ((int)$v['id'] === (int)($objRow['LeverID'] ?? 0)) { $leverNavn = $v['name']; break; }
                    }
                  ?>
                  <span><?= h($leverNavn) ?></span>
                <?php endif; ?>
              </td>
            </tr>
            <tr>
              <th class="text-end">Byggenummer</th>
              <td>
                <?php if ($isObjectFlag): ?>
                  <!-- Use a distinct field name for the object's build number -->
                  <input type="text" class="form-control" name="ObjByggeNr" form="editForm" value="<?= h($objRow['ByggeNr'] ?? '') ?>">
                <?php else: ?>
                  <span><?= h($objRow['ByggeNr'] ?? '') ?></span>
                <?php endif; ?>
              </td>
            </tr>
            <tr>
              <th class="text-end">Skrogverft</th>
              <td>
                <?php if ($isObjectFlag): ?>
                  <select class="form-select" name="SkrogID" form="editForm">
                    <option value="">Samme som leverende verft</option>
                    <?php foreach ($verft as $v): ?>
                      <option value="<?= $v['id'] ?>" <?= (int)($objRow['SkrogID'] ?? 0) === (int)$v['id'] ? 'selected' : '' ?>><?= h($v['name']) ?></option>
                    <?php endforeach; ?>
                  </select>
                <?php else: ?>
                  <?php
                    $skrogNavn = '';
                    foreach ($verft as $v) {
                        if ((int)$v['id'] === (int)($objRow['SkrogID'] ?? 0)) { $skrogNavn = $v['name']; break; }
                    }
                  ?>
                  <span><?= h($skrogNavn) ?></span>
                <?php endif; ?>
              </td>
            </tr>
            <tr>
              <th class="text-end">Byggenummer, skrog</th>
              <td>
                <?php if ($isObjectFlag): ?>
                  <input type="text" class="form-control" name="BnrSkrog" form="editForm" value="<?= h($objRow['BnrSkrog'] ?? '') ?>" placeholder="Standard til objekts byggenummer hvis tomt">
                <?php else: ?>
                  <span><?= h($objRow['BnrSkrog'] ?? '') ?></span>
                <?php endif; ?>
              </td>
            </tr>
            <tr>
              <th class="text-end">Strøket år</th>
              <td>
                <?php if ($isObjectFlag): ?>
                  <input type="number" class="form-control" name="StroketYear" form="editForm" value="<?= h($objRow['StroketYear'] ?? '') ?>">
                <?php else: ?>
                  <span><?= h($objRow['StroketYear'] ?? '') ?></span>
                <?php endif; ?>
              </td>
            </tr>
            <tr>
              <th class="text-end">Strøket ID</th>
              <td>
                <?php if ($isObjectFlag): ?>
                  <select class="form-select" name="StroketID" form="editForm">
                    <option value="">-- Velg --</option>
                    <?php foreach ($stroker as $s): ?>
                      <option value="<?= $s['id'] ?>" <?= (int)($objRow['StroketID'] ?? 0) === (int)$s['id'] ? 'selected' : '' ?>><?= h($s['name']) ?></option>
                    <?php endforeach; ?>
                  </select>
                <?php else: ?>
                  <?php
                    $strokeName = '';
                    foreach ($stroker as $s) {
                        if ((int)$s['id'] === (int)($objRow['StroketID'] ?? 0)) { $strokeName = $s['name']; break; }
                    }
                  ?>
                  <span><?= h($strokeName) ?></span>
                <?php endif; ?>
              </td>
            </tr>
            <tr>
              <th class="text-end">Historikk</th>
              <td>
                <?php if ($isObjectFlag): ?>
                  <textarea class="form-control" name="Historikk" form="editForm" rows="3" style="width:100%"><?= h($objRow['Historikk'] ?? '') ?></textarea>
                <?php else: ?>
                  <span><?= nl2br(h($objRow['Historikk'] ?? '')) ?></span>
                <?php endif; ?>
              </td>
            </tr>
            <tr>
              <th class="text-end">Objektnotater</th>
              <td>
                <?php if ($isObjectFlag): ?>
                  <textarea class="form-control" name="ObjNotater" form="editForm" rows="3" style="width:100%"><?= h($objRow['ObjNotater'] ?? '') ?></textarea>
                <?php else: ?>
                  <span><?= nl2br(h($objRow['ObjNotater'] ?? '')) ?></span>
                <?php endif; ?>
              </td>
            </tr>
            <tr>
              <th class="text-end">Ingen data</th>
              <td>
                <?php if ($isObjectFlag): ?>
                  <div class="form-check">
                    <input class="form-check-input" type="checkbox" name="IngenData" id="IngenData" form="editForm" <?= (int)($objRow['IngenData'] ?? 0) === 1 ? 'checked' : '' ?>>
                    <label class="form-check-label" for="IngenData">Ingen data</label>
                  </div>
                <?php else: ?>
                  <span><?= (int)($objRow['IngenData'] ?? 0) === 1 ? 'Ja' : 'Nei' ?></span>
                <?php endif; ?>
              </td>
            </tr>
          </tbody>
        </table>
        <?php else: ?>
          <p>Ingen objektdata funnet.</p>
        <?php endif; ?>
      </div>

      <!-- Skjema for redigering av spesifikasjon og tidsrad -->
      <form method="post" id="editForm">
        <!-- Teknisk spesifikasjon -->
        <?php if ($spesRow): ?>
          <h2 class="h4 mt-4">Teknisk spesifikasjon</h2>
          <table class="table compact table-sm table-borderless align-middle">
            <tbody>
              <tr>
                <th class="text-end" style="width:30%">År spes.</th>
                <td style="width:70%"><input type="number" class="form-control" name="YearSpes" value="<?= h($spesRow['YearSpes'] ?? '') ?>"></td>
              </tr>
              <tr>
                <th class="text-end">Måned spes.</th>
                <td><input type="number" class="form-control" name="MndSpes" value="<?= h($spesRow['MndSpes'] ?? '') ?>" min="0" max="12"></td>
              </tr>
              <tr>
                <th class="text-end">Verft</th>
                <td>
                  <select class="form-select" name="Verft_ID">
                    <option value="">-- Velg --</option>
                    <?php foreach ($verft as $v): ?>
                      <option value="<?= $v['id'] ?>" <?= (int)($spesRow['Verft_ID'] ?? 0) === (int)$v['id'] ? 'selected' : '' ?>><?= h($v['name']) ?></option>
                    <?php endforeach; ?>
                  </select>
                  <div class="form-text">Velg verft (ingen endring av verftlisten her).</div>
                </td>
              </tr>
              <tr>
                <th class="text-end">Byggenummer</th>
                <td><input type="text" class="form-control" name="SpecByggenr" value="<?= h($spesRow['Byggenr'] ?? '') ?>"></td>
              </tr>
              <tr>
                <th class="text-end">Materiale</th>
                <td><input type="text" class="form-control" name="Materiale" value="<?= h($spesRow['Materiale'] ?? '') ?>"></td>
              </tr>
              <tr>
                <th class="text-end">Materiale (kode)</th>
                <td>
                  <select class="form-select" name="FartMat_ID">
                    <option value="">-- Velg --</option>
                    <?php foreach ($fartMat as $m): ?>
                      <option value="<?= $m['id'] ?>" <?= (int)($spesRow['FartMat_ID'] ?? 0) === (int)$m['id'] ? 'selected' : '' ?>><?= h($m['name']) ?></option>
                    <?php endforeach; ?>
                  </select>
                </td>
              </tr>
              <tr>
                <th class="text-end">Farttype (kode)</th>
                <td>
                  <select class="form-select" name="FartType_ID">
                    <option value="">-- Velg --</option>
                    <?php foreach ($fartTyper as $ft): ?>
                      <option value="<?= $ft['id'] ?>" <?= (int)($spesRow['FartType_ID'] ?? 0) === (int)$ft['id'] ? 'selected' : '' ?>><?= h($ft['name']) ?></option>
                    <?php endforeach; ?>
                  </select>
                </td>
              </tr>
              <tr>
                <th class="text-end">Funksjon (kode)</th>
                <td>
                  <select class="form-select" name="FartFunk_ID">
                    <option value="">-- Velg --</option>
                    <?php foreach ($fartFunk as $f): ?>
                      <option value="<?= $f['id'] ?>" <?= (int)($spesRow['FartFunk_ID'] ?? 0) === (int)$f['id'] ? 'selected' : '' ?>><?= h($f['name']) ?></option>
                    <?php endforeach; ?>
                  </select>
                </td>
              </tr>
              <tr>
                <th class="text-end">Skrogtype</th>
                <td>
                  <select class="form-select" name="FartSkrog_ID">
                    <option value="">-- Velg --</option>
                    <?php foreach ($fartSkrog as $sk): ?>
                      <option value="<?= $sk['id'] ?>" <?= (int)($spesRow['FartSkrog_ID'] ?? 0) === (int)$sk['id'] ? 'selected' : '' ?>><?= h($sk['name']) ?></option>
                    <?php endforeach; ?>
                  </select>
                </td>
              </tr>
              <tr>
                <th class="text-end">Driftsmiddel</th>
                <td>
                  <select class="form-select" name="FartDrift_ID">
                    <option value="">-- Velg --</option>
                    <?php foreach ($fartDrift as $d): ?>
                      <option value="<?= $d['id'] ?>" <?= (int)($spesRow['FartDrift_ID'] ?? 0) === (int)$d['id'] ? 'selected' : '' ?>><?= h($d['name']) ?></option>
                    <?php endforeach; ?>
                  </select>
                </td>
              </tr>
              <tr>
                <th class="text-end">Funksjonsdetalj</th>
                <td><input type="text" class="form-control" name="FunkDetalj" value="<?= h($spesRow['FunkDetalj'] ?? '') ?>"></td>
              </tr>
              <tr>
                <th class="text-end">Tekniske detaljer</th>
                <td><input type="text" class="form-control" name="TeknDetalj" value="<?= h($spesRow['TeknDetalj'] ?? '') ?>"></td>
              </tr>
              <tr>
                <th class="text-end">Klassifikasjon</th>
                <td>
                  <select class="form-select" name="FartKlasse_ID">
                    <option value="">-- Velg --</option>
                    <?php foreach ($fartKlasse as $c): ?>
                      <option value="<?= $c['id'] ?>" <?= (int)($spesRow['FartKlasse_ID'] ?? 0) === (int)$c['id'] ? 'selected' : '' ?>><?= h($c['name']) ?></option>
                    <?php endforeach; ?>
                  </select>
                </td>
              </tr>
              <tr>
                <th class="text-end">Klassenavn</th>
                <td><input type="text" class="form-control" name="Fartklasse" value="<?= h($spesRow['Fartklasse'] ?? '') ?>"></td>
              </tr>
              <tr>
                <th class="text-end">Kapasitet</th>
                <td><input type="text" class="form-control" name="Kapasitet" value="<?= h($spesRow['Kapasitet'] ?? '') ?>"></td>
              </tr>
              <tr>
                <th class="text-end">Rigg</th>
                <td><input type="text" class="form-control" name="Rigg" value="<?= h($spesRow['Rigg'] ?? '') ?>"></td>
              </tr>
              <tr>
                <th class="text-end">Rigg (kode)</th>
                <td>
                  <select class="form-select" name="FartRigg_ID">
                    <option value="">-- Velg --</option>
                    <?php foreach ($fartRigg as $r): ?>
                      <option value="<?= $r['id'] ?>" <?= (int)($spesRow['FartRigg_ID'] ?? 0) === (int)$r['id'] ? 'selected' : '' ?>><?= h($r['name']) ?></option>
                    <?php endforeach; ?>
                  </select>
                </td>
              </tr>
              <tr>
                <th class="text-end">Motor (kode)</th>
                <td>
                  <select class="form-select" name="FartMotor_ID">
                    <option value="">-- Velg --</option>
                    <?php foreach ($fartMotor as $m): ?>
                      <option value="<?= $m['id'] ?>" <?= (int)($spesRow['FartMotor_ID'] ?? 0) === (int)$m['id'] ? 'selected' : '' ?>><?= h($m['name']) ?></option>
                    <?php endforeach; ?>
                  </select>
                </td>
              </tr>
              <tr>
                <th class="text-end">Motordetalj</th>
                <td><input type="text" class="form-control" name="MotorDetalj" value="<?= h($spesRow['MotorDetalj'] ?? '') ?>"></td>
              </tr>
              <tr>
                <th class="text-end">Motoreffekt</th>
                <td><input type="text" class="form-control" name="MotorEff" value="<?= h($spesRow['MotorEff'] ?? '') ?>"></td>
              </tr>
              <tr>
                <th class="text-end">Maxfart</th>
                <td><input type="number" class="form-control" name="MaxFart" value="<?= h($spesRow['MaxFart'] ?? '') ?>"></td>
              </tr>
              <tr>
                <th class="text-end">Lengde (m)</th>
                <td><input type="number" class="form-control" name="Lengde" value="<?= h($spesRow['Lengde'] ?? '') ?>"></td>
              </tr>
              <tr>
                <th class="text-end">Bredde (m)</th>
                <td><input type="number" class="form-control" name="Bredde" value="<?= h($spesRow['Bredde'] ?? '') ?>"></td>
              </tr>
              <tr>
                <th class="text-end">Dypgående (m)</th>
                <td><input type="number" class="form-control" name="Dypg" value="<?= h($spesRow['Dypg'] ?? '') ?>"></td>
              </tr>
              <tr>
                <th class="text-end">Tonnasje</th>
                <td><input type="text" class="form-control" name="Tonnasje" value="<?= h($spesRow['Tonnasje'] ?? '') ?>"></td>
              </tr>
              <tr>
                <th class="text-end">Tonnasjenhet</th>
                <td>
                  <select class="form-select" name="TonnEnh_ID">
                    <option value="">-- Velg --</option>
                    <?php foreach ($tonnEnheter as $te): ?>
                      <option value="<?= $te['id'] ?>" <?= (int)($spesRow['TonnEnh_ID'] ?? 0) === (int)$te['id'] ? 'selected' : '' ?>><?= h($te['name']) ?></option>
                    <?php endforeach; ?>
                  </select>
                </td>
              </tr>
              <tr>
                <th class="text-end">Drektighet</th>
                <td><input type="text" class="form-control" name="Drektigh" value="<?= h($spesRow['Drektigh'] ?? '') ?>"></td>
              </tr>
              <tr>
                <th class="text-end">Drektenhet</th>
                <td>
                  <select class="form-select" name="DrektEnh_ID">
                    <option value="">-- Velg --</option>
                    <?php foreach ($drektEnheter as $de): ?>
                      <option value="<?= $de['id'] ?>" <?= (int)($spesRow['DrektEnh_ID'] ?? 0) === (int)$de['id'] ? 'selected' : '' ?>><?= h($de['name']) ?></option>
                    <?php endforeach; ?>
                  </select>
                </td>
              </tr>
            </tbody>
          </table>
        <?php else: ?>
          <p>Ingen spesifikasjonsrad funnet for dette fartøyet.</p>
        <?php endif; ?>

        <!-- Navn/tidsopplysninger -->
        <h2 class="h4 mt-4">Navn og tidsdata</h2>
        <table class="table compact table-sm table-borderless align-middle">
          <tbody>
            <tr>
              <th class="text-end" style="width:30%">År</th>
              <td style="width:70%"><input type="number" class="form-control" name="YearTid" value="<?= h($tidRow['YearTid'] ?? '') ?>"></td>
            </tr>
            <tr>
              <th class="text-end">Måned</th>
              <td><input type="number" class="form-control" name="MndTid" value="<?= h($tidRow['MndTid'] ?? '') ?>" min="0" max="12"></td>
            </tr>
            <tr>
              <th class="text-end">Fartøysnavn</th>
              <td><input type="text" class="form-control" name="FartNavn" value="<?= h($tidRow['FartNavn'] ?? '') ?>"></td>
            </tr>
            <tr>
              <th class="text-end">Fartøystype</th>
              <td>
                <select class="form-select" name="FartTypeTid">
                  <?php foreach ($fartTyper as $ft): ?>
                    <option value="<?= $ft['id'] ?>" <?= (int)($tidRow['FartType_ID'] ?? 1) === (int)$ft['id'] ? 'selected' : '' ?>><?= h($ft['name']) ?></option>
                  <?php endforeach; ?>
                </select>
              </td>
            </tr>
            <tr>
              <th class="text-end">Pennant/Tilnavn</th>
              <td><input type="text" class="form-control" name="PennantTiln" value="<?= h($tidRow['PennantTiln'] ?? '') ?>"></td>
            </tr>
            <tr>
              <th class="text-end">Rederi</th>
              <td><input type="text" class="form-control" name="Rederi" value="<?= h($tidRow['Rederi'] ?? '') ?>"></td>
            </tr>
            <tr>
              <th class="text-end">Flaggstat</th>
              <td>
                <select class="form-select" name="Nasjon_ID">
                  <option value="">-- Velg --</option>
                  <?php foreach ($nasjoner as $n): ?>
                    <option value="<?= $n['id'] ?>" <?= (int)($tidRow['Nasjon_ID'] ?? 0) === (int)$n['id'] ? 'selected' : '' ?>><?= h($n['name']) ?></option>
                  <?php endforeach; ?>
                </select>
              </td>
            </tr>
            <tr>
              <th class="text-end">Registreringshavn</th>
              <td><input type="text" class="form-control" name="RegHavn" value="<?= h($tidRow['RegHavn'] ?? '') ?>"></td>
            </tr>
            <tr>
              <th class="text-end">MMSI</th>
              <td><input type="text" class="form-control" name="MMSI" value="<?= h($tidRow['MMSI'] ?? '') ?>"></td>
            </tr>
            <tr>
              <th class="text-end">Kallesignal</th>
              <td><input type="text" class="form-control" name="Kallesignal" value="<?= h($tidRow['Kallesignal'] ?? '') ?>"></td>
            </tr>
            <tr>
              <th class="text-end">Fiskerinr</th>
              <td><input type="text" class="form-control" name="Fiskerinr" value="<?= h($tidRow['Fiskerinr'] ?? '') ?>"></td>
            </tr>
            <tr>
              <th class="text-end">Navneskifte/Eierskifte/Annet</th>
              <td>
                <div class="form-check form-check-inline">
                  <input class="form-check-input" type="checkbox" id="Navning" name="Navning" <?= (int)($tidRow['Navning'] ?? 0) === 1 ? 'checked' : '' ?>>
                  <label class="form-check-label" for="Navning">Navneskifte</label>
                </div>
                <div class="form-check form-check-inline">
                  <input class="form-check-input" type="checkbox" id="Eierskifte" name="Eierskifte" <?= (int)($tidRow['Eierskifte'] ?? 0) === 1 ? 'checked' : '' ?>>
                  <label class="form-check-label" for="Eierskifte">Eierskifte</label>
                </div>
                <div class="form-check form-check-inline">
                  <input class="form-check-input" type="checkbox" id="Annet" name="Annet" <?= (int)($tidRow['Annet'] ?? 0) === 1 ? 'checked' : '' ?>>
                  <label class="form-check-label" for="Annet">Annet</label>
                </div>
              </td>
            </tr>
            <tr>
              <th class="text-end">Hendelse (valgfri)</th>
              <td><input type="text" class="form-control" name="Hendelse" value="<?= h($tidRow['Hendelse'] ?? '') ?>"></td>
            </tr>
          </tbody>
        </table>

        <!-- Lenker -->
        <h2 class="h4 mt-4">Lenker</h2>
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
          // Vis eksisterende lenker eller en tom rad hvis ingen finnes
          $initialCount = max(1, count($linkRows));
          for ($i = 0; $i < $initialCount; $i++):
              $lk = $linkRows[$i] ?? ['LinkType_ID' => '', 'LinkInnh' => '', 'Link' => ''];
              $ltId   = $lk['LinkType_ID'];
              $ltInnh = $lk['LinkInnh'];
              $ltLink = $lk['Link'];
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
        <div class="mt-4">
          <button type="submit" class="btn btn-primary float-end">Lagre</button>
        </div>
      </form>
    </div>
    <!-- JavaScript for å legge til/fjerne lenker -->
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
    </script>
    <?php include __DIR__ . '/../includes/footer.php'; ?>