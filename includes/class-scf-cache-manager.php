<?php
/**
 * SCF Cache Manager
 */

if (!defined('ABSPATH')) {
    exit;
}

class SCF_Cache_Manager {
    
    private $cache_group = 'scf_shortcodes';
    private $cache_duration;
    private $use_database = false;
    
    public function __construct() {
        $options = get_option('scf_shortcodes_options', []);
        $this->cache_duration = $options['cache_duration'] ?? 3600;
        $this->use_database = $options['use_database_cache'] ?? false;
    }
    
    /**
     * Get cached value
     */
    public function get($key) {
        if ($this->use_database) {
            return $this->get_from_database($key);
        }
        
        return wp_cache_get($key, $this->cache_group);
    }
    
    /**
     * Set cached value
     */
    public function set($key, $value, $expiration = null) {
        $expiration = $expiration ?: $this->cache_duration;
        
        if ($this->use_database) {
            return $this->set_to_database($key, $value, $expiration);
        }
        
        return wp_cache_set($key, $value, $this->cache_group, $expiration);
    }
    
    /**
     * Delete cached value
     */
    public function delete($key) {
        if ($this->use_database) {
            return $this->delete_from_database($key);
        }
        
        return wp_cache_delete($key, $this->cache_group);
    }
    
    /**
     * Clear all cache
     */
    public function clear_all_cache() {
        if ($this->use_database) {
            global $wpdb;
            $table_name = $wpdb->prefix . 'scf_shortcodes_cache';
            $wpdb->query("DELETE FROM $table_name");
        }
        
        wp_cache_flush_group($this->cache_group);
        
        do_action('scf_shortcodes_cache_cleared');
    }
    
    /**
     * Clear cache for specific post
     */
    public function clear_post_cache($post_id) {
        if ($this->use_database) {
            global $wpdb;
            $table_name = $wpdb->prefix . 'scf_shortcodes_cache';
            $wpdb->query($wpdb->prepare(
                "DELETE FROM $table_name WHERE cache_key LIKE %s",
                '%_' . $post_id . '_%'
            ));
        }
        
        // Clear object cache patterns
        $patterns = [
            "field_%_{$post_id}",
            "flexible_%_{$post_id}",
            "field_info_%_{$post_id}"
        ];
        
        foreach ($patterns as $pattern) {
            wp_cache_delete($pattern, $this->cache_group);
        }
        
        do_action('scf_shortcodes_post_cache_cleared', $post_id);
    }
    
    /**
     * Get from database cache
     */
    private function get_from_database($key) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'scf_shortcodes_cache';
        
        $result = $wpdb->get_row($wpdb->prepare(
            "SELECT cache_value, expiration FROM $table_name WHERE cache_key = %s",
            $key
        ));
        
        if (!$result) {
            return false;
        }
        
        // Check if expired
        if (strtotime($result->expiration) < time()) {
            $this->delete_from_database($key);
            return false;
        }
        
        return maybe_unserialize($result->cache_value);
    }
    
    /**
     * Set to database cache
     */
    private function set_to_database($key, $value, $expiration) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'scf_shortcodes_cache';
        $expiration_date = date('Y-m-d H:i:s', time() + $expiration);
        
        return $wpdb->replace(
            $table_name,
            [
                'cache_key' => $key,
                'cache_value' => maybe_serialize($value),
                'expiration' => $expiration_date
            ],
            ['%s', '%s', '%s']
        );
    }
    
    /**
     * Delete from database cache
     */
    private function delete_from_database($key) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'scf_shortcodes_cache';
        
        return $wpdb->delete(
            $table_name,
            ['cache_key' => $key],
            ['%s']
        );
    }
    
    /**
     * Clean expired cache entries
     */
    public function clean_expired_cache() {
        if (!$this->use_database) {
            return;
        }
        
        global $wpdb;
        $table_name = $wpdb->prefix . 'scf_shortcodes_cache';
        
        $wpdb->query("DELETE FROM $table_name WHERE expiration < NOW()");
    }
    
    /**
     * Get cache statistics
     */
    public function get_cache_stats() {
        if (!$this->use_database) {
            return ['type' => 'object_cache', 'entries' => 'unknown'];
        }
        
        global $wpdb;
        $table_name = $wpdb->prefix . 'scf_shortcodes_cache';
        
        $total = $wpdb->get_var("SELECT COUNT(*) FROM $table_name");
        $expired = $wpdb->get_var("SELECT COUNT(*) FROM $table_name WHERE expiration < NOW()");
        
        return [
            'type' => 'database',
            'total_entries' => $total,
            'expired_entries' => $expired,
            'active_entries' => $total - $expired
        ];
    }
}
