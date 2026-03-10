<?php
$config = require __DIR__ . '/config.php';
$db = $config['db'];

define('DB_HOST', $db['host']);
define('DB_USER', $db['user']);
define('DB_PASS', $db['password']);
define('DB_NAME', $db['database']);
define('DB_CHARSET', $db['charset']);
define('DB_PORT', $db['port']);

/**
 * MySQLi connection
 *
 * @return mysqli
 * @throws mysqli_sql_exception
 */
function db_connection(): mysqli
{
    static $mysqli = null;

    if ($mysqli === null) {
        mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
        $mysqli = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME, DB_PORT);
        $mysqli->set_charset(DB_CHARSET);
    }

    return $mysqli;
}
