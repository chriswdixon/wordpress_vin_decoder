<?php
/**
 * Frontend functionality for VIN Decoder plugin
 */

if (!defined('ABSPATH')) {
    exit;
}

class VIN_Decoder_Frontend {

    /**
     * Main plugin instance
     */
    private $plugin;

    /**
     * Database handler
     */
    private $db;

    /**
     * Constructor
     */
    public function __construct() {
        $this->plugin = VIN_Decoder_Plugin::get_instance();
        $this->db = $this->plugin->db;

        $this->init_hooks();
    }

    /**
     * Initialize hooks
     */
    private function init_hooks() {
        add_action('wp_enqueue_scripts', array($this, 'enqueue_frontend_scripts'));
        add_action('wp_ajax_nopriv_decode_vin', array($this, 'ajax_decode_vin'));
        add_action('wp_ajax_decode_vin', array($this, 'ajax_decode_vin'));
        add_filter('wpcf7_mail_components', array($this, 'add_vin_data_to_email'), 10, 3);
        add_filter('wpcf7_posted_data', array($this, 'process_form_submission'), 10, 1);
        add_filter('wpcf7_form_hidden_fields', array($this, 'add_hidden_vin_field'), 10, 1);
    }

    /**
     * Enqueue frontend scripts and styles
     */
    public function enqueue_frontend_scripts() {
        // Only load on pages with Contact Form 7
        if (!has_shortcode(get_post()->post_content ?? '', 'contact-form-7')) {
            return;
        }

        wp_enqueue_script(
            'vin-decoder-frontend',
            VIN_DECODER_PLUGIN_URL . 'assets/js/frontend.js',
            array('jquery'),
            VIN_DECODER_VERSION,
            true
        );

        wp_localize_script('vin-decoder-frontend', 'vinDecoderFrontend', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('vin_decoder_frontend_nonce'),
            'strings' => array(
                'processing' => __('Processing VIN...', 'vin-decoder'),
                'error_invalid_vin' => __('Invalid VIN format. Please check and try again.', 'vin-decoder'),
                'error_decode_failed' => __('Failed to decode VIN. Please try again.', 'vin-decoder'),
                'error_network' => __('Network error. Please try again.', 'vin-decoder'),
                'vin_decoded' => __('VIN decoded successfully!', 'vin-decoder'),
            )
        ));

