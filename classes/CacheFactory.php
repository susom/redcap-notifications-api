<?php

namespace Stanford\RedcapNotificationsAPI;

class CacheFactory
{
    public static function getCacheClient($type, $host = '', $port = '', $luaPath)
    {
        if($type == 'redis' and !empty($host) and !empty($port) and !empty($luaPath)){
            return new Redis($host, $port, $luaPath);
        }
        return new Database();
    }

}
