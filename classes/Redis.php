<?php

namespace Stanford\RedcapNotificationsAPI;

use Predis\Client;

class Redis implements CacheInterface
{

    private Client $client;

    public function __construct($redisHost, $redisPort)
    {
        $this->client = new \Predis\Client([
            'host' => $redisHost,
            'port' => $redisPort,
            'connections' => 'relay' //For performance improvements
        ]);
    }

    public function setKey($key, $value)
    {
        return $this->client->set($key, $value);
    }

    public function setKeys(array $arr)
    {
        return $this->client->mset($arr);
    }

    public function getKey($key)
    {
        return $this->client->get($key);
    }

    public function getKeys(array $arr)
    {
        return $this->client->mget($arr);
    }


    public function deleteKey($key)
    {
        return $this->client->del($key);
    }

    public function deleteKeys(array $arr)
    {
        return $this->client->del($arr);
    }

    public function listKeys(string $pattern)
    {
        return $this->client->keys($pattern);
    }

    public function expireKey($key)
    {
        // TODO: Implement expireKey() method.
    }
}
