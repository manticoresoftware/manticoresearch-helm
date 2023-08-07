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
$labels = null;

include("env_reader.php");

if ($replicationMode !== null &&
    !in_array($replicationMode, [REPLICATION_MODE_MULTI_MASTER, REPLICATION_MODE_MASTER_SLAVE])) {
    $replicationMode = REPLICATION_MODE_MULTI_MASTER;
}

Analog::log("Replication mode: ".$replicationMode);


$api = new ApiClient();
$resources = new Resources($api, $labels, new NotificationStub());
$manticoreJson = new ManticoreJson($clusterName.'_cluster', $binaryPort);

$count = $resources->getActivePodsCount();
$hostname = gethostname();


function notifyBalancers(ApiClient $apiClient, $labels){
    $labels['app.kubernetes.io/component'] = 'balancer';
    $balancerPods = $apiClient->getManticorePods($labels);

    if (isset($balancerPods['items'])) {
        foreach ($balancerPods['items'] as $pod) {

            $balancerIp = $pod['status']['podIP'].":8080";
            $dbgResult = $apiClient->get($balancerIp)->getBody()->getContents();
            Analog::log("Call balancer ".$balancerIp.". Response: ".$dbgResult);
        }
    }
}

Analog::log("Pods count ".$count);

$min = 0;
if (getenv("POD_START_VIA_PROBE") === false ){
    $min = 1;
}

if ($count <= $min) {
    Analog::log("One pod");
    $manticoreJson->startManticore();
    $manticore = new ManticoreConnector('localhost', $qlPort, $clusterName, -1);
    $manticore->setMaxAttempts(180);

    Analog::log("Wait until $hostname came alive");
    $resources->wait($hostname, 60);

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

    Analog::log("Wait until $hostname came alive");
    $resources->wait($hostname, 60);

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
            notifyBalancers($api, $labels);
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
        notifyBalancers($api, $labels);
        exit(1);
    }


    Analog::log("Wait until $hostname came alive");
    $resources->wait($hostname, 60);
    Analog::log("Join to $joinHost");
    $manticore->joinCluster($joinHost.'.'.$workerService);
}

notifyBalancers($api, $labels);
?>
