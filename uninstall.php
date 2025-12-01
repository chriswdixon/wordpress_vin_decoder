<?php
/**
 * VIN Decoder Plugin Uninstall
 *
 * This file is called when the plugin is uninstalled.
 * It cleans up all plugin data from the database.
 */

// Prevent direct access
if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

// Include the database class to handle cleanup
if (file_exists(plugin_dir_path(__FILE__) . 'includes/class-vin-decoder-db.php')) {
    require_once plugin_dir_path(__FILE__) . 'includes/class-vin-decoder-db.php';
}

// Clean up database tables
global $wpdb;

$vin_decodes_table = $wpdb->prefix . 'vin_decoder_decodes';
$vin_submissions_table = $wpdb->prefix . 'vin_decoder_submissions';

// Drop tables
$wpdb->query("DROP TABLE IF EXISTS {$vin_submissions_table}");
$wpdb->query("DROP TABLE IF EXISTS {$vin_decodes_table}");

// Clean up options
$options_to_delete = array(
    'vin_decoder_db_version',
    'vin_decoder_api_timeout',
    'vin_decoder_enable_secondary_api',
    'vin_decoder_secondary_api_key',
    'vin_decoder_decodes_table',
    'vin_decoder_submissions_table',
);

foreach ($options_to_delete as $option) {
    delete_option($option);
}

// Clear any cached data
wp_cache_flush();
