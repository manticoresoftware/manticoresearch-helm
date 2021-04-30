<?php

namespace chart;

use GuzzleHttp\Client;
use GuzzleHttp\Psr7;
use GuzzleHttp\Exception\RequestException;

class k8sapi
{
//kubectl proxy --port=8080 &


    const API_URL_SCHEME = '{{API-URL}}/{{API-VERSION}}/namespaces/{{NAMESPACE}}/{{API-SECTION}}';
    const TYPE_STATEFULSET = 'statefulsets';
    const TYPE_SERVICE = 'services';
    const TYPE_PODS = 'pods';


    private $apiUrl = 'https://kubernetes.default.svc';
    private $cert = '/var/run/secrets/kubernetes.io/serviceaccount/ca.crt';
    private $apiSections = [

        self::TYPE_SERVICE       => 'api/v1',
        self::TYPE_STATEFULSET   => 'apis/apps/v1',
        'configmaps'             => 'api/v1',
        'persistentvolumeclaims' => 'api/v1',
        'secrets'                => 'api/v1',
        self::TYPE_PODS          => 'api/v1'
    ];

    private $bearer;
    private $httpClient;

    public function __construct()
    {

        $this->bearer    = $this->getBearer();
        $this->userAgent = 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 ' .
                           '(KHTML, like Gecko) Chrome/71.0.3578.98 Safari/537.36';

        $this->namespace  = $this->getNamespace();
        $this->httpClient = new Client();
    }

    public function getManticorePods()
    {
        return json_decode($this->request(self::TYPE_PODS)->getBody()->getContents(), true);
    }


    private function request($section, $type = "GET")
    {
        $params = [
            'verify'  => $this->cert,
            'version' => 2.0,
            'headers' => [
                'Authorization' => 'Bearer ' . $this->bearer,
                'Accept'        => 'application/json',
                'User-Agent'    => $this->userAgent,
            ]
        ];

        return $this->call($type, $this->getUrl($section), $params);
    }


    private function getUrl($section)
    {
        return str_replace(['{{API-URL}}', '{{API-VERSION}}', '{{NAMESPACE}}', '{{API-SECTION}}'],
            [$this->apiUrl, $this->apiSections[$section], $this->namespace, $section], self::API_URL_SCHEME);
    }


    private function getBearer()
    {
        $bearerFile = '/var/run/secrets/kubernetes.io/serviceaccount/token';
        if (file_exists($bearerFile)) {
            return file_get_contents($bearerFile);
        }

        return false;
    }


    private function getNamespace()
    {
        $bearerFile = '/var/run/secrets/kubernetes.io/serviceaccount/namespace';
        if (file_exists($bearerFile)) {
            return file_get_contents($bearerFile);
        }

        return false;
    }

    public function get($url)
    {
        return $this->call('GET', $url);
    }

    private function call($method, $url, $params = [])
    {

        try {
            return $this->httpClient->request($method, $url, $params);
        } catch (RequestException $e) {
            echo Psr7\Message::toString($e->getRequest()) . "\n";
            if ($e->hasResponse()) {
                echo Psr7\Message::toString($e->getResponse());
            }

            exit(1);
        }
    }
}
