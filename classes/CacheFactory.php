<?php

namespace Stanford\RedcapNotificationsAPI;

class CacheFactory
{
    public static function getCacheClient($host = '', $port = '')
    {
        if(!empty($host) and !empty($port)){
            return new Redis($host, $port);
        }
        return new Database();
    }

}
