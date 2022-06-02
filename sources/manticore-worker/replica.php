<?php

use Core\K8s\ApiClient;
use Core\K8s\Resources;
use Core\Manticore\ManticoreConnector;
use Core\Manticore\ManticoreJson;

require 'vendor/autoload.php';

$port          = getenv("MANTICORE_PORT");
$clusterName   = getenv("CLUSTER_NAME");
$balancerUrl   = getenv('BALANCER_URL');
$label         = getenv('WORKER_LABEL');
$workerService = getenv('WORKER_SERVICE');

if (empty($port)) {
    die("MANTICORE_PORT is not set\n");
}

$api = new ApiClient();
$resources = new Resources($api, $label, new \Core\Notifications\NotificationStub());
$manticoreJson = new ManticoreJson($clusterName);
$dnsPods       = dns_get_record($workerService, DNS_A | DNS_AAAA);

if (count($dnsPods) <= 1) {
    $manticoreJson->startManticore();
    $manticore = new ManticoreConnector('localhost', $port, $label, -1);
    $manticore->setMaxAttempts(180);
    if ($manticore->checkClusterName()) {
        echo "==> Cluster exist\n";
    } else {
        $manticore->createCluster();
        echo "==> Cluster created\n";
    }

    $manticore->addNotInClusterTablesIntoCluster();

} elseif ($manticoreJson->getConf() === []) {
    $podIps = [];
    foreach ($dnsPods as $dnsPod) {
        $podIps[] = $dnsPod['ip'];
    }
    echo "==> Nodes list was updated\n";
    $manticoreJson->updateNodesList($podIps);
    $manticoreJson->startManticore();

    $manticore = new ManticoreConnector('localhost', $port, $label, -1);
    $manticore->setMaxAttempts(180);

    if ($manticore->checkClusterName()){
        echo "==> Cluster exist\n";
        $manticore->addNotInClusterTablesIntoCluster();

    }else{
        $joinHost = $resources->getMinAvailableReplica();
        echo "==> Join to $joinHost\n";
        $manticore->joinCluster($joinHost);
    }


} else {
    $manticoreJson->startManticore();

    $manticore = new ManticoreConnector('localhost', $port, $label, -1);
    $manticore->setMaxAttempts(180);

    $joinHost = $resources->getMinAvailableReplica();
    echo "==> Join to $joinHost\n";
    $manticore->joinCluster($joinHost);
}
?>
