<?php

namespace Stanford\RedcapNotificationsAPI;

class Database implements CacheInterface
{

    public function setKey($key, $value)
    {
        $parsedKey = RedcapNotificationsAPI::parseKey($key);
        // insert/update for regular notifications records
        if (isset($parsedKey['notification_id'])) {
            $type = $parsedKey['type'] != RedcapNotificationsAPI::ALL_PROJECTS ? $parsedKey['type'] : null;
            if ($log_id = $this->isKeyExists($parsedKey['notification_id'])) {
                $sql = sprintf("UPDATE redcap_external_modules_log SET message = '%s', project_id = '%s', record = '%s' WHERE log_id = %d ", $value, $type, $key, $log_id);
            } else {
                $prefix_directory = (new RedcapNotificationsAPI)->PREFIX;
                $sql = sprintf("INSERT INTO redcap_external_modules_log (timestamp, ip, external_module_id, project_id, record, message) VALUES (now(), '%s', (SELECT external_module_id FROM redcap_external_modules WHERE directory_prefix =  '%s'), '%s', '%s', '%s') ", $_SERVER['REMOTE_ADDR'], $prefix_directory, $type, $key, $value);
            }
        } else {
            // insert/update for dismissed notification record
            if (isset($parsedKey['username']) and $this->getKey($key)) {
                $sql = sprintf("UPDATE redcap_external_modules_log SET message = '%s'  WHERE record = '%s' ", $value, $key);
            } else {
                $prefix_directory = (new RedcapNotificationsAPI)->PREFIX;
                $sql = sprintf("INSERT INTO redcap_external_modules_log (timestamp, ip, external_module_id, project_id, record, message) VALUES (now(), '%s', (SELECT external_module_id FROM redcap_external_modules WHERE directory_prefix =  '%s'), '%s', '%s', '%s') ", $_SERVER['REMOTE_ADDR'], $prefix_directory, null, $key, $value);
            }
        }

        db_query(sprintf("SELECT GET_LOCK(%s, 5)", db_escape($key)));
        db_query($sql);
        db_query(sprintf("select RELEASE_LOCK(%s)", db_escape($key)));
    }

    public function getNotificationRecord($notification_id)
    {
        $sql = sprintf("SELECT * from redcap_external_modules_log WHERE record LIKE '%%_%s' LIMIT 1", db_escape($notification_id));
        $q = db_query($sql);
        $row = db_fetch_assoc($q);
        return db_num_rows($q) > 0 ? $row['message'] : [];
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
        while ($row = db_fetch_assoc($q)) {
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

    public function deleteExpiredDismissedKey($key)
    {
        $sql = sprintf("SELECT * FROM redcap_external_modules_log WHERE message LIKE '%%%s%%'", db_escape($key));
        $q = db_query($sql);
        while ($row = db_fetch_assoc($q)) {
            // regex to remove expired from dismissed notification
            $re = '/(' . $key . ',)|(' . $key . ')|(,' . $key . ')/m';
            $value = preg_replace($re, '', $row['message']);
            // save new dismissed list for each user.
            $this->setKey($key, $value);
        }
    }
}
