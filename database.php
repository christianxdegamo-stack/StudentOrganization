<?php
/**
 * OrgSync - database settings.
 * Default XAMPP values: user "root" with an EMPTY password.
 * Change these only if your MySQL setup is different.
 */
const DB_HOST    = 'localhost';
const DB_NAME    = 'orgsync_db';
const DB_USER    = 'root';
const DB_PASS    = '';
const DB_CHARSET = 'utf8mb4';
const APP_TIMEZONE = 'Asia/Manila';   // also used as the MySQL session time zone (+08:00)
const MYSQL_TZ     = '+08:00';

date_default_timezone_set(APP_TIMEZONE);

/**
 * APP_URL = web path to the project folder, e.g. "/orgsync".
 * Detected automatically so the project works from any XAMPP folder name.
 */
(function () {
    $dir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/'));
    $dir = preg_replace('#/(admin|officer|student|auth)$#', '', $dir);
    define('APP_URL', rtrim($dir, '/'));
})();

/** Returns one shared PDO connection (created the first time it is needed). */
function db(): PDO
{
    static $pdo = null;
    if ($pdo === null) {
        try {
            $pdo = new PDO(
                'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=' . DB_CHARSET,
                DB_USER,
                DB_PASS,
                [
                    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES   => false,   // real prepared statements
                    PDO::MYSQL_ATTR_INIT_COMMAND => "SET time_zone = '" . MYSQL_TZ . "'",
                ]
            );
        } catch (PDOException $e) {
            http_response_code(500);
            exit('<h3>Database connection failed</h3><p>Make sure MySQL is running in the XAMPP Control Panel '
                . 'and that you imported <code>database/orgsync_db.sql</code> in phpMyAdmin. '
                . 'Then check the settings in <code>config/database.php</code>.</p>');
        }
    }
    return $pdo;
}
