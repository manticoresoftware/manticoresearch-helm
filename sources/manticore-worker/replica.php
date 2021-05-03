<?php

use chart\k8sapi;

require 'vendor/autoload.php';

$port          = getenv("MANTICORE_PORT");
$clusterName   = getenv("CLUSTER_NAME");
$balancerUrl   = getenv('BALANCER_URL');
$label         = getenv('WORKER_LABEL');
$workerService = getenv('WORKER_SERVICE');

if (empty($port)) {
    die("Set manticore port to environments\n");
}

for ($i = 0; $i <= 50; $i++) {
    $connection = new mysqli('localhost:' . $port, '', '', '');

    if ( ! $connection->connect_errno) {
        break;
    }

    echo "\n\nWait for searchd came alive\n";
    sleep(1);
}


$clusterExists = '';
$clusterStatus = $connection->query("show status");
if ($clusterStatus !== null) {

    $clusterStatus = (array)$clusterStatus->fetch_all(MYSQLI_ASSOC);
    foreach ($clusterStatus as $row) {
        if ($row['Counter'] === 'cluster_name') {
            $clusterExists = $row['Value'];
        }
    }
}


if ($clusterExists === '') {
    $api = new k8sapi();

    $manticoreStatefulsets = $api->getManticorePods();

    if ( ! isset($manticoreStatefulsets['items'])) {
        echo "\n\nK8s api don't responsed\n";
        exit(1);
    }

    $min   = [];
    $count = 0;

    foreach ($manticoreStatefulsets['items'] as $pod) {
        if (isset($pod['metadata']['labels']['label'])
            && $pod['metadata']['labels']['label'] === $label) {
            if ($pod['status']['phase'] === 'Running' || $pod['status']['phase'] === 'Pending') {
                $fullName = trim($pod['metadata']["name"]);

                $key       = (int)substr($fullName, -1);
                $min[$key] = $fullName;
                $count++;
            } else {
                echo json_encode($pod) . "\n\n";
            }
        }
    }

    echo "Replica hook: Pods count:" . $count . "\n";


    if ($count > 1) {

        ksort($min);
        $first = array_shift($min);

        for ($i = 0; $i <= 5; $i++) {
            echo "Replica hook: Join cluster\n";
            $sql = "JOIN CLUSTER $clusterName at '" . $first . "." . $workerService . ":9312'";
            $connection->query($sql);
            echo "Replica hook: Sql query: $sql\n";
            if ($connection->error) {
                echo "Replica hook: QL error: " . $connection->error . "\n";
            } else {
                echo "Replica hook: Join success \n";
                break;
            }
        }

    } else {

        echo "Replica hook: Create new cluster\n";
        $sql = "CREATE CLUSTER $clusterName";
        $connection->query($sql);
        echo "Replica hook: Sql query: $sql\n";
        if ($connection->error) {
            echo "Replica hook: QL error: " . $connection->error . "\n";
        }
    }


    $balancerCall = $api->get($balancerUrl);
    if ($balancerCall->getStatusCode() !== 200) {
        echo "Something went wrong during balancer notification\n";
    }

    echo "Replica hook: Replication connect ended\n";
} else {
    echo "Cluster $clusterName already exists\n";
}


?>
