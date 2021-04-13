<?php

require 'vendor/autoload.php';

define("INDEX_HASH_STORAGE", 'indexhash.sha1');
define("LOG_STORAGE", 'observer.log');

$api = new k8s_api();

$manticoreStatefulsets = $api->getManticorePods();

if (!isset($manticoreStatefulsets['items'])) {
    logger("K8s api don't responsed");
    exit(1);
}

$manticorePods = [];

foreach ($manticoreStatefulsets['items'] as $pod) {
    if (isset($pod['metadata']['labels']['label'])
        && $pod['metadata']['labels']['label'] === 'manticore-worker') {
        if ($pod['status']['phase'] == 'Running' || $pod['status']['phase'] == 'Pending') {
            $manticorePods[$pod["metadata"]['name']] = (int) substr($pod["metadata"]['name'], -2);
        }
    }
}

ksort($manticorePods);
$url = array_shift($manticorePods);

/**
 * Get current state of indexes. For that we request first manticore node directly.
 */
$connection = new mysqli($url, 'root', 'root', 'app154');

/**
 * @var $tablesList mysqli_result
 */
$tablesList = $connection->query("SHOW TABLES");
if ($tablesList !== false) {

    $tablesList = (array) $tablesList->fetch_all(MYSQLI_ASSOC);

    $tablesList = array_map(function ($row) {
        return current($row);
    }, $tablesList);

    $hash = sha1(implode('.', $tablesList));

    if (file_exists(INDEX_HASH_STORAGE)) {
        $previousHash = trim(file_get_contents(INDEX_HASH_STORAGE));

        if ($previousHash !== $hash) {
            logger("Start recompiling config");
        }
    }
}


function logger($line)
{
    $line = date("Y-m-d H:i:s").': '.$line;
    echo "$line\n";
    file_put_contents(LOG_STORAGE, $line, FILE_APPEND);
}
