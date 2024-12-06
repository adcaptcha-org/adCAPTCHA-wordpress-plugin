<?php
if (!function_exists('add_action')) {
    function add_action($hook, $callback, $priority = 10, $accepted_args = 1) {
        global $mocked_actions;
        $mocked_actions[] = compact('hook', 'callback', 'priority', 'accepted_args');
    }
}

if (!function_exists('add_filter')) {
    function add_filter($hook, $callback, $priority = 10, $accepted_args = 1) {
        global $mocked_filters;
        $mocked_filters[] = compact('hook', 'callback', 'priority', 'accepted_args');
    }
}

if(!function_exists('remove_action')) {
    function remove_action($hook, $callback, $priority = 10, $accepted_args = 1) {
        global $mocked_remove_actions;
        $mocked_remove_actions[] = compact('hook', 'callback', 'priority', 'accepted_args');
    }
}