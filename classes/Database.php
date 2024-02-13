<?php

namespace Stanford\RedcapNotificationsAPI;

class Database implements CacheInterface
{

    public function setKey($key, $value)
    {
        $parsedKey = RedcapNotificationsAPI::parseKey($key);
        $prefix_directory = (new RedcapNotificationsAPI)->PREFIX;
        $type = $parsedKey['type'] != RedcapNotificationsAPI::ALL_PROJECTS ?$parsedKey['type']:null;
        // check if there is a key for notification_id
        if($log_id = $this->isKeyExists($parsedKey['notification_id'])){
            $sql = sprintf("UPDATE redcap_external_modules_log SET message = '%s', project_id = '%s', record = '%s' WHERE log_id = %d ", $value, $type, $key, $log_id);
        }else{
            $sql = sprintf("INSERT INTO redcap_external_modules_log (timestamp, ip, external_module_id, project_id, record, message) VALUES (now(), '%s', (SELECT external_module_id FROM redcap_external_modules WHERE directory_prefix =  '%s'), '%s', '%s', '%s') ", $_SERVER['REMOTE_ADDR'], $prefix_directory, $type, $key, $value);
        }
        db_query($sql);
    }

    private function isKeyExists($notification_id)
    {
        $sql = sprintf("SELECT log_id from redcap_external_modules_log WHERE record LIKE '%%_%s'", db_escape($notification_id));
        $q = db_query($sql);
        $row = db_fetch_assoc($q);
        return db_num_rows($q) > 0 ? $row['log_id'] : false;
    }
    public function getKey($key)
    {
        $sql = sprintf("SELECT * FROM redcap_external_modules_log WHERE record LIKE '%s%%'", db_escape($key));
        $q = db_query($sql);
        $result = [];
        while($row = db_fetch_assoc($q)) {
            $result[$row['record']] = $row['message'];
        }
        return $result;
    }

    public function getData($key)
    {
        return self::getKey($key);
    }

    public function searchKey($phrase)
    {
        $sql = sprintf("SELECT * FROM redcap_external_modules_log WHERE record LIKE '%%%s%%'", db_escape($phrase));
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
