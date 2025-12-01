<?php
/**
 * Database handler for VIN Decoder plugin
 */

if (!defined('ABSPATH')) {
    exit;
}

class VIN_Decoder_DB {

    /**
     * Table names
     */
    private $vin_decodes_table;
    private $vin_submissions_table;

    /**
     * Constructor
     */
    public function __construct() {
        global $wpdb;
        $this->vin_decodes_table = $wpdb->prefix . 'vin_decoder_decodes';
        $this->vin_submissions_table = $wpdb->prefix . 'vin_decoder_submissions';
    }

    /**
     * Create database tables
     */
    public function create_tables() {
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();

        // VIN decodes table
        $sql_decodes = "CREATE TABLE {$this->vin_decodes_table} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            vin varchar(17) NOT NULL,
            make varchar(100) DEFAULT '',
            model varchar(100) DEFAULT '',
            year varchar(4) DEFAULT '',
            trim varchar(100) DEFAULT '',
            body_class varchar(100) DEFAULT '',
            vehicle_type varchar(100) DEFAULT '',
            engine_cylinders varchar(50) DEFAULT '',
            engine_displacement varchar(50) DEFAULT '',
            engine_hp varchar(50) DEFAULT '',
            fuel_type varchar(50) DEFAULT '',
            transmission varchar(100) DEFAULT '',
            drive_type varchar(50) DEFAULT '',
            manufacturer varchar(100) DEFAULT '',
            plant_city varchar(100) DEFAULT '',
            plant_state varchar(100) DEFAULT '',
            plant_country varchar(100) DEFAULT '',
            gvwr varchar(50) DEFAULT '',
            curb_weight varchar(50) DEFAULT '',
            airbags varchar(255) DEFAULT '',
            abs varchar(50) DEFAULT '',
            raw_data longtext DEFAULT '',
            api_source varchar(50) DEFAULT 'nhtsa',
            decoded_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY vin (vin),
            KEY make_model (make, model),
            KEY decoded_at (decoded_at)
        ) $charset_collate;";

