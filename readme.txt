=== Pulseem ===
Contributors: shaharpulseem, ofer1331
Tags: woocommerce, email marketing, marketing automation, abandoned cart, checkout forms
Requires at least: 6.6
Tested up to: 6.9
Requires PHP: 7.4
Stable tag: 1.4.2
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

Seamless integration of Pulseem marketing automation platform with WooCommerce for advanced email campaigns and customer management.

== Description ==

Transform your WooCommerce store into a powerful marketing machine with the Pulseem Integration plugin. Connect your store seamlessly to the Pulseem platform and unlock advanced marketing automation capabilities.

= Key Features =

* **Customer Synchronization** - Automatically sync customer and order data between WooCommerce and Pulseem
* **User Group Management** - Organize and manage customer groups directly from your WordPress admin dashboard
* **Abandoned Cart Recovery** - Track abandoned carts and send automated email reminders to recover lost sales
* **Purchase Tracking** - Monitor customer behavior and purchase patterns for better targeting
* **Multi-Form Support** - Works with WooCommerce, Elementor Forms, Contact Form 7, and Bricks Builder
* **Real-time Sync** - Instant data synchronization ensures your marketing campaigns are always up-to-date

= Marketing Automation =

* Send targeted email campaigns based on customer behavior
* Create automated workflows for different customer segments
* Track campaign performance and customer engagement
* Personalize messages based on purchase history

= E-commerce Integration =

* Native WooCommerce integration with zero configuration needed
* Support for variable products and product categories
* Order status tracking and customer lifecycle management
* Secure API communication with your Pulseem account

= Developer Friendly =

* Clean, well-documented code following WordPress standards
* Extensive hooks and filters for customization
* Comprehensive logging for debugging and monitoring
* REST API endpoints for advanced integrations

