<?php
/**
 * API integration for VIN Decoder plugin
 */

if (!defined('ABSPATH')) {
    exit;
}

class VIN_Decoder_API {

    /**
     * Main plugin instance
     */
    private $plugin;

    /**
     * Constructor
     */
    public function __construct() {
        $this->plugin = VIN_Decoder_Plugin::get_instance();
    }

    /**
     * Decode VIN using available APIs
     */
    public function decode_vin($vin) {
        $settings = $this->plugin->get_settings();
        $results = array();

        // Primary API: NHTSA
        $nhtsa_result = $this->call_nhtsa_api($vin, $settings['api_timeout']);
        if (!is_wp_error($nhtsa_result)) {
            $results['nhtsa'] = $nhtsa_result;
        }

        // Secondary API: VinDecoder.eu (if enabled)
        if ($settings['enable_secondary_api'] === 'yes') {
            $vindecoder_result = $this->call_vindecoder_api($vin, $settings['api_timeout'], $settings['secondary_api_key']);
            if (!is_wp_error($vindecoder_result)) {
                $results['vindecoder'] = $vindecoder_result;
            }
        }

        return $this->merge_api_results($results);
    }

    /**
     * Call NHTSA API
     */
    private function call_nhtsa_api($vin, $timeout = 10) {
        $url = "https://vpic.nhtsa.dot.gov/api/vehicles/DecodeVin/{$vin}?format=json";

        $response = wp_remote_get($url, array(
            'timeout' => $timeout,
            'headers' => array(
                'User-Agent' => 'VIN Decoder Plugin/' . VIN_DECODER_VERSION . ' (WordPress)'
            )
        ));

        if (is_wp_error($response)) {
            return $response;
        }

        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            return new WP_Error('json_decode_error', 'Failed to decode NHTSA API response');
        }

        if (!isset($data['Results']) || !is_array($data['Results'])) {
            return new WP_Error('invalid_response', 'Invalid NHTSA API response format');
        }

