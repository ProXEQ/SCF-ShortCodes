<?php
/**
 * SCF Flexible Content Handler - Hybrid Type Detection System
 */

if (!defined('ABSPATH')) {
    exit;
}

class SCF_Flexible_Handler {
    
    private $cache_manager;
    private $error_handler;
    private $layout_cache = [];
    private $type_cache = []; // Dodany cache dla typów pól
    private $field_discovery_cache = []; // Cache dla odkrywania pól
    
    public function __construct($cache_manager, $error_handler) {
        $this->cache_manager = $cache_manager;
        $this->error_handler = $error_handler;
    }
    
    /**
     * Handle flexible content shortcode
     */
    public function handle_flexible_shortcode($atts) {
        try {
            $atts = $this->sanitize_flexible_atts($atts);
            
            if (empty($atts['field'])) {
                throw new Exception(__('Missing field parameter for flexible content', 'scf-shortcodes'));
            }
            
            $post_id = $this->get_post_id($atts['post_id']);
            $cache_key = $this->generate_cache_key('flexible', $atts, $post_id);
            
            // Check cache
            if ($atts['cache'] === 'true') {
                $cached = $this->cache_manager->get($cache_key);
                if ($cached !== false) {
                    return $cached;
                }
            }
            
            $flexible_data = $this->get_flexible_content_safe(
                $atts['field'],
                $atts['layout'],
                $post_id,
                intval($atts['limit'])
            );
            
            if (empty($flexible_data)) {
                return $atts['fallback'];
            }
            
            $output = $this->format_flexible_output($flexible_data, $atts['format'], $atts['class']);
            
            // Cache result
            if ($atts['cache'] === 'true') {
                $this->cache_manager->set($cache_key, $output);
            }
            
            return $output;
            
        } catch (Exception $e) {
            return $this->error_handler->handle_shortcode_error($e, 'scf_flexible');
        }
    }
    
    /**
     * Handle flexible field shortcode
     */
    public function handle_flexible_field_shortcode($atts) {
        try {
            $atts = $this->sanitize_flexible_field_atts($atts);
            
            if (empty($atts['field']) || empty($atts['subfield'])) {
                throw new Exception(__('Missing field or subfield parameter', 'scf-shortcodes'));
            }
            
            $post_id = $this->get_post_id($atts['post_id']);
            $flexible_data = $this->get_flexible_content_safe(
                $atts['field'],
                $atts['layout'],
                $post_id,
                1
            );
            
            $index = intval($atts['index']);
            $value = isset($flexible_data[$index][$atts['subfield']]) ? $flexible_data[$index][$atts['subfield']] : null;
            
            if ($value !== null) {
                // NOWA LOGIKA: Hybrydowe formatowanie dla flexible field
                return $this->format_value_with_suffixes($value, $atts['subfield'], 'flexible', $atts);
            }
            
            return $atts['fallback'];
            
        } catch (Exception $e) {
            return $this->error_handler->handle_shortcode_error($e, 'scf_flexible_field');
        }
    }
    
    /**
     * Get flexible content with optimized processing
     */
    private function get_flexible_content_safe($field, $layout = '', $post_id = null, $limit = 0) {
        if (!function_exists('have_rows') || !have_rows($field, $post_id)) {
            return [];
        }
        
        $flexible_data = [];
        $count = 0;
        
        while (have_rows($field, $post_id)) {
            the_row();
            
            if ($limit > 0 && $count >= $limit) {
                break;
            }
            
            $current_layout = function_exists('get_row_layout') ? get_row_layout() : '';
            
            if (!empty($layout) && $current_layout !== $layout) {
                continue;
            }
            
            $row_data = $this->get_row_data_optimized($current_layout);
            if (!empty($row_data)) {
                $flexible_data[] = $row_data;
                $count++;
                
                if (empty($layout)) {
                    break;
                }
            }
        }
        
        return $flexible_data;
    }
    
