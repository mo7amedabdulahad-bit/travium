<?php
// Dummy global config file to satisfy require_once in src/config.php
// This is used by verification scripts running in CLI mode.

// REMOVED global keyword to ensure we modify the variable in the caller's scope
// whether it is global or local.
echo "debug: dummy_global_config loaded. defined vars: " . implode(',', array_keys(get_defined_vars())) . "\n";

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
$globalConfig['staticParameters']['default_timezone'] = 'UTC';
$globalConfig['staticParameters']['default_language'] = 'en';
$globalConfig['staticParameters']['default_direction'] = 'LTR';
$globalConfig['staticParameters']['default_dateFormat'] = 'y.m.d';
$globalConfig['staticParameters']['default_timeFormat'] = 'H:i';
$globalConfig['staticParameters']['session_timeout'] = 3600;
$globalConfig['staticParameters']['default_payment_location'] = 1;
$globalConfig['staticParameters']['global_css_class'] = 'travium';

// Misc keys found in config.php
$globalConfig['auto_reinstall'] = false;

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
