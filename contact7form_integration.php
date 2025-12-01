// Contact Form 7 VIN Integration - PHP part

function enqueue_cf7_vin_script() {
    if (function_exists('wpcf7_enqueue_scripts')) {
        wp_enqueue_script('jquery');
        wp_enqueue_script(
            'cf7-vin-decoder',
            get_stylesheet_directory_uri() . '/js/cf7-vin-decoder.js', // path to JS file
            array('jquery'),
            '1.0',
            true
        );
    }
}
add_action('wp_enqueue_scripts', 'enqueue_cf7_vin_script');

// Handle VIN data in Contact Form 7 emails
function add_vin_data_to_cf7_mail($components, $wpcf7_contact_form, $mail_object) {
    // Check if VIN data field exists in the form submission
    if (isset($_POST['vin_data']) && !empty($_POST['vin_data'])) {
        $vin_data = sanitize_textarea_field($_POST['vin_data']);
        
        // Add VIN data to email body
        $components['body'] .= "\n\n--- Vehicle Information ---\n" . $vin_data;
    }
    
    return $components;
}
add_filter('wpcf7_mail_components', 'add_vin_data_to_cf7_mail', 10, 3);

// Alternative: Use CF7 posted data filter
function process_cf7_vin_data($posted_data) {
    // Check if we have a VIN field in the submitted data
    if (is_array($posted_data)) {
        foreach ($posted_data as $key => $value) {
            if (stripos($key, 'vin') !== false && !empty($value)) {
                // This is where you could process VIN data if needed
                // For now, we just log it
                error_log('CF7 VIN submitted: ' . sanitize_text_field($value));
                break;
            }
        }
    }
    
    return $posted_data;
}
add_filter('wpcf7_posted_data', 'process_cf7_vin_data', 10, 1);

// Save VIN data to CF7 submissions (if using Flamingo or similar)
function save_cf7_vin_data($posted_data) {
    if (isset($_POST['vin_data']) && !empty($_POST['vin_data'])) {
        $posted_data['vehicle_information'] = sanitize_textarea_field($_POST['vin_data']);
    }
    
    return $posted_data;
}
add_filter('wpcf7_posted_data', 'save_cf7_vin_data', 20, 1);