    /**
     * Optimized row data extraction with hybrid formatting
     */
    private function get_row_data_optimized($current_layout = '') {
        $available_fields = $this->discover_available_fields($current_layout);
        $row_data = [];
        
        foreach ($available_fields as $field_name) {
            $value = get_sub_field($field_name);
            
            if ($value === false || $this->is_empty_value($value)) {
                continue;
            }
            
            $field_info = $this->get_scf_field_info($field_name, 'flexible');
            
            // NOWA LOGIKA: Hybrydowe formatowanie wartości
            $formatted_value = $this->format_flexible_field_value($value, $field_name, $field_info);
            
            $row_data[] = [
                'key' => $field_name,
                'label' => $field_info['label'] ?: $this->clean_field_name_conversion($field_name),
                'value' => $formatted_value,
                'raw_value' => $value,
                'append' => $field_info['append'],
                'prepend' => $field_info['prepend'],
                'type' => $field_info['type']
            ];
        }
        
        return $row_data;
    }
    
    /**
     * Format flexible field value with hybrid type detection
     */
    private function format_flexible_field_value($value, $field_name, $field_info) {
        // Określ typ pola
        $field_type = $this->determine_field_type($field_name, $field_info['type'], $value);
        
        // Formatuj na podstawie typu
        $formatted_value = $this->format_by_detected_type($value, $field_type, $field_name);
        
        // Dodaj sufiksy/prefiksy
        return $field_info['prepend'] . $formatted_value . $field_info['append'];
    }
    
    /**
     * Determine field type for flexible content
     */
    private function determine_field_type($field_name, $scf_type = '', $value = null) {
        $cache_key = "flex_type_{$field_name}";
        
        if (isset($this->type_cache[$cache_key])) {
            return $this->type_cache[$cache_key];
        }
        
        // 1. Użyj typu z SCF API jeśli dostępny
        if (!empty($scf_type)) {
            $this->type_cache[$cache_key] = $scf_type;
            return $scf_type;
        }
        
        // 2. Wykryj typ na podstawie wartości
        $detected_type = $this->detect_type_from_value($value);
        $this->type_cache[$cache_key] = $detected_type;
        
        return $detected_type;
    }
    
    /**
     * Detect field type from value structure
     */
    /**
 * Detect field type from value structure - ENHANCED VERSION
 */
private function detect_type_from_value($value) {
    if (is_array($value)) {
        if (isset($value['url']) && isset($value['sizes'])) return 'image';
        if (isset($value[0]) && is_array($value[0]) && isset($value[0]['url'])) return 'gallery';
        if (isset($value['url']) && isset($value['filename'])) return 'file';
        return 'array';
    }
    
    if (is_object($value)) {
        if ($value instanceof WP_Post) return 'post_object';
        if ($value instanceof WP_User) return 'user';
        if ($value instanceof WP_Term) return 'taxonomy';
    }
    
    if (is_string($value)) {
        // NOWE: Wykrywanie pól WYSIWYG
        if ($this->contains_html_tags($value)) {
            return 'wysiwyg';
        }
        
        if (filter_var($value, FILTER_VALIDATE_EMAIL)) return 'email';
        if (filter_var($value, FILTER_VALIDATE_URL)) return 'url';
        if (preg_match('/^\+?[\d\s\-\(\)]+$/', $value)) return 'phone';
    }
    
    if (is_numeric($value)) return 'number';
    
    return 'text';
}
    
    /**
     * Format by detected field type
     */
    private function format_by_detected_type($value, $field_type, $field_name) {
        switch ($field_type) {
            case 'image':
                return $this->format_image_auto($value);
            case 'gallery':
                return $this->format_gallery_auto($value);
            case 'file':
                return $this->format_file_auto($value);
            case 'post_object':
            case 'relationship':
                return $this->format_relationship_field($value);
            case 'user':
                return $this->format_user_field($value);
            case 'taxonomy':
                return $this->format_taxonomy_field($value);
            case 'email':
                return sprintf('<a href="mailto:%s">%s</a>', esc_attr($value), esc_html($value));
            case 'url':
                return sprintf('<a href="%s" target="_blank">%s</a>', esc_url($value), esc_html($value));
            case 'phone':
                $clean_phone = preg_replace('/[^0-9+]/', '', $value);
                return sprintf('<a href="tel:%s">%s</a>', esc_attr($clean_phone), esc_html($value));
            case 'number':
                return $this->format_number_auto($value, $field_name);
            case 'date':
            case 'date_picker':
                return $this->format_date_auto($value);
            case 'textarea':
                return wpautop($this->sanitize_output($value));
            case 'array':
                return $this->format_array_auto($value);
            case 'text':
            default:
                return $this->sanitize_output($value);
        }
    }
    
