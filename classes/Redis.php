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

    public function setKey($key, $value): \Predis\Response\Status
    {
        return $this->client->set($key, $value);
    }

    public function setKeys(array $arr): \Predis\Response\Status
    {
        return $this->client->mset($arr);
    }

    public function getKey($key): ?string
    {
        return $this->client->get($key);
    }

    public function getKeys(array $arr): array
    {
        return $this->client->mget($arr);
    }

    public function deleteKey($key): int
    {
        return $this->client->del($key);
    }

    public function deleteKeys(array $arr): int
    {
        return $this->client->del($arr);
    }

    public function listKeys(string $pattern): array
    {
        return $this->client->keys($pattern);
    }

    public function expireKey($key)
    {
        // TODO: Implement expireKey() method.
    }

    public function info(): array
    {
        return $this->client->info();
    }
}
