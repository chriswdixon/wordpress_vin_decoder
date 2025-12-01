<?php
/**
 * Plugin Name: VIN Decoder for Contact Form 7
 * Plugin URI: https://github.com/chriswdixon/wordpress_vin_decoder
 * Description: Automatically decode Vehicle Identification Numbers (VINs) in Contact Form 7 submissions. Features include comprehensive vehicle data extraction, admin dashboard for viewing decoded VINs, and seamless form integration.
 * Version: 1.0.0
 * Author: Chris Dixon
 * Author URI: https://github.com/chriswdixon
 * License: MIT
 * License URI: https://opensource.org/licenses/MIT
 * Text Domain: vin-decoder
 * Domain Path: /languages
 * Requires at least: 5.0
 * Tested up to: 6.8.3
 * Requires PHP: 7.4
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('VIN_DECODER_VERSION', '1.0.0');
define('VIN_DECODER_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('VIN_DECODER_PLUGIN_URL', plugin_dir_url(__FILE__));
define('VIN_DECODER_PLUGIN_BASENAME', plugin_basename(__FILE__));
define('VIN_DECODER_DB_VERSION', '1.0');

/**
 * Main Plugin Class
 */
class VIN_Decoder_Plugin {

    /**
     * Single instance of the plugin
     */
    private static $instance = null;

    /**
     * Database handler
     */
    public $db;

    /**
     * Admin handler
     */
    public $admin;

    /**
     * Frontend handler
     */
    public $frontend;

    /**
     * Get single instance of the plugin
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor
     */
    private function __construct() {
        $this->init();
        $this->load_dependencies();
        $this->init_hooks();
    }

    /**
     * Initialize the plugin
     */
    private function init() {
        // Load textdomain for internationalization
        load_plugin_textdomain('vin-decoder', false, dirname(VIN_DECODER_PLUGIN_BASENAME) . '/languages');
    }

    /**
     * Load plugin dependencies
     */
    private function load_dependencies() {
        // Load database class
        require_once VIN_DECODER_PLUGIN_DIR . 'includes/class-vin-decoder-db.php';

        // Load admin class
        require_once VIN_DECODER_PLUGIN_DIR . 'includes/class-vin-decoder-admin.php';

        // Load frontend class
        require_once VIN_DECODER_PLUGIN_DIR . 'includes/class-vin-decoder-frontend.php';

        // Load API class
        require_once VIN_DECODER_PLUGIN_DIR . 'includes/class-vin-decoder-api.php';

        // Initialize components
        $this->db = new VIN_Decoder_DB();
        $this->admin = new VIN_Decoder_Admin();
        $this->frontend = new VIN_Decoder_Frontend();
    }

    /**
     * Initialize hooks
     */
    private function init_hooks() {
        register_activation_hook(__FILE__, array($this, 'activate'));
        register_deactivation_hook(__FILE__, array($this, 'deactivate'));

        add_action('init', array($this, 'init_plugin'));
        add_action('plugins_loaded', array($this, 'check_dependencies'));
    }

    /**
     * Plugin activation
     */
    public function activate() {
        // Create database tables
        $this->db->create_tables();

        // Set default options
        add_option('vin_decoder_db_version', VIN_DECODER_DB_VERSION);
        add_option('vin_decoder_api_timeout', 10);
        add_option('vin_decoder_enable_secondary_api', 'no');

        // Flush rewrite rules
        flush_rewrite_rules();
    }

    /**
     * Plugin deactivation
     */
    public function deactivate() {
        // Flush rewrite rules
        flush_rewrite_rules();
    }

    /**
     * Initialize plugin after WordPress is loaded
     */
    public function init_plugin() {
        // Check for database updates
        $this->check_db_updates();
    }

    /**
     * Check for required dependencies
     */
    public function check_dependencies() {
        if (!is_plugin_active('contact-form-7/wp-contact-form-7.php')) {
            add_action('admin_notices', array($this, 'contact_form_7_missing_notice'));
        }
    }

    /**
     * Check database version and update if needed
     */
    private function check_db_updates() {
        $current_version = get_option('vin_decoder_db_version', '0');

        if (version_compare($current_version, VIN_DECODER_DB_VERSION, '<')) {
            $this->db->create_tables();
            update_option('vin_decoder_db_version', VIN_DECODER_DB_VERSION);
        }
    }

    /**
     * Display notice if Contact Form 7 is not active
     */
    public function contact_form_7_missing_notice() {
        ?>
        <div class="notice notice-error">
            <p><?php _e('VIN Decoder for Contact Form 7 requires Contact Form 7 to be installed and active.', 'vin-decoder'); ?></p>
        </div>
        <?php
    }

    /**
     * Get plugin settings
     */
    public function get_settings() {
        return array(
            'api_timeout' => get_option('vin_decoder_api_timeout', 10),
            'enable_secondary_api' => get_option('vin_decoder_enable_secondary_api', 'no'),
            'secondary_api_key' => get_option('vin_decoder_secondary_api_key', ''),
        );
    }
}

/**
 * Initialize the plugin
 */
function vin_decoder_init() {
    return VIN_Decoder_Plugin::get_instance();
}

// Start the plugin
vin_decoder_init();
