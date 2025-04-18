<?php
define('PHPUNIT_RUNNING', true);
require_once __DIR__ . '/../vendor/autoload.php';

require_once __DIR__ . '/../vendor/antecedent/patchwork/Patchwork.php';
use function Patchwork\replace;
require __DIR__ . '/test_helpers.php';
require_once __DIR__ . '/mocks/WPErrorMock.php';

// Initialize Patchwork early
replace('add_action', function (...$args) {
    return true; 
});

\Brain\Monkey\setUp();
\Brain\Monkey\tearDown();
