#!/usr/bin/env php
<?php
/**
 * Debug BadWordsFilter and checkUrls
 */

require_once __DIR__ . '/servers/s1/include/env.php';
require_once SRC_PATH_PROD . '/bootstrap.php';

use Core\Helper\StringChecker;
use Core\Helper\BadWordsFilter;

echo "===== BadWordsFilter Debug =====\n\n";

$testNames = ['MyVillage', 'TestAlliance', 'Village123'];

foreach ($testNames as $name) {
    $cleared = StringChecker::clearString($name);
    
    echo "Original: $name\n";
    echo "Cleared:  $cleared\n";
    
    $badWords = (new BadWordsFilter())->containsBadWords($cleared, $name);
    echo "Bad words found: " . ($badWords ? 'YES' : 'NO') . "\n";
    
    $urls = StringChecker::checkUrls($cleared, $name);
    echo "URLs check passed: " . ($urls ? 'YES' : 'NO') . "\n";
    
    $final = !$badWords && $urls;
    echo "Final result: " . ($final ? 'VALID' : 'INVALID') . "\n";
    echo str_repeat('-', 50) . "\n";
}

echo "\n===== Complete =====\n";
