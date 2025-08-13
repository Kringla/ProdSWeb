<?php
// Velg kreds basert på rolle. ADM får write, alle andre får read-only.
function db_connect(?bool $forceAdmin = null) {
    // 1) Les konstanter for begge brukere fra miljøvariabler eller egen config
    $cfgAdmin = [
        'host' => DB_HOST, 'db' => DB_NAME,
        'user' => DB_ADMIN_USER, 'pass' => DB_ADMIN_PASS
    ];
    $cfgRO = [
        'host' => DB_HOST, 'db' => DB_NAME,
        'user' => DB_RO_USER, 'pass' => DB_RO_PASS
    ];

    // 2) Finn rolle: admin kun når *server-side* session sier det
    $isAdmin = $forceAdmin === true
        || (isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'admin');

    $c = $isAdmin ? $cfgAdmin : $cfgRO;

    $conn = new mysqli($c['host'], $c['user'], $c['pass'], $c['db']);
    if ($conn->connect_error) {
        throw new RuntimeException('DB-tilkobling feilet: ' . $conn->connect_error);
    }
    $conn->set_charset('utf8mb4');

    // Gjør tilgjengelig som globalt $conn for gammel kode som forventer det
    $GLOBALS['conn'] = $conn;
    return $conn;
}
