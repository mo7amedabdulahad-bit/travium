<?php
// Dummy connection file to satisfy require(CONNECTION_FILE) in config.php
// The verification scripts rely on Config::getInstance() which loads config.php

$connection = [];

// config.php line 13: 'db' => (object)$connection['database']
// This implies $connection['database'] must be an array of credentials.
$connection['database'] = [
    'hostname' => 'localhost',
    'username' => 'travium',
    'password' => 'travium',
    'database' => 'travium',
];

// config.php line 21: 'auto_reinstall' => $connection['auto_reinstall']
$connection['auto_reinstall'] = false;
$connection['auto_reinstall_start_after'] = 0;

$connection['engine_filename'] = 'engine.php';
$connection['worldId'] = 1;
$connection['serverName'] = 'Test Server';
$connection['gameWorldUrl'] = 'http://localhost/';
$connection['secure_hash_code'] = 'mock_hash_code';
$connection['speed'] = 1;
$connection['round_length'] = 365;


