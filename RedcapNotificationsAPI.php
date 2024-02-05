<?php

namespace Stanford\RedcapNotificationsAPI;
require_once "vendor/autoload.php";
require_once "emLoggerTrait.php";
require_once "classes/CacheInterface.php";
require_once "classes/Redis.php";
require_once "classes/Database.php";
require_once "classes/CacheFactory.php";

use REDCap;
use DateTime;


class RedcapNotificationsAPI extends \ExternalModules\AbstractExternalModule
{

    use emLoggerTrait;

    const DEFAULT_NOTIF_SNOOZE_TIME_MIN = 5;
    const DEFAULT_NOTIF_REFRESH_TIME_HOUR = 6;

    const SYSTEM = 'SYSTEM';
    const ALL_PROJECTS = 'ALL_PROJECTS';

    const USER_ROLE = 'USER_ROLE';

    const PROD = 'PROD';

    const DEV = 'DEV';
    const DESIGNATED_CONTACT = 'DESIGNATED_CONTACT';
    const ALL_USERS = 'ALL_USERS';


    private $SURVEY_USER = '[survey respondent]';

    private $cacheClient;
//    public function __construct() {
//		parent::__construct();
//	}

    /**
     *  Using this function to update the [note_last_update_time] field of a notification
     *  record so we can tell when it's been changed in the REDCap Notifications Project.
     *
     * @param $project_id
     * @param $record
     * @param $instrument
     * @param $event_id
     * @param $group_id
     * @param $survey_hash
     * @param $response_id
     * @param $repeat_instance
     * @return void
     * @throws \Exception
     */
    function redcap_save_record($project_id, $record, $instrument, $event_id, $group_id,
                                $survey_hash, $response_id, $repeat_instance)
    {
        // If this is the notification project, update the latest update date
        $notification_pid = $this->getSystemProjectIDs('notification-pid');
        if ($notification_pid == $project_id and !empty($record)) {

            $last_update_ts = (new DateTime())->format('Y-m-d H:i:s');

            $params = array(
                "records" => $record,
                "return_format" => "json",
                "fields" => array("force_refresh")
            );
            $json = REDCap::getData($params);
            $response = json_decode($json, true);

            if (!empty($response) && $response = current($response)) {
                $this->emDebug("forcer refresh get data?", $response);
                if ($response["force_refresh___1"] == "1") {
                    $this->setForceRefreshSetting($record, $last_update_ts);
                }
            }

            // Save the last record update date/time
            $saveData = array(
                array(
                    "record_id" => $record,
                    "note_last_update_time" => $last_update_ts
                )
            );
            $response = REDCap::saveData('json', json_encode($saveData), 'overwrite');
            if (!empty($response['errors'])) {
                $this->emError("Could not update record with last update time " . json_encode($saveData));
            } else {
                $this->cacheNotification($saveData);
            }
        }
    }

    /**
     * @throws \Exception
     */
    public function  cacheNotification($record): void
    {
        try {
            $notificationId = $record[\REDCap::getRecordIdField()];
            $allProjects = false;
            $pid = null;
            $userRole = null;
            $isDesignatedContact = false;

            // if project status defined otherwise will be for both prod and dev
            $isProd = $record['project_status'];

            // determine nitification user role.
            if ($record['note_user_types'] == 'dc') {
                $isDesignatedContact = true;
            }

            // if pid/s defined  loop over  listed PID
            if ($record['note_project_id']) {
                $pids = explode(',', $record['note_project_id']);
            } else {
                $allProjects = true;
            }

            // if notifications for specific projects loop over
            if (!$allProjects) {
                foreach ($pids as $pid) {
                    if ($record['note_user_types'] == 'admin') {
                        $userRole = $this->getProjectAdminRole($pid);
                    }
                    $key = self::generateKey($notificationId, false, $pid, $isProd, $userRole, $isDesignatedContact);
                    $this->getCacheClient()->setKey($key, json_encode($record));
                }
            } else {
                $key = self::generateKey($notificationId, true, $pid, $isProd, $userRole, $isDesignatedContact);
                $this->getCacheClient()->setKey($key, json_encode($record));
            }
            \REDCap::logEvent("Notification '$notificationId' was cached correctly.");
        } catch (\Exception $e) {
            echo $e->getMessage();
            \REDCap::logEvent('Exeption:: Cant create notification cache', $e->getMessage());
        }
    }


