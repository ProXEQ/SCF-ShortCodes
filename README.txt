=== SCF-ShortCodes ===
Contributors: PixelMobs
Tags: shortcodes, custom fields, scf, acf, flexible content
Requires at least: 5.0
Tested up to: 6.4
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Universal shortcode system for Secure Custom Fields with advanced caching and error handling.

== Description ==

SCF-ShortCodes provides a comprehensive shortcode system for displaying Secure Custom Fields data with advanced features:

* **Universal Field Support** - Works with all SCF field types
* **Automatic Label Detection** - Pulls field labels directly from SCF
* **Suffix/Prefix Support** - Automatically includes field suffixes and prefixes
* **Advanced Caching** - Intelligent caching system for optimal performance
* **Error Handling** - Robust error handling with logging
* **Flexible Content** - Full support for flexible content layouts
* **Responsive Design** - Mobile-friendly output styling

== Installation ==

1. Upload the plugin files to `/wp-content/plugins/scf-shortcodes/`
2. Activate the plugin through the 'Plugins' screen in WordPress
3. Go to Settings > SCF-ShortCodes to configure the plugin

== Frequently Asked Questions ==

= Does this work with ACF? =
Yes, SCF-ShortCodes is compatible with both Secure Custom Fields and Advanced Custom Fields.

= Can I customize the output styling? =
Yes, the plugin includes CSS classes for all output elements that you can style with custom CSS.

== Shortcodes ==

= Basic Field =
`[scf_field field="field_name"]`

= Flexible Content =
`[scf_flexible field="content" layout="hero" format="table"]`

= Group Fields =
`[scf_group field="contact_info" format="card"]`

= Repeater Fields =
`[scf_repeater field="team" format="cards"]`

== Changelog ==

= 1.0.0 =
* Initial release
* Full shortcode system implementation
* Advanced caching system
* Error handling and logging
* Admin interface
