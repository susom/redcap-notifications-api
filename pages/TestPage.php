<?php

// namespace Stanford\RedcapNotificationsAPI;
/** @var \Stanford\RedcapNotificationsAPI\RedcapNotificationsAPI $module*/
use \Predis\Collection\Iterator;

try {
//    Create new client
    $factory = new \Stanford\RedcapNotificationsAPI\CacheFactory();
    $client = $factory->getCacheClient( 'redis', '6379');

    echo "Set Key '1_prod_all_2' => '{data:value}' also setting keyset ";
    $a = $client->setKey('1_prod_all_2','{data:value}');
    var_dump($client->listKeys("*"));

    echo "Set Keys `key2, key3`";
    $b = $client->setKeys(['key2' =>'value2', 'key3' => 'value3']);
    var_dump($client->listKeys("*"));

    echo "Get Key '1_prod_all_2'";
    $c = $client->getKey("1_prod_all_2");
    var_dump($c);

    echo "Get Keys `key2, key3`";
    $d = $client->getKeys(["key2", "key3"]);
    var_dump($d);

    $pattern = 'key*';
    $output = "";
    echo "Testing Scan for pattern key*";
    foreach (new Iterator\Keyspace($client->getRedisClient(), $pattern) as $key) {
        $output .= "$key \n";
    }
    var_dump($output);

    echo "Testing getAllHashed";
    var_dump($client->getAllHashed("1_prod_all"));


    echo "Delete Key `1_prod_all_1`";
    $e = $client->deleteKey("1_prod_all");
    var_dump($client->listKeys("*"));

    echo "Delete Keys `key2, key3, keyset_1_prod_all`";
    $f = $client->deleteKeys(["key2", "key3", "keyset_1_prod_all"]);
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
