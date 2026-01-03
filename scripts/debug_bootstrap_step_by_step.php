<?php
// Debug script to simulate bootstrap.php step-by-step without affecting prod
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "Step 0: Start\n";

define('ROOT_PATH', dirname(__DIR__) . '/');
define('INCLUDE_PATH', ROOT_PATH . 'src/');
define('GLOBAL_CONFIG_FILE', __DIR__ . '/dummy_global_config.php');
if (!defined("CONNECTION_FILE")) {
    define("CONNECTION_FILE", __DIR__ . '/dummy_connection.php');
}

echo "Step 1: Check Redis\n";
if (!extension_loaded("redis")) {
    die("FAIL: Redis extension not loaded in CLI.\n");
}
echo "OK: Redis loaded.\n";

echo "Step 2: Constants\n";
define("GLOBAL_CACHING_KEY", get_current_user());
define("RESOURCES_PATH", INCLUDE_PATH . "resources" . DIRECTORY_SEPARATOR);
define("LOCALE_PATH", RESOURCES_PATH . "Translation" . DIRECTORY_SEPARATOR);
define("TEMPLATES_PATH", RESOURCES_PATH . "Templates" . DIRECTORY_SEPARATOR);
define("IS_DEV", true);
define("ERROR_LOG_FILE", INCLUDE_PATH . "error_log.log");
echo "OK: Constants defined.\n";

echo "Step 3: Loading Autoloader\n";
if (file_exists(INCLUDE_PATH . "Core/Autoloader.php")) {
    require_once INCLUDE_PATH . "Core/Autoloader.php";
    echo "OK: Autoloader required.\n";
} else {
    die("FAIL: Core/Autoloader.php not found.\n");
}

echo "Step 4: Loading Functions\n";
if (file_exists(INCLUDE_PATH . "functions.general.php")) {
    require_once INCLUDE_PATH . "functions.general.php";
    echo "OK: functions.general.php required.\n";
} else {
    die("FAIL: functions.general.php not found.\n");
}

echo "Step 5: Init Caching\n";
try {
    $cache = \Core\Caching\Caching::getInstance();
    echo "OK: Caching instance created.\n";
} catch (\Throwable $e) {
    die("FAIL: Caching::getInstance() threw: " . $e->getMessage() . "\n");
}

echo "Step 6: Init Config\n";
try {
    $config = \Core\Config::getInstance();
    echo "OK: Config instance created.\n";
} catch (\Throwable $e) {
    die("FAIL: Config::getInstance() threw: " . $e->getMessage() . "\n");
}

echo "Step 7: Check DB Property\n";
if (!$config || !property_exists($config, 'db')) {
    echo "WARN: Config 'db' property missing. Dumping config keys:\n";
    print_r(array_keys((array)$config));
} else {
    echo "OK: Config has 'db'.\n";
}

echo "Step 8: Init DB\n";
try {
    $db = \Core\Database\DB::getInstance();
    echo "OK: DB instance created.\n";
} catch (\Throwable $e) {
    die("FAIL: DB::getInstance() threw: " . $e->getMessage() . "\n");
}

echo "Step 9: Database Query Test\n";
if (true || php_sapi_name() == 'cli') {
    try {
        $result = $db->query("SELECT * FROM config");
        if (!$result) {
            echo "FAIL: DB Query failed (false returned).\n";
        } elseif ($result->num_rows == 0) {
            echo "FAIL: Config table empty.\n";
        } else {
            echo "OK: Config table query retrieved " . $result->num_rows . " rows.\n";
        }
    } catch (\Throwable $e) {
         die("FAIL: DB Query threw: " . $e->getMessage() . "\n");
    }
}

echo "Step 10: Logic Complete.\n";
