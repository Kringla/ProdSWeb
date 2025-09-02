<?php
// includes/field_widths.php
// Load CSV/TSV defining preferred field widths per (Table, Field).

if (!function_exists('sw_load_field_widths')) {
    function sw_detect_sep(string $line): string {
        if (strpos($line, "\t") !== false) return "\t";
        // detect literal tab first
        if (strpos($line, "	") !== false) return "	";
        return ",";
    }

    function sw_try_paths(): array {
        // Common locations: project root and near includes/
        $candidates = [];
        // current dir (includes)
        $candidates[] = __DIR__ . DIRECTORY_SEPARATOR . 'skipsWeb_Fieldss v1.txt';
        // parent of includes
        $candidates[] = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'skipsWeb_Fieldss v1.txt';
        // project root two up (in case of /user/ structure)
        $candidates[] = dirname(dirname(__DIR__)) . DIRECTORY_SEPARATOR . 'skipsWeb_Fieldss v1.txt';
        // same dir but lowercase variant
        $candidates[] = __DIR__ . DIRECTORY_SEPARATOR . 'skipsweb_fieldss v1.txt';
        $candidates[] = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'skipsweb_fieldss v1.txt';
        return $candidates;
    }

    function sw_load_field_widths(?string $path = null): array {
        static $cache = null;
        if ($cache !== null) return $cache;

        $cache = [];
        $paths = [];
        if ($path) $paths[] = $path;
        $paths = array_merge($paths, sw_try_paths());

        $fh = null;
        $chosen = null;
        foreach ($paths as $p) {
            if (is_readable($p)) { $fh = fopen($p, 'r'); if ($fh) { $chosen = $p; break; } }
        }
        if (!$fh) return $cache;

        // Read header (skip comment lines)
        $header = null;
        while (($line = fgets($fh)) !== false) {
            $trim = trim($line);
            if ($trim === '' || $trim[0] === '#') continue;
            $header = $line;
            break;
        }
        if ($header === null) { fclose($fh); return $cache; }

        $sep = sw_detect_sep($header);
        $cols = array_map('trim', str_getcsv($header, $sep));
        $idxTab = array_search('TabellNavn', $cols);
        $idxFel = array_search('FeltNavn',   $cols);
        $idxWid = array_search('Width',      $cols);
        if ($idxTab === false || $idxFel === false || $idxWid === false) {
            fclose($fh); return $cache;
        }

        while (($line = fgets($fh)) !== false) {
            $trim = trim($line);
            if ($trim === '' || $trim[0] === '#') continue;
            $parts = array_map('trim', str_getcsv($line, $sep));
            if (count($parts) <= max($idxTab, $idxFel, $idxWid)) continue;
            $t = $parts[$idxTab] ?? '';
            $f = $parts[$idxFel] ?? '';
            $w = $parts[$idxWid] ?? '';
            if ($t !== '' && $f !== '' && $w !== '') {
                $cache[$t][$f] = $w;
            }
        }
        fclose($fh);
        return $cache;
    }

    function sw_width_inline(string $tabell, string $felt, string $default = ''): string {
        static $map;
        if ($map === null) $map = sw_load_field_widths(null);
        $val = $map[$tabell][$felt] ?? $default;
        if ($val === '') return '';
        return ' style="max-width:' . htmlspecialchars($val, ENT_QUOTES) . ';"';
    }
}
