<?php
function e($string) {
    return htmlspecialchars($string, ENT_QUOTES, 'UTF-8');
}
function is_valid_id($id) {
    return ctype_digit(strval($id)) && intval($id) > 0;
}
function isAdmin() {
    return isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'admin';
}

/**
 * URL-helper som respekterer BASE_URL.
 * Eksempel: url('user/fartoy_nat.php')
 */
function url(string $path = ''): string {
    $base = defined('BASE_URL') ? rtrim(BASE_URL, '/') : '';
    return $base . '/' . ltrim($path, '/');
}

if (!function_exists('asset')) {
    /**
     * asset('/assets/img/hero1.jpg')  ->  '/assets/img/hero1.jpg?v=1699999999'
     * Legger på mtime for cache-busting og respekterer BASE_URL.
     */
    function asset(string $path): string {
        $base = defined('BASE_URL') ? rtrim(BASE_URL, '/') : '';
        $rel  = '/' . ltrim($path, '/');
        $fs   = rtrim(__DIR__ . '/..', '/') . $rel; // prosjektrot = includes/..
        $v    = @filemtime($fs);
        return $base . $rel . ($v ? ('?v=' . $v) : '');
    }
}