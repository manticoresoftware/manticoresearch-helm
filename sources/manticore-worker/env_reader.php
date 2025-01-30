<?php

$variables = [
    'qlPort' => ['env' => 'MANTICORE_PORT', 'type' => 'int'],
    'binaryPort' => ['env' => 'MANTICORE_BINARY_PORT', 'type' => 'int'],
    'clusterName' => ['env' => 'CLUSTER_NAME', 'type' => 'string'],
    'instance' => ['env' => 'INSTANCE_LABEL', 'type' => 'string'],
    'workerService' => ['env' => 'WORKER_SERVICE', 'type' => 'string'],
    'replicationMode' => ['env' => 'REPLICATION_MODE', 'type' => 'string'],
    'autoAddTablesIntoCluster' => ['env' => 'AUTO_ADD_TABLES_IN_CLUSTER', 'type' => 'bool'],
    'isBalancerEnabled' => ['env' => 'IS_BALANCER_ENABLED', 'type' => 'bool'],
    'logLevel' => ['env' => 'LOG_LEVEL', 'type' => 'level'],
];


foreach ($variables as $variable => $desc) {
    $$variable = getenv($desc['env']);

    if ($$variable === false) {
        if ($desc['type'] === 'level') {
            $$variable = \Monolog\Logger::WARNING;
            continue;
        }
        \Core\Logger\Logger::error($desc['env']." is not defined\n");
        exit(1);
    }

    if ($desc['type'] === 'int') {
        $$variable = (int)$$variable;
    } elseif ($desc['type'] === 'level') {
        $$variable = \Monolog\Logger::toMonologLevel($$variable);
    } elseif ($desc['type'] === 'bool') {
        $$variable = (bool)$$variable;
    }
}


$labels = [
    'app.kubernetes.io/component' => 'worker',
    'app.kubernetes.io/instance' => $instance,
];
