<?php

/**
 * Plugin Name:       WP Client Manager
 * Description:       Manage client websites and control maintenance mode remotely.
 * Version:           2.0.0
 * Author:            WebExpert Rabbi
 * Author URI:        https://webexpertrabbi.com/
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

// Global variable for the table name
global $wpdb;
define('WPCM_TABLE_NAME', $wpdb->prefix . 'client_sites');

// Load WP_List_Table class if it's not already loaded
if (!class_exists('WP_List_Table')) {
    require_once(ABSPATH . 'wp-admin/includes/class-wp-list-table.php');
}

/**
 * Client Sites List Table Class.
 * This class will handle the display of our data in a standard WordPress table.
 */
class WPCM_Client_Sites_List_Table extends WP_List_Table
{

    private $serial_number_start = 0;

    public function __construct()
    {
        parent::__construct([
            'singular' => 'Client Site',
            'plural'   => 'Client Sites',
            'ajax'     => false
        ]);
    }

    public function get_columns()
    {
        return [
            'cb'                => '<input type="checkbox" />',
            'serial_number'     => 'SL',
            'site_name'         => 'Site Name',
            'site_url'          => 'Site URL',
            'activation_key'    => 'Activation Key',
            'status'            => 'Status',
            'activation_date'   => 'Activation Date',
        ];
    }

    public function prepare_items()
    {
        $columns = $this->get_columns();
        $hidden = [];
        $sortable = [];
        $this->_column_headers = [$columns, $hidden, $sortable];

        $per_page = 10;
        $current_page = $this->get_pagenum();
        $offset = ($current_page - 1) * $per_page;
        $this->serial_number_start = $offset;

        global $wpdb;
        $total_items = $wpdb->get_var("SELECT COUNT(id) FROM " . WPCM_TABLE_NAME);

        $this->set_pagination_args([
            'total_items' => $total_items,
            'per_page'    => $per_page
        ]);

        $this->items = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM " . WPCM_TABLE_NAME . " ORDER BY id DESC LIMIT %d OFFSET %d",
                $per_page,
                $offset
            ),
            ARRAY_A
        );
    }

    public function column_default($item, $column_name)
    {
        return esc_html($item[$column_name]);
    }

    public function column_serial_number($item)
    {
        static $i = 0;
        $i++;
        return $this->serial_number_start + $i;
    }

    public function column_site_name($item)
    {
        $page = 'wp-client-manager';

        // Action links
        $actions = [];
        $actions['edit'] = sprintf('<a href="?page=%s&action=edit&id=%s">Edit</a>', $page, $item['id']);

        if ($item['status'] === 'active') {
            $actions['maintenance'] = sprintf('<a href="?page=%s&action=maintenance&id=%s&_wpnonce=%s">Set Maintenance</a>', $page, $item['id'], wp_create_nonce('wpcm_status_change'));
        } else {
            $actions['active'] = sprintf('<a href="?page=%s&action=active&id=%s&_wpnonce=%s">Set Active</a>', $page, $item['id'], wp_create_nonce('wpcm_status_change'));
        }

        $actions['delete'] = sprintf('<a href="?page=%s&action=delete&id=%s&_wpnonce=%s" onclick="return confirm(\'Are you sure you want to delete this record?\')">Delete</a>', $page, $item['id'], wp_create_nonce('wpcm_delete_record'));

        return sprintf('%1$s %2$s', esc_html($item['site_name']), $this->row_actions($actions));
    }

    public function column_status($item)
    {
        $status_class = 'status-' . esc_attr($item['status']);
        return sprintf('<span class="%s">%s</span>', $status_class, ucfirst(esc_html($item['status'])));
    }

    public function column_site_url($item)
    {
        return sprintf('<a href="%1$s" target="_blank">%1$s</a>', esc_url($item['site_url']));
    }

    public function column_activation_key($item)
    {
        return sprintf('<input type="text" class="key-field" value="%s" readonly onclick="this.select();">', esc_attr($item['activation_key']));
    }
}


/**
 * Renders the dashboard page content. (FINAL VERSION with Responsive Layout)
 */
