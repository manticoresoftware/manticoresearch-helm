<?php

namespace chart;

class ManticoreJson
{
    private $conf = [];
    private $path;
    private $clusterName;

    public function __construct($clusterName)
    {
        $this->clusterName = $clusterName;
        if (defined('DEV')) {
            $this->conf = [

                "clusters" => [
                    "m_cluster" => [
                        "nodes" => "10.42.8.159:9312,10.42.2.82:9312",
                        "options" => "",
                        "indexes" => ["pq", "tests"],
                    ],
                ],

                "indexes" => [
                    "pq" => [
                        "type" => "percolate",
                        "path" => "pq",
                    ],
                    "tests" => [
                        "type" => "rt",
                        "path" => "tests",
                    ],

                ],
            ];
            $this->path = '/tmp/manticore.json';
        } else {
            $this->path = '/var/lib/manticore/manticore.json';

            if (file_exists($this->path)) {
                try {
                    $manticoreJson = file_get_contents($this->path);
                    echo "=> Manticore json content: ".$manticoreJson;
                    $this->conf = json_decode($manticoreJson, true);
                } catch (\Exception $exception) {
                    $this->conf = [];
                }
            } else {
                $this->conf = [];
            }
        }
    }


    public function hasCluster(): bool
    {
        return isset($this->conf['clusters'][$this->clusterName]);
    }

    public function getClusterNodes()
    {
        if (!isset($this->conf['clusters'][$this->clusterName]['nodes'])) {
            return [];
        }
        $nodes = $this->conf['clusters'][$this->clusterName]['nodes'];

        return explode(',', $nodes);
    }

    public function updateNodesList(array $nodesList): void
    {
        if ($nodesList != []){
            $newNodes = implode(',', $nodesList);

            if (!isset($this->conf['clusters'][$this->clusterName]['nodes']) ||
                $newNodes !== $this->conf['clusters'][$this->clusterName]['nodes']) {

                $this->conf['clusters'][$this->clusterName]['nodes'] = $newNodes;
                $this->save();
            }
        }
    }

    public function getConf(){
        return $this->conf;
    }

    public function startManticore()
    {
        exec('supervisorctl start searchd');
    }

    public function checkNodesAvailability(K8sResources $resources, $port, $label, $attempts): void
    {
        $nodes = $resources->getPodsHostnames();

        $availableNodes = [];
        foreach ($nodes as $node) {
            // Skip current node
            if ($node === gethostname()) {
                continue;
            }

            try {
                $connection = new \Core\ManticoreConnector($node, $port, $label, $attempts);
                if (!$connection->checkClusterName()) {
                    echo "=> Cluster name mismatch at $node\n";
                    continue;
                }
                $availableNodes[] = $node.':'.$port;
            } catch (\RuntimeException $exception) {
            }
        }

        $this->updateNodesList($availableNodes);
    }

    private function save(): void
    {
        file_put_contents($this->path, json_encode($this->conf, JSON_PRETTY_PRINT));
    }
}
