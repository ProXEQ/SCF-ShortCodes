<?php
/**
 * SCF Error Handler
 */

if (!defined('ABSPATH')) {
    exit;
}

class SCF_Error_Handler {
    
    private $debug_mode;
    private $log_errors;
    
    public function __construct() {
        $this->debug_mode = defined('WP_DEBUG') && WP_DEBUG;
        $options = get_option('scf_shortcodes_options', []);
        $this->log_errors = $options['log_errors'] ?? true;
    }
    
    /**
     * Handle shortcode errors
     */
    public function handle_shortcode_error($exception, $shortcode_name) {
        $error_message = $exception->getMessage();
        $error_code = $exception->getCode();
        
        // Log error for developers
        if ($this->log_errors) {
            $this->log_error(sprintf(
                'Shortcode Error [%s]: %s (Code: %d)',
                $shortcode_name,
                $error_message,
                $error_code
            ));
        }
        
        // Return user-friendly error or debug info
        if ($this->debug_mode) {
            return sprintf(
                '<!-- SCF ShortCodes Error [%s]: %s -->',
                esc_html($shortcode_name),
                esc_html($error_message)
            );
        }
        
        // Silent failure for production
        return '';
    }
    
    /**
     * Log error message
     */
    public function log_error($message, $context = []) {
        if (!$this->log_errors) {
            return;
        }
        
        $log_message = '[SCF-ShortCodes] ' . $message;
        
        if (!empty($context)) {
            $log_message .= ' Context: ' . wp_json_encode($context);
        }
        
        error_log($log_message);
        
        // Also store in database for admin review
        $this->store_error_in_database($message, $context);
    }
    
    /**
     * Store error in database
     */
    private function store_error_in_database($message, $context) {
        $errors = get_option('scf_shortcodes_errors', []);
        
        $error_entry = [
            'message' => $message,
            'context' => $context,
            'timestamp' => current_time('mysql'),
            'user_id' => get_current_user_id(),
            'url' => $_SERVER['REQUEST_URI'] ?? '',
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? ''
        ];
        
        array_unshift($errors, $error_entry);
        
        // Keep only last 100 errors
        $errors = array_slice($errors, 0, 100);
        
        update_option('scf_shortcodes_errors', $errors);
    }
    
    /**
     * Get recent errors
     */
    public function get_recent_errors($limit = 20) {
        $errors = get_option('scf_shortcodes_errors', []);
        return array_slice($errors, 0, $limit);
    }
    
    /**
     * Clear error log
     */
    public function clear_error_log() {
        delete_option('scf_shortcodes_errors');
    }
    
    /**
     * Validate shortcode attributes
     */
    public function validate_shortcode_atts($atts, $required_fields = []) {
        $errors = [];
        
        foreach ($required_fields as $field) {
            if (empty($atts[$field])) {
                $errors[] = sprintf(__('Missing required parameter: %s', 'scf-shortcodes'), $field);
            }
        }
        
        if (!empty($errors)) {
            throw new Exception(implode(', ', $errors));
        }
        
        return true;
    }
    
    /**
     * Sanitize and validate field name
     */
    public function validate_field_name($field_name) {
        if (empty($field_name)) {
            throw new Exception(__('Field name cannot be empty', 'scf-shortcodes'));
        }
        
        if (!preg_match('/^[a-zA-Z0-9_-]+$/', $field_name)) {
            throw new Exception(__('Invalid field name format', 'scf-shortcodes'));
        }
        
        return sanitize_text_field($field_name);
    }
    
    /**
     * Handle fatal errors
     */
    public function register_fatal_error_handler() {
        register_shutdown_function(function() {
            $error = error_get_last();
            
            if ($error && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
                $this->log_error('Fatal Error: ' . $error['message'], [
                    'file' => $error['file'],
                    'line' => $error['line'],
                    'type' => $error['type']
                ]);
                
                // Notify admin if configured
                $options = get_option('scf_shortcodes_options', []);
                if ($options['notify_admin_on_fatal'] ?? false) {
                    $this->notify_admin_of_fatal_error($error);
                }
            }
        });
    }
    
    /**
     * Notify admin of fatal error
     */
    private function notify_admin_of_fatal_error($error) {
        $admin_email = get_option('admin_email');
        $site_name = get_bloginfo('name');
        
        $subject = sprintf(__('[%s] SCF-ShortCodes Fatal Error', 'scf-shortcodes'), $site_name);
        
        $message = sprintf(
            __("A fatal error occurred in SCF-ShortCodes plugin:\n\nError: %s\nFile: %s\nLine: %d\nTime: %s\n\nPlease check your error logs for more details.", 'scf-shortcodes'),
            $error['message'],
            $error['file'],
            $error['line'],
            current_time('mysql')
        );
        
        wp_mail($admin_email, $subject, $message);
    }
}
