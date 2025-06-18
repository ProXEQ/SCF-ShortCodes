# SCF-ShortCodes Plugin Documentation

![Version](https://img.shields.io/badgeds.io/s.io/badge/license shortcode system for Secure Custom Fields with advanced features, intelligent type detection, and performance optimization.

## Table of Contents

- [Features](#features)
- [Installation](#installation)
- [Quick Start](#quick-start)
- [Available Shortcodes](#available-shortcodes)
- [Field Type Detection](#field-type-detection)
- [Advanced Usage](#advanced-usage)
- [Performance & Caching](#performance--caching)
- [Styling & Customization](#styling--customization)
- [Troubleshooting](#troubleshooting)
- [API Reference](#api-reference)
- [Contributing](#contributing)

## Features

### 🚀 Core Features
- **Universal Field Support** - Works with all SCF field types
- **Intelligent Type Detection** - Automatically detects field types and formats accordingly
- **Hybrid Approach** - Automatic detection with manual override capability
- **Advanced Caching** - Multi-level caching system for optimal performance
- **Error Handling** - Robust error handling with graceful degradation

### 🎨 Display Features
- **Multiple Output Formats** - List, table, cards, grid layouts
- **Automatic Label Detection** - Pulls field labels directly from SCF
- **Suffix/Prefix Support** - Automatically includes field suffixes and prefixes
- **Responsive Design** - Mobile-friendly output styling
- **Custom CSS Classes** - Full control over styling

### 🔧 Technical Features
- **Object Cache Integration** - WordPress object cache support
- **Database Cache Option** - Optional database-based caching
- **Memory Optimization** - Efficient memory usage and cleanup
- **Debug Mode** - Comprehensive debugging and logging
- **Admin Interface** - Easy-to-use settings panel

## Installation

### Method 1: Manual Installation

1. Download the plugin files
2. Upload to `/wp-content/plugins/scf-shortcodes/`
3. Activate the plugin through the 'Plugins' screen in WordPress
4. Go to Settings > SCF-ShortCodes to configure

### Method 2: WordPress Admin

1. Go to Plugins > Add New
2. Upload the plugin zip file
3. Activate the plugin
4. Configure settings

### Requirements

- WordPress 5.0 or higher
- PHP 7.4 or higher
- Secure Custom Fields plugin

## Quick Start

### Basic Usage

Display a simple text field:
```
[scf_field field="product_name"]
```

Display an image field (automatically formatted):
```
[scf_field field="product_image"]
```

Display flexible content as a table:
```
[scf_flexible field="specifications" format="table"]
```

### With Fallback

```
[scf_field field="optional_field" fallback="No data available"]
```

### With Custom Styling

```
[scf_field field="title" wrapper="h2" class="main-title"]
```

## Available Shortcodes

### 1. `[scf_field]` - Basic Field Display

Display any SCF field with intelligent formatting.

#### Parameters

| Parameter | Type | Default | Description |
|-----------|------|---------|-------------|
| `field` | string | *required* | SCF field name |
| `post_id` | int | current post | Post ID to get field from |
| `format` | string | `auto` | Output format (auto, raw, html, excerpt, currency, url, email, phone) |
| `type` | string | auto-detect | Force specific field type |
| `fallback` | string | empty | Text to show when field is empty |
| `wrapper` | string | none | HTML wrapper element (h1, h2, div, span, etc.) |
| `class` | string | none | Additional CSS classes |
| `size` | string | `medium` | Image size (thumbnail, medium, large, full, url) |
| `length` | int | 20 | Text length for excerpt format |
| `show_suffix` | bool | `true` | Include field suffixes/prefixes |
| `cache` | bool | `true` | Enable caching |

#### Examples

**Basic text field:**
```
[scf_field field="product_name"]
```

**Image with specific size:**
```
[scf_field field="hero_image" size="large"]
```

**Currency formatting:**
```
[scf_field field="price" format="currency"]
```

**Email as clickable link:**
```
[scf_field field="contact_email" format="email"]
```

**Text excerpt:**
```
[scf_field field="description" format="excerpt" length="30"]
```

**With wrapper and styling:**
```
[scf_field field="page_title" wrapper="h1" class="hero-title"]
```

**Force specific type:**
```
[scf_field field="mixed_content" type="wysiwyg"]
```

### 2. `[scf_flexible]` - Flexible Content Display

Display flexible content fields with various layouts.

#### Parameters

| Parameter | Type | Default | Description |
|-----------|------|---------|-------------|
| `field` | string | *required* | Flexible content field name |
| `layout` | string | first available | Specific layout name |
| `format` | string | `list` | Output format (list, table) |
| `post_id` | int | current post | Post ID to get field from |
| `limit` | int | 0 (no limit) | Maximum number of items |
| `fallback` | string | empty | Text when no data |
| `class` | string | none | Additional CSS classes |
| `cache` | bool | `true` | Enable caching |

#### Examples

**All layouts as list:**
```
[scf_flexible field="page_sections" format="list"]
```

**Specific layout as table:**
```
[scf_flexible field="specifications" layout="technical_specs" format="table"]
```

**Limited results:**
```
[scf_flexible field="testimonials" layout="review" limit="3"]
```

**With custom styling:**
```
[scf_flexible field="features" format="list" class="feature-list"]
```

### 3. `[scf_flexible_field]` - Single Flexible Field

Extract a specific field from flexible content.

#### Parameters

| Parameter | Type | Default | Description |
|-----------|------|---------|-------------|
| `field` | string | *required* | Flexible content field name |
| `subfield` | string | *required* | Sub-field name within layout |
| `layout` | string | any | Specific layout name |
| `post_id` | int | current post | Post ID to get field from |
| `index` | int | 0 | Item index (for multiple items) |
| `fallback` | string | empty | Text when no data |

#### Examples

**Get specific field from layout:**
```
[scf_flexible_field field="specifications" layout="dimensions" subfield="height"]
```

**Get from second item:**
```
[scf_flexible_field field="team_members" layout="member" subfield="name" index="1"]
```

**With fallback:**
```
[scf_flexible_field field="contact_info" layout="phone" subfield="number" fallback="No phone available"]
```

### 4. `[scf_group]` - Group Field Display

Display group fields as cards or individual sub-fields.

#### Parameters

| Parameter | Type | Default | Description |
|-----------|------|---------|-------------|
| `field` | string | *required* | Group field name |
| `subfield` | string | none | Specific sub-field |
| `format` | string | `raw` | Output format (raw, card) |
| `post_id` | int | current post | Post ID to get field from |
| `fallback` | string | empty | Text when no data |
| `class` | string | none | Additional CSS classes |

#### Examples

**Single sub-field:**
```
[scf_group field="contact_details" subfield="phone"]
```

**Entire group as card:**
```
[scf_group field="contact_details" format="card"]
```

**With custom styling:**
```
[scf_group field="company_info" format="card" class="info-card"]
```

### 5. `[scf_repeater]` - Repeater Field Display

Display repeater fields in various formats.

#### Parameters

| Parameter | Type | Default | Description |
|-----------|------|---------|-------------|
| `field` | string | *required* | Repeater field name |
| `subfield` | string | none | Specific sub-field |
| `format` | string | `list` | Output format (list, cards, grid, table) |
| `post_id` | int | current post | Post ID to get field from |
| `limit` | int | 0 (no limit) | Maximum number of items |
| `separator` | string | `, ` | Separator for sub-field values |
| `fallback` | string | empty | Text when no data |
| `class` | string | none | Additional CSS classes |

#### Examples

**All items as cards:**
```
[scf_repeater field="team_members" format="cards"]
```

**Specific sub-field values:**
```
[scf_repeater field="team_members" subfield="name" separator=" | "]
```

**As data table:**
```
[scf_repeater field="products" format="table" limit="10"]
```

**Grid layout:**
```
[scf_repeater field="portfolio" format="grid" class="portfolio-grid"]
```

### 6. `[scf_suffix]` - Field Suffixes/Prefixes

Get field suffixes or prefixes defined in SCF.

#### Parameters

| Parameter | Type | Default | Description |
|-----------|------|---------|-------------|
| `field` | string | *required* | Field name |
| `type` | string | `suffix` | Type to get (suffix, prefix) |
| `post_id` | int | current post | Post ID |

#### Examples

**Get field suffix:**
```
[scf_suffix field="height" type="suffix"]
```

**Get field prefix:**
```
[scf_suffix field="price" type="prefix"]
```

**Use in text:**
```
The height is [scf_field field="height" show_suffix="false"] [scf_suffix field="height"]
```

## Field Type Detection

The plugin automatically detects field types and formats them appropriately:

### Automatic Detection

| Field Type | Detection Method | Auto Format |
|------------|------------------|-------------|
| **Image** | Array with `url` and `sizes` | `` tag with alt text |
| **Gallery** | Array of image objects | Grid of images |
| **File** | Array with `url` and `filename` | Download link with file size |
| **WYSIWYG** | Contains HTML tags | Formatted HTML output |
| **Email** | Valid email format | `mailto:` link |
| **URL** | Valid URL format | Clickable link |
| **Phone** | Phone number pattern | `tel:` link |
| **Post Object** | WP_Post instance | Post title |
| **User** | WP_User instance | Display name |
| **Taxonomy** | WP_Term instance | Term name |
| **Number** | Numeric value | Formatted number |
| **Date** | Date string | Localized date |

### Manual Override

Force specific type detection:

```
[scf_field field="mixed_content" type="wysiwyg"]
[scf_field field="image_url" type="url"]
[scf_field field="contact" type="email"]
```

### Format Override

Override automatic formatting:

```
[scf_field field="image" format="url"]   -->
[scf_field field="content" format="excerpt" length="50"]
[scf_field field="price" format="raw"]  
```

## Advanced Usage

### Conditional Display

```
[scf_field field="special_offer" fallback="No current offers"]
```

### Nested Shortcodes

```
[scf_field field="title" wrapper="h2" class="section-title"]
[scf_flexible field="content" layout="text_block" format="list"]
```

### Complex Layouts

**Product specification table:**
```

    [scf_field field="product_name" wrapper="h2"]
    [scf_field field="product_image" size="large"]
    [scf_flexible field="specifications" layout="technical" format="table"]
    [scf_field field="price" format="currency" wrapper="div" class="price"]

```

**Team member cards:**
```

    [scf_repeater field="team_members" format="cards" limit="6" class="team-grid"]

```

### Dynamic Content

**Blog post with flexible sections:**
```
[scf_field field="hero_image" size="full" class="hero-image"]
[scf_field field="post_title" wrapper="h1" class="post-title"]
[scf_flexible field="post_content" format="list"]
[scf_group field="author_info" format="card" class="author-bio"]
```

## Performance & Caching

### Caching Levels

1. **Object Cache** - WordPress object cache integration
2. **Database Cache** - Optional database storage for persistent cache
3. **Field Info Cache** - Caches field metadata
4. **Layout Cache** - Caches flexible content layouts

### Cache Configuration

**Enable/disable caching per shortcode:**
```
[scf_field field="dynamic_content" cache="false"]
[scf_flexible field="static_content" cache="true"]
```

**Global cache settings:**
- Cache Duration: 3600 seconds (1 hour) default
- Auto-clear on post save: Enabled
- Debug mode: Shows cache hits/misses

### Performance Tips

1. **Use caching** for static content
2. **Limit repeater results** with `limit` parameter
3. **Specify field types** to avoid detection overhead
4. **Use fallbacks** to prevent empty queries

```
[scf_repeater field="large_dataset" limit="10" cache="true"]
[scf_field field="known_image" type="image" cache="true"]
```

## Styling & Customization

### Default CSS Classes

The plugin provides default CSS classes for all output formats:

```css
/* Flexible Content */
.scf-flexible-list { /* List format */ }
.scf-flexible-table { /* Table format */ }

/* Repeater */
.scf-repeater-cards { /* Cards format */ }
.scf-repeater-grid { /* Grid format */ }
.scf-repeater-table { /* Table format */ }
.scf-repeater-list { /* List format */ }

/* Group */
.scf-group-card { /* Card format */ }

/* Images */
.scf-gallery { /* Gallery container */ }
.scf-auto-image { /* Auto-formatted images */ }

/* Files */
.scf-file-link { /* File download links */ }
```

### Custom Styling

**Add custom classes:**
```
[scf_field field="title" class="custom-title"]
[scf_repeater field="items" format="cards" class="my-custom-grid"]
```

**Override default styles:**
```css
.my-custom-grid .scf-card {
    background: #f8f9fa;
    border-radius: 10px;
    padding: 20px;
}
```

### Responsive Design

All default styles are mobile-friendly:

```css
@media (max-width: 768px) {
    .scf-repeater-cards {
        grid-template-columns: 1fr;
    }
    
    .scf-flexible-table {
        font-size: 14px;
    }
}
```

## Troubleshooting

### Common Issues

**1. Empty Output**
```

[scf_field field="field_name" fallback="Field is empty or doesn't exist"]
```

**2. HTML Not Rendering**
```

[scf_field field="content" type="wysiwyg"]
[scf_field field="content" format="html"]
```

**3. Images Not Displaying**
```

[scf_field field="image" type="image" size="medium"]
[scf_field field="image" format="url"]  
```

**4. Cache Issues**
```

[scf_field field="dynamic_content" cache="false"]
```

### Debug Mode

Enable debug mode in plugin settings to see:
- Cache hits/misses
- Field type detection
- Error messages
- Performance metrics

### Error Messages

When debug mode is enabled, errors appear as HTML comments:
```html


```

### Log Files

Check WordPress debug log for detailed error information:
```
[SCF-ShortCodes] Error: Failed to get field info for 'field_name'
[SCF-ShortCodes] Cache cleared for post ID: 123
```

## API Reference

### Filters

**Customize field labels:**
```php
add_filter('scf_shortcode_field_labels', function($labels) {
    $labels['custom_field'] = 'My Custom Label';
    return $labels;
});
```

**Modify cache duration:**
```php
add_filter('scf_shortcode_cache_duration', function($duration) {
    return 7200; // 2 hours
});
```

### Actions

**After cache clear:**
```php
add_action('scf_shortcodes_cache_cleared', function() {
    // Custom logic after cache clear
});
```

**After post cache clear:**
```php
add_action('scf_shortcodes_post_cache_cleared', function($post_id) {
    // Custom logic for specific post
});
```

### PHP API

**Get field info programmatically:**
```php
$field_handler = new SCF_Field_Handler($cache_manager, $error_handler);
$field_info = $field_handler->get_field_info('field_name');
```

**Clear cache programmatically:**
```php
$cache_manager = new SCF_Cache_Manager();
$cache_manager->clear_all_cache();
$cache_manager->clear_post_cache($post_id);
```

## Contributing

### Development Setup

1. Clone the repository
2. Install development dependencies
3. Set up local WordPress environment
4. Enable WP_DEBUG mode

### Code Standards

- Follow WordPress Coding Standards
- Use PHP 7.4+ features
- Include PHPDoc comments
- Write unit tests for new features

### Submitting Changes

1. Fork the repository
2. Create feature branch
3. Make changes with tests
4. Submit pull request

### Reporting Issues

Use GitHub issues with:
- WordPress version
- PHP version
- Plugin version
- Steps to reproduce
- Expected vs actual behavior

## License

This plugin is licensed under the GPL v2 or later.

## Support

- **Documentation**: This README
- **Issues**: GitHub Issues
- **Support Forum**: WordPress.org support forum

---

**Made with ❤️ for the WordPress community**