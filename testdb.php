<?php
require_once __DIR__ . '/config/config.php';
if ($conn instanceof mysqli) {
    echo "DB-tilkobling: OK!";
} else {
    echo "Ingen tilkoblingfunnet.";
}