**Note:** This plugin requires an active Pulseem account and API key. [Sign up for Pulseem](https://www.pulseem.co.il/) to get started.

== Installation ==

= Automatic Installation =
1. Go to your WordPress admin area and navigate to Plugins > Add New
2. Search for "Pulseem"
3. Click "Install Now" and then "Activate"
4. Go to Pulseem Settings to configure your API connection

= Manual Installation =
1. Download the plugin zip file
2. Upload the plugin folder to `/wp-content/plugins/`
3. Activate the plugin through the 'Plugins' menu in WordPress
4. Navigate to Pulseem Settings and enter your API key

= Configuration =
1. Obtain your API key from your Pulseem dashboard
2. Go to WordPress Admin > Pulseem Settings
3. Enter your API key in the API Connection tab
4. Configure your desired features (customer sync, abandoned cart tracking, etc.)
5. Set up your customer groups and automation rules

== Frequently Asked Questions ==

= Do I need a Pulseem account to use this plugin? =

Yes, you need an active Pulseem account and a valid API key. The plugin acts as a bridge between your WooCommerce store and the Pulseem marketing platform.

= Which form builders are supported? =

The plugin supports:
* WooCommerce checkout and registration forms (native)
* Elementor Forms
* Contact Form 7
* Bricks Builder checkout and registration forms

= What WooCommerce versions are compatible? =

The plugin supports WooCommerce version 9.3 and above. We regularly test with the latest WooCommerce releases to ensure compatibility.

= Is customer data secure? =

Yes, all data transmission uses secure HTTPS connections and follows WordPress security best practices. Your API key is stored securely and all customer data is handled according to privacy regulations.

= Can I customize which data is synchronized? =

Yes, the plugin provides extensive settings to control exactly which customer data, order information, and events are synchronized with Pulseem.

= Does this work with other plugins? =

The plugin is designed to work alongside other WordPress and WooCommerce plugins. If you encounter any conflicts, please contact our support team.

== Screenshots ==

1. Main settings dashboard with API configuration and feature toggles
2. Customer synchronization settings with group management
3. Abandoned cart tracking configuration
4. Real-time synchronization status and logs

== Changelog ==

= 1.4.2 =
* Fix: Removed duplicate API data from caller logs (user_register, purchase, abandoned_cart, elementor, cf7)
* Fix: Added full API logging to page tracking (api_url, method, http_code, request_body, response_body)
* Fix: Accurate DB table size using ANALYZE TABLE before information_schema query
* New: RTL/LTR CSS support for checkout agreement checkbox
* Improvement: Added missing WordPress.org plugin headers (Plugin URI, Domain Path, Requires at least, Tested up to, Requires PHP)

= 1.4.1 =
* Improvement: Code cleanup and removed outdated internal documentation files
* Improvement: Added .gitignore for cleaner repository management
* Maintenance: Version bump and repository preparation

= 1.4.0 =
* New: Professional logging system with request ID correlation, statistics dashboard, and configurable log levels
* New: Log export in CSV and JSON formats
* New: Bulk log management with select, delete, and auto-cleanup cron
* New: Log detail modal with full request context
* New: Column sorting and per-page selector on logs page
* New: Log retention settings (7/14/30/60/90 days or never)
* Improvement: Unified all logging to PulseemLogger — removed all wc_get_logger() and error_log() calls
* Improvement: Buffered logging for better performance (batch DB inserts on shutdown)
* Improvement: Added logging to settings changes, page tracking, cron events, and activation/deactivation
* Improvement: Database schema auto-migration for seamless upgrades from older versions

= 1.3.6 =
* Security: Added nonce verification on all AJAX handlers
* Security: Added ABSPATH guards on all PHP files
* Security: Replaced serialize/unserialize with JSON encoding
* Security: Added REST route permission callback
* Security: Added capability checks on admin pages
* Improvement: Moved inline scripts and styles to enqueued files
* Improvement: Prefixed all global functions and classes
* Improvement: Fixed hardcoded admin-ajax.php URL
* Improvement: Added uninstall.php for clean data removal
* Improvement: Added deactivation hook to clear cron events
* Improvement: Added GDPR/privacy data exporter and eraser
* Improvement: Added external services documentation
* Improvement: Removed deprecated serialize usage for cart data

= 1.3.3 =
* Enhanced security with improved input validation and sanitization
* Fixed compatibility issues with WordPress 6.8
* Improved database query performance and caching
* Updated admin interface with better user experience
* Fixed abandoned cart tracking edge cases
* Code cleanup and adherence to WordPress coding standards
* Better error handling and logging throughout the plugin

= 1.3.0 =
* Added comprehensive Bricks Builder support for checkout and registration forms
* Enhanced user group management with bulk operations
* Improved API communication reliability with retry mechanisms
* Added real-time synchronization status indicators
* Performance optimizations for large customer databases

= 1.2.0 =
* Integrated Contact Form 7 for lead capture and customer acquisition
* Enhanced Elementor Forms support with custom field mapping
* Added product synchronization capabilities
* Performance optimizations and memory usage improvements
* Improved admin dashboard with better navigation

= 1.1.0 =
* Introduced abandoned cart tracking and recovery features
* Enhanced WooCommerce integration with better order processing
* Added comprehensive logging and debugging capabilities
* Improved admin dashboard with tabbed interface
* Better error handling and user feedback

= 1.0.0 =
* Initial stable release with core functionality
* WooCommerce checkout and registration form support
* Basic Elementor Forms and Contact Form 7 integration
* Customer and order synchronization
* Admin dashboard for configuration and management

== Upgrade Notice ==

= 1.4.1 =
Code cleanup and repository maintenance. No functional changes.

= 1.4.0 =
New professional logging system with statistics, export, and auto-cleanup. All logging unified into PulseemLogger. Database schema upgrades automatically. Clear your site cache after updating.

= 1.3.6 =
Important security update with nonce verification, ABSPATH guards, and REST API permission callbacks. All users should update immediately.

= 1.3.4 =
Added acceptance field support for Elementor forms integration. Users can now map acceptance/checkbox fields to control opt-in requirements automatically. Improved opt-in handling for both email and SMS channels.

= 1.3.3 =
This update includes important security improvements and WordPress 6.8 compatibility. All users should update immediately for the best experience and security.

= 1.3.0 =
Major feature update with Bricks Builder support and enhanced group management. Backup your site before updating.

== External services ==

This plugin connects to the following external services:

= Pulseem Marketing Platform REST API =

* **Service URL**: https://ui-api.pulseem.com
* **Purpose**: Customer synchronization, group management, abandoned cart recovery, purchase tracking, and product synchronization.
* **Data sent**: Customer email addresses, names, phone numbers, order data, product data, and page view data.
* **When data is sent**: On user registration, checkout, order completion, page views (if enabled), and product updates.
* **Terms of Use**: [https://site.pulseem.co.il/terms-of-engagement/](https://site.pulseem.co.il/terms-of-engagement/)
* **Privacy Policy**: [https://site.pulseem.co.il/privacy-policy/](https://site.pulseem.co.il/privacy-policy/)

= Pulseem SOAP API =

* **Service URL**: http://www.pulseem.com/Pulseem/PulseemServices.asmx
* **Purpose**: Account authentication and verification.
* **Data sent**: API credentials (API key, username, password).
* **When data is sent**: During plugin setup and API authentication.
* **Terms of Use**: [https://site.pulseem.co.il/terms-of-engagement/](https://site.pulseem.co.il/terms-of-engagement/)
* **Privacy Policy**: [https://site.pulseem.co.il/privacy-policy/](https://site.pulseem.co.il/privacy-policy/)

= Pulseem Tracking Script =

* **Service URL**: https://webscript.prd.services.pulseem.com/main.js
* **Purpose**: Frontend tracking and analytics for marketing automation.
* **Data sent**: Page views, user behavior data, purchase events.
* **When data is sent**: On every page load when the plugin is active.
* **Terms of Use**: [https://site.pulseem.co.il/terms-of-engagement/](https://site.pulseem.co.il/terms-of-engagement/)
* **Privacy Policy**: [https://site.pulseem.co.il/privacy-policy/](https://site.pulseem.co.il/privacy-policy/)

== Third-party libraries ==

This plugin includes the following third-party libraries (minified versions):

* **Tailwind CSS** (tailwindcss.min.js) - Utility-first CSS framework. Source: [https://github.com/tailwindlabs/tailwindcss](https://github.com/tailwindlabs/tailwindcss) - MIT License
* **Alpine.js** (alpinejs.min.js) - Lightweight JavaScript framework. Source: [https://github.com/alpinejs/alpine](https://github.com/alpinejs/alpine) - MIT License
* **canvas-confetti** (confetti.browser.min.js) - Confetti animation library. Source: [https://github.com/catdad/canvas-confetti](https://github.com/catdad/canvas-confetti) - ISC License
* **Select2** (select2/) - jQuery replacement for select boxes. Source: [https://github.com/select2/select2](https://github.com/select2/select2) - MIT License

== Support ==

For technical support, feature requests, or general questions:

* **Documentation**: Visit our comprehensive documentation portal
* **Support Forum**: Get help from our community and support team
* **Direct Contact**: [Contact our support team](https://site.pulseem.co.il/%d7%a6%d7%95%d7%a8-%d7%a7%d7%a9%d7%a8/)
* **Company Website**: [Pulseem.co.il](https://www.pulseem.co.il/)

== Privacy ==

This plugin connects your WooCommerce store to the Pulseem marketing platform. When activated and configured:

* Customer email addresses and basic information are synchronized with Pulseem
* Order data and purchase history may be transmitted for marketing automation
* All data transmission occurs over secure HTTPS connections
* No data is shared with third parties beyond the Pulseem platform
* You maintain full control over what data is synchronized

Please review [Pulseem's privacy policy](https://site.pulseem.co.il/privacy-policy/) for information about how your data is handled on their platform.
