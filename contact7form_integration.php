<?php
/**
 * Legacy Contact Form 7 VIN Integration
 *
 * This file is kept for backward compatibility.
 * The main functionality has been moved to the VIN Decoder plugin classes.
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Only load if the main plugin is not active
if (!class_exists('VIN_Decoder_Plugin')) {

    // Legacy functions for backward compatibility
    function enqueue_cf7_vin_script() {
        if (function_exists('wpcf7_enqueue_scripts')) {
            wp_enqueue_script('jquery');
            wp_enqueue_script(
                'cf7-vin-decoder',
                plugin_dir_url(__FILE__) . 'cf7_vin_decoder.js',
                array('jquery'),
                '1.0',
                true
            );
        }
    }
    add_action('wp_enqueue_scripts', 'enqueue_cf7_vin_script');

    // Handle VIN data in Contact Form 7 emails
    function add_vin_data_to_cf7_mail($components, $wpcf7_contact_form, $mail_object) {
        if (isset($_POST['vin_data']) && !empty($_POST['vin_data'])) {
            $vin_data = sanitize_textarea_field($_POST['vin_data']);
            $components['body'] .= "\n\n--- Vehicle Information ---\n" . $vin_data;
        }
        return $components;
    }
    add_filter('wpcf7_mail_components', 'add_vin_data_to_cf7_mail', 10, 3);

    // Process CF7 posted data
    function process_cf7_vin_data($posted_data) {
        if (is_array($posted_data)) {
            foreach ($posted_data as $key => $value) {
                if (stripos($key, 'vin') !== false && !empty($value)) {
                    error_log('CF7 VIN submitted: ' . sanitize_text_field($value));
                    break;
                }
            }
        }
        return $posted_data;
    }
    add_filter('wpcf7_posted_data', 'process_cf7_vin_data', 10, 1);

    // Save VIN data to CF7 submissions
    function save_cf7_vin_data($posted_data) {
        if (isset($_POST['vin_data']) && !empty($_POST['vin_data'])) {
            $posted_data['vehicle_information'] = sanitize_textarea_field($_POST['vin_data']);
        }
        return $posted_data;
    }
    add_filter('wpcf7_posted_data', 'save_cf7_vin_data', 20, 1);

} else {
    // If the main plugin is active, show a notice
    add_action('admin_notices', function() {
        echo '<div class="notice notice-info"><p>';
        echo __('VIN Decoder: The legacy contact7form_integration.php file is loaded but the main plugin is also active. Consider removing this file to avoid conflicts.', 'vin-decoder');
        echo '</p></div>';
    });
}

