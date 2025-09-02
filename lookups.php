<?php
// includes/lookups.php
// Helper utilities for SkipsWeb param-table lookups and select rendering.

if (!function_exists('sw_db')) {
    function sw_db($candidate = null) {
        if ($candidate instanceof mysqli) return $candidate;
        // Try common globals
        if (isset($GLOBALS['db']) && $GLOBALS['db'] instanceof mysqli) return $GLOBALS['db'];
        if (isset($GLOBALS['mysqli']) && $GLOBALS['mysqli'] instanceof mysqli) return $GLOBALS['mysqli'];
        if (isset($GLOBALS['conn']) && $GLOBALS['conn'] instanceof mysqli) return $GLOBALS['conn'];
        // Last resort: try $GLOBALS['link']
        if (isset($GLOBALS['link']) && $GLOBALS['link'] instanceof mysqli) return $GLOBALS['link'];
        return null;
    }
}

if (!function_exists('sw_fetch_options')) {
    function sw_fetch_options($dbOrNull, string $table, string $idCol, string $nameCol): array {
        $db = sw_db($dbOrNull);
        if (!$db) return [];
        $table = preg_replace('/[^a-zA-Z0-9_]/', '', $table);
        $idCol = preg_replace('/[^a-zA-Z0-9_]/', '', $idCol);
        $nameCol = preg_replace('/[^a-zA-Z0-9_]/', '', $nameCol);
        $sql = "SELECT {$idCol} AS id, {$nameCol} AS name FROM {$table} ORDER BY {$nameCol}";
        $res = $db->query($sql);
        if (!$res) return [];
        $out = [];
        while ($row = $res->fetch_assoc()) {
            $out[(int)$row['id']] = $row['name'];
        }
        $res->free();
        return $out;
    }
}

if (!function_exists('sw_render_select')) {
    function sw_render_select(string $name, array $options, $selectedId = null, array $attrs = []): string {
        $attr = '';
        foreach ($attrs as $k => $v) {
            $attr .= ' ' . $k . '="' . htmlspecialchars((string)$v, ENT_QUOTES) . '"';
        }
        $html = '<select name="' . htmlspecialchars($name, ENT_QUOTES) . '" class="form-select"' . $attr . '>';
        $html .= '<option value="">—</option>';
        foreach ($options as $id => $label) {
            $sel = ((string)$selectedId === (string)$id) ? ' selected' : '';
            $html .= '<option value="' . (int)$id . '"' . $sel . '>' . htmlspecialchars((string)$label) . '</option>';
        }
        $html .= '</select>';
        return $html;
    }
}
