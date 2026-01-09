<?php
define("ROOT_PATH", __DIR__ . "/");
define("INCLUDE_PATH", ROOT_PATH . "src/");
define("GLOBAL_CONFIG_FILE", __DIR__ . "/verify_env.php");
define("CONNECTION_FILE", __DIR__ . "/verify_env.php");

require_once INCLUDE_PATH . "Core/Config.php";
require_once INCLUDE_PATH . "Core/Database/DB.php";
require_once INCLUDE_PATH . "Game/Formulas.php";
require_once INCLUDE_PATH . "Game/SpeedCalculator.php";
require_once INCLUDE_PATH . "Model/MovementsModel.php";
require_once INCLUDE_PATH . "Game/Hero/HeroItems.php";
require_once INCLUDE_PATH . "Model/ArtefactsModel.php";
require_once INCLUDE_PATH . "Model/WonderOfTheWorldModel.php";

// Mock helper functions if needed, or rely on autoloader if Composer is used.
// Assuming we need to setup autoloader.
if (file_exists(ROOT_PATH . 'vendor/autoload.php')) {
    require_once ROOT_PATH . 'vendor/autoload.php';
} else {
    // Basic manual loading if no vendor autoloader (based on file structure)
    spl_autoload_register(function ($class) {
        $file = INCLUDE_PATH . str_replace('\\', '/', $class) . '.php';
        if (file_exists($file)) {
            require_once $file;
        }
    });
}

// Function dummies
function getCustom($name) { return 0; }
function unitIdToNr($id) { 
    $id = (int)$id;
    $Tribe = (int)(($id-1)/10)+1;
    return $id - ($Tribe-1)*10;
}
function unitIdToTribe($id) {
    return (int)(($id-1)/10)+1;
}

use Core\Config;
use Game\Formulas;
use Game\SpeedCalculator;

try {
    echo "Loading Formulas...\n";
    Formulas::getInstance(); // Should trigger load()
    
    // Check Config
    echo "Checking Config...\n";
    $speedIncrease = Config::getProperty("game", "movement_speed_increase");
    echo "Config 'movement_speed_increase': " . var_export($speedIncrease, true) . "\n";
    
    $gameSpeed = Config::getProperty("game", "speed");
    echo "Config 'speed': " . var_export($gameSpeed, true) . "\n";

    // Test Distance
    // Coordinate 0,0 (kid typically around MAP_SIZE*2*MAP_SIZE + ... logic is complex, let's use xy2kid)
    $kid1 = Formulas::xy2kid(0, 0);
    $kid2 = Formulas::xy2kid(10, 0); // Distance 10
    
    echo "KID 0,0: $kid1\n";
    echo "KID 10,0: $kid2\n";
    
    $distance = Formulas::getDistance($kid1, $kid2);
    echo "Calculated Distance (should be 10): $distance\n";

    // Test SpeedCalculator
    echo "Testing SpeedCalculator...\n";
    $calc = new SpeedCalculator();
    $calc->setFrom($kid1);
    $calc->setTo($kid2);
    
    // Roman Legionnaire (Unit 1), Speed 6
    // If unitID is 1.
    $unitId = 1;
    $uSpeed = Formulas::uSpeed($unitId);
    echo "Unit 1 Speed (Base 6 * Multiplier): $uSpeed\n";
    
    $calc->setMinSpeed([$uSpeed]);
    
    $time = $calc->calc();
    echo "Calculated Time (Distance $distance / Speed $uSpeed * 3600): $time seconds\n";
    
    $expectedTime = round(($distance / $uSpeed) * 3600);
    echo "Expected Time: $expectedTime seconds\n";
    
    if ($time == 0 || $time < 10) {
        echo "FAIL: Time is surprisingly low!\n";
    } else {
        echo "SUCCESS: Time looks reasonable.\n";
    }

} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    echo $e->getTraceAsString();
}
