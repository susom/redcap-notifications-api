<?php

// namespace Stanford\RedcapNotificationsAPI;
/** @var \Stanford\RedcapNotificationsAPI\RedcapNotificationsAPI $module*/
use \Predis\Collection\Iterator;

try {
//    Create new client
    $factory = new \Stanford\RedcapNotificationsAPI\CacheFactory();
    $path = ($module->getUrl('/lua_scripts/getHashedValues.lua'));
    $client = $factory->getCacheClient( 'redis', '6379', $path);


    echo "Set Key '1_prod_all_1' => '{data:value}'";
    echo "        ";
    echo "Set Key '1_prod_all_2' => '{data:secondhashedvalue}'";
    $a = $client->setKey('1_prod_all_1','{data:value}');
    $a = $client->setKey('1_prod_all_2','{data:secondhashedvalue}');
    var_dump($client->listKeys("*"));

    echo "Set Keys `1_prod_all_2, 1_prod_all_3`";
    $b = $client->setKeys(['2_prod_all_1' =>'{data:value2}', '3_prod_all_1' => '{data:value3}']);
    var_dump($client->listKeys("*"));

    echo "Get Key '1_prod_all_1' and '1_prod_all_2'";
    $c = $client->getKey("1_prod_all_1");
    $c2 = $client->getKey("1_prod_all_2");
    var_dump($c, $c2);

    echo "Get Keys `2_prod_all_1, 3_prod_all_1`";
    $d = $client->getKeys(["2_prod_all_1", "3_prod_all_1"]);
    var_dump($d);

//    $pattern = 'key*';
//    $output = "";
//    echo "Testing Scan for pattern key*";
//    foreach (new Iterator\Keyspace($client->getRedisClient(), $pattern) as $key) {
//        $output .= "$key \n";
//    }
//    var_dump($output);

    echo "Testing getAllValues";
    var_dump($client->getAllValues("1_prod_all_1"));


    echo "Delete Key `1_prod_all_1`";
    $e = $client->deleteKey("1_prod_all_1");
    var_dump($client->listKeys("*"));

    echo "Delete Keys `1_prod_all_2, 1_prod_all_3`";
    $f = $client->deleteKeys(["2_prod_all_1", "3_prod_all_1"]);
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
