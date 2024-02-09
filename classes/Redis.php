<?php

namespace Stanford\RedcapNotificationsAPI;

use Predis\Client;
use DateTime;

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


    /**
     *
     * @param $key
     * @param $value JSON pyaload
     * @return void
     */
    public function setKey($key, $value): void
    {

        //Grab notification ID from pre-generated key
        $explode = explode(RedcapNotificationsAPI::getDelimiter(), $key);
        $notification_id = $explode[3];

        //Notification ID will be hashed in redis, remove
        unset($explode[3]);
        $storage_key = implode(RedcapNotificationsAPI::getDelimiter(), $explode);


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

    /**
     * Key expected in the following format: PID_[PROD/DEV]_ROLE_ID
     * @param $key
     * @return string|null
     */
    public function getKey($key): ?string
    {
        $explode = explode(RedcapNotificationsAPI::getDelimiter(), $key);
        $notification_id = $explode[3];

        unset($explode[3]);
        $storage_key = implode(RedcapNotificationsAPI::getDelimiter(), $explode);

        return $this->client->hget($storage_key, $notification_id);
    }

    /**
     * Grabs all values in redis for a given hash
     * Expecting key in the format PID_[PROD/DEV]_ROLE
     * @param $key
     * @return void
     */
    public function getData($key): array
    {
        $kv = $this->client->hgetall($key);

        // Check to see if any values should be expired
        foreach($kv as $k => $value){
            $json = json_decode($value, true);

            if(!empty($json['note_end_dt'])) {
                $expire = new DateTime($json['note_end_dt']);

                // Expire key hash and delete from return if expiration date is reached
                if($expire <= new DateTime()) {
                    $this->client->hdel($key, [$k]);
                    unset($kv[$k]);
                }
            }

            // Change index of returned keys to be the full cached string
            if(isset($kv[$k])){
                $newKey = $key . RedcapNotificationsAPI::getDelimiter() . $k; //Based on delimiter being an underscore must change if altered
                $kv[$newKey] = $kv[$k];
                unset($kv[$k]);
            }
        }

        return $kv ?? [];

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

    /**
     * Remove selected key
     * NOTE: Removing a key will delete all associated field,value pairs -- currently each notification as a f,v
     * @param $key
     * @return int
     */
    public function deleteKey($key): int
    {
        //Grab notification ID from pre-generated key
        $explode = explode(RedcapNotificationsAPI::getDelimiter(), $key);
        $notification_id = $explode[3];

        //Notification ID will be hashed in redis, remove
        unset($explode[3]);
        $storage_key = implode(RedcapNotificationsAPI::getDelimiter(), $explode);

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
        //        $values = $this->getHashedValues($this->luaPath, 1, $key, 0);
        return $this->client->eval(file_get_contents($path), $num_keys, $key, $cursor);
    }
}
