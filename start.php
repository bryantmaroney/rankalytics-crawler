<?php
include 'auto_load.php';

if (isset($argv[1])) {
    $type = $argv[1];
}

if (isset($_GET['type'])) {
    $type = $_GET['type'];
}

if (!isset($type)) {
    exit('missing type ( rankalytics_crawler / other_crawler )');
}

if (isset($type)) {
    $file = fopen($config['prj_path'] . "/stats/status.txt", "w");
    fwrite($file, 1);
    fclose($file);

    shell_exec('php run_all.php ' . $type . ' > /dev/null 2 &');
}

echo 'started!';