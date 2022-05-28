<?php

use chart\k8sapi;

require 'vendor/autoload.php';

$port = getenv("MANTICORE_PORT");
$clusterName = getenv("CLUSTER_NAME");
$balancerUrl = getenv('BALANCER_URL');
$label = getenv('WORKER_LABEL');
$workerService = getenv('WORKER_SERVICE');

if (empty($port)) {
    die("MANTICORE_PORT is not set\n");
}


$manticoreJson = new \chart\ManticoreJson($clusterName);
$dnsPods = dns_get_record($workerService, DNS_A|DNS_AAAA);

$podIps = [];
foreach ($dnsPods as $dnsPod){
    $podIps[] = $dnsPod['ip'];
}

if ($podIps!==[]){
    $manticoreJson->updateNodesList($podIps);
}

$manticoreJson->startManticore();


while (true){
    $connection = new mysqli('localhost:'.$port, '', '', '');

    if (!$connection->connect_errno) {
        break;
    }

    echo "\n\nWaiting for searchd to come alive\n";
    sleep(1);
}


$clusterExists = '';
$clusterStatus = $connection->query("show status");
if ($clusterStatus !== null) {

    $clusterStatus = (array) $clusterStatus->fetch_all(MYSQLI_ASSOC);
    foreach ($clusterStatus as $row) {
        if ($row['Counter'] === 'cluster_name') {
            $clusterExists = $row['Value'];
        }
    }
}


function getStatefulsetIndex($fullName){
    $parts    = explode("-", $fullName);
    return (int) array_pop($parts);
}



if ($clusterExists === '') {
    $api = new k8sapi();

    $manticoreStatefulsets = $api->getManticorePods();

    if (!isset($manticoreStatefulsets['items'])) {
        echo "\n\nFATAL: No response from k8s API\n";
        exit(1);
    }

    $min = [];
    $count = 0;

    $hostIndex    = getStatefulsetIndex( gethostname());

    foreach ($manticoreStatefulsets['items'] as $pod) {
        if (isset($pod['metadata']['labels']['label'])
            && $pod['metadata']['labels']['label'] === $label) {
            if ($pod['status']['phase'] === 'Running' || $pod['status']['phase'] === 'Pending') {
                $fullName = trim($pod['metadata']["name"]);

                $key = getStatefulsetIndex($fullName);
                $min[$key] = $fullName;
                $count++;
            } else {
                echo json_encode($pod)."\n\n";
            }
        }
    }

    echo "Replica hook: Pods count:".$count."\n";


    if ($count > 1) {

        ksort($min);
        $first = array_shift($min);

        if ($hostIndex === 0) {
            $first = array_shift($min);
            echo "Replica hook: Zero replica creation. Shift +1:".$first."\n";
        }

        for ($i = 0; $i <= 300; $i++) {
            echo "Replica hook: Joining cluster\n";
            $sql = "JOIN CLUSTER $clusterName at '".$first.".".$workerService.":9312'";
            $connection->query($sql);
            echo "Replica hook: Sql query: $sql\n";
            if ($connection->error) {
                echo "Replica hook: QL error: ".$connection->error."\n";
                sleep(1);
            } else {
                echo "Replica hook: Join success\n";
                break;
            }
        }

    } else {

        echo "Replica hook: Creating new cluster\n";
        $sql = "CREATE CLUSTER $clusterName";
        $connection->query($sql);
        echo "Replica hook: Sql query: $sql\n";
        if ($connection->error) {
            echo "Replica hook: QL error: ".$connection->error."\n";
        }
    }


    $balancerCall = $api->get($balancerUrl);
    if ($balancerCall->getStatusCode() !== 200) {
        echo "Something went wrong with balancer notification\n";
    }

    echo "Replica hook: Replication connect ended\n";
} else {
    echo "Cluster $clusterName already exists\n";
}


?>
