<?php
// Dummy global config file to satisfy require_once in src/config.php
// This is used by verification scripts running in CLI mode.
global $globalConfig;
if (!isset($globalConfig)) $globalConfig = [];
