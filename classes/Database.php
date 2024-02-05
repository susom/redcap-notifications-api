<?php

namespace Stanford\RedcapNotificationsAPI;

class Database implements CacheInterface
{

    public function setKey($key, $value)
    {
        $parsedKey = RedcapNotificationsAPI::parseKey($key);
        $prefix_directory = (new RedcapNotificationsAPI)->PREFIX;
        $type = $parsedKey['type'] != RedcapNotificationsAPI::ALL_PROJECTS ?$parsedKey['type']:null;
        $sql = sprintf("INSERT INTO redcap_external_modules_log (timestamp, ip, external_module_id, project_id, record, message) VALUES (now(), '%s', (SELECT external_module_id FROM redcap_external_modules WHERE directory_prefix =  '%s'), '%s', '%s', '%s')", $_SERVER['REMOTE_ADDR'], $prefix_directory, $type, $key, $value);
        $q = db_query($sql);
    }

    public function getKey($key)
    {
        $sql = sprintf("SELECT * FROM redcap_external_modules_log WHERE record = '%s'", db_escape($key));
        $q = db_query($sql);
        $row = db_fetch_assoc($q);
        return $row;
    }

    public function deleteKey($key)
    {
        $sql = sprintf("DELETE FROM redcap_external_modules_log WHERE record = '%s'", db_escape($key));
        $q = db_query($sql);
    }

    public function getKeys(array $arr)
    {
        // TODO: Implement getKeys() method.
    }

    public function setKeys(array $arr)
    {
        //TODO Implement setKeys() method.
    }

    public function expireKey($key)
    {
        // TODO: Implement expireKey() method.
    }

}