        wp_enqueue_style(
            'vin-decoder-frontend',
            VIN_DECODER_PLUGIN_URL . 'assets/css/frontend.css',
            array(),
            VIN_DECODER_VERSION
        );
    }

    /**
     * AJAX handler for VIN decoding
     */
    public function ajax_decode_vin() {
        try {
            // Verify nonce
            if (!wp_verify_nonce($_POST['nonce'] ?? '', 'vin_decoder_frontend_nonce')) {
                throw new Exception(__('Security check failed', 'vin-decoder'));
            }

            $vin = sanitize_text_field($_POST['vin'] ?? '');

            // Validate VIN
            if (empty($vin) || !$this->is_valid_vin($vin)) {
                throw new Exception(__('Invalid VIN format', 'vin-decoder'));
            }

            // Check if VIN already exists in database
            $existing_decode = $this->db->get_vin_decode($vin);
            if ($existing_decode) {
                $decoded_data = json_decode($existing_decode->raw_data, true);
                wp_send_json_success(array(
                    'vin_data' => $decoded_data,
                    'formatted' => $this->format_vin_data($decoded_data),
                    'cached' => true
                ));
                return;
            }

            // Decode VIN using API
            $decoded_data = $this->decode_vin_api($vin);

            if (empty($decoded_data)) {
                throw new Exception(__('Unable to decode VIN', 'vin-decoder'));
            }

            // Store in database
            $vin_id = $this->db->store_vin_decode($vin, $decoded_data);
            if (is_wp_error($vin_id)) {
                error_log('VIN Decoder: Failed to store VIN data: ' . $vin_id->get_error_message());
            }

            wp_send_json_success(array(
                'vin_data' => $decoded_data,
                'formatted' => $this->format_vin_data($decoded_data),
                'cached' => false
            ));

        } catch (Exception $e) {
            wp_send_json_error(array(
                'message' => $e->getMessage()
            ));
        }
    }

    /**
     * Process Contact Form 7 submission
     */
    public function process_form_submission($posted_data) {
        // Look for VIN field in the submitted data
        $vin_field = $this->find_vin_field_in_data($posted_data);

        if ($vin_field && !empty($posted_data[$vin_field])) {
            $vin = sanitize_text_field($posted_data[$vin_field]);

            // Get or decode VIN data
            $vin_decode = $this->db->get_vin_decode($vin);

            if (!$vin_decode) {
                // Try to decode it
                $decoded_data = $this->decode_vin_api($vin);
                if (!empty($decoded_data)) {
                    $vin_id = $this->db->store_vin_decode($vin, $decoded_data);
                    $vin_decode = $this->db->get_vin_decode($vin);
                }
            }

            if ($vin_decode) {
                // Store submission data
                $submission_data = array(
                    'form_data' => $posted_data,
                    'vin_field' => $vin_field,
                    'vin_decoded' => true,
                );

                $this->db->store_submission($vin_decode->id, $submission_data);

                // Add VIN data to posted data for email processing
                $posted_data['vin_data'] = $this->format_vin_data(json_decode($vin_decode->raw_data, true));
                $posted_data['vin_decoded'] = 'yes';
            }
        }

        return $posted_data;
    }

    /**
     * Add VIN data to Contact Form 7 email
     */
    public function add_vin_data_to_email($components, $wpcf7_contact_form, $mail_object) {
        // Check if we have VIN data
        if (!empty($_POST['vin_data'])) {
            $vin_data = sanitize_textarea_field($_POST['vin_data']);
            $components['body'] .= "\n\n--- Vehicle Information ---\n" . $vin_data;
        }

        return $components;
    }

    /**
     * Add hidden VIN data field to CF7 forms
     */
    public function add_hidden_vin_field($fields) {
        $fields['vin_data'] = '';
        return $fields;
    }

    /**
     * Decode VIN using APIs
     */
    private function decode_vin_api($vin) {
        $settings = $this->plugin->get_settings();
        $timeout = $settings['api_timeout'];

        // Primary API: NHTSA
        $nhtsa_data = $this->call_nhtsa_api($vin, $timeout);

        $combined_data = array();
        if ($nhtsa_data) {
            $combined_data = $this->process_nhtsa_data($nhtsa_data);
        }

        // Secondary API: VinDecoder.eu (if enabled)
        if ($settings['enable_secondary_api'] === 'yes') {
            $vindecoder_data = $this->call_vindecoder_api($vin, $timeout, $settings['secondary_api_key']);
            if ($vindecoder_data) {
                $combined_data = array_merge($combined_data, $this->process_vindecoder_data($vindecoder_data));
            }
        }

        return $combined_data;
    }

    /**
     * Call NHTSA API
     */
    private function call_nhtsa_api($vin, $timeout) {
        $url = "https://vpic.nhtsa.dot.gov/api/vehicles/DecodeVin/{$vin}?format=json";

        $response = wp_remote_get($url, array(
            'timeout' => $timeout,
            'headers' => array(
                'User-Agent' => 'VIN Decoder Plugin/' . VIN_DECODER_VERSION
            )
        ));

        if (is_wp_error($response)) {
            error_log('VIN Decoder: NHTSA API error: ' . $response->get_error_message());
            return false;
        }

        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        if (!$data || !isset($data['Results'])) {
            error_log('VIN Decoder: Invalid NHTSA API response');
            return false;
        }

        return $data;
    }

    /**
     * Call VinDecoder.eu API
     */
    private function call_vindecoder_api($vin, $timeout, $api_key = '') {
        $url = "https://api.vindecoder.eu/3.2/{$vin}/decode/json";

        $args = array(
            'timeout' => $timeout,
            'headers' => array(
                'User-Agent' => 'VIN Decoder Plugin/' . VIN_DECODER_VERSION
            )
        );

        if (!empty($api_key)) {
            $args['headers']['Authorization'] = 'Bearer ' . $api_key;
        }

        $response = wp_remote_get($url, $args);

        if (is_wp_error($response)) {
            error_log('VIN Decoder: VinDecoder.eu API error: ' . $response->get_error_message());
            return false;
        }

        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        if (!$data || !isset($data['decode'])) {
            error_log('VIN Decoder: Invalid VinDecoder.eu API response');
            return false;
        }

        return $data;
    }

    /**
     * Process NHTSA API data
     */
    private function process_nhtsa_data($data) {
        $vin_info = array();

        if (!isset($data['Results']) || !is_array($data['Results'])) {
            return $vin_info;
        }

        foreach ($data['Results'] as $item) {
            if (!isset($item['Value']) || $item['Value'] === "Not Applicable" || empty($item['Value'])) {
                continue;
            }

            switch ($item['Variable']) {
                case "Make":
                    $vin_info['make'] = $item['Value'];
                    break;
                case "Model":
                    $vin_info['model'] = $item['Value'];
                    break;
                case "Model Year":
                    $vin_info['year'] = $item['Value'];
                    break;
                case "Trim":
                    $vin_info['trim'] = $item['Value'];
                    break;
                case "Series":
                    $vin_info['series'] = $item['Value'];
                    break;
                case "Body Class":
                    $vin_info['bodyClass'] = $item['Value'];
                    break;
                case "Vehicle Type":
                    $vin_info['vehicleType'] = $item['Value'];
                    break;
                case "Number of Doors":
                    $vin_info['doors'] = $item['Value'];
                    break;
                case "Number of Seats":
                    $vin_info['seats'] = $item['Value'];
                    break;
                case "Engine Number of Cylinders":
                    $vin_info['cylinders'] = $item['Value'];
                    break;
                case "Displacement (L)":
                    $vin_info['displacement'] = $item['Value'];
                    break;
                case "Engine Model":
                    $vin_info['engineModel'] = $item['Value'];
                    break;
                case "Engine HP":
                    $vin_info['horsepower'] = $item['Value'];
                    break;
                case "Fuel Type - Primary":
                    $vin_info['fuelType'] = $item['Value'];
                    break;
                case "Transmission Style":
                    $vin_info['transmission'] = $item['Value'];
                    break;
                case "Drive Type":
                    $vin_info['driveType'] = $item['Value'];
                    break;
                case "Gross Vehicle Weight Rating From":
                    $vin_info['gvwrFrom'] = $item['Value'];
                    break;
                case "Curb Weight (pounds)":
                    $vin_info['curbWeight'] = $item['Value'];
                    break;
                case "Air Bag Localization":
                    $vin_info['airbags'] = $item['Value'];
                    break;
                case "Anti-lock Braking System (ABS)":
                    $vin_info['abs'] = $item['Value'];
                    break;
                case "Manufacturer Name":
                    $vin_info['manufacturer'] = $item['Value'];
                    break;
                case "Plant City":
                    $vin_info['plantCity'] = $item['Value'];
                    break;
                case "Plant State":
                    $vin_info['plantState'] = $item['Value'];
                    break;
                case "Plant Country":
                    $vin_info['plantCountry'] = $item['Value'];
                    break;
            }
        }

        return $vin_info;
    }

    /**
     * Process VinDecoder.eu API data
     */
    private function process_vindecoder_data($data) {
        $vin_info = array();

        if (!isset($data['decode']) || !is_array($data['decode']) || empty($data['decode'])) {
            return $vin_info;
        }

        $decode = $data['decode'][0];

        if (isset($decode['msrp']) && !empty($decode['msrp'])) {
            $vin_info['msrp'] = $decode['msrp'];
        }

        if (isset($decode['category']) && !empty($decode['category'])) {
            $vin_info['category'] = $decode['category'];
        }

        return $vin_info;
    }

    /**
     * Format VIN data for display/email
     */
    private function format_vin_data($vin_info) {
        $sections = array();

        // Basic Vehicle Information
        $basic = array();
        if (!empty($vin_info['year'])) $basic[] = "Year: {$vin_info['year']}";
        if (!empty($vin_info['make'])) $basic[] = "Make: {$vin_info['make']}";
        if (!empty($vin_info['model'])) $basic[] = "Model: {$vin_info['model']}";
        if (!empty($vin_info['trim'])) $basic[] = "Trim: {$vin_info['trim']}";
        if (!empty($vin_info['series'])) $basic[] = "Series: {$vin_info['series']}";
        if (!empty($basic)) {
            $sections[] = "=== BASIC INFORMATION ===\n" . implode("\n", $basic);
        }

        // Vehicle Type & Body
        $body = array();
        if (!empty($vin_info['bodyClass'])) $body[] = "Body Class: {$vin_info['bodyClass']}";
        if (!empty($vin_info['vehicleType'])) $body[] = "Vehicle Type: {$vin_info['vehicleType']}";
        if (!empty($vin_info['doors'])) $body[] = "Doors: {$vin_info['doors']}";
        if (!empty($vin_info['seats'])) $body[] = "Seats: {$vin_info['seats']}";
        if (!empty($body)) {
            $sections[] = "=== BODY & CONFIGURATION ===\n" . implode("\n", $body);
        }

        // Engine Details
        $engine = array();
        if (!empty($vin_info['cylinders'])) $engine[] = "Cylinders: {$vin_info['cylinders']}";
        if (!empty($vin_info['displacement'])) $engine[] = "Engine Size: {$vin_info['displacement']}L";
        if (!empty($vin_info['engineModel'])) $engine[] = "Engine Model: {$vin_info['engineModel']}";
        if (!empty($vin_info['horsepower'])) $engine[] = "Horsepower: {$vin_info['horsepower']}";
        if (!empty($engine)) {
            $sections[] = "=== ENGINE SPECIFICATIONS ===\n" . implode("\n", $engine);
        }

        // Fuel System
        $fuel = array();
        if (!empty($vin_info['fuelType'])) $fuel[] = "Fuel Type: {$vin_info['fuelType']}";
        if (!empty($fuel)) {
            $sections[] = "=== FUEL SYSTEM ===\n" . implode("\n", $fuel);
        }

        // Drivetrain
        $drivetrain = array();
        if (!empty($vin_info['transmission'])) $drivetrain[] = "Transmission: {$vin_info['transmission']}";
        if (!empty($vin_info['driveType'])) $drivetrain[] = "Drive Type: {$vin_info['driveType']}";
        if (!empty($drivetrain)) {
            $sections[] = "=== DRIVETRAIN ===\n" . implode("\n", $drivetrain);
        }

        // Dimensions & Weight
        $dimensions = array();
        if (!empty($vin_info['gvwrFrom'])) $dimensions[] = "GVWR: {$vin_info['gvwrFrom']} lbs";
        if (!empty($vin_info['curbWeight'])) $dimensions[] = "Curb Weight: {$vin_info['curbWeight']} lbs";
        if (!empty($dimensions)) {
            $sections[] = "=== DIMENSIONS & WEIGHT ===\n" . implode("\n", $dimensions);
        }

        // Safety Features
        $safety = array();
        if (!empty($vin_info['airbags'])) $safety[] = "Airbags: {$vin_info['airbags']}";
        if (!empty($vin_info['abs'])) $safety[] = "ABS: {$vin_info['abs']}";
        if (!empty($safety)) {
            $sections[] = "=== SAFETY FEATURES ===\n" . implode("\n", $safety);
        }

        // Manufacturing Info
        $manufacturing = array();
        if (!empty($vin_info['manufacturer'])) $manufacturing[] = "Manufacturer: {$vin_info['manufacturer']}";
        $location_parts = array();
        if (!empty($vin_info['plantCity'])) $location_parts[] = $vin_info['plantCity'];
        if (!empty($vin_info['plantState'])) $location_parts[] = $vin_info['plantState'];
        if (!empty($vin_info['plantCountry'])) $location_parts[] = $vin_info['plantCountry'];
        if (!empty($location_parts)) {
            $manufacturing[] = "Plant Location: " . implode(", ", $location_parts);
        }
        if (!empty($manufacturing)) {
            $sections[] = "=== MANUFACTURING ===\n" . implode("\n", $manufacturing);
        }

        // Additional Data
        $additional = array();
        if (!empty($vin_info['msrp'])) $additional[] = "MSRP: {$vin_info['msrp']}";
        if (!empty($vin_info['category'])) $additional[] = "Category: {$vin_info['category']}";
        if (!empty($additional)) {
            $sections[] = "=== ADDITIONAL INFO ===\n" . implode("\n", $additional);
        }

        return implode("\n\n", $sections);
    }

    /**
     * Validate VIN format
     */
    private function is_valid_vin($vin) {
        // Remove non-alphanumeric characters and convert to uppercase
        $clean_vin = preg_replace('/[^A-HJ-NPR-Z0-9]/i', '', $vin);
        $clean_vin = strtoupper($clean_vin);

        // VIN must be exactly 17 characters
        if (strlen($clean_vin) !== 17) {
            return false;
        }

        // Basic character validation (no I, O, Q)
        if (!preg_match('/^[A-HJ-NPR-Z0-9]{17}$/', $clean_vin)) {
            return false;
        }

        return true;
    }

    /**
     * Find VIN field in posted data
     */
    private function find_vin_field_in_data($posted_data) {
        $vin_keywords = array('vin', 'vehicle', 'chassis');

        foreach ($posted_data as $key => $value) {
            $key_lower = strtolower($key);
            foreach ($vin_keywords as $keyword) {
                if (strpos($key_lower, $keyword) !== false) {
                    return $key;
                }
            }
        }

        return false;
    }
}
