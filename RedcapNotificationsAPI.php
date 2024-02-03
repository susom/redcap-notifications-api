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

    const DESIGNATED_CONTACT = 'DESIGNATED_CONTACT';
    const PROJECT_SPECIFIC = 'PROJECT_SPECIFIC';


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

            }
        }
    }

    private function cacheNotification($record)
    {

    }

    public function determineKey($record)
    {

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
        // TODO
    }
    public static function generateKey($notificationId, $system = false, $allProjects = false, $pid = null, $userRole = null, $isDesignatedContact = false)
    {
        $key = '';
        if ($system or $allProjects) {
            if ($system) {
                $key .= self::SYSTEM . '_';
            }
            if ($allProjects) {
                $key .= self::ALL_PROJECTS . '_';
                if ($userRole) {
                    $key .= '_' . self::USER_ROLE . '_' . $userRole . '_';
                } elseif ($isDesignatedContact) {
                    $key .= '_' . self::DESIGNATED_CONTACT . '_';
                }
            }
        } elseif ($pid) {
            if ($userRole) {
                $key .= $pid . '_' . self::USER_ROLE . '_' . $userRole . '_';
            } elseif ($isDesignatedContact) {
                $key .= $pid . '_' . self::DESIGNATED_CONTACT . '_';
            }
            $key .= $pid . '_';
        }
        return $key . $notificationId;
    }

    /**
     * @return mixed
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
