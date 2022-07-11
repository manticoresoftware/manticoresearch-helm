<?php


use Core\Cache\Cache;
use Core\K8s\ApiClient;
use Core\Logger\Logger;
use Core\Manticore\ManticoreConnector;
use Core\Mutex\Locker;

require 'vendor/autoload.php';

const OPTIMIZE_FILE = DIRECTORY_SEPARATOR.'tmp'.DIRECTORY_SEPARATOR.'optimize.process.lock';

define("WORKER_LABEL", getenv('WORKER_LABEL'));
define("WORKER_PORT", getenv('WORKER_PORT'));
define("CHUNKS_COEFFICIENT", (int) getenv('CHUNKS_COEFFICIENT'));

$locker = new Locker('optimize');
$locker->checkLock();

/* First we check if now something optimizing? */

if ($locker->checkOptimizeLock(OPTIMIZE_FILE)) {
    Logger::log("Optimize hasn't finished yet");
    $locker->unlock();
}

$api   = new ApiClient();
$cache = new Cache();


$nodes                 = [];
$manticoreStatefulsets = $api->getManticorePods(WORKER_LABEL);
$nodesRequest          = $api->getNodes();

if ( ! isset($manticoreStatefulsets['items'])) {
    Logger::log("K8S API didn't respond");
    $locker->unlock();
}

$manticorePods = [];

$nodes = prepareNodes($nodesRequest['items']);

function prepareNodes($nodes): array
{
    $clearedNodes = [];
    foreach ($nodes as $node) {
        $clearedNodes[$node['metadata']['name']] = $node['status']['capacity']['cpu'];
    }

    return $clearedNodes;
}

$checkedWorkers = $cache->get(Cache::CHECKED_WORKERS);
$checkedIndexes = $cache->get(Cache::CHECKED_INDEXES);

foreach ($manticoreStatefulsets['items'] as $pod) {
    if ($pod['status']['phase'] === 'Running') {
        if (isset($checkedWorkers[$pod['metadata']['name']])) {
            continue;
        }

        $cpuLimit = false;

        /* Get CPU count as pod limit resources */
        foreach ($pod['spec']['containers'] as $container) {
            if (strpos($container['name'], 'worker') === false) {
                continue;
            }

            if (isset($container['resources']['limits']['cpu'])) {
                $cpuLimit = $container['resources']['limits']['cpu'];
                if (stripos($cpuLimit, 'm') !== false) {
                    $cpuLimit = (int) ((int) $cpuLimit / 1000);
                }
                $cpuLimit = ceil($cpuLimit);
            }
        }

        /* Get CPU count from node */
        if ( ! $cpuLimit) {
            $cpuLimit = (int) $nodes[$pod['spec']['nodeName']];
        }

        $manticore = new ManticoreConnector($pod['status']['podIP'], WORKER_PORT, WORKER_LABEL, -1);
        $indexes   = $manticore->getTables(false);

        foreach ($indexes as $index) {
            if (isset($checkedIndexes[$index])) {
                continue;
            }
            $checkedIndexes[$index] = 1;

            $chunks = $manticore->getChunksCount($index, false);

            if ($chunks > $cpuLimit * CHUNKS_COEFFICIENT) {
                Logger::log("Starting OPTIMIZE $index ".$pod['metadata']['name']."  ($chunks > $cpuLimit * ".CHUNKS_COEFFICIENT.") ".
                    (($chunks > $cpuLimit * CHUNKS_COEFFICIENT) ? 'true' : 'false'));

                $manticore->optimize($index, $cpuLimit * CHUNKS_COEFFICIENT);
                $locker->setOptimizeLock($pod['status']['podIP']);
                $cache->store(Cache::CHECKED_WORKERS, $checkedWorkers);
                $cache->store(Cache::CHECKED_INDEXES, $checkedIndexes);

                Logger::log("OPTIMIZED started successfully. Stopping watching.");
                $locker->unlock(0);
            }
        }
        $checkedIndexes                           = [];
        $checkedWorkers[$pod['metadata']['name']] = 1;
    }
}

$cache->store(Cache::CHECKED_INDEXES, []);
$cache->store(Cache::CHECKED_WORKERS, []);

$locker->unlock(0);
