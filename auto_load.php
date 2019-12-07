<?php
function __autoload($className)
{
    $main_dir = 'classes/';
    $sub_dirs = array(
        'global/',
        'crawlers/'
    );

    foreach ($sub_dirs as $s_no => $s_dir) {
        $load_this = __DIR__ . '/' . $main_dir . $s_dir . $className . '.php';
        if (file_exists($load_this)) {
            require_once $load_this;
        }
    }
}

$config = require 'config.php';