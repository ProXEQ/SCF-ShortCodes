<?php
/**
 * SCF ShortCodes Admin
 */

if (!defined('ABSPATH')) {
    exit;
}

class SCF_ShortCodes_Admin {
    
    private static $instance = null;
    
    public static function get_instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        add_action('admin_menu', [$this, 'add_admin_menu']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_assets']);
        add_action('admin_init', [$this, 'register_settings']);
        add_filter('plugin_action_links_' . SCF_SHORTCODES_PLUGIN_BASENAME, [$this, 'add_plugin_links']);
    }
    
    /**
     * Add admin menu
     */
    public function add_admin_menu() {
        add_options_page(
            __('SCF-ShortCodes Settings', 'scf-shortcodes'),
            __('SCF-ShortCodes', 'scf-shortcodes'),
            'manage_options',
            'scf-shortcodes',
            [$this, 'admin_page']
        );
    }
    
    /**
     * Enqueue admin assets
     */
    public function enqueue_admin_assets($hook) {
        if ($hook !== 'settings_page_scf-shortcodes') {
            return;
        }
        
        wp_enqueue_style(
            'scf-shortcodes-admin',
            SCF_SHORTCODES_PLUGIN_URL . 'admin/css/admin-style.css',
            [],
            SCF_SHORTCODES_VERSION
        );
        
        wp_enqueue_script(
            'scf-shortcodes-admin',
            SCF_SHORTCODES_PLUGIN_URL . 'admin/js/admin-script.js',
            ['jquery'],
            SCF_SHORTCODES_VERSION,
            true
        );
        
        wp_localize_script('scf-shortcodes-admin', 'scfShortcodes', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('scf_admin_nonce'),
            'strings' => [
                'confirmClearCache' => __('Are you sure you want to clear all cache?', 'scf-shortcodes'),
                'cacheCleared' => __('Cache cleared successfully!', 'scf-shortcodes'),
                'error' => __('An error occurred. Please try again.', 'scf-shortcodes')
            ]
        ]);
    }
    
    /**
     * Register settings
     */
    public function register_settings() {
        register_setting('scf_shortcodes_options', 'scf_shortcodes_options', [
            'sanitize_callback' => [$this, 'sanitize_options']
        ]);
        
        add_settings_section(
            'scf_shortcodes_general',
            __('General Settings', 'scf-shortcodes'),
            [$this, 'general_section_callback'],
            'scf-shortcodes'
        );
        
        add_settings_field(
            'cache_enabled',
            __('Enable Caching', 'scf-shortcodes'),
            [$this, 'cache_enabled_callback'],
            'scf-shortcodes',
            'scf_shortcodes_general'
        );
        
        add_settings_field(
            'cache_duration',
            __('Cache Duration (seconds)', 'scf-shortcodes'),
            [$this, 'cache_duration_callback'],
            'scf-shortcodes',
            'scf_shortcodes_general'
        );
        
        add_settings_field(
            'debug_mode',
            __('Debug Mode', 'scf-shortcodes'),
            [$this, 'debug_mode_callback'],
            'scf-shortcodes',
            'scf_shortcodes_general'
        );
    }
    
    /**
     * Admin page
     */
    public function admin_page() {
        include SCF_SHORTCODES_PLUGIN_DIR . 'admin/views/admin-page.php';
    }
    
    /**
     * Sanitize options
     */
    public function sanitize_options($options) {
        $sanitized = [];
        
        $sanitized['cache_enabled'] = isset($options['cache_enabled']) ? 1 : 0;
        $sanitized['cache_duration'] = absint($options['cache_duration'] ?? 3600);
        $sanitized['debug_mode'] = isset($options['debug_mode']) ? 1 : 0;
        $sanitized['log_errors'] = isset($options['log_errors']) ? 1 : 0;
        $sanitized['auto_clear_cache'] = isset($options['auto_clear_cache']) ? 1 : 0;
        
        return $sanitized;
    }
    
    /**
     * Add plugin action links
     */
    public function add_plugin_links($links) {
        $settings_link = sprintf(
            '<a href="%s">%s</a>',
            admin_url('options-general.php?page=scf-shortcodes'),
            __('Settings', 'scf-shortcodes')
        );
        
        array_unshift($links, $settings_link);
        
        return $links;
    }
    
    // Settings callbacks
    public function general_section_callback() {
        echo '<p>' . __('Configure SCF-ShortCodes plugin settings.', 'scf-shortcodes') . '</p>';
    }
    
    public function cache_enabled_callback() {
        $options = get_option('scf_shortcodes_options', []);
        $checked = isset($options['cache_enabled']) && $options['cache_enabled'] ? 'checked' : '';
        echo "<input type='checkbox' name='scf_shortcodes_options[cache_enabled]' value='1' $checked />";
    }
    
    public function cache_duration_callback() {
        $options = get_option('scf_shortcodes_options', []);
        $value = $options['cache_duration'] ?? 3600;
        echo "<input type='number' name='scf_shortcodes_options[cache_duration]' value='$value' min='60' max='86400' />";
    }
    
    public function debug_mode_callback() {
        $options = get_option('scf_shortcodes_options', []);
        $checked = isset($options['debug_mode']) && $options['debug_mode'] ? 'checked' : '';
        echo "<input type='checkbox' name='scf_shortcodes_options[debug_mode]' value='1' $checked />";
        echo '<p class="description">' . __('Enable debug mode to show error messages in shortcode output.', 'scf-shortcodes') . '</p>';
    }
}
