<?php

use Core\K8s\ApiClient;
use Core\K8s\Resources;
use Core\Logger\Logger;
use Core\Manticore\ManticoreConnector;
use Core\Manticore\ManticoreJson;
use Core\Notifications\NotificationStub;
use Monolog\Handler\StreamHandler;

require 'vendor/autoload.php';

const REPLICATION_MODE_MULTI_MASTER = 'multi-master';
const REPLICATION_MODE_MASTER_SLAVE = 'master-slave';

const ERROR_NO_DATA_DIR_PASSED = 'Option data_dir was not passed in worker.config.content Replication disabled';


$qlPort = null;
$binaryPort = null;
$clusterName = null;
$balancerUrl = null;
$instance = null;
$workerService = null;
$notAddTablesAutomatically = null;
$replicationMode = null;
$labels = null;
$logLevel = null;
include("env_reader.php");

Logger::setHandler(new StreamHandler('php://stdout', $logLevel));


if ($replicationMode !== null &&
    !in_array($replicationMode, [REPLICATION_MODE_MULTI_MASTER, REPLICATION_MODE_MASTER_SLAVE])) {
    $replicationMode = REPLICATION_MODE_MULTI_MASTER;
}

Logger::info("Replication mode: ".$replicationMode);

$api = new ApiClient();
$resources = new Resources($api, (array)$labels, new NotificationStub());
$manticoreJson = new ManticoreJson($clusterName.'_cluster', $binaryPort);

$count = $resources->getActivePodsCount();
$hostname = gethostname();

foreach ($resources->getPodsFullHostnames() as $fullHostname) {
    if (strpos($fullHostname, $hostname) !== false &&
        mb_strlen($hostname) >= 253) {
        Logger::error("Full hostname exceeds max length in 253. Decrease chart name or namespace name length");
        exit(1);
    }
}

function notifyBalancers(ApiClient $apiClient, $labels)
{
    $labels['app.kubernetes.io/component'] = 'balancer';
    $balancerPods = $apiClient->getManticorePods($labels);

    if (isset($balancerPods['items'])) {
        foreach ($balancerPods['items'] as $pod) {
            $balancerIp = $pod['status']['podIP'].":8080";
            Logger::debug("Call balancer ".$balancerIp);
            $dbgResult = $apiClient->get($balancerIp)->getBody()->getContents();
            Logger::debug("Call balancer ".$balancerIp.". Response: ".$dbgResult);
        }
    }
}


function checkIsJoinNodeReady($joinHost, $qlPort, $clusterName)
{
    Logger::info("Wait until join host come available", [$joinHost, $qlPort]);

    $connector = new ManticoreConnector($joinHost, $qlPort, $clusterName, 60);

    for ($i = 0; $i <= 60; $i++) {
        Logger::info("Check is cluster exist at ".$joinHost);
        if ($connector->checkClusterName() === false) {
            sleep(1);
        } else {
            break;
        }
    }
}

Logger::info("Pods count ".$count);

$min = 0;
if (getenv("POD_START_VIA_PROBE") === false) {
    $min = 1;
}

if ($count <= $min) {
    Logger::info("One pod");
    $manticoreJson->startManticore();
    $manticore = new ManticoreConnector('localhost', $qlPort, $clusterName, -1);

    $settings = $manticore->showSettings();
    if (!isset($settings['searchd.data_dir'])){
        Logger::error(ERROR_NO_DATA_DIR_PASSED);
        $manticoreJson->stopManticore();
        exit(1);
    }

    $manticore->setMaxAttempts(180);

    Logger::info("Wait until $hostname came alive");
    $resources->wait($hostname, 60);

    if ($manticore->checkClusterName()) {
        Logger::info('Cluster exist');
    } else {
        $manticore->createCluster();
        Logger::info('Cluster created');
    }
    if ($notAddTablesAutomatically) {
        $manticore->addNotInClusterTablesIntoCluster();
    }
} elseif ($manticoreJson->getConf() !== [] && $manticoreJson->hasCluster()) {
    Logger::info("Non empty conf");

    $manticoreJson->checkNodesAvailability($resources, $qlPort, $clusterName, 5);
    $manticoreJson->startManticore();

    $manticore = new ManticoreConnector('localhost', $qlPort, null, -1);

    $settings = $manticore->showSettings();
    if (!isset($settings['searchd.data_dir'])){
        Logger::error(ERROR_NO_DATA_DIR_PASSED);
        $manticoreJson->stopManticore();
        exit(1);
    }

    $manticore->setCustomClusterName($clusterName);
    $manticore->setMaxAttempts(180);

    Logger::info("Wait until $hostname came alive");
    $resources->wait($hostname, 60);

    if ($manticore->checkClusterName()) {
        Logger::info('Cluster exist');
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
            Logger::info("No host to join");
            notifyBalancers($api, $labels);
            exit(1);
        }

        checkIsJoinNodeReady($joinHost.'.'.$workerService, $qlPort, $clusterName);
        Logger::info("Join to $joinHost.$workerService");
        $manticore->joinCluster($joinHost.'.'.$workerService);
    }
} else {
    Logger::info("Empty conf with more than one node in cluster");
    $manticoreJson->startManticore();

    $manticore = new ManticoreConnector('localhost', $qlPort, null, -1);

    $settings = $manticore->showSettings();
    if (!isset($settings['searchd.data_dir'])){
        Logger::error(ERROR_NO_DATA_DIR_PASSED);
        $manticoreJson->stopManticore();
        exit(1);
    }

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
        Logger::info("No host to join");
        notifyBalancers($api, $labels);
        exit(1);
    }

    Logger::info("Wait until $hostname came alive");
    $resources->wait($hostname, 60);

    Logger::info("Wait for NS...");
    $resultCode = 1;
    while ($resultCode !== 0) {
        $output = [];
        exec("nslookup $(hostname -f)", $output, $resultCode);
        sleep(1);
    }

    checkIsJoinNodeReady($joinHost.'.'.$workerService, $qlPort, $clusterName);
    Logger::info("Join to $joinHost.$workerService");
    $manticore->joinCluster($joinHost.'.'.$workerService);
}

notifyBalancers($api, $labels);
?>
