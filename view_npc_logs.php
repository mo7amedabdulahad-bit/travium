<?php
/**
 * NPC Log Viewer (PHP version for cross-platform)
 * 
 * View NPC activity logs from command line or web
 * 
 * Usage:
 *   php view_npc_logs.php           - Show last 50 entries
 *   php view_npc_logs.php tail      - Monitor in real-time
 *   php view_npc_logs.php all       - Show all logs
 *   php view_npc_logs.php <npc_name> - Filter by NPC name
 */

// Define required constants before bootstrap
define('IS_DEV', true);
define('ERROR_LOG_FILE', __DIR__ . '/error.log');

$logFile = '/home/travium/logs/npc_activity.log';

echo "\n";
echo "╔════════════════════════════════════════════════════════════════════════════╗\n";
echo "║                    NPC ACTIVITY LOG VIEWER                                 ║\n";
echo "╚════════════════════════════════════════════════════════════════════════════╝\n";
echo "\n";

// Check if log exists
if (!file_exists($logFile)) {
    echo "No log file found. NPCs haven't been processed yet.\n";
    echo "Log will be created at: $logFile\n\n";
    echo "Run automation or wait for NPCs to take actions.\n\n";
    exit(0);
}

$mode = $argv[1] ?? '';

switch ($mode) {
    case 'tail':
        echo "Monitoring NPC activity (Ctrl+C to stop)...\n";
        echo "════════════════════════════════════════════════════════════════════════════\n";
        
        // Simple tail implementation
        $lastSize = 0;
        while (true) {
            clearstatcache();
            $currentSize = filesize($logFile);
            
            if ($currentSize > $lastSize) {
                $fp = fopen($logFile, 'r');
                fseek($fp, $lastSize);
                while (!feof($fp)) {
                    $line = fgets($fp);
                    if ($line !== false) {
                        echo $line;
                    }
                }
                $lastSize = $currentSize;
                fclose($fp);
            }
            
            sleep(1);
        }
        break;
        
    case 'all':
        echo "All NPC Activity:\n";
        echo "════════════════════════════════════════════════════════════════════════════\n";
        echo file_get_contents($logFile);
        break;
        
    case '':
        echo "Last 50 NPC Actions:\n";
        echo "════════════════════════════════════════════════════════════════════════════\n";
        $lines = file($logFile);
        $last50 = array_slice($lines, -50);
        echo implode('', $last50);
        break;
        
    default:
        echo "Filtering for NPC: $mode\n";
        echo "════════════════════════════════════════════════════════════════════════════\n";
        $lines = file($logFile);
        foreach ($lines as $line) {
            if (stripos($line, $mode) !== false) {
                echo $line;
            }
        }
        break;
}

echo "\n";
echo "════════════════════════════════════════════════════════════════════════════\n";
echo "Log file: $logFile\n";
echo "Total entries: " . count(file($logFile)) . "\n";
echo "\n";
