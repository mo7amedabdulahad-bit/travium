<?php
$file = dirname(__DIR__) . '/src/config.php';
$content = file_get_contents($file);
$lines = explode("\n", $content);
$count = count($lines);
$start = max(0, $count - 20);
for ($i = $start; $i < $count; $i++) {
    echo ($i+1) . ": " . $lines[$i] . "\n";
}