function wpcm_render_dashboard_page()
{
    global $wpdb;

    // Check if we are in the edit view
    if (isset($_GET['action']) && $_GET['action'] === 'edit' && isset($_GET['id'])) {
        $id = intval($_GET['id']);
        $site = $wpdb->get_row($wpdb->prepare("SELECT * FROM " . WPCM_TABLE_NAME . " WHERE id = %d", $id));

        if ($site) {
            // Display the Edit Form (This part remains unchanged)
?>
            <div class="wrap">
                <h1>Edit Client Site</h1>
                <form method="POST" action="admin.php?page=wp-client-manager">
                    <input type="hidden" name="action" value="wpcm_update_site">
                    <input type="hidden" name="id" value="<?php echo esc_attr($id); ?>">
                    <?php wp_nonce_field('wpcm_update_action', 'wpcm_update_nonce'); ?>

                    <table class="form-table">
                        <tr valign="top">
                            <th scope="row"><label for="site_name">Site Name</label></th>
                            <td><input type="text" id="site_name" name="site_name" value="<?php echo esc_attr($site->site_name); ?>" class="regular-text" required></td>
                        </tr>
                        <tr valign="top">
                            <th scope="row"><label for="site_url">Site URL</label></th>
                            <td><input type="url" id="site_url" name="site_url" value="<?php echo esc_attr($site->site_url); ?>" class="regular-text" required></td>
                        </tr>
                        <tr valign="top">
                            <th scope="row">
                                <h3>Maintenance Page Settings</h3>
                            </th>
                            <td>
                                <hr>
                            </td>
                        </tr>
                        <tr valign="top">
                            <th scope="row"><label for="maintenance_title">Maintenance Title</label></th>
                            <td><input type="text" id="maintenance_title" name="maintenance_title" value="<?php echo esc_attr($site->maintenance_title); ?>" class="regular-text" placeholder="Under Maintenance"></td>
                        </tr>
                        <tr valign="top">
                            <th scope="row"><label for="maintenance_logo_url">Logo URL</label></th>
                            <td><input type="url" id="maintenance_logo_url" name="maintenance_logo_url" value="<?php echo esc_attr($site->maintenance_logo_url); ?>" class="regular-text" placeholder="https://example.com/logo.png"></td>
                        </tr>
                        <tr valign="top">
                            <th scope="row"><label for="maintenance_text">Maintenance Text</label></th>
                            <td><textarea id="maintenance_text" name="maintenance_text" rows="5" class="large-text"><?php echo esc_textarea($site->maintenance_text); ?></textarea></td>
                        </tr>
                    </table>

                    <?php submit_button('Save Changes'); ?>
                </form>
            </div>
    <?php
        } else {
            echo '<div class="wrap"><h1>Site not found.</h1></div>';
        }
        return; // Stop rendering the rest of the page
    }

    // --- Main Dashboard View ---
    $list_table = new WPCM_Client_Sites_List_Table();
    $list_table->prepare_items();
    ?>
    <style>
        .wrap {
            max-width: 1200px;
        }

        .status-active {
            color: #228B22;
            font-weight: bold;
        }

        .status-maintenance {
            color: #DC143C;
            font-weight: bold;
        }

        .key-field {
            background: #f0f2f5;
            cursor: copy;
            width: 100%;
            border: 1px solid #ddd;
        }

        /* --- NEW RESPONSIVE LAYOUT CSS --- */
        .wpcm-row {
            display: flex;
            flex-wrap: wrap;
            margin-top: 20px;
            gap: 20px;
            /* Creates space between columns */
        }

        .wpcm-col {
            flex: 1;
            min-width: 300px;
            /* Prevents columns from getting too narrow */
            background: #fff;
            padding: 20px;
            border: 1px solid #ccd0d4;
        }

        /* Media query for mobile devices */
        @media (max-width: 782px) {
            .wpcm-row {
                flex-direction: column;
            }
        }

        /* --- END NEW CSS --- */

        .add-site-form .form-field {
            margin-bottom: 10px;
        }

        .add-site-form label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
        }

        .add-site-form input[type="text"],
        .add-site-form input[type="url"] {
            width: 100%;
        }
    </style>

    <div class="wrap">
        <h1 class="wp-heading-inline">Client Site Manager</h1>

        <div class="wpcm-row">
            <div class="wpcm-col add-site-form">
                <h2>Add New Client Site</h2>
                <form method="POST" action="admin.php?page=wp-client-manager">
                    <?php wp_nonce_field('wpcm_add_action', 'wpcm_add_nonce'); ?>
                    <div class="form-field">
                        <label for="site_name">Site Name</label>
                        <input type="text" id="site_name" name="site_name" required>
                    </div>
                    <div class="form-field">
                        <label for="site_url">Site URL</label>
                        <input type="url" id="site_url" name="site_url" placeholder="https://clienturl.com" required>
                    </div>
                    <?php submit_button('Add Site'); ?>
                </form>
            </div>

            <div class="wpcm-col" id="wpcm-server-ips">
                <h3>Server IP Information</h3>
                <?php
                $public_ipv4_response = wp_remote_get('https://api.ipify.org');
                $public_ipv6_response = wp_remote_get('https://api64.ipify.org');
                $public_ipv4 = is_wp_error($public_ipv4_response) ? 'Unavailable' : esc_html(wp_remote_retrieve_body($public_ipv4_response));
                $public_ipv6 = is_wp_error($public_ipv6_response) ? 'Unavailable' : esc_html(wp_remote_retrieve_body($public_ipv6_response));

                $internal_ip_url = get_rest_url(null, '/wp-client-manager/v1/get-loopback-ip');
                $internal_response = wp_remote_get($internal_ip_url, ['timeout' => 10]);
                $internal_ip = 'Unavailable';
                if (!is_wp_error($internal_response) && wp_remote_retrieve_response_code($internal_response) === 200) {
                    $body = json_decode(wp_remote_retrieve_body($internal_response), true);
                    if (isset($body['loopback_ip'])) {
                        $internal_ip = esc_html($body['loopback_ip']);
                    }
                }
                ?>
                <p><strong>Public IPv4:</strong> <?php echo $public_ipv4; ?></p>
                <p><strong>Public IPv6:</strong> <?php echo $public_ipv6; ?></p>
                <p><strong>Internal/Loopback IP:</strong> <?php echo $internal_ip; ?></p>
                <p><strong>Standard Local IP:</strong> 127.0.0.1</p>
            </div>
        </div>

        <form method="post" style="margin-top: 20px;">
            <?php $list_table->display(); ?>
        </form>
    </div>
