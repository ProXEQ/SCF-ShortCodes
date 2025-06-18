<?php
/**
 * Uninstall SCF-ShortCodes
 */

if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

// Remove plugin options
delete_option('scf_shortcodes_options');
delete_option('scf_shortcodes_errors');

// Remove cache table
global $wpdb;
$table_name = $wpdb->prefix . 'scf_shortcodes_cache';
$wpdb->query("DROP TABLE IF EXISTS $table_name");

// Clear any remaining cache
wp_cache_flush_group('scf_shortcodes');

// Remove any scheduled events
wp_clear_scheduled_hook('scf_shortcodes_cleanup_cache');
