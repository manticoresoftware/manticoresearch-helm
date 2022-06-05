<?php


use Core\Cache\Cache;
use Core\K8s\ApiClient;
use Core\Logger\Logger;
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
$manticoreStatefulsets = $api->getManticorePods();
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
    if (isset($pod['metadata']['labels']['label'])
        && $pod['metadata']['labels']['label'] === WORKER_LABEL
        && $pod['status']['phase'] === 'Running'
    ) {
        //  Manticore::logger("Check pod ".$pod['metadata']['name']);
        if (isset($checkedWorkers[$pod['metadata']['name']])) {
//            Manticore::logger("Skip pod ".$pod['metadata']['name']." cause it already handled");
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

//        Manticore::logger("Init Manticore ".$pod['metadata']['name']." at ".$pod['status']['podIP'].":".WORKER_PORT);
        $manticore = new \Core\Manticore\ManticoreConnector($pod['status']['podIP'], WORKER_PORT, WORKER_LABEL, -1);
        $indexes   = $manticore->getTables();

        foreach ($indexes as $index) {
//            Manticore::logger("Check index ".$index);
            if (isset($checkedIndexes[$index])) {
//                Manticore::logger("Skip index ".$index." cause it already handled");
                continue;
            }
            $checkedIndexes[$index] = 1;

            $chunks = $manticore->getChunksCount($index);

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
