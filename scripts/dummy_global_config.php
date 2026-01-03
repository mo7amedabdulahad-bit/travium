<?php
// Dummy global config file to satisfy require_once in src/config.php
// This is used by verification scripts running in CLI mode.
global $globalConfig;
if (!isset($globalConfig)) $globalConfig = [];

// Initialize required keys to prevent "Undefined array key" warnings in src/config.php
if (!isset($globalConfig['staticParameters'])) $globalConfig['staticParameters'] = [];
if (!isset($globalConfig['dataSources'])) $globalConfig['dataSources'] = [];
if (!isset($globalConfig['voting'])) $globalConfig['voting'] = [];
if (!isset($globalConfig['mailer'])) $globalConfig['mailer'] = [];

// Mock essentials
$globalConfig['staticParameters']['recaptcha_public_key'] = 'mock_key';
$globalConfig['staticParameters']['recaptcha_private_key'] = 'mock_key';
$globalConfig['staticParameters']['indexUrl'] = 'http://localhost/';
$globalConfig['dataSources']['globalDB'] = [
    'hostname' => 'localhost',
    'username' => 'travium',
    'password' => 'travium',
    'database' => 'travium',
    'charset' => 'utf8mb4'
];

if (!defined("CONNECTION_FILE")) {
    define("CONNECTION_FILE", __DIR__ . '/dummy_connection.php');
}
