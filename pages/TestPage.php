<?php

// namespace Stanford\RedcapNotifications;
/** @var \Stanford\RedcapNotifications\RedcapNotifications $module*/

try {
//    Create new client
    $factory = new \Stanford\RedcapNotifications\CacheFactory();
    $client = $factory->getCacheClient( 'redis', '6379');

    $a = $client->getKey('test');
    $b = $client->setKey('test2','boohoo');
    $c = $client->setKeys(['nice' =>'guy', 'nice2' => 'guys2']);

    echo "Keys";
    var_dump($client->listKeys("*"));

} catch (Exception $e) {
    echo "Exception : $e";
}