    public function getProjectAdminRole($pid)
    {
        $sql = sprintf("SELECT unique_role_name from redcap_user_roles WHERE project_id = %d", db_escape($pid));
        $q = db_query($sql);
        $row = db_fetch_assoc($q);
        return $row['unique_role_name'];
    }

    /**
     * display emdebugs only for custom comma delimited list of userids to debug for select subset of userids to try to find out why they constantly callback for notif payloads
     *
     * @param
     * @return void
     */
    public function emDebugForCustomUseridList()
    {
        $temp = $this->getSystemSetting("user-specific-log-list");
        $temp = str_replace(" ", "", $temp);
        $custom_log_list = empty($temp) ? [] : explode(",", $temp);

        $cur_user = $this->getUser()->getUsername();
        if (in_array($cur_user, $custom_log_list)) {
            $args = func_get_args();
            $this->emDebug("REDCapNotifs Custom Debug for $cur_user", $args);
        }
    }

    public function parseKey($key)
    {
        $parts = explode('_', $key);
        $parsed = array();
        // first part should be all projects or pid
        if (self::ALL_PROJECTS == $parts[0] or is_numeric($parts[0])) {
            $parsed['type'] = $parts[0];
        } else {
            throw new \Exception("Unknown notification type $key");
        }

        // second part has to be prod or dev
        if (in_array($parts[1], [self::DEV, self::PROD])) {
            $parsed['status'] = $parts[1];
        } else {
            throw new \Exception("Unknown notification Prod/dev $key");
        }

        // third part has to be designated contact, all users or defined user role.
        if (in_array($parts[2], [self::DESIGNATED_CONTACT, self::ALL_USERS]) or is_string($parts[2])) {
            $parsed['role'] = $parts[2];
        } else {
            throw new \Exception("Unknown notification role $key");
        }

        // third part has to be designated contact, all users or defined user role.
        if ($parts[3]) {
            $parsed['notification_id'] = $parts[3];
        } else {
            throw new \Exception("Missing notification id $key");
        }

        return $parsed;
    }

    public static function generateKey($notificationId, $allProjects = false, $pid = null, $isProd = false, $userRole = null, $isDesignatedContact = false)
    {
        $key = '';
        if ($allProjects) {
            $key .= self::ALL_PROJECTS . '_';

        } elseif ($pid) {
            $key .= $pid . '_';
        } else {
            throw new \Exception("Cant build Notification Key for '$notificationId'");
        }
        if ($isProd) {
            $key .= self::PROD . '_';
        } else {
            $key .= self::DEV . '_';
        }
        if ($userRole) {
            $key .= $userRole . '_';
        } elseif ($isDesignatedContact) {
            $key .= self::DESIGNATED_CONTACT . '_';
        } else {
            $key .= self::ALL_USERS . '_';
        }

        return $key . $notificationId;
    }

    /**
     * @return \Stanford\RedcapNotificationsAPI\Database or \Stanford\RedcapNotificationsAPI\Redis
     */
    public function getCacheClient()
    {
        if (!$this->cacheClient) {
            $this->setCacheClient(CacheFactory::getCacheClient($this->getSystemSetting('redis-host'), $this->getSystemSetting('redis-port')));
        }
        return $this->cacheClient;
    }

    /**
     * @param mixed $cacheClient
     */
    public function setCacheClient($cacheClient): void
    {
        $this->cacheClient = $cacheClient;
    }


}
