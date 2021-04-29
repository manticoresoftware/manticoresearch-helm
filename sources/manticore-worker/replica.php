<?php

use chart\k8sapi;

require 'vendor/autoload.php';

$port = getenv("MANTICORE_PORT");
$clusterName = getenv("CLUSTER_NAME");

if (empty($port)) {
    die("Set manticore port to environments\n");
}


for ($i = 0; $i <= 50; $i++) {
    $sphinxQL = new mysqli('localhost:'.$port, '', '', '');

    if (!$sphinxQL->connect_errno) {
        break;
    }

    echo "\n\nWait for searchd came alive\n";
    sleep(1);
}


$api = new k8sapi();

$manticoreStatefulsets = $api->getManticorePods();

if (!isset($manticoreStatefulsets['items'])) {
    echo "\n\nK8s api don't responsed\n";
    exit(1);
}

$min = [];
$count = 0;

foreach ($manticoreStatefulsets['items'] as $pod) {
    if (isset($pod['metadata']['labels']['label'])
        && $pod['metadata']['labels']['label'] === 'manticore-worker'
        && $pod['status']['phase'] === 'Running') {
        $min[] = substr(trim($pod['metadata']["name"]), -1);
        $count++;
    }
}

echo "Replica hook: Pods count:".$count."\n";


if ($count > 1) {
    for ($i = 0; $i <= 5; $i++) {
        echo "Replica hook: Join cluster\n";
        $sql = "JOIN CLUSTER $clusterName at 'worker-".min($min).".worker-svc:9312'";
        $sphinxQL->query($sql);
        echo "Replica hook: Sql query: $sql\n";
        if ($sphinxQL->error) {
            echo "Replica hook: QL error: ".$sphinxQL->error."\n";
        } else {
            echo "Replica hook: Join success \n";
            break;
        }
    }

} else {

    echo "Replica hook: Create new cluster\n";
    $sql = "CREATE CLUSTER $clusterName";
    $sphinxQL->query($sql);
    echo "Replica hook: Sql query: $sql\n";
    if ($sphinxQL->error) {
        echo "Replica hook: QL error: ".$sphinxQL->error."\n";
    }
}


$balancerCall = $api->get(getenv('BALANCER_URL'));
print_r([$balancerCall->getBody()->getContents(), $balancerCall->getStatusCode()]);

echo "Replica hook: Replication connect ended\n";


?>
