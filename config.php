<?php
// F Mail Configuration

// Database Settings
define('DB_HOST', 'sql312.infinityfree.com');
define('DB_NAME', 'if0_41277930_mail_tm');
define('DB_USER', 'if0_41277930');
define('DB_PASS', 'Surjo25369');
define('DB_CHARSET', 'utf8mb4');

// BRAND SETTINGS (Will be overridden by DB if exists)
define('BRAND_NAME', 'F Mail');
define('ADMIN_PASSWORD', 'admin123');

// SMTP Settings
define('SMTP_HOST', 'smtp.gmail.com');
define('SMTP_PORT', 587);
define('SMTP_USER', 'your-email@gmail.com');
define('SMTP_PASS', 'your-app-password');
define('SMTP_ENC', 'tls');

// Initialize DB for settings if needed
function getSetting($pdo, $key, $default = '') {
    if (!$pdo) return $default;
    try {
        $stmt = $pdo->prepare("SELECT val FROM site_settings WHERE `key` = ?");
        $stmt->execute([$key]);
        $res = $stmt->fetch();
        return ($res && $res['val'] !== null) ? $res['val'] : $default;
    } catch (Exception $e) {
        return $default;
    }
}

// SMTP Configuration (Legacy support for older functions)
// Note: $pdo must be initialized before this file is required if settings are needed from DB
$smtp_config = [
    'host' => defined('SMTP_HOST') ? SMTP_HOST : 'smtp.gmail.com',
    'port' => defined('SMTP_PORT') ? SMTP_PORT : 587,
    'username' => defined('SMTP_USER') ? SMTP_USER : 'your-email@gmail.com',
    'password' => defined('SMTP_PASS') ? SMTP_PASS : 'your-app-password',
    'encryption' => defined('SMTP_ENC') ? SMTP_ENC : 'tls'
];
?>
