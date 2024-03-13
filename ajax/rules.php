<?php

// namespace Stanford\RedcapNotificationsAPI;
/** @var \Stanford\RedcapNotificationsAPI\RedcapNotificationsAPI $module*/


try{
    $module->executeNotificationsRules();
}catch (\Exception $e){
    $module->emError($e->getMessage());
    http_response_code(404);
    echo json_encode(array('status' => 'error', 'message' => $e->getMessage()));
}