        return $this->process_nhtsa_data($data);
    }

    /**
     * Call VinDecoder.eu API
     */
    private function call_vindecoder_api($vin, $timeout = 10, $api_key = '') {
        $url = "https://api.vindecoder.eu/3.2/{$vin}/decode/json";

        $args = array(
            'timeout' => $timeout,
            'headers' => array(
                'User-Agent' => 'VIN Decoder Plugin/' . VIN_DECODER_VERSION . ' (WordPress)'
            )
        );

        if (!empty($api_key)) {
            $args['headers']['Authorization'] = 'Bearer ' . $api_key;
        }

        $response = wp_remote_get($url, $args);

        if (is_wp_error($response)) {
            return $response;
        }

        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            return new WP_Error('json_decode_error', 'Failed to decode VinDecoder.eu API response');
        }

        if (!isset($data['decode']) || !is_array($data['decode'])) {
            return new WP_Error('invalid_response', 'Invalid VinDecoder.eu API response format');
        }

        return $this->process_vindecoder_data($data);
    }

    /**
     * Process NHTSA API data
     */
    private function process_nhtsa_data($data) {
        $vin_info = array();

        foreach ($data['Results'] as $item) {
            if (!isset($item['Value']) || $item['Value'] === "Not Applicable" || empty($item['Value'])) {
                continue;
            }

            $value = trim($item['Value']);

            switch ($item['Variable']) {
                // Basic Info
                case "Make":
                    $vin_info['make'] = $value;
                    break;
                case "Model":
                    $vin_info['model'] = $value;
                    break;
                case "Model Year":
                    $vin_info['year'] = $value;
                    break;
                case "Trim":
                    $vin_info['trim'] = $value;
                    break;
                case "Series":
                    $vin_info['series'] = $value;
                    break;

                // Vehicle Type & Body
                case "Body Class":
                    $vin_info['bodyClass'] = $value;
                    break;
                case "Vehicle Type":
                    $vin_info['vehicleType'] = $value;
                    break;
                case "Vehicle Descriptor":
                    $vin_info['descriptor'] = $value;
                    break;
                case "Number of Doors":
                    $vin_info['doors'] = $value;
                    break;
                case "Number of Seats":
                    $vin_info['seats'] = $value;
                    break;
                case "Number of Seat Rows":
                    $vin_info['seatRows'] = $value;
                    break;

                // Engine Details
                case "Engine Number of Cylinders":
                    $vin_info['cylinders'] = $value;
                    break;
                case "Displacement (L)":
                    $vin_info['displacement'] = $value;
                    break;
                case "Displacement (CI)":
                    $vin_info['displacementCI'] = $value;
                    break;
                case "Engine Model":
                    $vin_info['engineModel'] = $value;
                    break;
                case "Engine HP":
                    $vin_info['horsepower'] = $value;
                    break;
                case "Engine HP (to)":
                    $vin_info['horsepowerTo'] = $value;
                    break;
                case "Engine Configuration":
                    $vin_info['engineConfig'] = $value;
                    break;

                // Fuel & Performance
                case "Fuel Type - Primary":
                    $vin_info['fuelType'] = $value;
                    break;
                case "Fuel Type - Secondary":
                    $vin_info['fuelTypeSecondary'] = $value;
                    break;
                case "Fuel Delivery / Fuel Injection Type":
                    $vin_info['fuelInjection'] = $value;
                    break;
                case "Turbo":
                    $vin_info['turbo'] = $value;
                    break;
                case "Supercharger":
                    $vin_info['supercharger'] = $value;
                    break;

                // Drivetrain
                case "Transmission Style":
                    $vin_info['transmission'] = $value;
                    break;
                case "Transmission Speeds":
                    $vin_info['transmissionSpeeds'] = $value;
                    break;
                case "Drive Type":
                    $vin_info['driveType'] = $value;
                    break;
                case "Axles":
                    $vin_info['axles'] = $value;
                    break;
                case "Axle Configuration":
                    $vin_info['axleConfig'] = $value;
                    break;

                // Dimensions & Weight
                case "Gross Vehicle Weight Rating From":
                    $vin_info['gvwrFrom'] = $value;
                    break;
                case "Gross Vehicle Weight Rating To":
                    $vin_info['gvwrTo'] = $value;
                    break;
                case "Curb Weight (pounds)":
                    $vin_info['curbWeight'] = $value;
                    break;
                case "Wheelbase (inches)":
                    $vin_info['wheelbase'] = $value;
                    break;
                case "Track Width (inches)":
                    $vin_info['trackWidth'] = $value;
                    break;

                // Safety & Features
                case "Air Bag Localization":
                    $vin_info['airbags'] = $value;
                    break;
                case "Anti-lock Braking System (ABS)":
                    $vin_info['abs'] = $value;
                    break;
                case "Electronic Stability Control (ESC)":
                    $vin_info['esc'] = $value;
                    break;
                case "Traction Control System (TCS)":
                    $vin_info['tcs'] = $value;
                    break;

                // Manufacturing
                case "Manufacturer Name":
                    $vin_info['manufacturer'] = $value;
                    break;
                case "Plant City":
                    $vin_info['plantCity'] = $value;
                    break;
                case "Plant State":
                    $vin_info['plantState'] = $value;
                    break;
                case "Plant Country":
                    $vin_info['plantCountry'] = $value;
                    break;
                case "Plant Company Name":
                    $vin_info['plantCompany'] = $value;
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

        if (empty($data['decode']) || !is_array($data['decode'])) {
            return $vin_info;
        }

        $decode = $data['decode'][0];

        // Add any additional data from VinDecoder.eu
        if (isset($decode['msrp']) && !empty($decode['msrp'])) {
            $vin_info['msrp'] = $decode['msrp'];
        }

        if (isset($decode['category']) && !empty($decode['category'])) {
            $vin_info['category'] = $decode['category'];
        }

        if (isset($decode['price']) && !empty($decode['price'])) {
            $vin_info['price'] = $decode['price'];
        }

        if (isset($decode['engine']) && !empty($decode['engine'])) {
            $vin_info['engineInfo'] = $decode['engine'];
        }

        return $vin_info;
    }

    /**
     * Merge results from multiple APIs
     */
    private function merge_api_results($results) {
        $merged = array();

        // Start with NHTSA data as primary
        if (isset($results['nhtsa']) && !is_wp_error($results['nhtsa'])) {
            $merged = $results['nhtsa'];
        }

        // Merge VinDecoder.eu data (only add new fields)
        if (isset($results['vindecoder']) && !is_wp_error($results['vindecoder'])) {
            foreach ($results['vindecoder'] as $key => $value) {
                if (!isset($merged[$key]) || empty($merged[$key])) {
                    $merged[$key] = $value;
                }
            }
        }

        return $merged;
    }

    /**
     * Test API connectivity
     */
    public function test_api_connectivity() {
        $results = array();

        // Test NHTSA API
        $test_vin = '1HGCM82633A123456'; // Sample VIN
        $nhtsa_test = $this->call_nhtsa_api($test_vin, 5);
        $results['nhtsa'] = !is_wp_error($nhtsa_test);

        // Test VinDecoder.eu API if enabled
        $settings = $this->plugin->get_settings();
        if ($settings['enable_secondary_api'] === 'yes') {
            $vindecoder_test = $this->call_vindecoder_api($test_vin, 5, $settings['secondary_api_key']);
            $results['vindecoder'] = !is_wp_error($vindecoder_test);
        } else {
            $results['vindecoder'] = 'disabled';
        }

        return $results;
    }
}
