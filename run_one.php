<?php
set_time_limit(0);

/* Load all classes & config */
include 'auto_load.php';

/* TESTS */
if ((isset($_GET['p']) and isset($_GET['type'])) OR isset($argv[1])) {
    if (isset($_GET['p'])) {
        $p = trim($_GET['p']);
        $type = trim($_GET['type']); // rankalytics_crawler
    } else {
        $p = $argv[1];
        $type = 'other_crawler';
    }
} else {
    exit('no work to do .. ');
}

while (1) {
    switch ($p) {
        case 'sites':
            $obj = new GoogleSites($type, $config);
            unset($obj);
            break;
        case 'ranks':
            $obj = new GoogleRanks($type, $config);
            unset($obj);
            break;
        case 'trends':
            $obj = new GoogleTrends($type, $config);
            unset($obj);
            break;
        default:
            exit('unknown crawler' . "\n");
            break;
    }

    exit();
}