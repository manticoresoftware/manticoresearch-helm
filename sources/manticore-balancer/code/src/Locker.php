<?php

namespace chart;

use mysqli;

class Locker
{
    private $fp;
    private $name;
    private $optimizeLockFile;

    public function __construct($name)
    {
        $this->fp = fopen(DIRECTORY_SEPARATOR.'tmp'.DIRECTORY_SEPARATOR.$name.'.lock', 'wb+');
        $this->name = $name;
        if (defined("OPTIMIZE_FILE")) {
            $this->optimizeLockFile = OPTIMIZE_FILE;
        }
    }

    public function checkLock(): bool
    {
        if (!flock($this->fp, LOCK_EX | LOCK_NB)) {
            Manticore::logger("Another process of $this->name already runned");
            $this->unlock();
        }

        return true;
    }

    public function unlock($exitStatus = 1): void
    {
        fclose($this->fp);
        exit($exitStatus);
    }

    public function checkOptimizeLock(): bool
    {
        if ($this->optimizeLockFile !== null && file_exists($this->optimizeLockFile)) {
            $ip = file_get_contents(OPTIMIZE_FILE);
            $manticore = new Manticore($ip);
            $rows = $manticore->showThreads();

            if ($rows) {
                foreach ($rows as $row) {
                    if (strpos($row, 'SYSTEM OPTIMIZE') !== false) {
                        return true;
                    }
                }
            }

            unlink(OPTIMIZE_FILE);
        }

        return false;
    }

    public function setOptimizeLock($ip): void
    {
        if ($this->optimizeLockFile === null) {
            throw new \http\Exception\RuntimeException("Optimize lock file is not setted");
        }
        file_put_contents($this->optimizeLockFile, $ip);
    }
}
