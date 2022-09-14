<?php


use Core\Cache\Cache;
use Core\K8s\ApiClient;
use Core\K8s\Resources;
use Core\Manticore\ManticoreConnector;
use Core\Mutex\Locker;
use Analog\Analog;
use Analog\Handler\EchoConsole;
use Core\Notifications\NotificationStub;

require 'vendor/autoload.php';
Analog::handler(EchoConsole::init());


$workerPort    = null;
$balancerPort  = null;
$clusterName   = null;
$instance      = null;
$configMapPath = null;
$workerService = null;

$variables = [
    'workerPort'    => ['env' => 'WORKER_PORT', 'type' => 'int'],
    'balancerPort'  => ['env' => 'BALANCER_PORT', 'type' => 'int'],
    'clusterName'   => ['env' => 'CLUSTER_NAME', 'type' => 'string'],
    'instance'      => ['env' => 'INSTANCE_LABEL', 'type' => 'string'],
    'configMapPath' => ['env' => 'CONFIGMAP_PATH', 'type' => 'string'],
    'workerService' => ['env' => 'WORKER_SERVICE', 'type' => 'string'],
];


foreach ($variables as $variable => $desc) {
    $$variable = getenv($desc['env']);

    if ($$variable === false) {
        Analog::log($desc['env']." is not defined\n");
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

if ( ! file_exists($configMapPath)) {
    throw new RuntimeException("Searchd config is not mounted");
}

$cache  = new Cache();
$locker = new Locker('observer');
$locker->checkLock();


$resources = new Resources(new ApiClient(), $labels, new NotificationStub());

if ($resources->getActivePodsCount() === 0) {
    Analog::log("No workers found");
    $locker->unlock();
}
$oldestWorker = $resources->getOldestActivePodName();

if (empty($oldestWorker)) {
    throw new RuntimeException("Can't find oldest worker");
}

$manticore = new ManticoreConnector($oldestWorker.'.'.$workerService, $workerPort, null, -1);
$tables    = $manticore->getTables(false);
$podsIps   = $resources->getPodsIp();

if ($tables !== []) {
    $previousHash = $cache->get(Cache::INDEX_HASH);
    $hash         = sha1(implode('.', $tables).implode($podsIps));

    if ($previousHash !== $hash) {
        Analog::log("Starting recompiling config");
        saveConfig($tables, $podsIps, $balancerPort, $configMapPath);
        $cache->store(Cache::INDEX_HASH, $hash);
    }
} else {
    Analog::log("No tables found");
    $locker->unlock();
}


function saveConfig($indexes, $nodes, $port, $configMapPath)
{
    $searchdConfig = file_get_contents($configMapPath);
    $prependConfig = '';
    foreach ($indexes as $index) {
        $prependConfig .= "\n\nindex ".$index."\n".
            "{\n".
            "\ttype = distributed\n".
            "\tha_strategy = roundrobin\n".
            "\tagent = ".implode("|", $nodes)."\n".
            "}\n\n";
    }

    file_put_contents(
        DIRECTORY_SEPARATOR.'etc'.
        DIRECTORY_SEPARATOR.'manticoresearch'.
        DIRECTORY_SEPARATOR.'manticore.conf',
        $prependConfig.$searchdConfig
    );

    (new ManticoreConnector('localhost', $port, null, -1))->reloadIndexes();
}


$locker->unlock(0);