<?php
}

// Add this new function to show admin notices
function wpcm_show_admin_notices()
{
    if (!isset($_GET['page']) || $_GET['page'] !== 'wp-client-manager' || !isset($_GET['message'])) {
        return;
    }

    $message_id = intval($_GET['message']);
    $type = 'success';
    $message = '';

    switch ($message_id) {
        case 1:
            $message = 'New client site added successfully.';
            break;
        case 2:
            $message = 'Client site deleted successfully.';
            break;
        case 3:
            $site_name = isset($_GET['site_name']) ? sanitize_text_field(urldecode($_GET['site_name'])) : 'the site';
            $status = isset($_GET['status']) ? sanitize_text_field($_GET['status']) : 'updated';
            $message = sprintf('Status for <strong>%s</strong> successfully updated to <strong>%s</strong>.', esc_html($site_name), esc_html($status));
            break;
        case 4:
            $type = 'error';
            $site_name = isset($_GET['site_name']) ? sanitize_text_field(urldecode($_GET['site_name'])) : 'The site';
            $error = isset($_GET['error']) ? sanitize_text_field(urldecode($_GET['error'])) : 'An unknown error occurred.';
            $message = sprintf('<strong>Error:</strong> Could not contact the client site "<strong>%s</strong>". Status was not changed. Reason: %s', esc_html($site_name), esc_html($error));
            break;
        case 5:
            $type = 'error';
            $message = 'Error: Could not find the specified site in the database.';
            break;
        case 6:
            $message = 'Site details updated successfully.';
            break;
    }

    if ($message) {
        printf('<div class="notice notice-%s is-dismissible"><p>%s</p></div>', esc_attr($type), $message);
    }
}
add_action('admin_notices', 'wpcm_show_admin_notices');



// আগের ফাংশনগুলো অপরিবর্তিত আছে
function wpcm_activate()
{
    global $wpdb;
    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');

    $table_name = WPCM_TABLE_NAME;
    $charset_collate = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE $table_name (
        id INT(11) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        site_name VARCHAR(255) NOT NULL,
        site_url VARCHAR(255) NOT NULL,
        activation_key VARCHAR(255) NOT NULL UNIQUE,
        status VARCHAR(50) NOT NULL DEFAULT 'active',
        activation_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        maintenance_title VARCHAR(255),
        maintenance_logo_url TEXT,
        maintenance_text TEXT
    ) $charset_collate;";

    dbDelta($sql);
    flush_rewrite_rules();
}
register_activation_hook(__FILE__, 'wpcm_activate');

function wpcm_add_admin_menu()
{
    add_menu_page('Client Manager', 'Client Manager', 'manage_options', 'wp-client-manager', 'wpcm_render_dashboard_page', 'dashicons-admin-site-alt3', 25);
}
add_action('admin_menu', 'wpcm_add_admin_menu');

/**
 * Handle all form submissions and action links before the page loads. (FINAL VERSION)
 */
