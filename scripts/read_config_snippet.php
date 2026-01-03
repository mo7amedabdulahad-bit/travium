<?php
$file = dirname(__DIR__) . '/src/config.php';
if (!file_exists($file)) die("File not found");
$content = file_get_contents($file);
// Print first 50 lines
$lines = explode("\n", $content);
for ($i = 0; $i < 50; $i++) {
    if (isset($lines[$i])) echo ($i+1) . ": " . $lines[$i] . "\n";
}
