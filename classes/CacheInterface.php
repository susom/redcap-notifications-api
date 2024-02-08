<?php

namespace Stanford\RedcapNotificationsAPI;

interface CacheInterface
{
    public function setKey($key, $value);

    public function setKeys(array $arr);

    public function searchKey($phrase);

    public function getKey($key);

    public function getData($key);

    public function getKeys(array $arr);

    public function deleteKey($key);

    public function expireKey($key);

}
