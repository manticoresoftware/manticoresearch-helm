<?php

use Core\K8s\ApiClient;
use Core\K8s\Resources;
use Core\Logger\Logger;
use Core\Manticore\ManticoreConnector;
use Core\Manticore\ManticoreJson;
use Core\Notifications\NotificationStub;

require 'vendor/autoload.php';

$port                      = getenv("MANTICORE_PORT");
$clusterName               = getenv("CLUSTER_NAME");
$balancerUrl               = getenv('BALANCER_URL');
$label                     = getenv('WORKER_LABEL');
$workerService             = getenv('WORKER_SERVICE');
$notAddTablesAutomatically = (bool) getenv('AUTO_ADD_TABLES_IN_CLUSTER');

if (empty($port)) {
    die("MANTICORE_PORT is not set\n");
}

$api           = new ApiClient();
$resources     = new Resources($api, $label, new NotificationStub());
$manticoreJson = new ManticoreJson($clusterName);

$count = $resources->getActivePodsCount();

Logger::log("Pods count ".$count);
if ($count <= 1) {
    Logger::log("One pod");
    $manticoreJson->startManticore();
    $manticore = new ManticoreConnector('localhost', $port, null, -1);
    $manticore->setCustomClusterName($clusterName);
    $manticore->setMaxAttempts(180);
    if ($manticore->checkClusterName()) {
        Logger::log('Cluster exist');
    } else {
        $manticore->createCluster();
        Logger::log('Cluster created');
    }
    if ($notAddTablesAutomatically) {
        $manticore->addNotInClusterTablesIntoCluster();
    }
} elseif ($manticoreJson->getConf() !== []) {
    Logger::log("Non empty conf");
    $podIps = $resources->getPodsIPs();
    Logger::log('Nodes list was updated ('.implode(',', $podIps).')');
    $manticoreJson->updateNodesList($podIps);
    $manticoreJson->startManticore();

    $manticore = new ManticoreConnector('localhost', $port, null, -1);
    $manticore->setCustomClusterName($clusterName);
    $manticore->setMaxAttempts(180);

    if ($manticore->checkClusterName()) {
        Logger::log('Cluster exist');
        if ($notAddTablesAutomatically) {
            $manticore->addNotInClusterTablesIntoCluster();
        }
    } else {
        $joinHost = $resources->getOldestActivePodName();
        Logger::log("Join to $joinHost");
        $manticore->joinCluster($joinHost.'.'.$workerService);
    }
} else {
    Logger::log("Empty conf with more than one node in cluster");
    $manticoreJson->startManticore();

    $manticore = new ManticoreConnector('localhost', $port, null, -1);
    $manticore->setCustomClusterName($clusterName);
    $manticore->setMaxAttempts(180);

    $joinHost = $resources->getOldestActivePodName();
    Logger::log("Join to $joinHost");
    $manticore->joinCluster($joinHost.'.'.$workerService);
}
?>
