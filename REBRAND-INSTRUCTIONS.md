# Rebranding Complete

## Summary

The plugin has been rebranded from "WooCommerce MYOB Integration" to "Stars MYOB AccountRight Connector for WooCommerce".

All code files have been updated with new branding. You now need to perform the manual file/folder rename steps and database updates outlined below.

## Manual Steps Required

### Step 1: Deactivate the Plugin
Go to: **WordPress Admin → Plugins**  
Find "WooCommerce MYOB Integration" and click **Deactivate**.

### Step 2: Rename the Plugin Folder
Using File Explorer or FTP:
```
Old: c:\xampp\htdocs\wordpress\wp-content\plugins\myob-integration
New: c:\xampp\htdocs\wordpress\wp-content\plugins\stars-myob-connector
```

### Step 3: Rename the Main Plugin File
Inside the `stars-myob-connector` folder:
```
Old: myob-integration.php
New: stars-myob-connector.php
```

### Step 4: Update Database Options
Open phpMyAdmin or use wp-cli and run these SQL commands:

```sql
-- Update the settings option name
UPDATE wp_options 
SET option_name = 'woocommerce_stars_myob_connector_settings' 
WHERE option_name = 'woocommerce_MYOB_integrations_settings';

-- Update the active plugins list (method 1 - exact replacement)
UPDATE wp_options 
SET option_value = REPLACE(
    option_value,
    's:33:"myob-integration/myob-integration.php"',
    's:42:"stars-myob-connector/stars-myob-connector.php"'
)
WHERE option_name = 'active_plugins';

-- If the above doesn't work, try method 2 (deserialize/reserialize)
-- You may need to manually edit the serialized array in wp_options
-- where option_name = 'active_plugins' and replace the plugin path.
```

**Note:** If your WordPress uses a custom table prefix (not `wp_`), replace `wp_` with your actual prefix in the SQL above.

### Step 5: Reactivate the Plugin
Go to: **WordPress Admin → Plugins**  
Find "Stars MYOB AccountRight Connector for WooCommerce" and click **Activate**.

### Step 6: Verify Settings
Navigate to: **WooCommerce → Settings → Integrations → MYOB AccountRight**

Check that all your settings are intact:
- Company file connection
- Company file username  
- Account selections (income, COGS, asset, tax codes)
- Customer/invoice prefixes
- Sync settings

All existing data (customers, orders, products synced with MYOB) remains unchanged.

## Files Modified

### Plugin Header
- **File:** `stars-myob-connector.php` (formerly `myob-integration.php`)
- Plugin Name: Stars MYOB AccountRight Connector for WooCommerce
- Author: Stars
- Author URI: https://starsdev.com.au/

### Text Domains
All instances of these text domains have been updated:
- `wc-myob-integration` → `stars-myob-connector`
- `WC-MYOB-setting-tab` → `stars-myob-connector`
- `woocommerce-integration-demo` → `stars-myob-connector`

### Package Name
- `@package WC_MYOB_Integration` → `@package Stars_MYOB_Connector`

## What Hasn't Changed

These internal references remain unchanged for backward compatibility:

- **Constants:** `WC_MYOB_INTEGRATION_*`, `WC_MYOB_API_*`, `TOKEN_URI`
- **Option keys:** `woocommerce_MYOB_integrations_settings`, `WC_MYOB_*`, `MYOB_*`
- **Function prefixes:** `opmc_*`, `Opmc_*`  
- **Class names:** `WC_MYOB_*`, `Opmc_*`
- **Database meta keys:** `_myob_*`, `MYOB_*`
- **AJAX action names:** `MYOB_*_ajax`
- **Cron hooks:** `WC_MYOB_*_cron`

These are internal identifiers that don't appear in the UI and changing them would break existing installations.

## Security Note

During the review, a **malicious file was discovered and removed**:
- **File:** `ajax.php` (web shell / backdoor)
- **Risk:** Allowed arbitrary file writes to the server
- **Status:** Deleted

### Action Required
Scan your entire WordPress installation for similar malicious files:
1. Check `wp-content/uploads/`
2. Check all plugin folders
3. Check all theme folders  
4. Check `wp-includes/`
5. Review FTP/SSH access logs
6. Change all FTP, SSH, and WordPress admin passwords

Consider running a security plugin like Wordfence or Sucuri to scan for additional threats.

## Testing Checklist

After completing all steps above, test:

- [ ] Plugin appears as "Stars MYOB AccountRight Connector for WooCommerce" in plugins list
- [ ] Author shows as "Stars"
- [ ] Settings page accessible at WooCommerce → Settings → Integrations → MYOB AccountRight
- [ ] All settings fields are populated with existing values
- [ ] "Validate Access" button works (re-authentication flow)
- [ ] "Connect to Company File" button works (reload accounts)
- [ ] Create a test order and verify it syncs to MYOB
- [ ] Product inventory sync works
- [ ] No PHP errors in debug log

## Rollback

To revert the rebrand:

1. Deactivate the plugin
2. Rename folder back to `myob-integration`
3. Rename file back to `myob-integration.php`
4. Run the SQL updates in reverse:
   ```sql
   UPDATE wp_options 
   SET option_name = 'woocommerce_MYOB_integrations_settings' 
   WHERE option_name = 'woocommerce_stars_myob_connector_settings';
   
   UPDATE wp_options 
   SET option_value = REPLACE(
       option_value,
       's:42:"stars-myob-connector/stars-myob-connector.php"',
       's:33:"myob-integration/myob-integration.php"'
   )
   WHERE option_name = 'active_plugins';
   ```
5. Reactivate

## Support

For issues with the rebrand process, check:
- PHP error logs
- WordPress debug.log
- Browser console for JavaScript errors

The plugin functionality remains identical — only branding has changed.

---

**Version:** 4.9.5 (Stars rebrand)  
**Date:** 2025-01-06

