<?php
set_time_limit(0);

/* Load all classes & config */
include 'auto_load.php';

$crawlers = array('sites', 'ranks', 'trends');

if (!isset($argv[1])) {
    echo 'missing crawler type as argument (rankalytics_crawler / other_crawler)';
    exit();
}

$type = $argv[1];

function isRunning($config)
{
    $status = trim(implode("", file($config['prj_path'] . '/stats/status.txt')));
    if ($status == "") {
        return 0;
    }

    return (int)$status;
}

function crawlerCompleted($response, $info)
{
    echo "completed: " . $info['url'] . "\n";
    echo "   " . $response;
}

if (isRunning($config) == 0) {
    exit('Not running..' . "\n");
}

while (isRunning($config) == 1) {
    $rc = new RollingCurl("crawlerCompleted");
    foreach ($crawlers as $c_no => $c_name) {
        echo "Started " . $c_name . " ..\n";
        $query = "http://45.56.78.107/run_one.php?p=" . $c_name . "&type=" . $type;
        $request = new RollingCurlRequest($query, "GET", null, null, null);
        $rc->add($request);
    }

    $rc->execute();
    unset($rc);

    sleep(1);
}