<?php

use chart\Cache;
use chart\Manticore;
use chart\k8sapi;
use chart\Locker;

require 'vendor/autoload.php';

const OPTIMIZE_FILE = DIRECTORY_SEPARATOR . 'tmp' . DIRECTORY_SEPARATOR . 'optimize.process.lock';

define("WORKER_LABEL", getenv('WORKER_LABEL'));
define("WORKER_PORT", getenv('WORKER_PORT'));

$locker = new Locker('optimize');
$locker->checkLock();

/* First we check if now something optimizing? */

if ($locker->checkOptimizeLock()) {
    Manticore::logger("Optimize don't finished yet");
    $locker->unlock();
}

$api   = new k8sapi();
$cache = new Cache();


$nodes                 = [];
$manticoreStatefulsets = $api->getManticorePods();
$nodesRequest          = $api->getNodes();

if ( ! isset($manticoreStatefulsets['items'])) {
    Manticore::logger("K8s api don't responsed");
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

Manticore::logger(json_encode($nodes));
$checkedWorkers = $cache->get(Cache::CHECKED_WORKERS);
$checkedIndexes = $cache->get(Cache::CHECKED_INDEXES);

foreach ($manticoreStatefulsets['items'] as $pod) {
    if (isset($pod['metadata']['labels']['label'])
        && $pod['metadata']['labels']['label'] === WORKER_LABEL
        && $pod['status']['phase'] === 'Running') {

        Manticore::logger("Check node " . $pod['spec']['nodeName']);
        if (isset($checkedWorkers[$pod['spec']['nodeName']])) {
            Manticore::logger("Skip node " . $pod['spec']['nodeName'] . " cause it already handled");
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
                    $cpuLimit = (int)((int)$cpuLimit / 1000);
                }
                $cpuLimit = ceil($cpuLimit);
            }
        }

        /* Get CPU count from node */
        if ( ! $cpuLimit) {
            $cpuLimit = $nodes[$pod['spec']['nodeName']];
        }

        Manticore::logger("Init Manticore " . $pod['spec']['nodeName'] . " at " . $pod['status']['podIP'] . ":" . WORKER_PORT);
        $manticore = new Manticore($pod['status']['podIP'] . ":" . WORKER_PORT);
        $indexes   = $manticore->getIndexes();

        foreach ($indexes as $index) {
            Manticore::logger("Check index " . $index);
            if (isset($checkedIndexes[$index])) {
                Manticore::logger("Skip index " . $index . " cause it already handled");
                continue;
            }
            $checkedIndexes[$index] = 1;

            $chunks = $manticore->getChunksCount($index);

            Manticore::logger("Check " . $pod['spec']['nodeName'] . "->" . $index . "  ($chunks * 2 > $cpuLimit) " . (($chunks * 2 > $cpuLimit) ? 'true' : 'false'));
            if ($chunks * 2 > $cpuLimit) {
                $manticore->optimize($index);
                $locker->setOptimizeLock($pod['status']['podIP'] . ":" . WORKER_PORT);
                $cache->store(Cache::CHECKED_WORKERS, $checkedWorkers);
                $cache->store(Cache::CHECKED_INDEXES, $checkedIndexes);
                $locker->unlock(0);
            }
        }
        $checkedIndexes                           = [];
        $checkedWorkers[$pod['spec']['nodeName']] = 1;
    }
}

$cache->store(Cache::CHECKED_INDEXES, []);
$cache->store(Cache::CHECKED_WORKERS, []);

$locker->unlock(0);