    /**
     * Auto format image field
     */
    private function format_image_auto($value) {
        if (!is_array($value) || !isset($value['url'])) {
            return $this->sanitize_output($value);
        }
        
        $src = $value['sizes']['medium'] ?? $value['url'];
        $alt = $value['alt'] ?? $value['title'] ?? '';
        
        return sprintf('<img src="%s" alt="%s" class="scf-auto-image">', esc_url($src), esc_attr($alt));
    }
    
    /**
     * Auto format gallery field
     */
    private function format_gallery_auto($value) {
        if (!is_array($value) || empty($value)) {
            return '';
        }
        
        $output = '<div class="scf-auto-gallery">';
        foreach ($value as $image) {
            if (is_array($image) && isset($image['url'])) {
                $src = $image['sizes']['thumbnail'] ?? $image['url'];
                $alt = $image['alt'] ?? $image['title'] ?? '';
                $output .= sprintf('<img src="%s" alt="%s">', esc_url($src), esc_attr($alt));
            }
        }
        return $output . '</div>';
    }
    
    /**
     * Auto format file field
     */
    private function format_file_auto($value) {
        if (!is_array($value) || !isset($value['url'])) {
            return $this->sanitize_output($value);
        }
        
        $title = $value['title'] ?? $value['filename'] ?? basename($value['url']);
        $size = isset($value['filesize']) ? size_format($value['filesize']) : '';
        $size_text = $size ? " ({$size})" : '';
        
        return sprintf(
            '<a href="%s" target="_blank" class="scf-file-link">%s%s</a>',
            esc_url($value['url']),
            esc_html($title),
            esc_html($size_text)
        );
    }
    
    /**
     * Auto format user field
     */
    private function format_user_field($value) {
        if ($value instanceof WP_User) {
            return $value->display_name ?: $value->user_login;
        }
        
        if (is_numeric($value)) {
            $user = get_user_by('ID', $value);
            return $user ? ($user->display_name ?: $user->user_login) : "User #{$value}";
        }
        
        return $this->sanitize_output($value);
    }
    
    /**
     * Auto format taxonomy field
     */
    private function format_taxonomy_field($value) {
        if ($value instanceof WP_Term) {
            return $value->name;
        }
        
        if (is_array($value)) {
            $names = [];
            foreach ($value as $term) {
                if ($term instanceof WP_Term) {
                    $names[] = $term->name;
                } elseif (is_numeric($term)) {
                    $term_obj = get_term($term);
                    if ($term_obj && !is_wp_error($term_obj)) {
                        $names[] = $term_obj->name;
                    }
                }
            }
            return implode(', ', $names);
        }
        
        if (is_numeric($value)) {
            $term = get_term($value);
            return $term && !is_wp_error($term) ? $term->name : "Term #{$value}";
        }
        
        return $this->sanitize_output($value);
    }
    
    /**
     * Auto format number field
     */
    private function format_number_auto($value, $field_name) {
        if (!is_numeric($value)) {
            return $this->sanitize_output($value);
        }
        
        $field_info = $this->get_scf_field_info($field_name, 'flexible');
        $has_currency = (
            stripos($field_info['append'], 'PLN') !== false ||
            stripos($field_info['append'], 'EUR') !== false ||
            stripos($field_info['append'], 'USD') !== false ||
            stripos($field_info['prepend'], '$') !== false ||
            stripos($field_info['prepend'], '€') !== false
        );
        
        if ($has_currency) {
            return number_format(floatval($value), 2, ',', ' ');
        }
        
        return number_format(floatval($value), 0, ',', ' ');
    }
    
