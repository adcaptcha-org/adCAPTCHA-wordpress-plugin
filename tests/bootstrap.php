<?php
require_once __DIR__ . '/../vendor/autoload.php';

// Suppress deprecations (temporary measure)
error_reporting(E_ALL & ~E_DEPRECATED & ~E_USER_DEPRECATED);

// Set up Brain Monkey
if (class_exists('\Brain\Monkey')) {
    \Brain\Monkey\setUp();

    // Ensure proper teardown on shutdown
    register_shutdown_function(function () {
        \Brain\Monkey\tearDown();
    });
}

// Debugging (remove in production/testing CI once verified)
// var_dump(getcwd());
// var_dump(realpath(__DIR__ . '/../vendor/autoload.php'));
