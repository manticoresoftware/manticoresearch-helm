<?php

$port        = getenv("MANTICORE_PORT");
$clusterName = getenv("CLUSTER_NAME");

if (empty($port)) {
    die("Set manticore port to environments\n");
}


for ($i = 0; $i <= 50; $i++) {
    $sphinxQL = new mysqli('localhost:' . $port, '', '', '');

    if ( ! $sphinxQL->connect_errno) {
        break;
    }

    echo "\n\nWait for searchd came alive\n";
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
            && $pod['metadata']['labels']['label'] === 'manticore-worker'
            && $pod['status']['phase'] === 'Running') {
            $min[] = substr(trim($pod['metadata']["name"]), -1);
            $count++;
        }
    }
} else {
    die("user role have not access\n");
}


echo "Replica hook: Pods count:" . $count . "\n";


if ($count > 1) {
    for ($i = 0; $i <= 5; $i++) {
        echo "Replica hook: Join cluster\n";
        $sql = "JOIN CLUSTER $clusterName at 'worker-" . min($min) . ".worker-svc:9312'";
        $sphinxQL->query($sql);
        echo "Replica hook: Sql query: $sql\n";
        if ($sphinxQL->error) {
            echo "Replica hook: QL error: " . $sphinxQL->error . "\n";
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
        echo "Replica hook: QL error: " . $sphinxQL->error . "\n";
    }
}


$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, getenv('BALANCER_URL'));
curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
$output = curl_exec($ch);
curl_close($ch);

echo "Replica hook: Replication connect ended\n";


?>
