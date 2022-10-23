<?php

use Analog\Analog;
use Analog\Handler\EchoConsole;
use Core\K8s\ApiClient;
use Core\K8s\Resources;
use Core\Manticore\ManticoreConnector;
use Core\Manticore\ManticoreJson;
use Core\Notifications\NotificationStub;

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

$api = new ApiClient();
$resources = new Resources($api, $labels, new NotificationStub());
$manticore = new ManticoreConnector('localhost', $qlPort, $clusterName, -1);

if ($manticore->checkClusterName() && !$manticore->isClusterPrimary()) {
    try {
        if (($replicationMode === REPLICATION_MODE_MASTER_SLAVE
                && gethostname() === $resources->getMinReplicaName())
            || ($replicationMode === REPLICATION_MODE_MULTI_MASTER
                && gethostname() === $resources->getOldestActivePodName(false))) {
            $manticoreJson = new ManticoreJson($clusterName.'_cluster', $binaryPort);

            if ($manticoreJson->isAllNodesNonPrimary($resources, $qlPort)) {
                Analog::info("The node is on in the cluster, but is not in primary state. Trying to fix it");
                $manticore->restoreCluster();
                Analog::info("Successfully fixed non-primary cluster state");
            }
        }
    } catch (JsonException $e) {
        Analog::error("Can't parse min or oldest replica JSON. ".$e->getMessage());
    }
}

