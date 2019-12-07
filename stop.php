<?php
include 'auto_load.php';

$file = fopen($config['prj_path'] . "/stats/status.txt", "w");
fwrite($file, 0);
fclose($file);

echo "Crawlers will finish job then close.";
exit;
