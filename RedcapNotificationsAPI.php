<?php

namespace Stanford\RedcapNotificationsAPI;
require_once "vendor/autoload.php";
require_once "emLoggerTrait.php";
require_once "classes/CacheInterface.php";
require_once "classes/Redis.php";
require_once "classes/Database.php";
require_once "classes/CacheFactory.php";

use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Promise;
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
    const SERVER_BOTH = 'PROD:DEV';
    const DEV = 'DEV';
    const DESIGNATED_CONTACT = 'DC';
    const ALLUSERS = 'ALLUSERS';

    const REDIS_MAP_NAME = 'NOTIFICATION_MAP';

    private $SURVEY_USER = '[survey respondent]';

    private $cacheClient;

    private $rules = [];

    private $notificationProjectId = null;

    private $client = null;

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

    public function determineKeyVariables($record)
    {
        $allProjects = false;
        $pids = null;
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
            $pids = preg_split('/\s*(,\s*|\n)\s*/', $record['note_project_id'], -1, PREG_SPLIT_NO_EMPTY);
            $this->emDebug("pids");
            $this->emDebug($pids);
            $excluded = preg_split('/\s*(,\s*|\n)\s*/', $record['project_exclusion'], -1, PREG_SPLIT_NO_EMPTY);
            // remove excluded projects
            $pids = array_diff($pids, $excluded);
            $this->emDebug("pids after exclusion");
            $this->emDebug($pids);
        } else {
            $allProjects = true;
        }

        return array($allProjects, $pids, $userRole, $isDesignatedContact, $isProd);
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
//            if (!is_null($record['project_status']) and $record['project_status'] != '') {
//                $isProd = $record['project_status'];
//            }
//
//
//            // determine nitification user role.
//            if ($record['note_user_types'] == 'dc') {
//                $isDesignatedContact = true;
//            } elseif ($record['note_user_types'] == 'admin') {
//                $userRole = self::ADMIN;
//            }
//
//            // if pid/s defined  loop over  listed PID
//            if ($record['note_project_id']) {
//                $pids = explode(',', $record['note_project_id']);
//            } else {
//                $allProjects = true;
//            }

            list($allProjects, $pids, $userRole, $isDesignatedContact, $isProd) = $this->determineKeyVariables($record);

            $this->emDebug("cacheNotification");

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
     * Function called by UI EM to fetch all notifications for a particular client
     * @param $pid
     * @param $projectStatus
     * @param $isAdmin
     * @return array
     */
    public function getNotifications($pid = null, $projectStatus = 0, $isAdmin = false): array
    {
        $notifications = array();
        $keys = self::buildCacheKeys($pid, $projectStatus, $isAdmin, self::isUserDesignatedContact($pid));
        foreach ($keys as $key) {
            // TODO we need to find another way otherwise this will be bottleneck
            if (empty($notifications)) {
                $notifications = $this->getCacheClient()->getData($key);
            } else {
                $notifications = array_merge($this->getCacheClient()->getData($key), $notifications);
            }

        }

        $dismissedNotifications = $this->getCacheClient()->getData(self::getUserDismissKey());
        if (!empty($dismissedNotifications)) {
            $dismissedNotifications = explode(',', end($dismissedNotifications));
            foreach ($dismissedNotifications as $dismissedNotification) {
                unset($notifications[$dismissedNotification]);
            }
        }

        return $notifications;
    }

    public static function getUserDismissKey(): string
    {
        if (defined('USERID')) {
            return USERID . '_dismissals';
        } else {
            throw new \Exception("No User found");
        }
    }

    /**
     * @throws \Exception
     */
    public function dismissNotification($key)
    {
        try {
            if (defined('USERID')) {
                // lock will prevent duplicating user dismiss key
                $dismissKey = self::getUserDismissKey();
                $value = $key;
                $dismissRecord = $this->getCacheClient()->getKey($dismissKey);

                // if user has other dismissed notifications add new one to the list.
                if (!empty($dismissRecord)) {
                    // only one dismiss record per user
                    if (gettype($dismissRecord) === 'array') //EM LOG return
                        $temp = end($dismissRecord);
                    else //REDIS return
                        $temp = $dismissRecord;

                    $value .= ',' . $temp;
                }
                $this->getCacheClient()->setKey($dismissKey, $value);

                return true;
            } else {
                throw new \Exception("User is not logged in! Failed to dismiss notification $key");
            }
        } catch (\Exception $e) {
            \REDCap::logEvent($e);
            return false;
        }
    }

    /**
     * Build all keys
     * Executed on behalf of client request
     * @param $pid
     * @param $projectStatus
     * @param $isAdmin
     * @param $isDesignatedContact
     * @return array
     */
    public static function buildCacheKeys($pid = null, $projectStatus = 0, $isAdmin = false, $isDesignatedContact = false)
    {

        // Grab keys affiliated with GLOBAL settings
        $keys = (new RedcapNotificationsAPI)->getGlobalKeys($projectStatus, $isAdmin, $isDesignatedContact);

        if ($pid) { // Fetch all project specific keys
            $keys[] = $pid . self::getDelimiter() . self::SERVER_BOTH . self::getDelimiter() . self::ALLUSERS;

            $projectPrefix = $projectStatus ? self::PROD : self::DEV;

            // Grab prod/dev all users key
            $keys[] = $pid . self::getDelimiter() . $projectPrefix . self::getDelimiter() . self::ALLUSERS;

            // Get key by admin or dc role
            if ($isAdmin) {
                $keys[] = $pid . self::getDelimiter() . $projectPrefix . self::getDelimiter() . self::ADMIN;
                $keys[] = $pid . self::getDelimiter() . self::SERVER_BOTH . self::getDelimiter() . self::ADMIN;
            }

            if ($isDesignatedContact) {
                $keys[] = $pid . self::getDelimiter() . $projectPrefix . self::getDelimiter() . self::DESIGNATED_CONTACT;
                $keys[] = $pid . self::getDelimiter() . self::SERVER_BOTH . self::getDelimiter() . self::DESIGNATED_CONTACT;
            }
        }

        return $keys;
    }

    /**
     * Will run on each client request for notification payload
     * @param $projectStatus
     * @param $isAdmin
     * @param $isDesignatedContact
     * @return array
     */
    public function getGlobalKeys($projectStatus, $isAdmin, $isDesignatedContact): array
    {
        $keys = [];


        // Then get corresponding global keys depending on role
        $statusPrefix = match ((int)$projectStatus) {
            0 => self::DEV,
            default => self::PROD,
        };

        // Add Global alerts corresponding to both dev/prod for all users
        $keys[] = self::ALL_PROJECTS . self::getDelimiter() . self::SERVER_BOTH . self::getDelimiter() . self::ALLUSERS;
        $keys[] = self::ALL_PROJECTS . self::getDelimiter() . $statusPrefix . self::getDelimiter() . self::ALLUSERS;


        if ($isAdmin) {
            $keys[] = self::ALL_PROJECTS . self::getDelimiter() . self::SERVER_BOTH . self::getDelimiter() . self::ADMIN;
            $keys[] = self::ALL_PROJECTS . self::getDelimiter() . $statusPrefix . self::getDelimiter() . self::ADMIN;
        }

        if ($isDesignatedContact) {
            $keys[] = self::ALL_PROJECTS . self::getDelimiter() . self::SERVER_BOTH . self::getDelimiter() . self::DESIGNATED_CONTACT;
            $keys[] = self::ALL_PROJECTS . self::getDelimiter() . $statusPrefix . self::getDelimiter() . self::DESIGNATED_CONTACT;
        }

        return $keys;
    }

    public static function isUserDesignatedContact($pid)
    {
        // TODO check if Designated Contact EM enabled or not.
        if (defined('USERID')) {
            $user = USERID;
            $sql = sprintf("SELECT contact_userid FROM designated_contact_selected WHERE contact_userid = '%s' and project_id = %s", db_escape($user), $pid);
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
            // if this key for dismissed notification then just return username
            $parsed['username'] = $parts[0];
            return $parsed;
        }

        // second part has to be prod or dev or both PRODDEV
        if (in_array($parts[1], [self::DEV, self::PROD, self::SERVER_BOTH])) {
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

    /**
     * On Notification save record, build the initial cache key
     * @param $notificationId
     * @param $allProjects
     * @param $pid
     * @param $isProd
     * @param $userRole
     * @param $isDesignatedContact
     * @return string
     * @throws \Exception
     */
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
            $key .= self::SERVER_BOTH . self::getDelimiter();
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
            $this->setCacheClient(CacheFactory::getCacheClient($this->getSystemSetting('notification-cache'), $this->getSystemSetting('redis-host'), $this->getSystemSetting('redis-port'), $this->getUrl('./lua_scripts/getHashedValues.lua')));
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


    public function cleanupExpiredDatabaseNotifications()
    {
        // this applies only to database cache.
        if ($this->getSystemSetting('notification-cache') == 'database' or $_GET['skip'] == 'true') {
            $projectId = $this->getSystemSetting('notification-pid');
            $param = array(
                'project_id' => $projectId,
                'return_format' => 'array',
            );
            $records = \REDCap::getData($param);
            $project = new \Project($projectId);
            foreach ($records as $record) {
                $record = end($record);
                $expiryTime = $record['note_end_dt'];
                // check if notification expired.
                if (time() > strtotime($expiryTime)) {
                    // get its current key.
                    $notificationId = $record[$project->table_pk];
                    list($allProjects, $pids, $userRole, $isDesignatedContact, $isProd) = $this->determineKeyVariables($record);
                    // if notifications for specific projects loop over
                    if (!$allProjects) {
                        foreach ($pids as $pid) {
                            $key = self::generateKey($notificationId, false, $pid, $isProd, $userRole, $isDesignatedContact);
                            $this->getCacheClient()->deleteKey($key);
                            $this->getCacheClient()->deleteExpiredDismissedKey($key);
                        }
                    } else {
                        $key = self::generateKey($notificationId, true, $pid, $isProd, $userRole, $isDesignatedContact);
                        $this->getCacheClient()->deleteKey($key);
                        $this->getCacheClient()->deleteExpiredDismissedKey($key);
                    }
                }
            }
        }
    }

    public function cronNotificationsRules()
    {
        try {
            $url = $this->getUrl('ajax/rules', true, true) . '&NOAUTH&pid=' . $this->getNotificationProjectId();
            $client = $this->getClient();
            $promises = [];
            $this->emDebug("Start cron.");
            foreach ($this->getRules() as $index => $rule) {
                $promises[$index] = $client->getAsync($url . '&index=' . $index);

            }
            $this->emDebug("Process Rules");

            $responses = Promise\Utils::unwrap($promises);
            $this->emDebug($responses);
        } catch (\GuzzleHttp\Exception\ClientException $e) {
            $response = $e->getResponse();
            $responseBodyAsString = $response->getBody()->getContents();
            \REDCap::logEvent($responseBodyAsString, "", "", null, null, $this->getNotificationProjectId());
            $this->emError($e->getMessage());
        } catch (\Exception $e) {
            \REDCap::logEvent($e->getMessage(), "", "", null, null, $this->getNotificationProjectId());
            $this->emError($e->getMessage());
        }
    }

    public function executeNotificationsRules($index)
    {
        try {


            foreach ($this->getRules() as $i => $rule) {
                $list = [];
                // only process the index passed. This is for cron job
                if($index != $i){
                    continue;
                }

                if ($rule['api_endpoint'] != '') {
                    $client = new \GuzzleHttp\Client();
                    $this->emLog("Process API call");
                    $this->emLog($rule['api_endpoint']);
                    $response = $client->get($rule['api_endpoint']);
                    if ($response->getStatusCode() < 300) {
                        $list = json_decode($response->getBody(), true);

                    }
                } else {
                    $this->emLog("Process SQL query");
                    $sql = sprintf($rule['sql_query']);
                    $this->emLog("SQL query: " . $sql);
                    $q = db_query($sql);
                    $this->emLog('Success SQL query: ' . ($q == false ? 'false' : 'true'));
                    $this->emLog('Number of rows: '.  db_num_rows($q));
                    while ($row = db_fetch_assoc($q)) {
                        $list[] = end($row);
                    }
                    // TODO verify Project IDs
                }
                // remove duplicates
                $list = array_unique(is_null($list) ? array() : $list);
                $this->emLog("Project list");
                $this->emLog($list);
                $this->updateNotificationsProjectList($rule['notification_record_id'], $list);
                // this loop will only process one rule at a time. This is for cron job
                break;
            }
        } catch (GuzzleException $e) {
            echo json_encode(array('status' => 'error', 'message' => $e->getMessage(), 'index' => $index));
        } catch (\Exception $e) {
            echo json_encode(array('status' => 'error', 'message' => $e->getMessage(), 'index' => $index));
        }

        echo json_encode(array('status' => 'success', 'index' => $index));
    }

    private function updateNotificationsProjectList($recordId, $list)
    {

        $data[\REDCap::getRecordIdField()] = $recordId;
        $data['note_project_id'] = implode(',', $list);
        $data['redcap_event_name'] = \REDCap::getEventNames(true, true, $this->getFirstEventId());
        $response = \REDCap::saveData('json', json_encode(array($data)));
        if (!empty($response['errors'])) {
            if (is_array($response['errors'])) {
                throw new \Exception(implode(",", $response['errors']));
            } else {
                throw new \Exception($response['errors']);
            }
        } else {
            // Trigger save record hook to cache the updated notification in our cache
            $params = array(
                "records" => [$recordId],
                "return_format" => "json",
                "project_id" => $this->getNotificationProjectId()
            );
            $json = REDCap::getData($params);
            $json = json_decode($json, true);
            $this->cacheNotification($json[0]);
        }
    }

    /**
     * @return array
     */
    public function getRules()
    {
        if (!$this->rules) {
            $this->setRules();
        }
        return $this->rules;
    }

    /**
     * save $instances
     */
    public function setRules(): void
    {
        $this->rules = $this->getSubSettings('notification-rules', $this->getNotificationProjectId());;
    }

    /**
     * @return null
     */
    public function getNotificationProjectId()
    {
        if (!$this->notificationProjectId) {
            $this->setNotificationProjectId($this->getSystemSetting('notification-pid'));
        }
        return $this->notificationProjectId;
    }

    /**
     * @param null $notificationProjectId
     */
    public function setNotificationProjectId($notificationProjectId): void
    {
        $this->notificationProjectId = $notificationProjectId;
    }

    /**
     * @return \GuzzleHttp\Client
     */
    public function getClient(): \GuzzleHttp\Client
    {
        if (!$this->client) {
            $this->setClient(new \GuzzleHttp\Client());
        }
        return $this->client;
    }

    /**
     * @param \GuzzleHttp\Client $client
     */
    public function setClient(\GuzzleHttp\Client $client): void
    {
        $this->client = $client;
    }


}