    /**
     * Auto format date field
     */
    private function format_date_auto($value) {
        if (empty($value)) return '';
        
        $timestamp = strtotime($value);
        if ($timestamp === false) {
            return $this->sanitize_output($value);
        }
        
        return date_i18n('j F Y', $timestamp);
    }
    
    /**
     * Auto format array field
     */
    private function format_array_auto($value) {
        if (!is_array($value)) {
            return $this->sanitize_output($value);
        }
        
        $formatted_items = array_map([$this, 'sanitize_output'], $value);
        return implode(', ', array_filter($formatted_items));
    }
    
    /**
     * Dynamic field discovery with enhanced caching
     */
    private function discover_available_fields($current_layout = '') {
        $cache_key = "fields_{$current_layout}";
        
        if (isset($this->field_discovery_cache[$cache_key])) {
            return $this->field_discovery_cache[$cache_key];
        }
        
        $fields = [];
        
        // Try to get fields from layout definition
        if (!empty($current_layout)) {
            $fields = $this->get_layout_fields_dynamic($current_layout);
        }
        
        // Fallback to brute force discovery
        if (empty($fields)) {
            $fields = $this->brute_force_field_discovery();
        }
        
        $this->field_discovery_cache[$cache_key] = $fields;
        return $fields;
    }
    
    /**
     * Get layout fields from SCF definition - OPTIMIZED
     */
    private function get_layout_fields_dynamic($layout_name) {
        if (!function_exists('acf_get_field_groups') || empty($layout_name)) {
            return [];
        }
        
        // Cache layout fields globally
        $global_cache_key = "layout_fields_{$layout_name}";
        $cached_fields = $this->cache_manager->get($global_cache_key);
        
        if ($cached_fields !== false) {
            return $cached_fields;
        }
        
        $field_groups = acf_get_field_groups();
        
        foreach ($field_groups as $group) {
            $fields = acf_get_fields($group['key']);
            if (!$fields) continue;
            
            $layout_fields = $this->find_layout_fields($fields, $layout_name);
            if (!empty($layout_fields)) {
                // Cache for 1 hour
                $this->cache_manager->set($global_cache_key, $layout_fields, 3600);
                return $layout_fields;
            }
        }
        
        return [];
    }
    
    /**
     * Recursively find layout fields
     */
    private function find_layout_fields($fields, $layout_name) {
        foreach ($fields as $field) {
            if ($field['type'] === 'flexible_content' && isset($field['layouts'])) {
                foreach ($field['layouts'] as $layout) {
                    if ($layout['name'] === $layout_name && isset($layout['sub_fields'])) {
                        return array_column($layout['sub_fields'], 'name');
                    }
                }
            }
            
            if (isset($field['sub_fields']) && is_array($field['sub_fields'])) {
                $nested_fields = $this->find_layout_fields($field['sub_fields'], $layout_name);
                if (!empty($nested_fields)) {
                    return $nested_fields;
                }
            }
        }
        
        return [];
    }
    
    /**
     * Optimized brute force field discovery
     */
    private function brute_force_field_discovery() {
        $discovered_fields = [];
        
        // Optimized pattern testing
        $test_patterns = [
            // Common field names
            'title', 'content', 'text', 'image', 'description', 'link',
            'nazwa', 'opis', 'tekst', 'zdjecie', 'tytul', 'tresc',
            // Numbered fields
            ...array_map(fn($i) => "field_{$i}", range(1, 5))
        ];
        
        foreach ($test_patterns as $pattern) {
            $value = get_sub_field($pattern);
            if ($value !== false) {
                $discovered_fields[] = $pattern;
            }
        }
        
        return $discovered_fields;
    }
    
    /**
     * Format flexible content output
     */
    private function format_flexible_output($data, $format, $class) {
        switch ($format) {
            case 'table':
                return $this->format_as_table($data, $class);
            default:
                return $this->format_as_list($data, $class);
        }
    }
    
