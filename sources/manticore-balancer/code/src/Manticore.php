<?php

namespace chart;

use Exception;
use mysqli;

class Manticore
{
    private $connection;

    public function __construct($url)
    {
        $this->connect($url);
    }

    private function connect($url): void
    {
        try {
            $this->connection = new mysqli($url . ":" . WORKER_PORT);
        } catch (Exception $exception) {
            self::logger("Can't connect to worker at :" . $url);
            exit(1);
        }
    }

    public function getIndexes()
    {
        $tables = [];

        $clusterStatus = $this->fetch("show status");

        $clusterName = "";
        foreach ($clusterStatus as $row) {
            if ($row['Counter'] === 'cluster_name') {
                $clusterName = $row['Value'];
            }

            if ($row['Counter'] === "cluster_" . $clusterName . "_indexes" && trim($row['Value']) !== "") {
                $tables = explode(',', $row['Value']);
            }
        }

        return $tables;
    }


    private function fetch($query)
    {
        $result = $this->connection->query($query);

        if ( ! empty($result)) {
            /** @var \mysqli_result $result */
            $result = $result->fetch_all(MYSQLI_ASSOC);
            if ($result !== null) {
                return $result;
            }
        }

        return false;
    }

    public function showThreads()
    {
        return $this->fetch('SHOW TREADS');
    }

    public function reloadIndexes(): void
    {
        $this->connection->query('RELOAD INDEXES');
    }

    public function getChunksCount($index): int
    {
        $indexStatus = $this->fetch('SHOW INDEX ' . $index . ' STATUS');
        foreach ($indexStatus as $row) {

            if ($row["Variable_name"] === 'disk_chunks') {
                return (int)$row["Value"];
            }
        }
        throw new \RuntimeException("Can't get chunks count");
    }


    public function optimize($index): void
    {
        $this->connection->query('OPTIMIZE INDEX ' . $index);
    }

    public static function logger($line): void
    {
        $line = date("Y-m-d H:i:s") . ': ' . $line . "\n";
        echo "$line\n";
    }

}
