<?php
/**
 * SCF Field Handler - Hybrid Type Detection System
 */

if (!defined('ABSPATH')) {
    exit;
}

class SCF_Field_Handler {
    
    private $cache_manager;
    private $error_handler;
    private $type_cache = []; // Dodany cache dla typów pól
    
    public function __construct($cache_manager, $error_handler) {
        $this->cache_manager = $cache_manager;
        $this->error_handler = $error_handler;
    }
    
    /**
     * Handle basic field shortcode
     */
    public function handle_field_shortcode($atts) {
        try {
            $atts = $this->sanitize_field_atts($atts);
            
            if (empty($atts['field'])) {
                throw new Exception(__('Missing field parameter', 'scf-shortcodes'));
            }
            
            $post_id = $this->get_post_id($atts['post_id']);
            $cache_key = $this->generate_cache_key('field', $atts, $post_id);
            
            // Check cache
            if ($atts['cache'] === 'true') {
                $cached = $this->cache_manager->get($cache_key);
                if ($cached !== false) {
                    return $cached;
                }
            }
            
            $value = $this->get_scf_field($atts['field'], $post_id);
            
            if ($this->is_empty_value($value)) {
                return $atts['fallback'];
            }
            
            // NOWA LOGIKA: Hybrydowe formatowanie
            $formatted_value = $atts['show_suffix'] === 'true' 
                ? $this->format_value_with_suffixes($value, $atts['field'], 'post', $atts)
                : $this->format_field_value($value, $atts);
            
            $output = $this->apply_wrapper($formatted_value, $atts['wrapper'], $atts['class']);
            
            // Cache result
            if ($atts['cache'] === 'true') {
                $this->cache_manager->set($cache_key, $output);
            }
            
            return $output;
            
        } catch (Exception $e) {
            return $this->error_handler->handle_shortcode_error($e, 'scf_field');
        }
    }
    
    /**
     * Handle group shortcode
     */
    public function handle_group_shortcode($atts) {
        try {
            $atts = $this->sanitize_group_atts($atts);
            
            if (empty($atts['field'])) {
                throw new Exception(__('Missing field parameter for group', 'scf-shortcodes'));
            }
            
            $post_id = $this->get_post_id($atts['post_id']);
            $group_data = $this->get_scf_field($atts['field'], $post_id);
            
            if ($this->is_empty_value($group_data)) {
                return $atts['fallback'];
            }
            
            if (!empty($atts['subfield'])) {
                $value = isset($group_data[$atts['subfield']]) ? $group_data[$atts['subfield']] : null;
                if ($value !== null) {
                    return $this->format_value_with_suffixes($value, $atts['subfield']);
                }
                return $atts['fallback'];
            }
            
            if ($atts['format'] === 'card') {
                return $this->format_group_as_card($group_data, $atts['field'], $atts['class']);
            }
            
            return $atts['fallback'];
            
        } catch (Exception $e) {
            return $this->error_handler->handle_shortcode_error($e, 'scf_group');
        }
    }
    
    /**
     * Handle repeater shortcode
     */
    public function handle_repeater_shortcode($atts) {
        try {
            $atts = $this->sanitize_repeater_atts($atts);
            
            if (empty($atts['field'])) {
                throw new Exception(__('Missing field parameter for repeater', 'scf-shortcodes'));
            }
            
            $post_id = $this->get_post_id($atts['post_id']);
            $repeater_data = $this->get_scf_field($atts['field'], $post_id);
            
            if ($this->is_empty_value($repeater_data)) {
                return $atts['fallback'];
            }
            
            $limit = intval($atts['limit']);
            if ($limit > 0) {
                $repeater_data = array_slice($repeater_data, 0, $limit);
            }
            
            if (!empty($atts['subfield'])) {
                $values = [];
                foreach ($repeater_data as $row) {
                    if (isset($row[$atts['subfield']]) && !$this->is_empty_value($row[$atts['subfield']])) {
                        $values[] = $this->format_value_with_suffixes($row[$atts['subfield']], $atts['subfield']);
                    }
                }
                return implode($atts['separator'], $values);
            }
            
            return $this->format_repeater_output($repeater_data, $atts['format'], $atts['class']);
            
        } catch (Exception $e) {
            return $this->error_handler->handle_shortcode_error($e, 'scf_repeater');
        }
    }
    
