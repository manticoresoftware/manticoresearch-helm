<?php

namespace chart;


class Cache
{
    public const INDEX_HASH = 'index_hash';
    public const CHECKED_WORKERS = 'checked_workers';
    public const CHECKED_INDEXES = 'checked_indexes';

    private $cacheStorageFile = DIRECTORY_SEPARATOR . 'tmp' . DIRECTORY_SEPARATOR . 'index_hash.dat';
    private $cache = [];

    public function __construct()
    {
        if (file_exists($this->cacheStorageFile)) {
            $cache = file_get_contents($this->cacheStorageFile);
            if ($cache) {
                $cache = json_decode($cache, true);
                if ($cache !== false) {
                    $this->cache = $cache;
                }
            }
        }
    }

    public function store($key, $value): void
    {
        $this->cache[$key] = $value;
        file_put_contents($this->cacheStorageFile, json_encode($this->cache));
    }

    public function get($key)
    {
        return $this->cache[$key] ?? [];
    }
}
