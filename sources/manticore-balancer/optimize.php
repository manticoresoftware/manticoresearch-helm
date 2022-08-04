<?php

use Core\Cache\Cache;
use Core\K8s\ApiClient;
use Core\Manticore\ManticoreConnector;
use Core\Mutex\Locker;
use Analog\Analog;
use Analog\Handler\EchoConsole;

require 'vendor/autoload.php';
Analog::handler(EchoConsole::init());

const OPTIMIZE_FILE = DIRECTORY_SEPARATOR.'tmp'.DIRECTORY_SEPARATOR.'optimize.process.lock';

$workerPort        = null;
$clusterName       = null;
$instance          = null;
$chunksCoefficient = null;

$variables = [
    'workerPort'        => ['env' => 'WORKER_PORT', 'type' => 'int'],
    'clusterName'       => ['env' => 'CLUSTER_NAME', 'type' => 'string'],
    'instance'          => ['env' => 'INSTANCE_LABEL', 'type' => 'string'],
    'chunksCoefficient' => ['env' => 'CHUNKS_COEFFICIENT', 'type' => 'int'],
];

foreach ($variables as $variable => $desc) {
    $$variable = getenv($desc['env']);

    if ($$variable === false) {
        Analog::error($desc['env']." is not defined\n");
        exit(1);
    }

    if ($desc['type'] === 'int') {
        $$variable = (int)$$variable;
    } elseif ($desc['type'] === 'bool') {
        $$variable = (bool)$$variable;
    }
}


$labels = [
    'app.kubernetes.io/component' => 'worker',
    'app.kubernetes.io/instance'  => $instance,
];


$locker = new Locker('optimize');
$locker->checkLock();

/* First we check if now something optimizing? */

if ($locker->checkOptimizeLock(OPTIMIZE_FILE, $workerPort)) {
    Analog::log("Optimize hasn't finished yet");
    $locker->unlock();
}

$api   = new ApiClient();
$cache = new Cache();


$nodes                 = [];
$manticoreStatefulsets = $api->getManticorePods($labels);
$nodesRequest          = $api->getNodes();

if ( ! isset($manticoreStatefulsets['items'])) {
    Analog::log("K8S API didn't respond");
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
                    $cpuLimit = (int)((int)$cpuLimit / 1000);
                }
                $cpuLimit = ceil($cpuLimit);
            }
        }

        /* Get CPU count from node */
        if ( ! $cpuLimit) {
            $cpuLimit = (int)$nodes[$pod['spec']['nodeName']];
        }

        $manticore = new ManticoreConnector($pod['status']['podIP'], $workerPort, $clusterName, -1);
        $indexes   = $manticore->getTables(false);

        foreach ($indexes as $index) {
            if (isset($checkedIndexes[$index])) {
                continue;
            }
            $checkedIndexes[$index] = 1;

            $chunks = $manticore->getChunksCount($index, false);

            if ($chunks > $cpuLimit * $chunksCoefficient) {
                Analog::log(
                    "Starting OPTIMIZE $index ".$pod['metadata']['name']."  ($chunks > $cpuLimit * ".$chunksCoefficient.") ".
                    (($chunks > $cpuLimit * $chunksCoefficient) ? 'true' : 'false')
                );

                $manticore->optimize($index, $cpuLimit * $chunksCoefficient);
                $locker->setOptimizeLock($pod['status']['podIP']);
                $cache->store(Cache::CHECKED_WORKERS, $checkedWorkers);
                $cache->store(Cache::CHECKED_INDEXES, $checkedIndexes);

                Analog::log("OPTIMIZED started successfully. Stopping watching.");
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
