<?php

$port            = getenv("MANTICORE_PORT");
$statefulSetName = getenv("MANTICORE_STATEFUL_SET_NAME");
$serviceName     = getenv("MANTICORE_SERVICE_NAME");
$namespace       = getenv("NAMESPACE_NAME");
$clusterName     = getenv("CLUSTER_NAME");

if (empty($port)) {
    die("Set manticore port to environments\n");
}


if (empty($statefulSetName)) {
    die("Set manticore statefulset name to environments\n");
}

if (empty($serviceName)) {
    die("Set manticore service name to environments\n");
}
if (empty($namespace)) {
    die("Set namespace name to environments\n");
}

for ($i = 0; $i <= 50; $i++) {
    $sphinxQL = new mysqli('localhost:' . $port, '', '', '');

    if ( ! $sphinxQL->connect_errno) {
        break;
    }

    sleep(1);
}

$command = 'curl --cacert /var/run/secrets/kubernetes.io/serviceaccount/ca.crt ' .
           '-H "Authorization: Bearer $(cat /var/run/secrets/kubernetes.io/serviceaccount/token)" ' .
           'https://kubernetes.default.svc/api/v1/namespaces/' .
           '$(cat /var/run/secrets/kubernetes.io/serviceaccount/namespace)' .
           '/pods';


exec($command, $output, $returnValue);


$output = implode('', $output);

$output = json_decode($output, true);

$min   = [];
$count = 0;

if ( ! empty($output['items'])) {
    foreach ($output['items'] as $pod) {
        if (isset($pod['metadata']['labels']['label'])
            && $pod['metadata']['labels']['label'] === 'manticore-worker') {
            echo "\n\nPod status: " . $pod['status']['phase'] . "\n";
            if ($pod['status']['phase'] === 'Running') {
                $min[] = substr(trim($pod['metadata']["name"]), -1);
                $count++;
            }
        }
    }
} else {
    die("user role have not access\n");
}


echo "Replica hook: Pods count:" . $count . "\n";

if ( ! empty($min)) {
    $minNodeId = min($min);
} else {
    $minNodeId = 0;
}

if ($count > 1) {
    for ($i = 0; $i <= 5; $i++) {
        echo "Replica hook: Join cluster\n";
        $sql = "JOIN CLUSTER pq_cluster at '" . $statefulSetName . "-" . ($minNodeId + $i) . "." . $serviceName . "." . $namespace . ".svc.cluster.local:9312'";
        $sphinxQL->query($sql);
        echo "Replica hook: Sql query: $sql\n";
        if ($sphinxQL->error) {
            echo "Replica hook: QL error: " . $sphinxQL->error . "\n";
        } else {
            echo "Replica hook: Join success \n";
            break;
        }
    }
}

echo "Replica hook: Replication connect ended\n";


?>
