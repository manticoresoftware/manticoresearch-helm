<?php

use chart\k8sapi;

require 'vendor/autoload.php';

define("INDEX_HASH_STORAGE", 'indexhash.sha1');
define("LOG_STORAGE", 'run.log');
define("LOCK_FILE", DIRECTORY_SEPARATOR . 'tmp' . DIRECTORY_SEPARATOR . 'observer.lock');
define("CONFIGMAP_PATH", getenv('CONFIGMAP_PATH'));
define("BALANCER_PORT", getenv('BALANCER_PORT'));
define("WORKER_PORT", getenv('WORKER_PORT'));
define('WORKER_LABEL', getenv('WORKER_LABEL'));

if ( ! file_exists(CONFIGMAP_PATH)) {
    logger("Searchd config is not mounted");
    exit(1);
}

$fp = fopen(LOCK_FILE, 'w+');

if ( ! flock($fp, LOCK_EX | LOCK_NB)) {
    logger("Another process of Observer already runned");
    fclose($fp);
    exit(1);
}

$api = new k8sapi();

$manticoreStatefulsets = $api->getManticorePods();

if ( ! isset($manticoreStatefulsets['items'])) {
    logger("K8s api don't responsed");
    exit(1);
}


$manticorePods = [];

foreach ($manticoreStatefulsets['items'] as $pod) {
    if (isset($pod['metadata']['labels']['label'])
        && $pod['metadata']['labels']['label'] === WORKER_LABEL) {
        if ($pod['status']['phase'] === 'Running') {
            $manticorePods[$pod["metadata"]['name']] = $pod['status']['podIP'];
        }
    }
}

if (empty($manticorePods)) {
    logger("No workers found");
    fclose($fp);
    exit(0);
}

$podsIps = array_values($manticorePods);
ksort($manticorePods);
$url = array_shift($manticorePods);

/**
 * Get current state of indexes. For that we request first manticore node directly.
 */

try {
    $connection = new mysqli($url . ":" . WORKER_PORT);
} catch (Exception $exception) {
    logger("Can't connect to worker at :" . $url);
    fclose($fp);
    exit(0);
}


/**
 * @var $clusterStatus mysqli_result
 */

$clusterStatus = $connection->query("show status");
if ($clusterStatus !== null) {

    $clusterStatus = (array)$clusterStatus->fetch_all(MYSQLI_ASSOC);

    $clusterName = "";
    foreach ($clusterStatus as $row) {
        if ($row['Counter'] === 'cluster_name') {
            $clusterName = $row['Value'];
        }


        if ($row['Counter'] === "cluster_" . $clusterName . "_indexes") {
            $tables = explode(',', $row['Value']);
        }
    }

    if ( ! empty($tables)) {

        $hash = sha1(implode('.', $tables) . implode($podsIps));

        $previousHash = '';
        if (file_exists(INDEX_HASH_STORAGE)) {
            $previousHash = trim(file_get_contents(INDEX_HASH_STORAGE));
        }
        if ($previousHash !== $hash) {
            logger("Start recompiling config");
            saveConfig($tables, $podsIps);
            file_put_contents(INDEX_HASH_STORAGE, $hash);
        }

    } else {
        logger("No tables found");
        fclose($fp);
        exit(0);
    }
}


function logger($line)
{
    $line = date("Y-m-d H:i:s") . ': ' . $line . "\n";
    echo "$line\n";
    file_put_contents(LOG_STORAGE, $line, FILE_APPEND);
}

function saveConfig($indexes, $nodes)
{
    $searchdConfig = file_get_contents(CONFIGMAP_PATH);
    $prependConfig = '';
    foreach ($indexes as $index) {
        $prependConfig .= "\n\nindex " . $index . "\n" .
                          "{\n" .
                          "\ttype = distributed\n" .
                          "\tha_strategy = roundrobin\n" .
                          "\tagent = " . implode("|", $nodes) . "\n" .
                          "}\n\n";
    }

    file_put_contents(DIRECTORY_SEPARATOR . 'etc' .
                      DIRECTORY_SEPARATOR . 'manticoresearch' .
                      DIRECTORY_SEPARATOR . 'manticore.conf', $prependConfig . $searchdConfig);

    reloadIndexes();
}

function reloadIndexes()
{
    $connection = new mysqli('localhost:' . BALANCER_PORT);
    $connection->query("RELOAD INDEXES");
}


flock($fp, LOCK_UN);
fclose($fp);
