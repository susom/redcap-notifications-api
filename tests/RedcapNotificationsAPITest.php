<?php

namespace Stanford\RedcapNotificationsAPI;


require_once __DIR__ . '/../../../redcap_connect.php';

final class RedcapNotificationsAPITest extends \ExternalModules\ModuleBaseTest
{

    public function testGetClient()
    {
        /** @var \Stanford\RedcapNotificationsAPI\RedcapNotificationsAPI $this */

        $client = $this->getClient();
        $this->assertInstanceOf(\GuzzleHttp\Client::class, $client);
    }

    public function testGetGlobalKeys()
    {
        /** @var \Stanford\RedcapNotificationsAPI\RedcapNotificationsAPI $this */
        $expected = [
            0 => "GLOBAL_PROD:DEV_ALLUSERS",
            1 => "GLOBAL_DEV_ALLUSERS",
            2 => "GLOBAL_PROD:DEV_ADMIN",
            3 => "GLOBAL_DEV_ADMIN",
            4 => "GLOBAL_PROD:DEV_DC",
            5 => "GLOBAL_DEV_DC",
        ];
        $keys = $this->getGlobalKeys(0, '1', true);
        $this->assertEquals($expected, $keys);
    }

    public function testBuildCacheKeys()
    {
        /** @var \Stanford\RedcapNotificationsAPI\RedcapNotificationsAPI $this */

        $expected = [
            0 => "GLOBAL_PROD:DEV_ALLUSERS",
            1 => "GLOBAL_DEV_ALLUSERS",
            2 => "GLOBAL_PROD:DEV_ADMIN",
            3 => "GLOBAL_DEV_ADMIN",
            4 => "GLOBAL_PROD:DEV_DC",
            5 => "GLOBAL_DEV_DC",
            6 => "56_PROD:DEV_ALLUSERS",
            7 => "56_DEV_ALLUSERS",
            8 => "56_DEV_ADMIN",
            9 => "56_PROD:DEV_ADMIN",
            10 => "56_DEV_DC",
            11 => "56_PROD:DEV_DC"
        ];
        $this->assertEquals($expected, $this::buildCacheKeys('56', 0, '1', true));
    }

    public function testDetermineKeyVariables()
    {
        /** @var \Stanford\RedcapNotificationsAPI\RedcapNotificationsAPI $this */
        $record = [
            'project_status' => '1',
            'note_user_types' => 'admin',
            'note_project_id' => '56',
        ];
        $expected = array(false, array('56'), 'ADMIN', false, "1");
        $this->assertEquals($expected,$this->determineKeyVariables($record));
    }

    /**
     * @throws \Exception
     */
    public function testParseKey()
    {
        /** @var \Stanford\RedcapNotificationsAPI\RedcapNotificationsAPI $this */
        $key = "GLOBAL_PROD:DEV_ALLUSERS_1";
        $expected = array(
            'type' => 'GLOBAL',
            'status' => 'PROD:DEV',
            'role' => 'ALLUSERS',
            'notification_id' => '1',
        );

        $this->assertEquals($expected, $this::parseKey($key));
    }

    public function testGenerateKey()
    {
        /** @var \Stanford\RedcapNotificationsAPI\RedcapNotificationsAPI $this */
        $expected = "GLOBAL_PROD:DEV_ALLUSERS_1";
        $this->assertEquals($expected, $this::generateKey('1', true, '', null));
    }
}