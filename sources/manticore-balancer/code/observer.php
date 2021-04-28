<?php

use chart\k8sapi;

require 'vendor/autoload.php';


/*
 * Тот хук что ты дал не годится
https://kubernetes.io/docs/concepts/containers/container-lifecycle-hooks/
А вот этот нам подходит


Часть конфига searchd выносим в ConfigMap. Маунтим его в волюм балансера в виде файла (стандартный подход работы конфигмапа)
В поде балансера мы высовываем наружу порт который будет слушать Observer скрипт
На поды воркеров мы вешаем хуки которые будут стучаться на Observer (порт на поде Balancer) и сообщать что под поднялся или упал.
Observer получит этот запрос и постучится у конфигу кубера. Получит поды с мантикорой, сверит таблицы, перепишет конфиг и передернет searchd.
Внутри Observer будет лок что бы два процесса не перебивали друг друга
По крону внутри Balancer мы запускаем observer который чекает кол-во воркеров и структуру таблиц. Хеширует эти данные. Сверяет хеши. Если отличаются - переписывает конфиг и отправляет команду балансеру перечитать конфиг
*/
define("CONFIG_HASH_STORAGE", 'indexhash.sha1');
define("INDEX_HASH_STORAGE", 'indexhash.sha1');
define("LOG_STORAGE", 'observer.log');
define("LOCK_FILE", DIRECTORY_SEPARATOR . 'tmp' . DIRECTORY_SEPARATOR . 'observer.lock');
define("CONFIGMAP_PATH", getenv('CONFIGMAP_PATH'));
define("BALANCER_PORT", getenv('BALANCER_PORT'));
define("WORKER_PORT", getenv('WORKER_PORT'));

if ( ! file_exists(CONFIGMAP_PATH)) {
    logger("Searchd config is not mounted");
    exit(1);
}

$fp = fopen(LOCK_FILE, 'w+');

if ( ! flock($fp, LOCK_EX | LOCK_NB)) {
    logger("Another process of Observer already runned");
    fclose($fp);
    exit(1);
}

$api = new k8sapi();

$manticoreStatefulsets = $api->getManticorePods();

if ( ! isset($manticoreStatefulsets['items'])) {
    logger("K8s api don't responsed");
    exit(1);
}


$manticorePods = [];

foreach ($manticoreStatefulsets['items'] as $pod) {
    if (isset($pod['metadata']['labels']['label'])
        && $pod['metadata']['labels']['label'] === 'manticore-worker') {
        if ($pod['status']['phase'] == 'Running' || $pod['status']['phase'] == 'Pending') {
            if (isset( $pod['status']['podIP'])){
                $manticorePods[$pod["metadata"]['name']] = $pod['status']['podIP'];
            }else{
                logger($pod);
            }

        }
    }
}

if (empty($manticorePods)) {
    logger("No workers found");
    fclose($fp);
    exit(0);
}

$podsIps = array_values($manticorePods);
ksort($manticorePods);
$url = array_shift($manticorePods);

/**
 * Get current state of indexes. For that we request first manticore node directly.
 */

try {
    $connection = new mysqli($url . ":" . WORKER_PORT);
} catch (Exception $exception) {
    logger("Can't connect to worker at :" . $url);
    fclose($fp);
    exit(0);
}


/**
 * @var $tablesList mysqli_result
 */

$tablesList = $connection->query("SHOW TABLES");
if ($tablesList !== null) {

    $tablesList = (array)$tablesList->fetch_all(MYSQLI_ASSOC);


    if ( ! empty($tablesList)) {
        $tablesList = array_map(function ($row) {
            return current($row);
        }, $tablesList);

        $hash = sha1(implode('.', $tablesList));

        $previousHash = '';
        if (file_exists(INDEX_HASH_STORAGE)) {
            $previousHash = trim(file_get_contents(INDEX_HASH_STORAGE));
        }
        if ($previousHash !== $hash) {
            logger("Start recompiling config");
            saveConfig($tablesList, $podsIps);
            file_put_contents(INDEX_HASH_STORAGE, $hash);
        } else {
            logger("Hashes are equals");
        }

    } else {
        logger("No tables found");
        fclose($fp);
        exit(0);
    }
}

function dd($text)
{
    die(print_r($text));
}

function logger($line)
{
    $line = date("Y-m-d H:i:s") . ': ' . $line . "\n";
    echo "$line\n";
    file_put_contents(LOG_STORAGE, $line, FILE_APPEND);
}

function saveConfig($indexes, $nodes)
{
    $searchdConfig = file_get_contents(CONFIGMAP_PATH);
    $prependConfig = '';
    foreach ($indexes as $index) {
        $prependConfig .= "\n\nindex " . $index . "\n" .
                          "{\n" .
                          "\ttype = distributed\n" .
                          "\tha_strategy = roundrobin\n" .
                          "\tagent = " . implode("|", $nodes) . "\n" .
                          "}\n\n";
    }

    file_put_contents(DIRECTORY_SEPARATOR . 'etc' .
                      DIRECTORY_SEPARATOR . 'manticoresearch' .
                      DIRECTORY_SEPARATOR . 'manticore.conf', $prependConfig . $searchdConfig);

    reloadIndexes();
}

function reloadIndexes()
{
    $connection = new mysqli('localhost:' . BALANCER_PORT);
    $connection->query("RELOAD INDEXES");
}


flock($fp, LOCK_UN);
fclose($fp);
