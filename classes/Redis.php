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

    public function setKey($key, $value): void
    {
        $explode = explode("_", $key);

        //Grab notification ID from pre-generated key
        $notification_id = $explode[3];

        //Notification ID will be hashed in redis, remove
        unset($explode[3]);
        $storage_key = implode("_", $explode);

        //Add key as PID_[PROD/DEV]_[ROLE] as key, setting hash as notification ID
        $this->client->hset($storage_key, $notification_id, $value);

        //Keep track of corresponding keyset (notification_id) for performance later
        $this->client->sadd("keyset_".$storage_key, [$notification_id]);
    }

    public function setKeys(array $arr): \Predis\Response\Status
    {
//        TODO
        return $this->client->mset($arr);
    }

    public function getKey($key): ?string
    {
        $explode = explode("_", $key);
        $notification_id = $explode[3];

        unset($explode[3]);
        $storage_key = implode("_", $explode);

        return $this->client->hget($storage_key, $notification_id);
    }

    /**
     * Expecting key in the format PID_[PROD/DEV]_ROLE
     * @param $key
     * @return void
     */
    public function getAllHashed($key): array
    {
        // Grab all notification_ids from corresponding keyset
        $set = $this->client->smembers("keyset_".$key);
        $arr = [];

        // Iterate through, grabbing each value
        foreach($set as $hash)
            $arr[] = $this->client->hget($key, $hash);
        return $arr;
    }

    public function searchKey($phrase)
    {
        // TODO: Implement searchKey() method.
    }

    public function getKeys(array $arr): array
    {
//        TODO
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

    public function getRedisClient()
    {
        return $this->client;
    }

    /**
     *
     * @param $pattern
     * @return void
     */
    public function search($pattern){
        $output = "";
        echo "Testing Scan for pattern key*";
        foreach (new Iterator\Keyspace($this->getRedisClient(), $pattern) as $key) {
            $output .= "$key \n";
        }
        var_dump($output);
    }


    public function info(): array
    {
        return $this->client->info();
    }
}
