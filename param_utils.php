<?php
// includes/param_utils.php
// Utilities to discover and manipulate parameter tables (tblz*) safely.

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/auth.php';

if (!function_exists('h')) {
    function h($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
}

// Ensure only admins use these helpers from UI pages
function require_admin_guard(): void {
    if (!function_exists('is_admin') || !is_admin()) {
        http_response_code(403);
        echo 'Forbidden';
        exit;
    }
}

function param_allowed_table(string $table): bool {
    // allow only names like tblz..., comprised of [a-z0-9_]
    if (!preg_match('/^tblz[a-z0-9_]*$/i', $table)) return false;
    // verify it actually exists
    $stmt = db()->prepare('SELECT COUNT(*) AS c FROM information_schema.tables WHERE table_schema = ? AND table_name = ?');
    $dbName = defined('DB_NAME') ? DB_NAME : (getenv('DB_NAME') ?: '');
    $stmt->bind_param('ss', $dbName, $table);
    $stmt->execute();
    $res = $stmt->get_result();
    $ok = false;
    if ($res && ($row = $res->fetch_assoc())) {
        $ok = ((int)$row['c'] > 0);
    }
    $stmt->close();
    return $ok;
}

function param_list_tables(): array {
    $dbName = defined('DB_NAME') ? DB_NAME : (getenv('DB_NAME') ?: '');
    $sql = "SELECT table_name FROM information_schema.tables WHERE table_schema = ? AND table_name LIKE 'tblz%' ORDER BY table_name";
    $stmt = db()->prepare($sql);
    $stmt->bind_param('s', $dbName);
    $stmt->execute();
    $res = $stmt->get_result();
    $out = [];
    while ($res && ($r = $res->fetch_assoc())) { $out[] = $r['table_name']; }
    $stmt->close();
    return $out;
}

function param_get_primary_key(string $table): ?string {
    $dbName = defined('DB_NAME') ? DB_NAME : (getenv('DB_NAME') ?: '');
    $sql = "SELECT k.COLUMN_NAME
              FROM information_schema.table_constraints t
              JOIN information_schema.key_column_usage k
                ON k.constraint_name = t.constraint_name
               AND k.table_schema = t.table_schema
               AND k.table_name   = t.table_name
             WHERE t.table_schema = ? AND t.table_name = ? AND t.constraint_type = 'PRIMARY KEY'
             ORDER BY k.ORDINAL_POSITION";
    $stmt = db()->prepare($sql);
    $stmt->bind_param('ss', $dbName, $table);
    $stmt->execute();
    $res = $stmt->get_result();
    $cols = [];
    while ($res && ($r = $res->fetch_assoc())) { $cols[] = $r['COLUMN_NAME']; }
    $stmt->close();
    if (count($cols) === 1) return $cols[0];
    return null; // only support single-column PK in generic UI
}

function param_table_columns(string $table): array {
    $dbName = defined('DB_NAME') ? DB_NAME : (getenv('DB_NAME') ?: '');
    $sql = "SELECT COLUMN_NAME, DATA_TYPE, COLUMN_TYPE, IS_NULLABLE, COLUMN_DEFAULT
              FROM information_schema.columns
             WHERE table_schema = ? AND table_name = ?
             ORDER BY ORDINAL_POSITION";
    $stmt = db()->prepare($sql);
    $stmt->bind_param('ss', $dbName, $table);
    $stmt->execute();
    $res = $stmt->get_result();
    $cols = [];
    while ($res && ($r = $res->fetch_assoc())) { $cols[] = $r; }
    $stmt->close();
    return $cols;
}

function param_fetch_all(string $table, string $pk, int $limit = 500): array {
    $limit = max(1, min($limit, 1000));
    $sql = "SELECT * FROM `$table` ORDER BY `$pk` ASC LIMIT $limit";
    $res = db()->query($sql);
    $rows = [];
    while ($res && ($r = $res->fetch_assoc())) { $rows[] = $r; }
    return $rows;
}

function param_fetch_one(string $table, string $pk, $id): ?array {
    $sql = "SELECT * FROM `$table` WHERE `$pk` = ?";
    $stmt = db()->prepare($sql);
    // bind as string; param tables IDs are usually ints but string is safe
    $stmt->bind_param('s', $id);
    $stmt->execute();
    $res = $stmt->get_result();
    $row = $res ? $res->fetch_assoc() : null;
    $stmt->close();
    return $row ?: null;
}

function param_insert(string $table, array $data): int {
    return with_rw(function(mysqli $rw) use ($table, $data) {
        $cols = array_keys($data);
        $place = implode(',', array_fill(0, count($cols), '?'));
        $colList = '`' . implode('`,`', $cols) . '`';
        $sql = "INSERT INTO `$table` ($colList) VALUES ($place)";
        $stmt = $rw->prepare($sql);
        $types = str_repeat('s', count($cols));
        $vals = array_values($data);
        $stmt->bind_param($types, ...$vals);
        $stmt->execute();
        $id = $rw->insert_id;
        $stmt->close();
        return (int)$id;
    });
}

function param_update(string $table, string $pk, $id, array $data): void {
    with_rw(function(mysqli $rw) use ($table, $pk, $id, $data) {
        $assign = implode(',', array_map(fn($c) => "`$c` = ?", array_keys($data)));
        $sql = "UPDATE `$table` SET $assign WHERE `$pk` = ?";
        $stmt = $rw->prepare($sql);
        $types = str_repeat('s', count($data)) . 's';
        $vals = array_values($data);
        $vals[] = (string)$id;
        $stmt->bind_param($types, ...$vals);
        $stmt->execute();
        $stmt->close();
    });
}

function param_delete(string $table, string $pk, $id): void {
    with_rw(function(mysqli $rw) use ($table, $pk, $id) {
        $sql = "DELETE FROM `$table` WHERE `$pk` = ?";
        $stmt = $rw->prepare($sql);
        $idS = (string)$id;
        $stmt->bind_param('s', $idS);
        $stmt->execute();
        $stmt->close();
    });
}

function param_build_form_fields(array $columns, string $pk, array $current = []): array {
    // Return an array of [name, type, max, value, is_pk, nullable]
    $out = [];
    foreach ($columns as $col) {
        $name = $col['COLUMN_NAME'];
        $isPk = ($name === $pk);
        $type = strtolower((string)$col['DATA_TYPE']);
        $ctype = (string)$col['COLUMN_TYPE'];
        $nullable = ((string)$col['IS_NULLABLE'] === 'YES');
        $max = null;
        if (preg_match('/^(varchar|char)\((\d+)\)/i', $ctype, $m)) {
            $max = (int)$m[2];
        }
        $value = array_key_exists($name, $current) ? (string)$current[$name] : '';
        $out[] = [
            'name' => $name,
            'type' => $type,
            'max' => $max,
            'value' => $value,
            'is_pk' => $isPk,
            'nullable' => $nullable,
        ];
    }
    return $out;
}

?>

