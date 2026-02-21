<?php
// Patch config.php to use Gpack 347.6 as default
$file = '/home/travium/htdocs/config.php';
$content = file_get_contents($file);
$version = '347.6';

// 1. Add the new gpack version to the list if missing
if (strpos($content, "'$version'") === false) {
    $content = str_replace(
        "'a17a8f72' => ['hash' => 'a17a8f72', 'name' => 'Travian T4.5', 'isNew' => true],",
        "'a17a8f72' => ['hash' => 'a17a8f72', 'name' => 'Travian T4.5', 'isNew' => true]," . "\n" .
        "    '$version' => ['hash' => '$version', 'name' => 'Travian v$version (SVG)', 'isNew' => true],",
        $content
    );
    echo "Added $version to gpack list\n";
} else {
    echo "$version already in gpack list\n";
}

// 2. Update the default to 347.6
$content = preg_replace(
    "/'default'\s*=>\s*'[^']+'/",
    "'default' => '$version'",
    $content
);
echo "Set default gpack to $version\n";

file_put_contents($file, $content);
echo "Config patched: $file\n";

// Verify
preg_match("/'default'\s*=>\s*'([^']+)'/", $content, $m);
echo "Verified default is now: " . ($m[1] ?? 'UNKNOWN') . "\n";
