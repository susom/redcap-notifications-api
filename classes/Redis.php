<?php

namespace Stanford\RedcapNotificationsAPI;
/** @var \Stanford\RedcapNotificationsAPI\RedcapNotificationsAPI $module*/

use Predis\Client;

class Redis implements CacheInterface
{

    private Client $client;
    private string $luaPath;

    public function __construct($redisHost, $redisPort, $luaPath)
    {
        $this->client = new \Predis\Client([
            'host' => $redisHost,
            'port' => $redisPort,
            'connections' => 'relay' //For performance improvements
        ]);

        $this->luaPath=$luaPath;
    }

    public function setKey($key, $value): void
    {
        //Grab notification ID from pre-generated key
        $explode = explode("_", $key);
        $notification_id = $explode[3];

        //Notification ID will be hashed in redis, remove
        unset($explode[3]);
        $storage_key = implode("_", $explode);

        //Add key as PID_[PROD/DEV]_[ROLE] as key, setting hash as notification ID
        $this->client->hset($storage_key, $notification_id, $value);

    }

    public function setKeys(array $arr): void
    {
//        TODO
        $ret = [];
        foreach($arr as $key => $value)
            $this->setKey($key, $value);
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
     * @param $key
     * @return void
     */
    public function getAllValues($key): array
    {
        // Expecting key in the format PID_[PROD/DEV]_ROLE
        $explode = explode("_", $key);
        $notification_id = $explode[3];

        unset($explode[3]);
        $storage_key = implode("_", $explode);

        $values = $this->getHashedValues($this->luaPath, 1, $storage_key, 0);
        return $values[1] ?? [];

    }

    public function searchKey($phrase)
    {
        // TODO: Implement searchKey() method.
    }

    public function getKeys(array $arr): array
    {
        $ret = [];
        foreach($arr as $key)
            $ret[] = $this->getKey($key);
        return $ret;
    }

    public function deleteKey($key): int
    {
        //Grab notification ID from pre-generated key
        $explode = explode("_", $key);
        $notification_id = $explode[3];

        //Notification ID will be hashed in redis, remove
        unset($explode[3]);
        $storage_key = implode("_", $explode);

        return $this->client->del($storage_key);
    }

    public function deleteKeys(array $arr): int
    {
        foreach($arr as $key)
            if(!$this->deleteKey($key))
                return 0;
        return 1;
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

    public function getHashedValues(string $path, int $num_keys, string $key, int $cursor){
        return $this->client->eval(file_get_contents($path), $num_keys, $key, $cursor);
    }
}
