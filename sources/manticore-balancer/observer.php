<?php


use Core\Cache\Cache;
use Core\K8s\ApiClient;
use Core\Logger\Logger;
use Core\Manticore\ManticoreConnector;
use Core\Mutex\Locker;

require 'vendor/autoload.php';

define("CONFIGMAP_PATH", getenv('CONFIGMAP_PATH'));
define("BALANCER_PORT", getenv('BALANCER_PORT'));
define("WORKER_PORT", getenv('WORKER_PORT'));
define('WORKER_LABEL', getenv('WORKER_LABEL'));


if ( ! file_exists(CONFIGMAP_PATH)) {
    throw new RuntimeException("Searchd config is not mounted");
}

$locker = new Locker('observer');
$locker->checkLock();


$api   = new ApiClient();
$cache = new Cache();

$manticoreStatefulsets = $api->getManticorePods(WORKER_LABEL);

if ( ! isset($manticoreStatefulsets['items'])) {
    Logger::log("FATAL: No response from k8s API");
    exit(1);
}


$manticorePods = [];

foreach ($manticoreStatefulsets['items'] as $pod) {
    if ($pod['status']['phase'] === 'Running') {
        $manticorePods[$pod["metadata"]['name']] = $pod['status']['podIP'];
    }
}

if (empty($manticorePods)) {
    Logger::log("No workers found");
    $locker->unlock();
}

$podsIps = array_values($manticorePods);
ksort($manticorePods);
$url = array_shift($manticorePods);

/**
 * Get current state of indexes. For that we request first manticore node directly.
 */


$manticore = new ManticoreConnector($url, WORKER_PORT, WORKER_LABEL, -1);
$tables    = $manticore->getTables(false);

if ($tables !== []) {
    $previousHash = $cache->get(Cache::INDEX_HASH);
    $hash         = sha1(implode('.', $tables) . implode($podsIps));

    if ($previousHash !== $hash) {
        Logger::log("Starting recompiling config");
        saveConfig($tables, $podsIps);
        $cache->store(Cache::INDEX_HASH, $hash);
    }

} else {
    Logger::log("No tables found");
    $locker->unlock();
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

    (new ManticoreConnector('localhost', BALANCER_PORT, WORKER_LABEL, -1))->reloadIndexes();
}


$locker->unlock(0);
