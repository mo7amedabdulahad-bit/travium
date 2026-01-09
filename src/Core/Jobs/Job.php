<?php

namespace Core\Jobs;

use Core\Config;
use Core\Database\DB;
use Core\ErrorHandler;
use function gc_enable;
use function logError;

class Job
{
    private $lastReload = 0;
    private $interval;
    private $callback;
    private $name;

    /**
     * @param            $name
     * @param            $interval
     * @param callable $callBack
     * @param bool|FALSE $daemon
     * #return Job
     */
    public function __construct($name, $interval, $callBack, $daemon = FALSE)
    {
        global $prgName;
        $this->lastReload = time();
        $prgName = $name;
        $this->name = $name;
        $this->callback = $callBack;
        if ($daemon) {
            global $PIDs, $loop;
            $PIDs[$name] = pcntl_fork();
            $loop = TRUE;
            pcntl_signal(SIGTERM,
                function ($signal) {
                    global $prgName, $loop;
                    $loop = FALSE;
                });
            if ($PIDs[$name] === 0) {
                $this->setInterval($interval);
                $db = DB::getInstance()->forceNewDatabase();
                $config = Config::getInstance();
                ini_set("memory_limit", -1);
                $db->query("UPDATE config SET needsRestart=0");
                gc_enable();

                $nextLoop = miliseconds();
                $consecutiveFailures = 0; // Track consecutive failures to prevent infinite error loops
                $lastHeartbeat = time();
                
                logError("[$prgName] Daemon started. PID: " . getmypid());

                while ($loop) {
                    mt_srand((int)make_seed());
                    
                    // Heartbeat every 60 seconds
                    if (time() - $lastHeartbeat > 60) {
                        logError("[$prgName] Heartbeat - Memory: " . floor(memory_get_usage() / 1024 / 1024) . "MB");
                        $lastHeartbeat = time();
                    }
                    
                    // BUGFIX: Validate database connection before each loop iteration
                    if (!$db->checkConnection()) {
                        logError("[$prgName] Database connection lost, attempting to reconnect...");
                        $consecutiveFailures++;
                        if ($consecutiveFailures > 10) {
                            logError("[$prgName] Too many consecutive database failures (10+), exiting daemon (Code 1)");
                            $loop = false;
                            break;
                        }
                        sleep(2);
                        continue; // Skip this iteration, retry connection next loop
                    }
                    
                    $config->dynamic = (object)$db->query("SELECT * FROM config LIMIT 1")->fetch_assoc();
                    if ($config->dynamic->needsRestart == 1) {
                        logError("[$prgName] needsRestart flag detected. Exiting daemon to reload (Code 1)");
                        $loop = false;
                        pcntl_signal_dispatch();
                        continue;
                    }
                    $exclude = $name == 'postService' && $config->dynamic->postServiceDone == 0;
                    if ($config->dynamic->finishStatusSet && !$exclude) {
                        logError("[$prgName] Finish status set. Exiting daemon (Code 1)");
                        $loop = false;
                    }
                    
                    try {
                        if ($config->dynamic->automationState || $exclude) {
                            $this->runJob($callBack, TRUE);
                            $consecutiveFailures = 0; // Reset counter on successful job execution
                        } else {
                            // Log occasionally if automation is paused
                            if (rand(0, 100) < 5) logError("[$prgName] Automation paused (automationState=0)");
                        }
                    } catch (\Exception $e) {
                        $consecutiveFailures++;
                        logError("[$prgName] Exception in job execution (failure #$consecutiveFailures): " . $e->getMessage());
                        ErrorHandler::getInstance()->handleExceptions($e);
                        
                        // BUGFIX: Reconnect database after exception
                        $db->forceNewDatabase();
                        
                        if ($consecutiveFailures > 10) {
                            logError("[$prgName] Too many consecutive exceptions (10+), exiting daemon (Code 1)");
                            $loop = false;
                            break;
                        }
                        sleep(2);
                    } catch (\Error $e) {
                        $consecutiveFailures++;
                        logError("[$prgName] Fatal error in job execution (failure #$consecutiveFailures): " . $e->getMessage());
                        ErrorHandler::getInstance()->handleExceptions($e);
                        
                        // BUGFIX: Reconnect database after error
                        $db->forceNewDatabase();
                        
                        if ($consecutiveFailures > 10) {
                            logError("[$prgName] Too many consecutive fatal errors (10+), exiting daemon (Code 1)");
                            $loop = false;
                            break;
                        }
                        sleep(2);
                    }
                    if ($config->game->start_time > time()) sleep(5);
                    usleep(max($this->interval * 1000 * 1000, 500));
                    pcntl_signal_dispatch();
                    if (rand(5, 100) % 5 == 0) {
                        gc_collect_cycles(); //Forces collection of any existing garbage cycles
                    }
                }
                logError("[$prgName] Daemon loop ended. Exiting process with code 1 to trigger systemd restart.");
                exit(1);
            }
        } else {
            $this->setInterval($interval);
            return $this;
        }
        return null;
    }

    private function setInterval($sec)
    {
        $this->interval = $sec;
    }

    public function runJob($callBack, $daemon = false)
    {
        if (!$this->checkInterval($daemon)) {
            return;
        }
        $db = DB::getInstance();
        if (!$db->checkConnection()) {
            return;
        }
        $this->updateLastReload();
        if (is_callable($callBack)) {
            $callBack();
        } else {
            foreach ($callBack as $cb) {
                try {
                    if (method_exists($cb, "runAction")) {
                        // Log sub-job execution start
                        // $subJobName = property_exists($cb, 'name') ? $cb->name : 'Unknown SubJob';
                        // logError("[{$this->name}] Starting sub-job: $subJobName");

                        $cb->runAction();
                        
                        // logError("[{$this->name}] Finished sub-job: $subJobName");
                    } else {
                        logError(print_r($this, TRUE));
                    }
                } catch (\Exception $e) {
                    ErrorHandler::getInstance()->handleExceptions($e);
                    sleep(2);
                }
            }
        }
    }

    private function checkInterval($daemon)
    {
        if ($daemon) return true;
        
        $elapsed = time() - $this->lastReload;
        return $elapsed >= $this->interval;
    }

    private function updateLastReload()
    {
        $this->lastReload = time();
    }

    public function runAction()
    {
        try {
            $this->runJob($this->callback);
        } catch (\Exception $e) {
            logError("[Job ERROR] {$this->name}: " . $e->getMessage());
            ErrorHandler::getInstance()->handleExceptions($e);
        }
    }
}