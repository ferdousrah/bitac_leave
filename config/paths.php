<?php
/**
 * Paths Configuration
 * Central place for all path definitions
 *
 * Supports two deployment modes:
 *   1. XAMPP/cPanel — app served from a subfolder (e.g. /bitac_leave/)
 *   2. Docker/Coolify — app served from web root (/)
 *
 * Set APP_BASE_URL env var to override (e.g. https://bitac.example.com).
 * Otherwise auto-detects: subfolder if matching folder name found in script
 * path; web root otherwise.
 */

// Define base paths
define('ROOT_PATH', dirname(__DIR__));
define('VIEW_PATH', ROOT_PATH . '/views');
define('API_PATH', ROOT_PATH . '/api');
define('INCLUDE_PATH', ROOT_PATH . '/includes');
define('INCLUDES_PATH', ROOT_PATH . '/includes'); // Alias for compatibility
define('CONFIG_PATH', ROOT_PATH . '/config');
define('LIBRARY_PATH', ROOT_PATH . '/library');
define('UPLOAD_PATH', ROOT_PATH . '/uploads');
define('ASSET_PATH', ROOT_PATH . '/app-assets');

// ── BASE_URL resolution ────────────────────────────────────────────────
// Priority: APP_BASE_URL env var → auto-detect subfolder → web root
$envBaseUrl = getenv('APP_BASE_URL');
if (!empty($envBaseUrl)) {
    define('BASE_URL', rtrim($envBaseUrl, '/'));
} else {
    // Detect protocol (env var or $_SERVER)
    $envProto = getenv('APP_PROTOCOL');
    if ($envProto) {
        $protocol = $envProto;
    } elseif (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
        $protocol = 'https';
    } elseif (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https') {
        $protocol = 'https'; // Behind reverse proxy (Coolify/Traefik)
    } else {
        $protocol = 'http';
    }

    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';

    // Try to detect subfolder mode (XAMPP/cPanel) — look for app folder name in SCRIPT_NAME
    $app_folder = basename(ROOT_PATH);
    $script_path = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '');
    $pos = strpos($script_path, '/' . $app_folder . '/');
    if ($pos !== false) {
        $base = substr($script_path, 0, $pos + strlen('/' . $app_folder));
    } else {
        // Web-root mode (Docker/Coolify) — no subfolder prefix
        $base = '';
    }

    define('BASE_URL', $protocol . '://' . $host . $base);
}

define('VIEW_URL', BASE_URL . '/views');
define('API_URL', BASE_URL . '/api');
define('ASSET_URL', BASE_URL . '/app-assets');
define('UPLOAD_URL', BASE_URL . '/uploads');
?>
