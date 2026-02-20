<?php

$srcBase = 'C:\\Users\\Mohamed\\OneDrive\\Desktop\\NPC Project\\REAL TRAVIAN';
$destBase = 'C:\\Users\\Mohamed\\OneDrive\\Desktop\\NPC Project\\travium\\integrations\\cdn';

$srcGpack = $srcBase . '\\cdn.legends.travian.com\\gpack\\326.6';
$destGpack = $destBase . '\\326.6';

if (!is_dir($srcGpack)) {
    die("Source Gpack not found: $srcGpack\n");
}

function copyRecursive($src, $dest) {
    if (!is_dir($dest)) {
        if (!mkdir($dest, 0777, true)) {
            echo "Failed to create directory: $dest\n";
            return;
        }
    }
    $dir = opendir($src);
    while (false !== ($file = readdir($dir))) {
        if (($file != '.') && ($file != '..')) {
            if (is_dir($src . DIRECTORY_SEPARATOR . $file)) {
                copyRecursive($src . DIRECTORY_SEPARATOR . $file, $dest . DIRECTORY_SEPARATOR . $file);
            } else {
                copy($src . DIRECTORY_SEPARATOR . $file, $dest . DIRECTORY_SEPARATOR . $file);
            }
        }
    }
    closedir($dir);
}

echo "Copying Gpack assets (v326.6) to CDN folder...\n";
copyRecursive($srcGpack, $destGpack);
echo "Gpack assets copied.\n";

// Copy JS files
$srcJs = $srcBase . '\\ts30.x3.europe.travian.com\\js';
$destJs = $destBase . '\\js';

if (is_dir($srcJs)) {
    echo "Copying JS files to CDN folder...\n";
    copyRecursive($srcJs, $destJs);
    echo "JS files copied.\n";
}

echo "Migration complete!\n";
