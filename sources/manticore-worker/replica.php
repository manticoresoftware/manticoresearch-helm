<?php

use Core\K8s\ApiClient;
use Core\K8s\Resources;
use Core\Logger\Logger;
use Core\Manticore\ManticoreConnector;
use Core\Manticore\ManticoreJson;
use Core\Notifications\NotificationStub;

require 'vendor/autoload.php';

$port          = getenv("MANTICORE_PORT");
$clusterName   = getenv("CLUSTER_NAME");
$balancerUrl   = getenv('BALANCER_URL');
$label         = getenv('WORKER_LABEL');
$workerService = getenv('WORKER_SERVICE');

if (empty($port)) {
    die("MANTICORE_PORT is not set\n");
}

$api           = new ApiClient();
$resources     = new Resources($api, $label, new NotificationStub());
$manticoreJson = new ManticoreJson($clusterName);
$dnsPods       = dns_get_record($workerService, DNS_A | DNS_AAAA);

if (count($dnsPods) <= 1) {
    $manticoreJson->startManticore();
    $manticore = new ManticoreConnector('localhost', $port, $label, -1);
    $manticore->setMaxAttempts(180);
    if ($manticore->checkClusterName()) {
        Logger::log('Cluster exist');
    } else {
        $manticore->createCluster();
        Logger::log('Cluster created');
    }

    $manticore->addNotInClusterTablesIntoCluster();
} elseif ($manticoreJson->getConf() === []) {
    $podIps = [];
    foreach ($dnsPods as $dnsPod) {
        $podIps[] = $dnsPod['ip'];
    }
    Logger::log('Nodes list was updated');
    $manticoreJson->updateNodesList($podIps);
    $manticoreJson->startManticore();

    $manticore = new ManticoreConnector('localhost', $port, $label, -1);
    $manticore->setMaxAttempts(180);

    if ($manticore->checkClusterName()) {
        Logger::log('Cluster exist');
        $manticore->addNotInClusterTablesIntoCluster();
    } else {
        $joinHost = $resources->getMinAvailableReplica();
        Logger::log("Join to $joinHost");
        $manticore->joinCluster($joinHost);
    }
} else {
    $manticoreJson->startManticore();

    $manticore = new ManticoreConnector('localhost', $port, $label, -1);
    $manticore->setMaxAttempts(180);

    $joinHost = $resources->getMinAvailableReplica();
    Logger::log("Join to $joinHost");
    $manticore->joinCluster($joinHost);
}
?>
