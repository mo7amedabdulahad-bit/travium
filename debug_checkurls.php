#!/usr/bin/env php
<?php
/**
 * Debug checkUrls function
 */

require_once __DIR__ . '/servers/s1/include/env.php';
require_once SRC_PATH_PROD . '/bootstrap.php';

echo "===== checkUrls Debug =====\n\n";

// Check if file exists
$filterFile = FILTERING_PATH . "filteredUrls.txt";
echo "Filter file path: $filterFile\n";
echo "File exists: " . (file_exists($filterFile) ? 'YES' : 'NO') . "\n";

if (file_exists($filterFile)) {
    $content = file_get_contents($filterFile);
    echo "File size: " . strlen($content) . " bytes\n";
    echo "First 200 chars: " . substr($content, 0, 200) . "\n";
    
    $invalidPages = explode(",", $content);
    echo "Number of filtered URLs: " . count($invalidPages) . "\n";
    echo "First 5 URLs: " . implode(", ", array_slice($invalidPages, 0, 5)) . "\n";
}

echo "\n===== Testing checkUrls() =====\n\n";

$testStrings = ['myvillage', 'testalliance', 'village'];
foreach ($testStrings as $str) {
    $result = \Core\Helper\StringChecker::checkUrls($str, $str);
    echo "$str: " . ($result ? 'PASS' : 'FAIL') . "\n";
}

echo "\n===== Complete =====\n";
