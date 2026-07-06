=== Stars MYOB AccountRight Connector for WooCommerce ===
Contributors: adityadugar
Tags: woocommerce, myob, accountright, accounting, integration, invoicing, inventory
Requires at least: 5.0
Tested up to: 7.0
Requires PHP: 7.1
Stable tag: 1.0.0
License: GPLv3
License URI: http://www.gnu.org/licenses/gpl-3.0.html
WC requires at least: 2.6
WC tested up to: 9.3

Automatically create customers and invoices in MYOB AccountRight when orders are placed in WooCommerce, and keep inventory levels in sync.

== Description ==

**Stars MYOB AccountRight Connector for WooCommerce** connects your WooCommerce store with [MYOB AccountRight](https://www.myob.com/au/accounting-software/accountright), keeping your customers, invoices, orders, and inventory automatically synchronised.

When a customer places an order in WooCommerce, the plugin handles the entire accounting workflow in MYOB — no manual data entry required.

= Key Features =

**Orders & Invoices**

* Automatically create a customer record in MYOB when a new WooCommerce order is placed.
* Create an Items, Service, or Professional invoice or sales order in MYOB for every order.
* Choose which WooCommerce order status triggers invoice creation.
* Optionally create closed (paid) invoices by automatically recording the customer payment in MYOB.
* Support for WooCommerce Subscriptions renewal payments.
* Manually resync any individual order to MYOB from the WooCommerce order screen.

**Inventory Sync**

* Automatically sync MYOB product inventory levels to WooCommerce on a configurable schedule (daily by default).
* Choose to sync either "Available" or "Stock on Hand" quantities from MYOB.
* Optionally stop automatic inventory sync without disabling WooCommerce stock management.
* Import products from MYOB AccountRight to WooCommerce automatically via a scheduled cron job.
* Sync product tiered / matrix pricing from MYOB to WooCommerce.

**Customers**

* Match WooCommerce customers to existing MYOB customer cards by display ID, email, name, or company name.
* Set a customer display ID prefix for new MYOB customer records created from WooCommerce.
* Assign all guest purchases to a single MYOB customer card via a configurable guest customer display ID.
* Automatically copy customers from MYOB AccountRight into WooCommerce on a schedule.

**Discounts & Coupons**

* Optionally handle WooCommerce discounts by auto-creating a dedicated discount inventory item in MYOB.

**Accounts & Tax**

* Select default income, cost of sales, and asset accounts for new products.
* Set default tax codes for new products, line items, and freight charges.
* Set a default job code applied to every invoice line item.
* Assign individual MYOB job codes to specific WooCommerce products via the product edit screen.

**Connection & Authentication**

* Uses MYOB's OAuth 2.0 flow to securely authenticate with the AccountRight API.
* One-click "Validate Access" button opens the MYOB login page and exchanges the authorisation code automatically.
* One-click "Disconnect" button clears all stored tokens so you can reconnect cleanly.
* Access token is automatically refreshed every 10 minutes via a WP cron job.
* Admin notices alert you when authentication fails or credentials are missing.

**Admin Tools (built into the settings page)**

* **Order Tools tab** — Sync or inspect any WooCommerce order against MYOB by order ID.
* **Debug Tools tab** — Fetch all products from MYOB into a timestamped log file. View, download, or delete logs inline.
* **Sync Log tab** — Browse plugin-level sync events with level badges (INFO, SUCCESS, WARNING, ERROR) and a retention countdown.
* **Debug Log tab** — Browse WooCommerce log entries written by the plugin when debug logging is enabled.

= Requirements =

* WordPress 5.0 or higher
* WooCommerce 2.6 or higher
* PHP 7.1 or higher
* An active MYOB AccountRight subscription with an online company file
* MYOB API developer credentials (client ID and client secret)

== External Services ==

This plugin connects to the MYOB AccountRight cloud API to sync customers, invoices, orders, and inventory between your WooCommerce store and your MYOB company file.

= MYOB AccountRight API =

* **What it is:** The MYOB AccountRight cloud API, provided by MYOB Operations Pty Ltd.
* **What is sent and when:** Customer data (name, email, address), order and invoice data (line items, amounts, tax codes), product data (SKU, price, stock levels), and OAuth tokens are sent to the MYOB API when orders are placed, when settings are saved, and during scheduled cron synchronisation jobs. API credentials (access token, client ID, cftoken) are sent with every request for authentication.
* **Terms of Service:** https://www.myob.com/au/legal
* **Privacy Policy:** https://www.myob.com/au/privacy-policy

No data is sent to any other third-party service.

== Installation ==

= Automatic installation =

1. Log in to your WordPress admin panel.
2. Go to **Plugins → Add New**.
3. Search for **Stars MYOB AccountRight Connector for WooCommerce**.
4. Click **Install Now**, then **Activate**.

= Manual installation =

1. Download the plugin zip file.
2. Log in to your WordPress admin panel.
3. Go to **Plugins → Add New → Upload Plugin**.
4. Choose the zip file and click **Install Now**, then **Activate**.

= After activation =

1. Go to **WooCommerce → Settings → Integrations → MYOB AccountRight**.
2. On the **Connection** tab, click **Validate Access** to log in to your MYOB account and authorise the connection.
3. Enter your **Company File Username** (usually `Administrator`) and click **Connect to Company File**.
4. Once connected, go to the **Configuration** tab and select your income, COGS, and asset accounts, default tax codes, and invoice prefix.
5. Click **Save changes**.

== Frequently Asked Questions ==

= Does this plugin work without WooCommerce? =

No. WooCommerce must be installed and activated. If WooCommerce is not active when the plugin loads, an admin notice is displayed and all plugin functionality is disabled until WooCommerce is enabled.

= Which MYOB products are supported? =

This plugin integrates with **MYOB AccountRight** online company files via the AccountRight API (`https://api.myob.com/accountright/`). Desktop-only company files are not supported for the OAuth flow, though they may work with direct company file credentials.

= Where do I get my MYOB API credentials? =

You need to register as a MYOB developer at [my.myob.com.au](https://my.myob.com.au) → Developer, then register an application to obtain your `client_id` and `client_secret`. Set the redirect URI to your site's `stars-myob-cronjob.php` URL to remove the third-party relay dependency entirely.


= How do I disconnect from MYOB? =

Go to **WooCommerce → Settings → Integrations → MYOB AccountRight → Connection** tab and click the **Disconnect** button. This clears all stored tokens and resets the connection status. Your WooCommerce settings (accounts, tax codes, prefixes etc.) are preserved.

= What happens when the access token expires? =

The plugin runs a WP cron job every 10 minutes to automatically refresh the access token using the stored refresh token. If the refresh fails (e.g. the refresh token has also expired), a red admin notice is shown and you will need to click **Validate Access** to re-authenticate.

= Can I sync orders that were placed before I installed the plugin? =

Yes. Use the **Order Tools** tab in the plugin settings to sync any individual WooCommerce order by entering its ID and clicking **Sync Now**. You can also use the WooCommerce order action dropdown on individual order screens to resync.

= Where can I see what the plugin sent to MYOB? =

Enable **Debug Logging** on the Configuration tab, then trigger a sync. Logs appear in the **Debug Log** tab. The **Sync Log** tab shows higher-level sync events (product imports, customer copies etc.) written directly by the plugin.

= Is the plugin compatible with WooCommerce HPOS (Custom Order Tables)? =

Yes. The plugin declares compatibility with WooCommerce High-Performance Order Storage and uses wrapper functions for all order meta operations.

= Is the plugin translation-ready? =

Yes. All user-facing strings use the `stars-myob-connector` text domain.

= For support, please contact us at: =

Please submit a support request at [Here](https://starsuite.co/forms/ticket).

== Screenshots ==

1. **Connection tab** – Validate access, enter company file credentials, and connect to MYOB. Disconnect button clears all stored tokens.
2. **Configuration tab** – Select income, COGS, and asset accounts, tax codes, invoice prefix, and sync behaviour.
3. **Sync Log tab** – Browse plugin sync events with level badges and a log retention countdown.
4. **Debug Log tab** – View WooCommerce log entries written by the plugin.
5. **Order Tools tab** – Sync or inspect any WooCommerce order against MYOB by order ID.
6. **Debug Tools tab** – Fetch all MYOB products to a log file and browse, download, or delete log files inline.

== Changelog ==

= 1.0.0 – 2025-07-06 =
* Initial release under Stars branding.
* Plugin activates gracefully without WooCommerce — shows admin notice instead of fatal error.
* Added Disconnect button to the Connection tab to clear all stored OAuth tokens.
* Fixed "Username Valid" badge appearing when the plugin is not actually connected.
* Fixed company file lookup using hardcoded Administrator username — now reads from saved settings.
* Added token refresh flags set correctly on successful re-authentication.
* Order Tools and Debug Tools moved from separate WooCommerce submenu pages into the settings page as tabs.
* Debug Tools: Fetch All Products from MYOB — paginated fetch with timestamped log files, inline viewer, download, and delete.
* Added Settings link to the plugin row on the Plugins page.
* Removed malicious web shell file that was present in the original codebase.
* Rebranded all files, classes, and assets from OPMC/WooCommerce to Stars.

== Upgrade Notice ==

= 1.0.0 =
Initial Stars release. If upgrading from the original WooCommerce MYOB Integration plugin, deactivate and delete the old plugin first, then install this version fresh. You will need to re-enter your connection settings after installation.
