<?php
/**
 * Plugin Name: SCF-ShortCodes
 * Plugin URI: https://pixelmobs.com/project/scf-shortcodes
 * Description: Universal shortcode system for Secure Custom Fields with advanced features and caching.
 * Version: 1.0.0
 * Author: PixelMobs
 * Author URI: https://pixelmobs.com/mob/piotr
 * Text Domain: scf-shortcodes
 * Domain Path: /languages
 * Requires at least: 5.0
 * Tested up to: 6.4
 * Requires PHP: 7.4
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Network: false
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('SCF_SHORTCODES_VERSION', '1.0.0');
define('SCF_SHORTCODES_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('SCF_SHORTCODES_PLUGIN_URL', plugin_dir_url(__FILE__));
define('SCF_SHORTCODES_PLUGIN_FILE', __FILE__);
define('SCF_SHORTCODES_PLUGIN_BASENAME', plugin_basename(__FILE__));

/**
 * Main plugin class
 */
final class SCF_ShortCodes_Plugin {
    
    private static $instance = null;
    
    /**
     * Get singleton instance
     */
    public static function get_instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Constructor - POPRAWIONY
     */
    private function __construct() {
        $this->init_hooks();
        $this->load_dependencies();
        // Usunięto wywołanie nieistniejącej metody init_plugin()
    }
    
    /**
     * Initialize WordPress hooks
     */
    private function init_hooks() {
        register_activation_hook(__FILE__, [$this, 'activate']);
        register_deactivation_hook(__FILE__, [$this, 'deactivate']);
        add_action('plugins_loaded', [$this, 'load_textdomain']);
        add_action('init', [$this, 'init'], 0);
    }
    
    /**
     * Load plugin dependencies
     */
    private function load_dependencies() {
        require_once SCF_SHORTCODES_PLUGIN_DIR . 'includes/class-scf-error-handler.php';
        require_once SCF_SHORTCODES_PLUGIN_DIR . 'includes/class-scf-cache-manager.php';
        require_once SCF_SHORTCODES_PLUGIN_DIR . 'includes/class-scf-field-handler.php';
        require_once SCF_SHORTCODES_PLUGIN_DIR . 'includes/class-scf-flexible-handler.php';
        require_once SCF_SHORTCODES_PLUGIN_DIR . 'includes/class-scf-shortcodes.php';
        
        if (is_admin()) {
            require_once SCF_SHORTCODES_PLUGIN_DIR . 'includes/class-scf-admin.php';
        }
    }
    
    /**
     * Initialize plugin - DODANA METODA
     */
    public function init() {
        // Check if SCF is active
        if (!$this->is_scf_active()) {
            add_action('admin_notices', [$this, 'scf_missing_notice']);
            return;
        }
        
        // Initialize main components
        SCF_ShortCodes::get_instance();
        
        if (is_admin()) {
            SCF_ShortCodes_Admin::get_instance();
        }
    }
    
    /**
     * Check if Secure Custom Fields is active
     */
    private function is_scf_active() {
        return function_exists('get_field') || class_exists('ACF');
    }
    
    /**
     * Show notice when SCF is missing
     */
    public function scf_missing_notice() {
        $class = 'notice notice-error';
        $message = __('SCF-ShortCodes requires Secure Custom Fields plugin to be installed and activated.', 'scf-shortcodes');
        
        printf('<div class="%1$s"><p>%2$s</p></div>', esc_attr($class), esc_html($message));
    }
    
    /**
     * Load plugin textdomain
     */
    public function load_textdomain() {
        load_plugin_textdomain(
            'scf-shortcodes',
            false,
            dirname(SCF_SHORTCODES_PLUGIN_BASENAME) . '/languages/'
        );
    }
    
    /**
     * Plugin activation
     */
    public function activate() {
        // Create database tables if needed
        $this->create_tables();
        
        // Set default options
        $this->set_default_options();
        
        // Flush rewrite rules
        flush_rewrite_rules();
    }
    
    /**
     * Plugin deactivation
     */
    public function deactivate() {
        // Clear cache
        if (class_exists('SCF_Cache_Manager')) {
            $cache_manager = new SCF_Cache_Manager();
            $cache_manager->clear_all_cache();
        }
        
        // Flush rewrite rules
        flush_rewrite_rules();
    }
    
    /**
     * Create database tables
     */
    private function create_tables() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'scf_shortcodes_cache';
        
        $charset_collate = $wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE $table_name (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            cache_key varchar(255) NOT NULL,
            cache_value longtext NOT NULL,
            expiration datetime NOT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY cache_key (cache_key),
            KEY expiration (expiration)
        ) $charset_collate;";
        
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta($sql);
    }
    
    /**
     * Set default plugin options
     */
    private function set_default_options() {
        $default_options = [
            'cache_enabled' => true,
            'cache_duration' => 3600,
            'debug_mode' => false,
            'auto_clear_cache' => true,
            'log_errors' => true,
            'use_database_cache' => false
        ];
        
        add_option('scf_shortcodes_options', $default_options);
    }
}

// Initialize plugin
SCF_ShortCodes_Plugin::get_instance();
