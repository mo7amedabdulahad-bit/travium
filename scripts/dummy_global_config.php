<?php
// Dummy global config file to satisfy require_once in src/config.php
// This is used by verification scripts running in CLI mode.
global $globalConfig;
if (!isset($globalConfig)) $globalConfig = [];

if (!defined("CONNECTION_FILE")) {
    define("CONNECTION_FILE", __DIR__ . '/dummy_connection.php');
}
