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

function execute_mocked_hook($hook_name) {
    global $mocked_actions;

    foreach ($mocked_actions as $action) {
        if ($action['hook'] === $hook_name) {
            if (is_callable($action['callback'])) {
                call_user_func($action['callback']);
            } else {
                error_log("Callback for hook {$hook_name} is not callable.");
            }
        }
    }
}

// Helper function to check hooks in mocked actions or filters
function check_hook_registration($mocked_hooks, $hook_name, $priority = 10, $accepted_args = 1) {
    foreach ($mocked_hooks as $hook) {
        if (
            isset($hook['hook'], $hook['callback'], $hook['priority'], $hook['accepted_args']) &&
            $hook['hook'] === $hook_name &&
            $hook['priority'] === $priority &&
            $hook['accepted_args'] === $accepted_args &&
            is_callable($hook['callback'])
        ) {
            return true;
        }
    }
    return false;
}