    /**
     * Format as HTML list - OPTIMIZED
     */
    private function format_as_list($data, $class) {
        $class_attr = $this->build_class_attr('scf-flexible-list', $class);
        $output = '<ul' . $class_attr . '>';
        
        foreach ($data as $row) {
            foreach ($row as $item) {
                if (is_array($item) && isset($item['label'], $item['value'])) {
                    $output .= sprintf('<li><strong>%s:</strong> %s</li>', 
                        esc_html($item['label']), 
                        $item['value'] // Already formatted and escaped
                    );
                }
            }
        }
        
        return $output . '</ul>';
    }
    
    /**
     * Format as HTML table - OPTIMIZED
     */
    private function format_as_table($data, $class) {
        $class_attr = $this->build_class_attr('scf-flexible-table', $class);
        $output = '<table' . $class_attr . '>';
        
        foreach ($data as $row) {
            foreach ($row as $item) {
                if (is_array($item) && isset($item['label'], $item['value'])) {
                    $output .= sprintf('<tr><td><strong>%s</strong></td><td>%s</td></tr>', 
                        esc_html($item['label']), 
                        $item['value'] // Already formatted and escaped
                    );
                }
            }
        }
        
        return $output . '</table>';
    }
    
    /**
     * Get SCF field info with complete metadata
     */
    private function get_scf_field_info($field_name, $context = 'post') {
        $cache_key = "field_info_{$field_name}_{$context}";
        
        $cached = $this->cache_manager->get($cache_key);
        if ($cached !== false) {
            return $cached;
        }
        
        $field_info = [
            'label' => '',
            'append' => '',
            'prepend' => '',
            'type' => ''
        ];
        
        try {
            if ($context === 'flexible' && function_exists('get_sub_field_object')) {
                $field_object = get_sub_field_object($field_name);
            } elseif (function_exists('get_field_object')) {
                $field_object = get_field_object($field_name, false);
            } else {
                $field_object = null;
            }
            
            if (is_array($field_object)) {
                $field_info = [
                    'label' => $field_object['label'] ?? '',
                    'append' => $field_object['append'] ?? '',
                    'prepend' => $field_object['prepend'] ?? '',
                    'type' => $field_object['type'] ?? ''
                ];
            }
        } catch (Exception $e) {
            $this->error_handler->log_error('Failed to get field info: ' . $e->getMessage());
        }
        
        $this->cache_manager->set($cache_key, $field_info);
        return $field_info;
    }
    
    /**
     * Format value with SCF suffixes - ENHANCED VERSION
     */
    private function format_value_with_suffixes($value, $field_name, $context = 'post', $atts = []) {
        $field_info = $this->get_scf_field_info($field_name, $context);
        
        // Hybrydowe formatowanie
        $field_type = $this->determine_field_type($field_name, $field_info['type'], $value);
        $formatted_value = $this->format_by_detected_type($value, $field_type, $field_name);
        
        return $field_info['prepend'] . $formatted_value . $field_info['append'];
    }
    
    /**
     * Sanitize output with proper object handling
     */
    private function sanitize_output($value) {
        return esc_html($this->convert_to_string($value));
    }
    
    /**
     * Convert any value to string recursively
     */
    private function convert_to_string($value) {
        if (is_null($value) || $value === '') return '';
        
        if (is_array($value)) {
            $converted_items = array_map([$this, 'convert_to_string'], $value);
            return implode(', ', array_filter($converted_items));
        }
        
        if (is_object($value)) {
            return $this->convert_object_to_string($value);
        }
        
        return strval($value);
    }
    
