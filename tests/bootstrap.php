<?php
require_once __DIR__ . '/../vendor/autoload.php';

if (class_exists('\Brain\Monkey')) {
    \Brain\Monkey\setUp();

    register_shutdown_function(function () {
        \Brain\Monkey\tearDown();
    });
}
