<?php
// Mock environment for verification script
global $globalConfig;

if (!defined("IS_DEV")) define("IS_DEV", true);
if (!defined("MAP_SIZE")) define("MAP_SIZE", 100);

$globalConfig = [
    'staticParameters' => [
        'default_timezone' => 'UTC',
        'session_timeout' => 3600,
        'indexUrl' => '',
        'global_css_class' => '',
        'default_language' => 'en',
        'forumUrl' => '',
        'answersUrl' => ''
    ]
];

global $connection;
$connection = [
    'database' => [
        'hostname' => 'localhost',
        'username' => 'root',
        'password' => '',
        'database' => 'travian'
    ],
    'auto_reinstall' => false,
    'auto_reinstall_start_after' => 0,
    'engine_filename' => 'engine',
    'worldId' => '1',
    'serverName' => 'Travian',
    'gameWorldUrl' => '',
    'secure_hash_code' => 'abc',
    'speed' => 100, // Testing with speed 100
    'movement_speed_increase' => 1,
    'round_length' => 14,
    'start_time' => time(),
    'secure_hash_code' => 'verification_hax'
];

// Helper to simulate getGame() if needed by mainInclude or others
if (!function_exists('getGame')) {
    function getGame($param) {
        global $config;
        if (isset($config->game->$param)) {
            return $config->game->$param;
        }
        return 0;
    }
}
