<?php
/**
 * Main SCF ShortCodes class
 */

if (!defined('ABSPATH')) {
    exit;
}

class SCF_ShortCodes {
    
    private static $instance = null;
    private $field_handler;
    private $flexible_handler;
    private $cache_manager;
    private $error_handler;
    
    public static function get_instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        $this->init_handlers();
        $this->register_shortcodes();
        $this->setup_hooks();
    }
    
    /**
     * Initialize handlers
     */
    private function init_handlers() {
        $this->error_handler = new SCF_Error_Handler();
        $this->cache_manager = new SCF_Cache_Manager();
        $this->field_handler = new SCF_Field_Handler($this->cache_manager, $this->error_handler);
        $this->flexible_handler = new SCF_Flexible_Handler($this->cache_manager, $this->error_handler);
    }
    
    /**
     * Register all shortcodes
     */
    private function register_shortcodes() {
        $shortcodes = [
            'scf_field' => [$this->field_handler, 'handle_field_shortcode'],
            'scf_flexible' => [$this->flexible_handler, 'handle_flexible_shortcode'],
            'scf_flexible_field' => [$this->flexible_handler, 'handle_flexible_field_shortcode'],
            'scf_group' => [$this->field_handler, 'handle_group_shortcode'],
            'scf_repeater' => [$this->field_handler, 'handle_repeater_shortcode'],
            'scf_suffix' => [$this->field_handler, 'handle_suffix_shortcode']
        ];
        
        foreach ($shortcodes as $tag => $callback) {
            add_shortcode($tag, $callback);
        }
    }
    
    /**
     * Setup WordPress hooks
     */
    private function setup_hooks() {
        add_action('wp_enqueue_scripts', [$this, 'enqueue_frontend_assets']);
        add_action('save_post', [$this, 'clear_post_cache']);
        add_action('wp_footer', [$this, 'add_inline_styles']);
        
        // AJAX hooks for admin
        add_action('wp_ajax_scf_clear_cache', [$this, 'ajax_clear_cache']);
        add_action('wp_ajax_scf_get_field_info', [$this, 'ajax_get_field_info']);
    }
    
    /**
     * Enqueue frontend assets
     */
    public function enqueue_frontend_assets() {
        wp_enqueue_style(
            'scf-shortcodes-frontend',
            SCF_SHORTCODES_PLUGIN_URL . 'assets/css/frontend-style.css',
            [],
            SCF_SHORTCODES_VERSION
        );
    }
    
    /**
     * Add inline styles
     */
    public function add_inline_styles() {
        $custom_css = $this->get_inline_css();
        if (!empty($custom_css)) {
            echo '<style id="scf-shortcodes-inline-css">' . $custom_css . '</style>';
        }
    }
    
    /**
     * Get inline CSS
     */
    private function get_inline_css() {
        return '
        .scf-flexible-list { list-style: none; padding: 0; margin: 0; }
        .scf-flexible-list li { padding: 8px 0; border-bottom: 1px solid #eee; }
        .scf-flexible-table { width: 100%; border-collapse: collapse; margin: 20px 0; }
        .scf-flexible-table td, .scf-flexible-table th { padding: 10px; border: 1px solid #ddd; }
        .scf-flexible-table td:first-child, .scf-flexible-table th { background-color: #f9f9f9; font-weight: bold; }
        .scf-group-card { background: #f9f9f9; padding: 20px; border-radius: 5px; margin: 10px 0; }
        .scf-repeater-cards { display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px; margin: 20px 0; }
        .scf-card { background: #fff; padding: 15px; border: 1px solid #ddd; border-radius: 5px; }
        .scf-repeater-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; }
        .scf-gallery { display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 10px; }
        .scf-gallery img { width: 100%; height: auto; border-radius: 3px; }
        ';
    }
    
    /**
     * Clear post cache
     */
    public function clear_post_cache($post_id) {
        $this->cache_manager->clear_post_cache($post_id);
    }
    
    /**
     * AJAX: Clear cache
     */
    public function ajax_clear_cache() {
        check_ajax_referer('scf_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die(__('Insufficient permissions', 'scf-shortcodes'));
        }
        
        $this->cache_manager->clear_all_cache();
        
        wp_send_json_success([
            'message' => __('Cache cleared successfully', 'scf-shortcodes')
        ]);
    }
    
    /**
     * AJAX: Get field info
     */
    public function ajax_get_field_info() {
        check_ajax_referer('scf_admin_nonce', 'nonce');
        
        $field_name = sanitize_text_field($_POST['field_name'] ?? '');
        
        if (empty($field_name)) {
            wp_send_json_error([
                'message' => __('Field name is required', 'scf-shortcodes')
            ]);
        }
        
        $field_info = $this->field_handler->get_field_info($field_name);
        
        wp_send_json_success($field_info);
    }
}
