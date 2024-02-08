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
    const ALL_PROJECTS = 'GLOBAL';

    const USER_ROLE = 'USER_ROLE';

    const ADMIN = 'ADMIN';

    const PROD = 'PROD';

    const DEV = 'DEV';
    const DESIGNATED_CONTACT = 'DC';
    const ALLUSERS = 'ALLUSERS';


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

        $last_update_ts = (new DateTime())->format('Y-m-d H:i:s');

        $params = array(
            "records" => [$record],
            "return_format" => "json",
            "project_id" => $project_id
        );
        $json = REDCap::getData($params);
        $json = json_decode($json, true);

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
            $this->cacheNotification($json[0]);
        }

    }

    /**
     * @throws \Exception
     */
    public function cacheNotification($record): void
    {
        try {
            $notificationId = $record[\REDCap::getRecordIdField()];
            $allProjects = false;
            $pid = null;
            $userRole = null;
            $isDesignatedContact = false;
            $isProd = null;
            // if project status defined otherwise will be for both prod and dev
            if (!is_null($record['project_status']) and $record['project_status'] != '') {
                $isProd = $record['project_status'];
            }


            // determine nitification user role.
            if ($record['note_user_types'] == 'dc') {
                $isDesignatedContact = true;
            } elseif ($record['note_user_types'] == 'admin') {
                $userRole = self::ADMIN;
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

    public function getNotifications($pid = null, $projectStatus = 0, $isAdmin = false)
    {
        $notifications = array();
        $keys = self::buildCacheKeys($pid, $projectStatus, $isAdmin, self::isUserDesignatedContact($pid));
        foreach ($keys as $key){
            $notifications[] = $this->getCacheClient()->getKey($key);
        }
        return $notifications;
    }

    /**
     * @param $pid
     * @param $projectStatus
     * @param $isAdmin
     * @param $isDesignatedContact
     * @return array
     */
    public static function buildCacheKeys($pid = null, $projectStatus = 0, $isAdmin = false, $isDesignatedContact = false)
    {
        $keys = array();
        if ($pid) {

            // Prod & Dev
            // this will go for all project regardless of status or role. e.g 123_PRODDEV_ALLUSERS
            $keys[] = $pid . self::getDelimiter() . self::PROD . self::DEV . self::getDelimiter() . self::ALLUSERS;

            if ($isAdmin) {
                $keys[] = $pid . self::getDelimiter() . self::PROD . self::DEV . self::getDelimiter() . self::ADMIN;
            }

            if ($isDesignatedContact) {
                $keys[] = $pid . self::getDelimiter() . self::PROD . self::DEV . self::getDelimiter() . self::DESIGNATED_CONTACT;
            }

            // Key for Prod projects
            if ($projectStatus) {
                $keys[] = $pid . self::getDelimiter() . self::PROD . self::getDelimiter() . self::ALLUSERS;

                if ($isAdmin) {
                    $keys[] = $pid . self::getDelimiter() . self::PROD . self::getDelimiter() . self::ADMIN;
                }

                if ($isDesignatedContact) {
                    $keys[] = $pid . self::getDelimiter() . self::PROD . self::getDelimiter() . self::DESIGNATED_CONTACT;
                }
            } else {

                // Key for Dev projects
                $keys[] = $pid . self::getDelimiter() . self::DEV . self::getDelimiter() . self::ALLUSERS;

                if ($isAdmin) {
                    $keys[] = $pid . self::getDelimiter() . self::DEV . self::getDelimiter() . self::ADMIN;
                }

                if ($isDesignatedContact) {
                    $keys[] = $pid . self::getDelimiter() . self::DEV . self::getDelimiter() . self::DESIGNATED_CONTACT;
                }
            }

        }

        return $keys;
    }

    public static function isUserDesignatedContact($pid)
    {
        // TODO check if Designated Contact EM enabled or not.
        if (defined('USERID')) {
            $user = USERID;
            $sql = sprintf("SELECT contact_userid FROM designated_contact_selected WHERE contact_userid = %s", db_escape($user));
            $q = db_query($sql);
            return !empty(db_fetch_assoc($q));
        }
        return false;
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

    public static function parseKey($key)
    {
        $parts = explode(self::getDelimiter(), $key);
        $parsed = array();
        // first part should be all projects or pid
        if (self::ALL_PROJECTS == $parts[0] or is_numeric($parts[0])) {
            $parsed['type'] = $parts[0];
        } else {
            throw new \Exception("Unknown notification type $key");
        }

        // second part has to be prod or dev or both PRODDEV
        if (in_array($parts[1], [self::DEV, self::PROD, self::PROD . self::DEV])) {
            $parsed['status'] = $parts[1];
        } else {
            throw new \Exception("Unknown notification Prod/dev $key");
        }

        // third part has to be designated contact, all users or defined user role.
        if (in_array($parts[2], [self::DESIGNATED_CONTACT, self::ALLUSERS]) or is_string($parts[2])) {
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
            $key .= self::ALL_PROJECTS . self::getDelimiter();
        } elseif ($pid) {
            $key .= $pid . self::getDelimiter();
        } else {
            throw new \Exception("Cant build Notification Key for '$notificationId'");
        }

        if (is_null($isProd)) {
            $key .= self::PROD . self::DEV . self::getDelimiter();
        } elseif ($isProd) {
            $key .= self::PROD . self::getDelimiter();
        } else {
            $key .= self::DEV . self::getDelimiter();
        }

        if ($userRole) {
            $key .= $userRole . self::getDelimiter();
        } elseif ($isDesignatedContact) {
            $key .= self::DESIGNATED_CONTACT . self::getDelimiter();
        } else {
            $key .= self::ALLUSERS . self::getDelimiter();
        }
        return $key . $notificationId;
    }

    /**
     * @return \Stanford\RedcapNotificationsAPI\Database or \Stanford\RedcapNotificationsAPI\Redis
     */
    public function getCacheClient()
    {
        if (!$this->cacheClient) {
            $this->setCacheClient(CacheFactory::getCacheClient($this->getSystemSetting('redis-host'), $this->getSystemSetting('redis-port'), $this->getUrl('/lua_scripts/getHashedValues.lua')));
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

    public static function getDelimiter()
    {
        return '_';
    }
}
