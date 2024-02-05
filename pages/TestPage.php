<?php

// namespace Stanford\RedcapNotificationsAPI;
/** @var \Stanford\RedcapNotificationsAPI\RedcapNotificationsAPI $module*/

try {
//    Create new client
    $factory = new \Stanford\RedcapNotificationsAPI\CacheFactory();
    $client = $factory->getCacheClient( 'redis', '6379');

    echo "Set Key `key1`";
    $a = $client->setKey('key1','value1');
    var_dump($client->listKeys("*"));

    echo "Set Keys `key2, key3`";
    $b = $client->setKeys(['key2' =>'value2', 'key3' => 'value3']);
    var_dump($client->listKeys("*"));

    echo "Get Key `key1`";
    $c = $client->getKey("key1");
    var_dump($c);

    echo "Get Keys `key2, key3`";
    $d = $client->getKeys(["key2", "key3"]);
    var_dump($d);

    echo "Delete Key `key1`";
    $e = $client->deleteKey("key1");
    var_dump($client->listKeys("*"));

    echo "Delete Keys `key2, key3`";
    $f = $client->deleteKeys(["key2", "key3"]);
    var_dump($client->listKeys("*"));



} catch (Exception $e) {
    echo "Exception : $e";
}

?>
<div class="accordion" id="clientInfo">
    <div class="accordion-item">
        <h2 class="accordion-header" id="headingOne">
            <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseOne" aria-expanded="false" aria-controls="collapseOne">
                Client info
            </button>
        </h2>
        <div id="collapseOne" class="accordion-collapse collapse" aria-labelledby="headingOne" data-bs-parent="#clientInfo">
            <div class="accordion-body">
                <?php var_dump($client->info()) ?>
            </div>
        </div>
    </div>
</div>
