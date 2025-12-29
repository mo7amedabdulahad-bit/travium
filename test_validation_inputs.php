#!/usr/bin/env php
<?php
/**
 * Test alliance description validation with actual input
 */

require_once __DIR__ . '/servers/s1/include/env.php';
require_once SRC_PATH_PROD . '/bootstrap.php';

use Core\Helper\StringChecker;

echo "===== Alliance Description Validation Test =====\n\n";

$testInputs = [
    'Plain text',
    'Text with [b]BBCode[/b]',
    'My Alliance Description',
    '[center]Welcome[/center]',
    'Simple description without any special chars',
    '',
    'Test 123',
];

foreach ($testInputs as $input) {
    echo "Input: '$input'\n";
    
    $cleared = StringChecker::clearString($input);
    echo "  After clearString(): '$cleared'\n";
    
    $valid = StringChecker::isValidMessage($input);
    echo "  isValidMessage(): " . ($valid ? 'VALID' : 'INVALID') . "\n";
    
    if (!$valid) {
        // Check which part failed
        $badWords = (new \Core\Helper\BadWordsFilter())->containsBadWords($cleared, $input);
        $urls = StringChecker::checkUrls($cleared, $input);
        echo "  -> Bad words found: " . ($badWords ? 'YES' : 'NO') . "\n";
        echo "  -> URLs check passed: " . ($urls ? 'YES' : 'NO') . "\n";
    }
    
    echo "\n";
}

echo "===== Complete =====\n";
