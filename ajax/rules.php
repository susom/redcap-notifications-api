<?php

// namespace Stanford\RedcapNotificationsAPI;
/** @var \Stanford\RedcapNotificationsAPI\RedcapNotificationsAPI $module*/


try{
    $index = filter_var($_GET['index'], FILTER_SANITIZE_NUMBER_INT);
    $module->executeNotificationsRules($index);
}catch (\Exception $e){
    $module->emError($e->getMessage());
    http_response_code(404);
    echo json_encode(array('status' => 'error', 'message' => $e->getMessage()));
}