<?php
/**
 * Plugin Name: TN Update Controller
 * Description: One catalogue, background update checks and guided updates for Techn plugins.
 * Version: 0.4.2
 * Author: Techn
 * Author URI: https://techn.com.au
 * Update URI: https://github.com/cchatterton/tn-update-controller
 * Requires at least: 6.5
 * Requires PHP: 7.4
 * Network: true
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: tn-update-controller
 */
if (!defined('ABSPATH')) { exit; }
define('TNUC_VERSION', '0.4.2');
define('TNUC_API_VERSION', 1);
define('TNUC_FILE', __FILE__);
define('TNUC_DIR', __DIR__ . '/');
define('TNUC_CATALOGUE_URL', 'https://raw.githubusercontent.com/cchatterton/tn-update-controller/main/catalogue.json');
foreach (['state', 'catalogue', 'legacy', 'updates', 'operations', 'actions', 'admin'] as $tnuc_module) {
    require_once TNUC_DIR . 'functions/' . $tnuc_module . '.php';
}
unset($tnuc_module);
register_activation_hook(__FILE__, 'tnuc_activate');
register_deactivation_hook(__FILE__, 'tnuc_deactivate');
add_action('plugins_loaded', 'tnuc_boot', PHP_INT_MAX);
function tnuc_boot(): void {
    if (!tnuc_available()) { return; }
    tnuc_suppress_legacy();
    add_action('init', 'tnuc_suppress_legacy', -999);
    add_action('admin_init', 'tnuc_suppress_legacy', -999);
    add_filter('site_transient_update_plugins', 'tnuc_project_updates', PHP_INT_MAX);
    add_filter('pre_set_site_transient_update_plugins', 'tnuc_project_updates', PHP_INT_MAX);
    add_filter('plugins_api', 'tnuc_plugin_information', PHP_INT_MAX, 3);
    add_filter('plugin_row_meta', 'tnuc_row_meta', PHP_INT_MAX, 4);
    add_filter('upgrader_pre_download', 'tnuc_verify_download', 10, 4);
    add_filter('upgrader_source_selection', 'tnuc_verify_source', 20, 4);
    add_action('tnuc_scheduled_check', 'tnuc_scheduled_check');
    add_action('admin_menu', 'tnuc_menu');
    add_action('network_admin_menu', 'tnuc_menu');
    add_action('admin_enqueue_scripts', 'tnuc_assets');
    add_action('admin_post_tnuc_action', 'tnuc_handle_form');
    add_action('wp_ajax_tnuc_action', 'tnuc_handle_ajax');
    add_filter('plugin_action_links_' . plugin_basename(TNUC_FILE), 'tnuc_settings_link');
    add_filter('network_admin_plugin_action_links_' . plugin_basename(TNUC_FILE), 'tnuc_settings_link');
}