        // VIN submissions table (links form submissions to VINs)
        $sql_submissions = "CREATE TABLE {$this->vin_submissions_table} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            vin_id bigint(20) unsigned NOT NULL,
            form_id varchar(50) DEFAULT '',
            submission_data longtext DEFAULT '',
            user_ip varchar(45) DEFAULT '',
            user_agent text DEFAULT '',
            submitted_at datetime DEFAULT CURRENT_TIMESTAMP,
            status varchar(20) DEFAULT 'completed',
            PRIMARY KEY (id),
            KEY vin_id (vin_id),
            KEY form_id (form_id),
            KEY submitted_at (submitted_at),
            KEY status (status),
            FOREIGN KEY (vin_id) REFERENCES {$this->vin_decodes_table}(id) ON DELETE CASCADE
        ) $charset_collate;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql_decodes);
        dbDelta($sql_submissions);

        // Store table names for later use
        update_option('vin_decoder_decodes_table', $this->vin_decodes_table);
        update_option('vin_decoder_submissions_table', $this->vin_submissions_table);
    }

    /**
     * Store VIN decode data
     */
    public function store_vin_decode($vin, $decoded_data, $api_source = 'nhtsa') {
        global $wpdb;

        // Prepare data for storage
        $data = array(
            'vin' => sanitize_text_field($vin),
            'make' => isset($decoded_data['make']) ? sanitize_text_field($decoded_data['make']) : '',
            'model' => isset($decoded_data['model']) ? sanitize_text_field($decoded_data['model']) : '',
            'year' => isset($decoded_data['year']) ? sanitize_text_field($decoded_data['year']) : '',
            'trim' => isset($decoded_data['trim']) ? sanitize_text_field($decoded_data['trim']) : '',
            'body_class' => isset($decoded_data['bodyClass']) ? sanitize_text_field($decoded_data['bodyClass']) : '',
            'vehicle_type' => isset($decoded_data['vehicleType']) ? sanitize_text_field($decoded_data['vehicleType']) : '',
            'engine_cylinders' => isset($decoded_data['cylinders']) ? sanitize_text_field($decoded_data['cylinders']) : '',
            'engine_displacement' => isset($decoded_data['displacement']) ? sanitize_text_field($decoded_data['displacement']) : '',
            'engine_hp' => isset($decoded_data['horsepower']) ? sanitize_text_field($decoded_data['horsepower']) : '',
            'fuel_type' => isset($decoded_data['fuelType']) ? sanitize_text_field($decoded_data['fuelType']) : '',
            'transmission' => isset($decoded_data['transmission']) ? sanitize_text_field($decoded_data['transmission']) : '',
            'drive_type' => isset($decoded_data['driveType']) ? sanitize_text_field($decoded_data['driveType']) : '',
            'manufacturer' => isset($decoded_data['manufacturer']) ? sanitize_text_field($decoded_data['manufacturer']) : '',
            'plant_city' => isset($decoded_data['plantCity']) ? sanitize_text_field($decoded_data['plantCity']) : '',
            'plant_state' => isset($decoded_data['plantState']) ? sanitize_text_field($decoded_data['plantState']) : '',
            'plant_country' => isset($decoded_data['plantCountry']) ? sanitize_text_field($decoded_data['plantCountry']) : '',
            'gvwr' => isset($decoded_data['gvwrFrom']) ? sanitize_text_field($decoded_data['gvwrFrom']) : '',
            'curb_weight' => isset($decoded_data['curbWeight']) ? sanitize_text_field($decoded_data['curbWeight']) : '',
            'airbags' => isset($decoded_data['airbags']) ? sanitize_text_field($decoded_data['airbags']) : '',
            'abs' => isset($decoded_data['abs']) ? sanitize_text_field($decoded_data['abs']) : '',
            'raw_data' => wp_json_encode($decoded_data),
            'api_source' => sanitize_text_field($api_source),
        );

        $format = array(
            '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s',
            '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s'
        );

        // Insert or update
        $result = $wpdb->replace($this->vin_decodes_table, $data, $format);

        if ($result === false) {
            return new WP_Error('db_error', __('Failed to store VIN decode data', 'vin-decoder'));
        }

        return $wpdb->insert_id;
    }

    /**
     * Store form submission data
     */
    public function store_submission($vin_id, $form_data = array(), $form_id = '') {
        global $wpdb;

        $data = array(
            'vin_id' => intval($vin_id),
            'form_id' => sanitize_text_field($form_id),
            'submission_data' => wp_json_encode($form_data),
            'user_ip' => $this->get_user_ip(),
            'user_agent' => isset($_SERVER['HTTP_USER_AGENT']) ? sanitize_text_field($_SERVER['HTTP_USER_AGENT']) : '',
            'status' => 'completed',
        );

        $format = array('%d', '%s', '%s', '%s', '%s', '%s');

        $result = $wpdb->insert($this->vin_submissions_table, $data, $format);

        if ($result === false) {
            return new WP_Error('db_error', __('Failed to store submission data', 'vin-decoder'));
        }

        return $wpdb->insert_id;
    }

    /**
     * Get VIN decode by VIN
     */
    public function get_vin_decode($vin) {
        global $wpdb;

        $sql = $wpdb->prepare(
            "SELECT * FROM {$this->vin_decodes_table} WHERE vin = %s",
            sanitize_text_field($vin)
        );

        return $wpdb->get_row($sql);
    }

    /**
     * Get VIN decodes with pagination and filtering
     */
    public function get_vin_decodes($args = array()) {
        global $wpdb;

        $defaults = array(
            'page' => 1,
            'per_page' => 20,
            'orderby' => 'decoded_at',
            'order' => 'DESC',
            'search' => '',
            'make' => '',
            'model' => '',
            'year' => '',
        );

        $args = wp_parse_args($args, $defaults);

        $offset = ($args['page'] - 1) * $args['per_page'];

        $where = array();
        $where_values = array();

        if (!empty($args['search'])) {
            $where[] = "(vin LIKE %s OR make LIKE %s OR model LIKE %s)";
            $search_term = '%' . $wpdb->esc_like($args['search']) . '%';
            $where_values = array_merge($where_values, array($search_term, $search_term, $search_term));
        }

        if (!empty($args['make'])) {
            $where[] = "make = %s";
            $where_values[] = $args['make'];
        }

        if (!empty($args['model'])) {
            $where[] = "model = %s";
            $where_values[] = $args['model'];
        }

        if (!empty($args['year'])) {
            $where[] = "year = %s";
            $where_values[] = $args['year'];
        }

        $where_clause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';

        $sql = $wpdb->prepare(
            "SELECT SQL_CALC_FOUND_ROWS * FROM {$this->vin_decodes_table}
            {$where_clause}
            ORDER BY {$args['orderby']} {$args['order']}
            LIMIT %d OFFSET %d",
            array_merge($where_values, array($args['per_page'], $offset))
        );

        $results = $wpdb->get_results($sql);
        $total = $wpdb->get_var("SELECT FOUND_ROWS()");

        return array(
            'results' => $results,
            'total' => intval($total),
            'pages' => ceil($total / $args['per_page']),
            'current_page' => $args['page'],
        );
    }

    /**
     * Get submission statistics
     */
    public function get_stats() {
        global $wpdb;

        $stats = array();

        // Total VIN decodes
        $stats['total_decodes'] = $wpdb->get_var("SELECT COUNT(*) FROM {$this->vin_decodes_table}");

        // Total submissions
        $stats['total_submissions'] = $wpdb->get_var("SELECT COUNT(*) FROM {$this->vin_submissions_table}");

        // Today's decodes
        $today = date('Y-m-d');
        $stats['today_decodes'] = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$this->vin_decodes_table} WHERE DATE(decoded_at) = %s",
            $today
        ));

        // Today's submissions
        $stats['today_submissions'] = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$this->vin_submissions_table} WHERE DATE(submitted_at) = %s",
            $today
        ));

        // Popular makes
        $stats['popular_makes'] = $wpdb->get_results(
            "SELECT make, COUNT(*) as count FROM {$this->vin_decodes_table}
            WHERE make != ''
            GROUP BY make
            ORDER BY count DESC
            LIMIT 10"
        );

        return $stats;
    }

    /**
     * Delete VIN decode data
     */
    public function delete_vin_decode($vin_id) {
        global $wpdb;
        return $wpdb->delete($this->vin_decodes_table, array('id' => intval($vin_id)), array('%d'));
    }

    /**
     * Get distinct values for filters
     */
    public function get_filter_values($column) {
        global $wpdb;

        $allowed_columns = array('make', 'model', 'year', 'vehicle_type', 'fuel_type');

        if (!in_array($column, $allowed_columns)) {
            return array();
        }

        return $wpdb->get_col(
            "SELECT DISTINCT {$column} FROM {$this->vin_decodes_table}
            WHERE {$column} != ''
            ORDER BY {$column} ASC"
        );
    }

    /**
     * Get user IP address
     */
    private function get_user_ip() {
        if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
            $ip = $_SERVER['HTTP_CLIENT_IP'];
        } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
        } else {
            $ip = $_SERVER['REMOTE_ADDR'];
        }

        // Clean up multiple IPs (take first one)
        $ip = explode(',', $ip)[0];

        return sanitize_text_field($ip);
    }

    /**
     * Clean up old data (optional maintenance function)
     */
    public function cleanup_old_data($days = 90) {
        global $wpdb;

        $date = date('Y-m-d H:i:s', strtotime("-{$days} days"));

        // Delete old submissions (keep decodes for reference)
        $wpdb->query($wpdb->prepare(
            "DELETE FROM {$this->vin_submissions_table} WHERE submitted_at < %s",
            $date
        ));
    }
}
