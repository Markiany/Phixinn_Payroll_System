<?php

require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/../app/Helpers/functions.php';

use Dotenv\Dotenv;
use App\Helpers\Lang;

// -----------------------------------------------------------------
// Load .env
// -----------------------------------------------------------------
$dotenv = Dotenv::createImmutable(__DIR__ . '/..');
$dotenv->load();

// -----------------------------------------------------------------
// Timezone
// -----------------------------------------------------------------
date_default_timezone_set($_ENV['APP_TIMEZONE'] ?? 'Asia/Manila');

// -----------------------------------------------------------------
// Error display (never show raw errors in production)
// -----------------------------------------------------------------
$debug = filter_var($_ENV['APP_DEBUG'] ?? false, FILTER_VALIDATE_BOOLEAN);
ini_set('display_errors', $debug ? '1' : '0');
error_reporting(E_ALL);

$logDir = __DIR__ . '/../storage/logs';
if (!is_dir($logDir)) {
    mkdir($logDir, 0775, true);
}
ini_set('log_errors', '1');
ini_set('error_log', $logDir . '/app.log');

// -----------------------------------------------------------------
// Sessions (shared across the 2 payroll PCs only in the sense that
// each PC keeps its own local session cookie/file, but both read
// and write the SAME MySQL data - see app/Helpers/Database.php)
// -----------------------------------------------------------------
$lifetime = (int) ($_ENV['SESSION_LIFETIME'] ?? 480) * 60;
session_set_cookie_params([
    'lifetime' => $lifetime,
    'path'     => '/',
    'httponly' => true,
    'samesite' => 'Lax',
]);
session_start();

// -----------------------------------------------------------------
// Language (English / Tagalog) - loaded from $_SESSION['locale']
// -----------------------------------------------------------------
Lang::load();