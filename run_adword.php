<?php
include 'auto_load.php';

if (isset($_GET['projectId'])) {
    $project_id = trim($_GET['projectId']);
}

if (isset($argv[1])) {
    $project_id = trim($argv[1]);
}

if (!isset($project_id) or trim($project_id) == '') {
    exit('no project id provided!');
}

$t_cmd = "php " . $config['prj_path'] . "/api/NewKeywordAdowordSave.php " . $project_id . ' > /dev/null 2 &';
shell_exec($t_cmd);

echo 'startead getting adwords info..';