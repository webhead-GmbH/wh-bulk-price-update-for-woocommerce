=== Bulk Price Update for WooCommerce ===
Contributors: webheadgmbh, mohammad425
Tags: woocommerce, bulk price update, price adjustment, product management, scheduled rules
Requires at least: 6.0
Tested up to: 6.9
Requires PHP: 7.4
Stable tag: 2.0.0
License: GPLv3
License URI: https://www.gnu.org/licenses/gpl-3.0.html

Easily update WooCommerce product prices in bulk and automate recurring price changes with Scheduled Rules.

== Description ==

**Bulk Price Update for WooCommerce** is a powerful yet easy-to-use plugin that allows you to manage and update product prices in your WooCommerce store efficiently. Alongside manual bulk updates, version 2 introduces **Scheduled Rules** so you can automate recurring, rule-based price adjustments without manual repetition.

### Key Features:
- **🔧 Flexible Price Adjustments:** Increase, decrease, multiply, divide, or set prices directly using either a percentage or a fixed amount.
- **🎯 Targeted Updates:** Apply price changes to all products, specific categories, tags, or even individual products.
- **⚙️ Selective Price Modification:** Choose to modify regular prices, sale prices, or both.
- **🔍 Advanced Filtering:** Exclude specific products from price updates.
- **🏷️ Attribute-Based Updates:** Modify prices based on product attributes like color, size, etc.
- **👀 Live Preview:** View a preview of the price changes before applying them to your store.
- **⏰ Scheduled Rules:** Create automated rules with schedules (hourly/daily/custom) and rule types like Margin Check (COG + Margin), Maximum Discount Percentage, and Price Adjustment.
- **📋 Execution Logs:** Review rule run history and product-level change logs.
- **🚀 Batch Processing:** Adjust settings like batch size and preview count for optimized performance.
- **💯 No Pro Version:** All features are fully accessible for free; no pro version or additional charges.

This plugin is designed to save you time and effort by providing a comprehensive set of tools to manage your product prices efficiently.

👉 Rate us on [WordPress](https://wordpress.org/support/plugin/wh-bulk-price-update-for-woocommerce/reviews/#new-post)

💻 Contribute or report issues on [GitHub](https://github.com/webhead-GmbH/wh-bulk-price-update-for-woocommerce)

== USE OF 3RD PARTY SERVICES ==

This plugin uses third-party services to enhance functionality. It retrieves the latest blog posts from [webhead.at](https://webhead.at) for the "Latest Posts" tab and fetches a list of other plugins from [plugins.webhead.at](https://plugins.webhead.at) for the "Other Plugins" tab. Both requests cache data for 24 hours, do not store or transmit user data, and are only triggered when accessing the respective tabs. For more details, see the [Webhead Privacy Policy](https://webhead.at/en/privacy/).

== Installation ==

= Automatic Installation =

Automatic installation is the easiest option -- WordPress will handle the file transfer, and you will not need to leave your web browser. To do an automatic install, log in to your WordPress dashboard, navigate to the Plugins menu, and click "Add New".

In the search field type "WH Bulk Price Update For WooCommerce" then click "Search Plugins". Once you have found us, you can view details about it such as the point release, rating, and description. Most importantly, you can install it by clicking "Install Now" and WordPress will take it from there.

= Manual Installation =

1. Upload the `wh-bulk-price-update-for-woocommerce` folder to the `/wp-content/plugins/` directory.
2. Activate the plugin through the 'Plugins' menu in WordPress.
3. Navigate to `Products > Bulk Price Update` to start updating prices in bulk.

== Frequently Asked Questions ==

= How can I preview price changes before applying them? =
You can preview the price changes by clicking on the "Preview Prices" button. This will show you the list of products and the expected changes before you finalize them.

= Can I exclude certain products from the price update? =
Yes, you can exclude specific products.

= What is Scheduled Rules and where can I find it? =
Scheduled Rules is the automation feature for recurring price changes. You can find it in the plugin page under the "Scheduled Rules" tab.

= Which rule types are available in Scheduled Rules? =
You can create rules for Margin Check (COG + Margin), Maximum Discount Percentage, and Price Adjustment.

= Can I preview a scheduled rule before saving it? =
Yes. In the Add/Edit Rule modal, go to the last step and use "Preview prices" to see the expected changes before saving.

= Can I run a scheduled rule immediately instead of waiting for schedule time? =
Yes. Use the "Run Now" action in the Scheduled Rules list.

= Can Scheduled Rules use a custom Cost of Goods field? =
Yes. In Settings, set the "Cost of goods custom field" meta key if your store uses another plugin or custom COG field.

= Where can I see what a scheduled rule changed? =
Open "Execution Logs" from the Scheduled Rules actions to review rule executions and affected products.

= Is there a limit on the number of products that can be updated at once? =
There is no hard limit, but for performance reasons, the plugin allows you to adjust the batch size and preview count in the settings.

= Can I update only sale prices or regular prices? =
Yes, you can update only regular prices, sale prices, or both.

= Does this plugin have a pro version? =
No, all features of this plugin are fully accessible for free. There is no pro version or any hidden costs.

== Screenshots ==

1. Main Dashboard
2. Specific Products And Categories Options
3. Scheduled Rules
4. Settings

== Changelog ==

= 2.0.0 =
* Added Scheduled Rules feature for automated rule-based price updates.
* Tested with WordPress 6.9 and WooCommerce 10.5.

= 1.0.7 =
* Fix: Price calculation and empty sale price handling ([GitHub issue #1](https://github.com/webhead-GmbH/wh-bulk-price-update-for-woocommerce/issues/1))
* Tested with WordPress 6.8 and WooCommerce 9.8

= 1.0.6 =
* Tested with WordPress 6.7 and WooCommerce 9.7

= 1.0.5 =
* Minor styling adjustments for a smoother user experience on the dashboard

= 1.0.4 =
* Tested up new versions of WordPress and WooCommerce
* Security improvement

= 1.0.3 =
* Improved preview functionality before applying changes.

= 1.0.2 =
* Tested up new versions of WordPress and WooCommerce

= 1.0.1 =
* Bug fixes and minor improvements.

= 1.0.0 =
* Initial release of Bulk Price Update for WooCommerce.