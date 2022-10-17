<?php

use Analog\Analog;
use Analog\Handler\EchoConsole;
use Core\K8s\ApiClient;
use Core\K8s\Resources;
use Core\Manticore\ManticoreConnector;
use Core\Notifications\NotificationStub;

require 'vendor/autoload.php';


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

$count = $resources->getActivePodsCount();

if ($count === 1){
    $manticore = new ManticoreConnector('localhost', $qlPort, $clusterName, -1);
    if ($manticore->checkClusterName() && !$manticore->isClusterPrimary()){
        Analog::info("Node on in cluster but hasn't primary status. Trying to fix it");
        $manticore->restoreCluster();
        Analog::info("Successfully fixed non primary cluster state");
    }
}
