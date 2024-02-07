<?php

namespace Stanford\RedcapNotificationsAPI;

class CacheFactory
{
    public static function getCacheClient($host = '', $port = '', $luaPath)
    {
        if(!empty($host) and !empty($port) and !empty($luaPath)){
            return new Redis($host, $port, $luaPath);
        }
        return new Database();
    }

}
