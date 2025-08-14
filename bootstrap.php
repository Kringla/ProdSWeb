<?php
// Konstanter (BASE_URL m.m.)
require_once __DIR__ . '/../config/constants.php';

// DB-tilkobling / konfig (skal ikke kaste fatal så lenge filen finnes)
require_once __DIR__ . '/../config/config.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// (Valgfritt) Felles hjelpefunksjoner
$fn = __DIR__ . '/functions.php';
if (file_exists($fn)) {
    require_once $fn;
}
