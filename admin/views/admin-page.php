<?php
if (!defined('ABSPATH')) {
    exit;
}

$options = get_option('scf_shortcodes_options', []);
$cache_manager = new SCF_Cache_Manager();
$cache_stats = $cache_manager->get_cache_stats();
$error_handler = new SCF_Error_Handler();
$recent_errors = $error_handler->get_recent_errors(10);
?>

<div class="wrap">
    <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
    
    <div class="scf-admin-container">
        <div class="scf-admin-main">
            <form method="post" action="options.php">
                <?php
                settings_fields('scf_shortcodes_options');
                do_settings_sections('scf-shortcodes');
                submit_button();
                ?>
            </form>
        </div>
        
        <div class="scf-admin-sidebar">
            <!-- Cache Management -->
            <div class="scf-admin-box">
                <h3><?php _e('Cache Management', 'scf-shortcodes'); ?></h3>
                <div class="scf-cache-stats">
                    <p><strong><?php _e('Cache Type:', 'scf-shortcodes'); ?></strong> <?php echo esc_html($cache_stats['type']); ?></p>
                    <?php if (isset($cache_stats['total_entries'])): ?>
                        <p><strong><?php _e('Total Entries:', 'scf-shortcodes'); ?></strong> <?php echo esc_html($cache_stats['total_entries']); ?></p>
                        <p><strong><?php _e('Active Entries:', 'scf-shortcodes'); ?></strong> <?php echo esc_html($cache_stats['active_entries']); ?></p>
                        <p><strong><?php _e('Expired Entries:', 'scf-shortcodes'); ?></strong> <?php echo esc_html($cache_stats['expired_entries']); ?></p>
                    <?php endif; ?>
                </div>
                <button type="button" class="button button-secondary" id="scf-clear-cache">
                    <?php _e('Clear All Cache', 'scf-shortcodes'); ?>
                </button>
            </div>
            
            <!-- Shortcode Documentation -->
            <div class="scf-admin-box">
                <h3><?php _e('Available Shortcodes', 'scf-shortcodes'); ?></h3>
                <div class="scf-shortcode-examples">
                    <h4><?php _e('Basic Field', 'scf-shortcodes'); ?></h4>
                    <code>[scf_field field="field_name"]</code>
                    
                    <h4><?php _e('Flexible Content', 'scf-shortcodes'); ?></h4>
                    <code>[scf_flexible field="content" layout="hero" format="table"]</code>
                    
                    <h4><?php _e('Group Fields', 'scf-shortcodes'); ?></h4>
                    <code>[scf_group field="contact_info" format="card"]</code>
                    
                    <h4><?php _e('Repeater Fields', 'scf-shortcodes'); ?></h4>
                    <code>[scf_repeater field="team" format="cards"]</code>
                    
                    <h4><?php _e('Field Suffix', 'scf-shortcodes'); ?></h4>
                    <code>[scf_suffix field="height" type="suffix"]</code>
                </div>
            </div>
            
            <!-- Error Log -->
            <?php if (!empty($recent_errors)): ?>
            <div class="scf-admin-box">
                <h3><?php _e('Recent Errors', 'scf-shortcodes'); ?></h3>
                <div class="scf-error-log">
                    <?php foreach ($recent_errors as $error): ?>
                        <div class="scf-error-item">
                            <strong><?php echo esc_html($error['timestamp']); ?></strong><br>
                            <?php echo esc_html($error['message']); ?>
                        </div>
                    <?php endforeach; ?>
                </div>
                <button type="button" class="button button-secondary" id="scf-clear-errors">
                    <?php _e('Clear Error Log', 'scf-shortcodes'); ?>
                </button>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>