    /**
     * Handle suffix shortcode
     */
    public function handle_suffix_shortcode($atts) {
        try {
            $atts = shortcode_atts([
                'field' => '',
                'type' => 'suffix',
                'post_id' => null
            ], $atts, 'scf_suffix');
            
            if (empty($atts['field'])) {
                return '';
            }
            
            $field_info = $this->get_field_info($atts['field']);
            
            return $atts['type'] === 'prefix' ? $field_info['prepend'] : $field_info['append'];
            
        } catch (Exception $e) {
            return $this->error_handler->handle_shortcode_error($e, 'scf_suffix');
        }
    }
    
    /**
     * Format field value based on type and format - HYBRYDOWA IMPLEMENTACJA
     */
    private function format_field_value($value, $atts) {
        // 1. Określ typ pola (hybrydowo)
        $field_type = $this->determine_field_type($atts['field'], $atts['type'], $value);
        
        // 2. Jeśli format jest 'auto', użyj typu do automatycznego formatowania
        if ($atts['format'] === 'auto') {
            return $this->format_by_detected_type($value, $field_type, $atts);
        }
        
        // 3. Jeśli format jest określony, użyj go (zachowanie zgodne wstecz)
        return $this->format_by_specified_format($value, $atts);
    }
    
    /**
     * Determine field type (hybrid approach)
     */
    private function determine_field_type($field_name, $user_type = '', $value = null) {
        // Cache key dla typu pola
        $cache_key = "field_type_{$field_name}";
        
        if (isset($this->type_cache[$cache_key])) {
            $detected_type = $this->type_cache[$cache_key];
        } else {
            // 1. Jeśli użytkownik podał typ - użyj go
            if (!empty($user_type)) {
                $this->type_cache[$cache_key] = $user_type;
                return $user_type;
            }
            
            // 2. Pobierz typ z SCF API
            $field_info = $this->get_field_info($field_name);
            if (!empty($field_info['type'])) {
                $detected_type = $field_info['type'];
            } else {
                // 3. Próbuj wykryć typ na podstawie wartości
                $detected_type = $this->detect_type_from_value($value);
            }
            
            $this->type_cache[$cache_key] = $detected_type;
        }
        
        return $detected_type;
    }
    /**
 * Format group as card
 */
private function format_group_as_card($data, $field_name, $class) {
    $field_info = $this->get_field_info($field_name);
    $title = $field_info['label'] ?: $this->clean_field_name_conversion($field_name);
    
    $class_attr = $this->build_class_attr('scf-group-card', $class);
    $output = '<div' . $class_attr . '><h4>' . esc_html($title) . '</h4>';
    
    foreach ($data as $key => $value) {
        if (!$this->is_empty_value($value)) {
            $field_info = $this->get_field_info($key);
            $label = $field_info['label'] ?: $this->clean_field_name_conversion($key);
            $formatted_value = $this->format_value_with_suffixes($value, $key);
            
            $output .= sprintf('<p><strong>%s:</strong> %s</p>', 
                esc_html($label), 
                $formatted_value
            );
        }
    }
    
    return $output . '</div>';
}

/**
 * Format repeater output
 */
private function format_repeater_output($data, $format, $class) {
    switch ($format) {
        case 'cards':
            return $this->format_repeater_as_cards($data, $class);
        case 'grid':
            return $this->format_repeater_as_grid($data, $class);
        case 'table':
            return $this->format_repeater_as_table($data, $class);
        default:
            return $this->format_repeater_as_list($data, $class);
    }
}

/**
 * Format repeater as cards
 */
private function format_repeater_as_cards($data, $class) {
    $class_attr = $this->build_class_attr('scf-repeater-cards', $class);
    $output = '<div' . $class_attr . '>';
    
    foreach ($data as $item) {
        $output .= '<div class="scf-card">';
        foreach ($item as $key => $value) {
            if (!$this->is_empty_value($value)) {
                $field_info = $this->get_field_info($key);
                $label = $field_info['label'] ?: $this->clean_field_name_conversion($key);
                $formatted_value = $this->format_value_with_suffixes($value, $key);
                
                $output .= sprintf('<p><strong>%s:</strong> %s</p>', 
                    esc_html($label), 
                    $formatted_value
                );
            }
        }
        $output .= '</div>';
    }
    
    return $output . '</div>';
}

/**
 * Format repeater as grid
 */
private function format_repeater_as_grid($data, $class) {
    $class_attr = $this->build_class_attr('scf-repeater-grid', $class);
    $output = '<div' . $class_attr . '>';
    
    foreach ($data as $item) {
        $output .= '<div class="scf-grid-item">';
        foreach ($item as $key => $value) {
            if (!$this->is_empty_value($value)) {
                $formatted_value = $this->format_value_with_suffixes($value, $key);
                $output .= '<div>' . $formatted_value . '</div>';
            }
        }
        $output .= '</div>';
    }
    
    return $output . '</div>';
}

/**
 * Format repeater as table
 */
private function format_repeater_as_table($data, $class) {
    if (empty($data)) {
        return '';
    }
    
    $class_attr = $this->build_class_attr('scf-repeater-table', $class);
    $output = '<table' . $class_attr . '><thead><tr>';
    
    // Table header
    $first_row = reset($data);
    foreach (array_keys($first_row) as $key) {
        $field_info = $this->get_field_info($key);
        $label = $field_info['label'] ?: $this->clean_field_name_conversion($key);
        $output .= '<th>' . esc_html($label) . '</th>';
    }
    
    $output .= '</tr></thead><tbody>';
    
    // Table body
    foreach ($data as $item) {
        $output .= '<tr>';
        foreach ($item as $key => $value) {
            $formatted_value = $this->format_value_with_suffixes($value, $key);
            $output .= '<td>' . $formatted_value . '</td>';
        }
        $output .= '</tr>';
    }
    
    return $output . '</tbody></table>';
}

/**
 * Format repeater as list
 */
private function format_repeater_as_list($data, $class) {
    $class_attr = $this->build_class_attr('scf-repeater-list', $class);
    $output = '<ul' . $class_attr . '>';
    
    foreach ($data as $item) {
        $output .= '<li>';
        $item_parts = [];
        
        foreach ($item as $key => $value) {
            if (!$this->is_empty_value($value)) {
                $field_info = $this->get_field_info($key);
                $label = $field_info['label'] ?: $this->clean_field_name_conversion($key);
                $formatted_value = $this->format_value_with_suffixes($value, $key);
                
                $item_parts[] = sprintf('<strong>%s:</strong> %s', 
                    esc_html($label), 
                    $formatted_value
                );
            }
        }
        
        $output .= implode(', ', $item_parts) . '</li>';
    }
    
    return $output . '</ul>';
}

/**
 * Format array values (images, galleries, etc.) - MISSING METHOD
 */
private function format_array_value($value, $atts) {
    // Single image
    if (isset($value['url'])) {
        return $this->format_image_auto($value, $atts);
    }
    
    // Gallery or multiple values
    if (is_array($value) && !empty($value)) {
        $format = $atts['format'];
        
        switch ($format) {
            case 'gallery':
                return $this->format_gallery_auto($value, $atts);
            case 'list':
                $items = array_map(function($item) {
                    return '<li>' . $this->sanitize_output($item) . '</li>';
                }, $value);
                return '<ul>' . implode('', $items) . '</ul>';
            default:
                return implode(', ', array_map([$this, 'sanitize_output'], $value));
        }
    }
    
    return '';
}

/**
 * Format single image - ENHANCED VERSION
 */
private function format_image($image, $atts) {
    if ($atts['size'] === 'url') {
        return esc_url($image['url']);
    }
    
    $src = isset($image['sizes'][$atts['size']]) ? $image['sizes'][$atts['size']] : $image['url'];
    $alt = isset($image['alt']) ? $image['alt'] : '';
    $class = !empty($atts['class']) ? ' class="' . esc_attr($atts['class']) . '"' : '';
    
    return sprintf('<img src="%s" alt="%s"%s>', esc_url($src), esc_attr($alt), $class);
}

/**
 * Format gallery - ENHANCED VERSION
 */
private function format_gallery($images, $atts) {
    if (empty($images)) {
        return '';
    }
    
    $size = $atts['size'];
    $class = !empty($atts['class']) ? ' scf-gallery ' . esc_attr($atts['class']) : ' scf-gallery';
    $output = '<div class="' . trim($class) . '">';
    
    foreach ($images as $image) {
        if (isset($image['url'])) {
            $src = isset($image['sizes'][$size]) ? $image['sizes'][$size] : $image['url'];
            $alt = isset($image['alt']) ? $image['alt'] : '';
            $output .= sprintf('<img src="%s" alt="%s">', esc_url($src), esc_attr($alt));
        }
    }
    
    return $output . '</div>';
}

    
    /**
     * Detect field type from value structure
     */
    private function detect_type_from_value($value) {
    if (is_array($value)) {
        // Image field structure
        if (isset($value['url']) && isset($value['sizes'])) {
            return 'image';
        }
        
        // Gallery - array of images
        if (isset($value[0]) && is_array($value[0]) && isset($value[0]['url'])) {
            return 'gallery';
        }
        
        // File field
        if (isset($value['url']) && isset($value['filename'])) {
            return 'file';
        }
        
        return 'array';
    }
    
    if (is_object($value)) {
        if ($value instanceof WP_Post) return 'post_object';
        if ($value instanceof WP_User) return 'user';
        if ($value instanceof WP_Term) return 'taxonomy';
    }
    
    if (is_string($value)) {
        // NOWE: Wykrywanie pól WYSIWYG na podstawie zawartości HTML
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
 * Check if string contains HTML tags (indicates WYSIWYG field)
 */
private function contains_html_tags($string) {
    // Sprawdź czy string zawiera tagi HTML
    if (strip_tags($string) !== $string) {
        return true;
    }
    
    // Sprawdź popularne tagi WYSIWYG
    $html_patterns = [
        '/<p\b[^>]*>/',
        '/<h[1-6]\b[^>]*>/',
        '/<div\b[^>]*>/',
        '/<span\b[^>]*>/',
        '/<strong\b[^>]*>/',
        '/<em\b[^>]*>/',
        '/<ul\b[^>]*>/',
        '/<ol\b[^>]*>/',
        '/<li\b[^>]*>/',
        '/<a\b[^>]*>/',
        '/<img\b[^>]*>/',
        '/<br\s*\/?>/',
    ];
    
    foreach ($html_patterns as $pattern) {
        if (preg_match($pattern, $string)) {
            return true;
        }
    }
    
    return false;
}
    
    /**
 * Format by detected field type - ENHANCED VERSION
 */
private function format_by_detected_type($value, $field_type, $atts) {
    switch ($field_type) {
        case 'image':
            return $this->format_image_auto($value, $atts);
        case 'gallery':
            return $this->format_gallery_auto($value, $atts);
        case 'file':
            return $this->format_file_auto($value, $atts);
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
            return $this->format_number_auto($value, $atts);
        case 'date':
        case 'date_picker':
            return $this->format_date_auto($value);
        case 'wysiwyg':    // NOWE: Obsługa pól WYSIWYG
        case 'textarea':
            return $this->format_wysiwyg_field($value);
        case 'array':
            return $this->format_array_auto($value);
        case 'text':
        default:
            return $this->sanitize_output($value);
    }
}

/**
 *  Format WYSIWYG field content
 */
private function format_wysiwyg_field($value) {
    // Dozwolone tagi HTML
    $allowed_tags = wp_kses_allowed_html('post');
    return wp_kses(wpautop($value), $allowed_tags);
}

    
    /**
     * Format by specified format (backward compatibility)
     */
    private function format_by_specified_format($value, $atts) {
        switch ($atts['format']) {
            case 'html':
                return wpautop($this->sanitize_output($value));
            case 'excerpt':
                return wp_trim_words($this->sanitize_output($value), intval($atts['length']));
            case 'currency':
                return $this->format_currency($value);
            case 'url':
                return esc_url($value);
            case 'email':
                return sprintf('<a href="mailto:%s">%s</a>', esc_attr($value), esc_html($value));
            case 'phone':
                $clean_phone = preg_replace('/[^0-9+]/', '', $value);
                return sprintf('<a href="tel:%s">%s</a>', esc_attr($clean_phone), esc_html($value));
            case 'image':
                return $this->format_image_auto($value, $atts);
            case 'gallery':
                return $this->format_gallery_auto($value, $atts);
            default:
                return $this->sanitize_output($value);
        }
    }
    
    /**
     * Auto format image field
     */
    private function format_image_auto($value, $atts = []) {
    if (!is_array($value) || !isset($value['url'])) {
        return $this->sanitize_output($value);
    }
    
    $size = isset($atts['size']) ? $atts['size'] : 'medium';
    
    if ($size === 'url') {
        return esc_url($value['url']);
    }
    
    $src = $value['sizes'][$size] ?? $value['url'];
    $alt = $value['alt'] ?? $value['title'] ?? '';
    $class_attr = !empty($atts['class']) ? ' class="' . esc_attr($atts['class']) . '"' : '';
    
    return sprintf('<img src="%s" alt="%s"%s>', esc_url($src), esc_attr($alt), $class_attr);
    }
    
    /**
     * Auto format gallery field
     */
    private function format_gallery_auto($value, $atts = []) {
    if (!is_array($value) || empty($value)) {
        return '';
    }
    
    $size = isset($atts['size']) ? $atts['size'] : 'medium';
    $class = !empty($atts['class']) ? ' scf-gallery ' . esc_attr($atts['class']) : ' scf-gallery';
    $output = '<div class="' . trim($class) . '">';
    
    foreach ($value as $image) {
        if (is_array($image) && isset($image['url'])) {
            $src = $image['sizes'][$size] ?? $image['url'];
            $alt = $image['alt'] ?? $image['title'] ?? '';
            $output .= sprintf('<img src="%s" alt="%s">', esc_url($src), esc_attr($alt));
        }
    }
    
    return $output . '</div>';
}
    
    /**
     * Auto format file field
     */
    private function format_file_auto($value, $atts) {
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
    private function format_number_auto($value, $atts) {
        if (!is_numeric($value)) {
            return $this->sanitize_output($value);
        }
        
        $field_info = $this->get_field_info($atts['field']);
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
     * Format currency value
     */
    private function format_currency($value) {
        if (!is_numeric($value)) {
            return $this->sanitize_output($value);
        }
        
        return number_format(floatval($value), 2, ',', ' ') . ' PLN';
    }
    
    /**
     * Get SCF field with error handling
     */
    private function get_scf_field($field_name, $post_id = null) {
        if (function_exists('get_field')) {
            return get_field($field_name, $post_id);
        }
        
        return get_post_meta($post_id ?: get_the_ID(), $field_name, true);
    }
    
    /**
     * Get field info with complete metadata
     */
    public function get_field_info($field_name, $context = 'post') {
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
     * Format value with SCF suffixes - ULEPSZONA WERSJA
     */
    private function format_value_with_suffixes($value, $field_name, $context = 'post', $atts = []) {
        $field_info = $this->get_field_info($field_name, $context);
        
        // Jeśli mamy atts, użyj hybrydowego formatowania
        if (!empty($atts)) {
            $field_type = $this->determine_field_type($field_name, $atts['type'] ?? '', $value);
            $formatted_value = $this->format_by_detected_type($value, $field_type, $atts);
        } else {
            // Fallback do prostego formatowania
            if (isset($field_info['type']) && $field_info['type'] === 'relationship') {
                $formatted_value = $this->format_relationship_field($value);
            } else {
                $formatted_value = $this->sanitize_output($value);
            }
        }
        
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
     * Sanitize field attributes - ROZSZERZONA WERSJA
     */
    private function sanitize_field_atts($atts) {
        return shortcode_atts([
            'field' => '',
            'post_id' => null,
            'format' => 'auto',
            'type' => '',
            'fallback' => '',
            'wrapper' => '',
            'class' => '',
            'size' => 'medium',
            'cache' => 'true',
            'length' => 20,
            'show_suffix' => 'true'
        ], $atts, 'scf_field');
    }
    
    /**
     * Sanitize group attributes
     */
    private function sanitize_group_atts($atts) {
        return shortcode_atts([
            'field' => '',
            'subfield' => '',
            'format' => 'raw',
            'post_id' => null,
            'fallback' => '',
            'class' => ''
        ], $atts, 'scf_group');
    }
    
    /**
     * Sanitize repeater attributes
     */
    private function sanitize_repeater_atts($atts) {
        return shortcode_atts([
            'field' => '',
            'subfield' => '',
            'format' => 'list',
            'post_id' => null,
            'limit' => 0,
            'separator' => ', ',
            'fallback' => '',
            'class' => ''
        ], $atts, 'scf_repeater');
    }
    
    // Pozostałe metody formatowania (format_group_as_card, format_repeater_output, itp.)
    // zostały pominięte dla zwięzłości - pozostają bez zmian z poprzedniej wersji
    
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
    
    private function apply_wrapper($content, $wrapper, $class) {
        if (empty($wrapper)) return $content;
        
        $class_attr = !empty($class) ? ' class="' . esc_attr($class) . '"' : '';
        return sprintf('<%s%s>%s</%s>', $wrapper, $class_attr, $content, $wrapper);
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
}