    /**
     * Convert WordPress objects to meaningful strings
     */
    private function convert_object_to_string($object) {
        if ($object instanceof WP_Post) {
            return $object->post_title ?: "Post #{$object->ID}";
        }
        
        if ($object instanceof WP_User) {
            return $object->display_name ?: $object->user_login;
        }
        
        if ($object instanceof WP_Term) {
            return $object->name ?: "Term #{$object->term_id}";
        }
        
        if ($object instanceof WP_Comment) {
            return wp_trim_words($object->comment_content, 10) ?: "Comment #{$object->comment_ID}";
        }
        
        if (method_exists($object, '__toString')) {
            try {
                return $object->__toString();
            } catch (Exception $e) {
                // Fallback
            }
        }
        
        if (isset($object->title)) return $object->title;
        if (isset($object->name)) return $object->name;
        if (isset($object->ID)) return get_class($object) . " #{$object->ID}";
        
        return get_class($object);
    }
    
    /**
     * Format relationship field values
     */
    private function format_relationship_field($posts) {
        if (!is_array($posts)) {
            return $this->convert_to_string($posts);
        }
        
        $titles = [];
        foreach ($posts as $post) {
            if ($post instanceof WP_Post) {
                $titles[] = $post->post_title;
            } elseif (is_numeric($post)) {
                $post_obj = get_post($post);
                if ($post_obj) {
                    $titles[] = $post_obj->post_title;
                }
            } else {
                $titles[] = $this->convert_to_string($post);
            }
        }
        
        return implode(', ', array_filter($titles));
    }
    
    /**
     * Sanitize flexible attributes - ENHANCED VERSION
     */
    private function sanitize_flexible_atts($atts) {
        return shortcode_atts([
            'field' => '',
            'layout' => '',
            'format' => 'list',
            'type' => '',
            'post_id' => null,
            'limit' => 0,
            'fallback' => '',
            'class' => '',
            'cache' => 'true'
        ], $atts, 'scf_flexible');
    }
    
    /**
     * Sanitize flexible field attributes - ENHANCED VERSION
     */
    private function sanitize_flexible_field_atts($atts) {
        return shortcode_atts([
            'field' => '',
            'layout' => '',
            'subfield' => '',
            'type' => '',
            'post_id' => null,
            'fallback' => '',
            'index' => 0
        ], $atts, 'scf_flexible_field');
    }
    
    // Helper methods
    private function get_post_id($post_id) {
        return $post_id ? intval($post_id) : (get_the_ID() ?: get_queried_object_id());
    }
    
    private function generate_cache_key($type, $atts, $post_id) {
        $key_parts = [$type, $post_id];
        foreach (['field', 'layout', 'format', 'subfield', 'type'] as $key) {
            if (isset($atts[$key])) {
                $key_parts[] = $atts[$key];
            }
        }
        return 'scf_' . md5(implode('_', $key_parts));
    }
    
    private function is_empty_value($value) {
        return empty($value) && $value !== '0' && $value !== 0;
    }
    
    private function build_class_attr($default_class, $additional_class) {
        $classes = [$default_class];
        if (!empty($additional_class)) {
            $classes[] = $additional_class;
        }
        return ' class="' . esc_attr(implode(' ', $classes)) . '"';
    }
    
    private function clean_field_name_conversion($field_name) {
        return ucwords(str_replace(['_', '-'], ' ', strtolower($field_name)));
    }
    /**
 * Check if string contains HTML tags (indicates WYSIWYG field)
 */
    private function contains_html_tags($string) {
        if (strip_tags($string) !== $string) {
            return true;
        }
        
        $html_patterns = [
            '/<p\b[^>]*>/', '/<h[1-6]\b[^>]*>/', '/<div\b[^>]*>/', 
            '/<span\b[^>]*>/', '/<strong\b[^>]*>/', '/<em\b[^>]*>/',
            '/<ul\b[^>]*>/', '/<ol\b[^>]*>/', '/<li\b[^>]*>/',
            '/<a\b[^>]*>/', '/<img\b[^>]*>/', '/<br\s*\/?>/,'
        ];
        
        foreach ($html_patterns as $pattern) {
            if (preg_match($pattern, $string)) {
                return true;
            }
        }
        
        return false;
    }

    /**
     * Format WYSIWYG field content
     */
    private function format_wysiwyg_field($value) {
    // Dozwolone tagi HTML
    $allowed_tags = wp_kses_allowed_html('post');
    return wp_kses(wpautop($value), $allowed_tags);
}
}
