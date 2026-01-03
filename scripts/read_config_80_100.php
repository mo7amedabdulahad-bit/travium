<?php
$file = dirname(__DIR__) . '/src/config.php';
$content = file_get_contents($file);
$lines = explode("\n", $content);
for ($i = 79; $i < 100 && $i < count($lines); $i++) {
    echo ($i+1) . ": " . $lines[$i] . "\n";
}