function wpcm_handle_actions()
{
    // We only want this to run on our plugin's page.
    if (!isset($_GET['page']) || $_GET['page'] !== 'wp-client-manager') {
        return;
    }

    global $wpdb;

    // --- HANDLE POST REQUESTS ---
    if (isset($_POST['action']) && $_POST['action'] === 'wpcm_update_site' && isset($_POST['wpcm_update_nonce']) && wp_verify_nonce($_POST['wpcm_update_nonce'], 'wpcm_update_action')) {
        $id = intval($_POST['id']);
        $site_name = sanitize_text_field($_POST['site_name']);
        $site_url = esc_url_raw($_POST['site_url']);
        $title = sanitize_text_field($_POST['maintenance_title']);
        $logo_url = esc_url_raw($_POST['maintenance_logo_url']);
        $text = wp_kses_post($_POST['maintenance_text']); // Using wp_kses_post to allow safe HTML

        $wpdb->update(
            WPCM_TABLE_NAME,
            [
                'site_name' => $site_name,
                'site_url' => $site_url,
                'maintenance_title' => $title,
                'maintenance_logo_url' => $logo_url,
                'maintenance_text' => $text
            ],
            ['id' => $id]
        );
        wp_cache_flush();
        wp_redirect(admin_url('admin.php?page=wp-client-manager&message=6'));
        exit;
    }

    if (isset($_POST['wpcm_add_nonce']) && wp_verify_nonce($_POST['wpcm_add_nonce'], 'wpcm_add_action')) {
        $site_name = sanitize_text_field($_POST['site_name']);
        $site_url = esc_url_raw($_POST['site_url']);

        if (!empty($site_name) && !empty($site_url)) {
            $activation_key = bin2hex(random_bytes(16));
            $wpdb->insert(WPCM_TABLE_NAME, ['site_name' => $site_name, 'site_url' => $site_url, 'activation_key' => $activation_key]);
            wp_redirect(admin_url('admin.php?page=wp-client-manager&message=1'));
            exit;
        }
    }

    // --- HANDLE GET REQUESTS (ACTION LINKS) ---
    if (isset($_GET['action']) && isset($_GET['id']) && isset($_GET['_wpnonce'])) {
        $action = sanitize_key($_GET['action']);
        $id = intval($_GET['id']);
        $nonce = $_GET['_wpnonce'];

        if ($action === 'delete' && wp_verify_nonce($nonce, 'wpcm_delete_record')) {
            $wpdb->delete(WPCM_TABLE_NAME, ['id' => $id]);
            wp_redirect(admin_url('admin.php?page=wp-client-manager&message=2'));
            exit;
        }

        if (($action === 'active' || $action === 'maintenance') && wp_verify_nonce($nonce, 'wpcm_status_change')) {
            $new_status = $action;
            $site = $wpdb->get_row($wpdb->prepare("SELECT * FROM " . WPCM_TABLE_NAME . " WHERE id = %d", $id));

            if ($site) {
                $webhook_url = rtrim($site->site_url, '/') . '/wp-json/client-controller/v1/update-status';
                $body = [
                    'key' => $site->activation_key,
                    'status' => $new_status,
                    'maintenance_title' => $site->maintenance_title,
                    'maintenance_logo_url' => $site->maintenance_logo_url,
                    'maintenance_text' => $site->maintenance_text
                ];

                $response = wp_remote_post($webhook_url, [
                    'method'    => 'POST',
                    'timeout'   => 20,
                    'headers'   => ['Content-Type' => 'application/json; charset=utf-8'],
                    'body'      => json_encode($body),
                    'blocking'  => true
                ]);

                if (is_wp_error($response) || wp_remote_retrieve_response_code($response) !== 200) {
                    if (is_wp_error($response)) {
                        $error_message = 'Connection Error: ' . $response->get_error_message();
                    } else {
                        $response_code = wp_remote_retrieve_response_code($response);
                        $response_body = substr(wp_remote_retrieve_body($response), 0, 300);
                        $error_message = "Client site responded with HTTP code: $response_code. Response Body: " . esc_html($response_body);
                    }
                    $redirect_url = admin_url('admin.php?page=wp-client-manager&message=4&site_name=' . urlencode($site->site_name) . '&error=' . urlencode($error_message));
                } else {
                    $wpdb->update(WPCM_TABLE_NAME, ['status' => $new_status], ['id' => $id]);
                    wp_cache_flush();
                    $redirect_url = admin_url('admin.php?page=wp-client-manager&message=3&site_name=' . urlencode($site->site_name) . '&status=' . $new_status);
                }
            } else {
                $redirect_url = admin_url('admin.php?page=wp-client-manager&message=5');
            }
            wp_redirect($redirect_url);
            exit;
        }
    }
}
add_action('admin_init', 'wpcm_handle_actions');

/**
 * Registers a new REST API endpoint to discover the server's loopback IP.
 */
function wpcm_register_ip_discovery_route()
{
    register_rest_route('wp-client-manager/v1', '/get-loopback-ip', [
        'methods' => 'GET',
        'callback' => 'wpcm_get_loopback_ip_callback',
        'permission_callback' => '__return_true'
    ]);
}
add_action('rest_api_init', 'wpcm_register_ip_discovery_route');

/**
 * The callback function for our IP discovery endpoint.
 * It simply returns the IP address it sees in the request.
 */
function wpcm_get_loopback_ip_callback()
{
    $ip = isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : 'Unavailable';
    return new WP_REST_Response(['loopback_ip' => $ip], 200);
}
