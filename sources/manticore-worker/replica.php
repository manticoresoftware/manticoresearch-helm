<?php

use Core\K8s\ApiClient;
use Core\K8s\Resources;
use Core\Manticore\ManticoreConnector;
use Core\Manticore\ManticoreJson;
use Core\Notifications\NotificationStub;
use Analog\Analog;
use Analog\Handler\EchoConsole;


require 'vendor/autoload.php';

const REPLICATION_MODE_MULTI_MASTER = 'multi-master';
const REPLICATION_MODE_MASTER_SLAVE = 'master-slave';

Analog::handler(EchoConsole::init());


$qlPort = null;
$binaryPort = null;
$clusterName = null;
$balancerUrl = null;
$instance = null;
$workerService = null;
$notAddTablesAutomatically = null;
$replicationMode = null;

$variables = [
    'qlPort' => ['env' => 'MANTICORE_PORT', 'type' => 'int'],
    'binaryPort' => ['env' => 'MANTICORE_BINARY_PORT', 'type' => 'int'],
    'clusterName' => ['env' => 'CLUSTER_NAME', 'type' => 'string'],
    'balancerUrl' => ['env' => 'BALANCER_URL', 'type' => 'string'],
    'instance' => ['env' => 'INSTANCE_LABEL', 'type' => 'string'],
    'workerService' => ['env' => 'WORKER_SERVICE', 'type' => 'string'],
    'replicationMode' => ['env' => 'REPLICATION_MODE', 'type' => 'string'],
    'notAddTablesAutomatically' => ['env' => 'AUTO_ADD_TABLES_IN_CLUSTER', 'type' => 'bool'],
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


if ($replicationMode !== null &&
    !in_array($replicationMode, [REPLICATION_MODE_MULTI_MASTER, REPLICATION_MODE_MASTER_SLAVE])) {
    $replicationMode = REPLICATION_MODE_MULTI_MASTER;
}

Analog::log("Replication mode: ".$replicationMode);

$labels = [
    'app.kubernetes.io/component' => 'worker',
    'app.kubernetes.io/instance' => $instance,
];


$api = new ApiClient();
$resources = new Resources($api, $labels, new NotificationStub());
$manticoreJson = new ManticoreJson($clusterName.'_cluster', $binaryPort);

$count = $resources->getActivePodsCount();

Analog::log("Pods count ".$count);
if ($count <= 1) {
    Analog::log("One pod");
    $manticoreJson->startManticore();
    $manticore = new ManticoreConnector('localhost', $qlPort, $clusterName, -1);
    $manticore->setMaxAttempts(180);
    if ($manticore->checkClusterName()) {
        Analog::log('Cluster exist');
    } else {
        $manticore->createCluster();
        Analog::log('Cluster created');
    }
    if ($notAddTablesAutomatically) {
        $manticore->addNotInClusterTablesIntoCluster();
    }
} elseif ($manticoreJson->getConf() !== [] && $manticoreJson->hasCluster()) {
    Analog::log("Non empty conf");

    $manticoreJson->checkNodesAvailability($resources, $qlPort, $clusterName, 5);
    $manticoreJson->startManticore();

    $manticore = new ManticoreConnector('localhost', $qlPort, null, -1);
    $manticore->setCustomClusterName($clusterName);
    $manticore->setMaxAttempts(180);

    if ($manticore->checkClusterName()) {
        Analog::log('Cluster exist');
        if ($notAddTablesAutomatically) {
            $manticore->addNotInClusterTablesIntoCluster();
        }
    } else {
        try {
            if ($replicationMode === REPLICATION_MODE_MASTER_SLAVE) {
                $joinHost = $resources->getMinReplicaName();
                $manticore->setMaxAttempts(-1);
            } else {
                $joinHost = $resources->getOldestActivePodName();
            }
        } catch (JsonException $e) {
            $joinHost = false;
        }
        if (empty($joinHost)) {
            Analog::log("No host to join");
            exit(1);
        }
        Analog::log("Join to $joinHost");
        $manticore->joinCluster($joinHost.'.'.$workerService);
    }
} else {
    Analog::log("Empty conf with more than one node in cluster");
    $manticoreJson->startManticore();

    $manticore = new ManticoreConnector('localhost', $qlPort, null, -1);
    $manticore->setCustomClusterName($clusterName);
    $manticore->setMaxAttempts(180);

    try {
        if ($replicationMode === REPLICATION_MODE_MASTER_SLAVE) {
            $joinHost = $resources->getMinReplicaName();
            $manticore->setMaxAttempts(-1);
        } else {
            $joinHost = $resources->getOldestActivePodName();
        }
    } catch (JsonException $e) {
        $joinHost = false;
    }

    if (empty($joinHost)) {
        Analog::log("No host to join");
        exit(1);
    }

    Analog::log("Join to $joinHost");
    $manticore->joinCluster($joinHost.'.'.$workerService);
}
?>
