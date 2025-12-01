<?php
/**
 * Admin functionality for VIN Decoder plugin
 */

if (!defined('ABSPATH')) {
    exit;
}

class VIN_Decoder_Admin {

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
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
        add_action('wp_ajax_vin_decoder_get_decodes', array($this, 'ajax_get_decodes'));
        add_action('wp_ajax_vin_decoder_delete_decode', array($this, 'ajax_delete_decode'));
        add_filter('set_screen_option', array($this, 'set_screen_option'), 10, 3);
    }

    /**
     * Add admin menu
     */
    public function add_admin_menu() {
        $hook = add_menu_page(
            __('VIN Decoder', 'vin-decoder'),
            __('VIN Decoder', 'vin-decoder'),
            'manage_options',
            'vin-decoder',
            array($this, 'admin_page'),
            'dashicons-car',
            30
        );

        // Add submenu pages
        add_submenu_page(
            'vin-decoder',
            __('Dashboard', 'vin-decoder'),
            __('Dashboard', 'vin-decoder'),
            'manage_options',
            'vin-decoder',
            array($this, 'admin_page')
        );

        add_submenu_page(
            'vin-decoder',
            __('VIN Database', 'vin-decoder'),
            __('VIN Database', 'vin-decoder'),
            'manage_options',
            'vin-decoder-database',
            array($this, 'database_page')
        );

        add_submenu_page(
            'vin-decoder',
            __('Settings', 'vin-decoder'),
            __('Settings', 'vin-decoder'),
            'manage_options',
            'vin-decoder-settings',
            array($this, 'settings_page')
        );

        // Add screen options
        add_action("load-{$hook}", array($this, 'add_screen_options'));
    }

    /**
     * Add screen options
     */
    public function add_screen_options() {
        $option = 'per_page';
        $args = array(
            'label' => __('VINs per page', 'vin-decoder'),
            'default' => 20,
            'option' => 'vin_decoder_per_page'
        );
        add_screen_option($option, $args);
    }

    /**
     * Set screen option
     */
    public function set_screen_option($status, $option, $value) {
        if ('vin_decoder_per_page' === $option) {
            return $value;
        }
        return $status;
    }

    /**
     * Enqueue admin scripts and styles
     */
    public function enqueue_admin_scripts($hook) {
        if (strpos($hook, 'vin-decoder') === false) {
            return;
        }

        wp_enqueue_style(
            'vin-decoder-admin',
            VIN_DECODER_PLUGIN_URL . 'assets/css/admin.css',
            array(),
            VIN_DECODER_VERSION
        );

        wp_enqueue_script(
            'vin-decoder-admin',
            VIN_DECODER_PLUGIN_URL . 'assets/js/admin.js',
            array('jquery'),
            VIN_DECODER_VERSION,
            true
        );

        wp_localize_script('vin-decoder-admin', 'vinDecoderAjax', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('vin_decoder_admin_nonce'),
            'strings' => array(
                'confirm_delete' => __('Are you sure you want to delete this VIN decode?', 'vin-decoder'),
                'deleting' => __('Deleting...', 'vin-decoder'),
                'error' => __('Error occurred', 'vin-decoder'),
                'no_results' => __('No VINs found', 'vin-decoder'),
            )
        ));
    }

    /**
     * Main admin page (dashboard)
     */
    public function admin_page() {
        $stats = $this->db->get_stats();

        ?>
        <div class="wrap">
            <h1><?php _e('VIN Decoder Dashboard', 'vin-decoder'); ?></h1>

            <div class="vin-decoder-stats-grid">
                <div class="vin-decoder-stat-card">
                    <h3><?php _e('Total VIN Decodes', 'vin-decoder'); ?></h3>
                    <div class="stat-number"><?php echo number_format($stats['total_decodes']); ?></div>
                </div>

                <div class="vin-decoder-stat-card">
                    <h3><?php _e('Total Submissions', 'vin-decoder'); ?></h3>
                    <div class="stat-number"><?php echo number_format($stats['total_submissions']); ?></div>
                </div>

                <div class="vin-decoder-stat-card">
                    <h3><?php _e('Today\'s Decodes', 'vin-decoder'); ?></h3>
                    <div class="stat-number"><?php echo number_format($stats['today_decodes']); ?></div>
                </div>

                <div class="vin-decoder-stat-card">
                    <h3><?php _e('Today\'s Submissions', 'vin-decoder'); ?></h3>
                    <div class="stat-number"><?php echo number_format($stats['today_submissions']); ?></div>
                </div>
            </div>

            <div class="vin-decoder-popular-makes">
                <h3><?php _e('Popular Vehicle Makes', 'vin-decoder'); ?></h3>
                <?php if (!empty($stats['popular_makes'])): ?>
                    <div class="makes-chart">
                        <?php foreach ($stats['popular_makes'] as $make): ?>
                            <div class="make-item">
                                <span class="make-name"><?php echo esc_html($make->make); ?></span>
                                <span class="make-count"><?php echo number_format($make->count); ?></span>
                                <div class="make-bar" style="width: <?php echo min(100, ($make->count / $stats['popular_makes'][0]->count) * 100); ?>%"></div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <p><?php _e('No data available yet.', 'vin-decoder'); ?></p>
                <?php endif; ?>
            </div>

            <div class="vin-decoder-quick-actions">
                <h3><?php _e('Quick Actions', 'vin-decoder'); ?></h3>
                <div class="action-buttons">
                    <a href="<?php echo admin_url('admin.php?page=vin-decoder-database'); ?>" class="button button-primary">
                        <?php _e('View VIN Database', 'vin-decoder'); ?>
                    </a>
                    <a href="<?php echo admin_url('admin.php?page=vin-decoder-settings'); ?>" class="button">
                        <?php _e('Plugin Settings', 'vin-decoder'); ?>
                    </a>
                </div>
            </div>
        </div>
        <?php
    }

    /**
     * Database page
     */
    public function database_page() {
        $per_page = $this->get_items_per_page('vin_decoder_per_page', 20);
        $current_page = isset($_GET['paged']) ? absint($_GET['paged']) : 1;

        // Build query args
        $args = array(
            'page' => $current_page,
            'per_page' => $per_page,
        );

        // Add filters
        if (!empty($_GET['search'])) {
            $args['search'] = sanitize_text_field($_GET['search']);
        }
        if (!empty($_GET['make'])) {
            $args['make'] = sanitize_text_field($_GET['make']);
        }
        if (!empty($_GET['model'])) {
            $args['model'] = sanitize_text_field($_GET['model']);
        }
        if (!empty($_GET['year'])) {
            $args['year'] = sanitize_text_field($_GET['year']);
        }

        $data = $this->db->get_vin_decodes($args);

        // Get filter values
        $makes = $this->db->get_filter_values('make');
        $models = $this->db->get_filter_values('model');
        $years = $this->db->get_filter_values('year');

        ?>
        <div class="wrap">
            <h1><?php _e('VIN Database', 'vin-decoder'); ?></h1>

            <form method="get" class="vin-decoder-filters">
                <input type="hidden" name="page" value="vin-decoder-database">

                <div class="filter-row">
                    <input type="text" name="search" placeholder="<?php _e('Search VIN, make, or model...', 'vin-decoder'); ?>"
                           value="<?php echo isset($_GET['search']) ? esc_attr($_GET['search']) : ''; ?>">

                    <select name="make">
                        <option value=""><?php _e('All Makes', 'vin-decoder'); ?></option>
                        <?php foreach ($makes as $make): ?>
                            <option value="<?php echo esc_attr($make); ?>" <?php selected(isset($_GET['make']) ? $_GET['make'] : '', $make); ?>>
                                <?php echo esc_html($make); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>

                    <select name="model">
                        <option value=""><?php _e('All Models', 'vin-decoder'); ?></option>
                        <?php foreach ($models as $model): ?>
                            <option value="<?php echo esc_attr($model); ?>" <?php selected(isset($_GET['model']) ? $_GET['model'] : '', $model); ?>>
                                <?php echo esc_html($model); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>

                    <select name="year">
                        <option value=""><?php _e('All Years', 'vin-decoder'); ?></option>
                        <?php foreach ($years as $year): ?>
                            <option value="<?php echo esc_attr($year); ?>" <?php selected(isset($_GET['year']) ? $_GET['year'] : '', $year); ?>>
                                <?php echo esc_html($year); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>

                    <button type="submit" class="button"><?php _e('Filter', 'vin-decoder'); ?></button>
                    <a href="<?php echo admin_url('admin.php?page=vin-decoder-database'); ?>" class="button"><?php _e('Clear', 'vin-decoder'); ?></a>
                </div>
            </form>

            <div class="vin-decoder-results-info">
                <?php printf(
                    __('Showing %d-%d of %d VINs', 'vin-decoder'),
                    (($current_page - 1) * $per_page) + 1,
                    min($current_page * $per_page, $data['total']),
                    $data['total']
                ); ?>
            </div>

            <table class="wp-list-table widefat fixed striped vin-decoder-table">
                <thead>
                    <tr>
                        <th><?php _e('VIN', 'vin-decoder'); ?></th>
                        <th><?php _e('Vehicle', 'vin-decoder'); ?></th>
                        <th><?php _e('Engine', 'vin-decoder'); ?></th>
                        <th><?php _e('Decoded', 'vin-decoder'); ?></th>
                        <th><?php _e('Actions', 'vin-decoder'); ?></th>
                    </tr>
                </thead>
                <tbody id="vin-decoder-results">
                    <?php $this->render_vin_rows($data['results']); ?>
                </tbody>
            </table>

            <?php $this->render_pagination($data, $args); ?>
        </div>
        <?php
    }

    /**
     * Settings page
     */
    public function settings_page() {
        if (isset($_POST['submit'])) {
            $this->save_settings();
        }

        $settings = $this->plugin->get_settings();

        ?>
        <div class="wrap">
            <h1><?php _e('VIN Decoder Settings', 'vin-decoder'); ?></h1>

            <form method="post" action="">
                <?php wp_nonce_field('vin_decoder_settings'); ?>

                <table class="form-table">
                    <tr>
                        <th scope="row"><?php _e('API Timeout', 'vin-decoder'); ?></th>
                        <td>
                            <input type="number" name="api_timeout" value="<?php echo esc_attr($settings['api_timeout']); ?>" min="5" max="30">
                            <p class="description"><?php _e('Timeout in seconds for API requests (5-30)', 'vin-decoder'); ?></p>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row"><?php _e('Enable Secondary API', 'vin-decoder'); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" name="enable_secondary_api" value="yes" <?php checked($settings['enable_secondary_api'], 'yes'); ?>>
                                <?php _e('Use VinDecoder.eu API for additional vehicle data', 'vin-decoder'); ?>
                            </label>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row"><?php _e('Secondary API Key', 'vin-decoder'); ?></th>
                        <td>
                            <input type="password" name="secondary_api_key" value="<?php echo esc_attr($settings['secondary_api_key']); ?>" class="regular-text">
                            <p class="description"><?php _e('API key for VinDecoder.eu (optional)', 'vin-decoder'); ?></p>
                        </td>
                    </tr>
                </table>

                <?php submit_button(); ?>
            </form>
        </div>
        <?php
    }

    /**
     * Save settings
     */
    private function save_settings() {
        if (!wp_verify_nonce($_POST['_wpnonce'], 'vin_decoder_settings')) {
            wp_die(__('Security check failed', 'vin-decoder'));
        }

        update_option('vin_decoder_api_timeout', intval($_POST['api_timeout']));
        update_option('vin_decoder_enable_secondary_api', isset($_POST['enable_secondary_api']) ? 'yes' : 'no');
        update_option('vin_decoder_secondary_api_key', sanitize_text_field($_POST['secondary_api_key']));

        echo '<div class="notice notice-success"><p>' . __('Settings saved successfully!', 'vin-decoder') . '</p></div>';
    }

    /**
     * Render VIN table rows
     */
    private function render_vin_rows($vins) {
        if (empty($vins)) {
            echo '<tr><td colspan="5">' . __('No VINs found.', 'vin-decoder') . '</td></tr>';
            return;
        }

        foreach ($vins as $vin) {
            ?>
            <tr data-vin-id="<?php echo esc_attr($vin->id); ?>">
                <td>
                    <strong><?php echo esc_html($vin->vin); ?></strong>
                </td>
                <td>
                    <?php if ($vin->year || $vin->make || $vin->model): ?>
                        <?php echo esc_html($vin->year); ?> <?php echo esc_html($vin->make); ?> <?php echo esc_html($vin->model); ?>
                        <?php if ($vin->trim): ?><br><small><?php echo esc_html($vin->trim); ?></small><?php endif; ?>
                    <?php else: ?>
                        <em><?php _e('No data available', 'vin-decoder'); ?></em>
                    <?php endif; ?>
                </td>
                <td>
                    <?php if ($vin->engine_displacement || $vin->engine_hp): ?>
                        <?php echo esc_html($vin->engine_displacement); ?>L, <?php echo esc_html($vin->engine_hp); ?> HP
                    <?php else: ?>
                        <em><?php _e('No data', 'vin-decoder'); ?></em>
                    <?php endif; ?>
                </td>
                <td>
                    <?php echo esc_html(date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($vin->decoded_at))); ?>
                </td>
                <td>
                    <button class="button button-small vin-view-details" data-vin-id="<?php echo esc_attr($vin->id); ?>">
                        <?php _e('View Details', 'vin-decoder'); ?>
                    </button>
                    <button class="button button-small button-link-delete vin-delete" data-vin-id="<?php echo esc_attr($vin->id); ?>">
                        <?php _e('Delete', 'vin-decoder'); ?>
                    </button>
                </td>
            </tr>
            <?php
        }
    }

    /**
     * Render pagination
     */
    private function render_pagination($data, $args) {
        if ($data['pages'] <= 1) {
            return;
        }

        $base_url = add_query_arg(array(
            'page' => 'vin-decoder-database',
            'search' => isset($args['search']) ? $args['search'] : '',
            'make' => isset($args['make']) ? $args['make'] : '',
            'model' => isset($args['model']) ? $args['model'] : '',
            'year' => isset($args['year']) ? $args['year'] : '',
        ), admin_url('admin.php'));

        ?>
        <div class="tablenav bottom">
            <div class="tablenav-pages">
                <?php
                echo paginate_links(array(
                    'base' => $base_url . '%_%',
                    'format' => '&paged=%#%',
                    'current' => $data['current_page'],
                    'total' => $data['pages'],
                    'prev_text' => __('&laquo; Previous'),
                    'next_text' => __('Next &raquo;'),
                ));
                ?>
            </div>
        </div>
        <?php
    }

    /**
     * AJAX handler for getting VIN decodes
     */
    public function ajax_get_decodes() {
        check_ajax_referer('vin_decoder_admin_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_die(__('Insufficient permissions', 'vin-decoder'));
        }

        $args = array(
            'page' => isset($_POST['page']) ? intval($_POST['page']) : 1,
            'per_page' => isset($_POST['per_page']) ? intval($_POST['per_page']) : 20,
        );

        if (!empty($_POST['search'])) {
            $args['search'] = sanitize_text_field($_POST['search']);
        }

        $data = $this->db->get_vin_decodes($args);

        ob_start();
        $this->render_vin_rows($data['results']);
        $rows_html = ob_get_clean();

        wp_send_json_success(array(
            'rows' => $rows_html,
            'pagination' => $this->get_pagination_html($data, $args),
            'total' => $data['total'],
        ));
    }

    /**
     * AJAX handler for deleting VIN decode
     */
    public function ajax_delete_decode() {
        check_ajax_referer('vin_decoder_admin_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_die(__('Insufficient permissions', 'vin-decoder'));
        }

        $vin_id = intval($_POST['vin_id']);

        if ($this->db->delete_vin_decode($vin_id)) {
            wp_send_json_success();
        } else {
            wp_send_json_error(__('Failed to delete VIN decode', 'vin-decoder'));
        }
    }

    /**
     * Get pagination HTML for AJAX
     */
    private function get_pagination_html($data, $args) {
        if ($data['pages'] <= 1) {
            return '';
        }

        ob_start();
        $this->render_pagination($data, $args);
        return ob_get_clean();
    }
}
