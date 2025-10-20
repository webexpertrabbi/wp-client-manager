<?php

/**
 * Plugin Name:       Client Controller
 * Description:       This plugin connects to a remote server to check for maintenance status.
 * Version:           1.2.0 (Webhook Version)
 * Author:            WebExpert Rabbi
 * Author URI:        https://webexpertrabbi.com/
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 */


if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

/**
 * Register a new REST API endpoint to receive webhook notifications.
 * This function will act as our webhook receiver.
 */
add_action('rest_api_init', function () {
    register_rest_route('client-controller/v1', '/update-status', [
        'methods'  => 'POST',
        'callback' => 'wpcm_client_handle_webhook',
        'permission_callback' => '__return_true'
    ]);
});


/**
 * Handle the incoming webhook call from the control server. (FINAL SECURE VERSION)
 * @param WP_REST_Request $request The request object.
 */
function wpcm_client_handle_webhook(WP_REST_Request $request)
{
    // Security Check 1: Verify the request IP address (Supports multiple IPs)
    if (defined('WPCM_ALLOWED_IP') && WPCM_ALLOWED_IP) {
        $request_ip = isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '';
        $allowed_ips = array_map('trim', explode(',', WPCM_ALLOWED_IP));

        if (!in_array($request_ip, $allowed_ips)) {
            return new WP_Error('invalid_ip', 'Forbidden: IP address not allowed.', ['status' => 403]);
        }
    }

    $params = $request->get_json_params();
    $key = isset($params['key']) ? sanitize_text_field($params['key']) : '';
    $status = isset($params['status']) ? sanitize_text_field($params['status']) : '';

    // Security Check 2: Verify the activation key
    if (!defined('WPCM_API_KEY') || empty($key) || !hash_equals(WPCM_API_KEY, $key)) {
        return new WP_Error('invalid_key', 'Invalid activation key.', ['status' => 403]);
    }

    if (in_array($status, ['active', 'maintenance'])) {
        update_option('wpcm_site_status', $status);

        if ($status === 'maintenance') {
            // Save custom maintenance data
            $title = isset($params['maintenance_title']) ? sanitize_text_field($params['maintenance_title']) : '';
            $logo_url = isset($params['maintenance_logo_url']) ? esc_url_raw($params['maintenance_logo_url']) : '';
            $text = isset($params['maintenance_text']) ? wp_kses_post($params['maintenance_text']) : ''; // Use wp_kses_post

            update_option('wpcm_maintenance_title', $title);
            update_option('wpcm_maintenance_logo_url', $logo_url);
            update_option('wpcm_maintenance_text', $text);
        } else {
            // Clean up old data when status is 'active'
            delete_option('wpcm_maintenance_title');
            delete_option('wpcm_maintenance_logo_url');
            delete_option('wpcm_maintenance_text');
        }

        return new WP_REST_Response(['success' => true, 'message' => 'Status updated to ' . $status], 200);
    }

    return new WP_Error('invalid_status', 'Invalid status provided.', ['status' => 400]);
}

/**
 * Check the status on every page load and enable maintenance mode if needed.
 * This function remains the same.
 */
add_action('template_redirect', 'wpcm_client_maintenance_mode');
function wpcm_client_maintenance_mode()
{
    $status = get_option('wpcm_site_status', 'active');

    if ($status === 'maintenance') {
        if (is_user_logged_in()) {
            wp_logout();
        }

        $maintenance_file = plugin_dir_path(__FILE__) . 'maintenance-page.php';
        if (file_exists($maintenance_file)) {
            include($maintenance_file);
            exit;
        } else {
            wp_die('This site is currently under maintenance. Please check back later.', 'Maintenance Mode', 503);
        }
    }
}
