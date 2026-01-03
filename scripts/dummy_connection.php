<?php
// Dummy connection file to satisfy require(CONNECTION_FILE) in config.php
// The verification scripts rely on Config::getInstance() which loads config.php
// config.php likely requires this file.

// We don't necessarily need real credentials here because bootstrap.php 
// uses DB::getInstance() which might read form config table or passed params.
// However, if config.php tries to use variables from here, we should provide them.

$connection = [
    'hostname' => 'localhost',
    'username' => 'travium',
    'password' => 'travium', // Placeholder
    'database' => 'travium',
];
