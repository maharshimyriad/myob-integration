<?php // exit if uninstall constant is not defined
if (!defined('WP_UNINSTALL_PLUGIN')) {
exit;
}


// delete settings

delete_option( 'woocommerce_MYOB_integrations_settings' );

delete_option( 'WC_MYOB_client_id' );
delete_option( 'WC_MYOB_secret' );
delete_option( 'WC_MYOB_code' );
delete_option( 'MYOB_access_token' );
delete_option( 'MYOB_access_refresh_token' );
delete_option( 'MYOB_access_token_type' );
delete_option( 'MYOB_access_token_scope' );

delete_option( 'WC_MYOB_asset_accounts_list' );
delete_option( 'WC_MYOB_cogs_accounts_list' );
delete_option( 'WC_MYOB_company_file_list' );
delete_option( 'WC_MYOB_expense_accounts_list' );
delete_option( 'WC_MYOB_income_accounts_list' );
delete_option( 'WC_MYOB_tax_codes_list' );
delete_option( 'WC_MYOB_refresh_token_timestamp' );

// delete_option( 'myob_customer_count' );
// delete_option( 'myob_customer_pull_next_url' );
// delete_option( 'myob_customer_synced_count' );
delete_option( 'myob_product_count' );
delete_option( 'myob_product_pull_next_url' );
delete_option( 'myob_product_synced_count' );

//Clear transients
delete_transient( 'MYOB_keep_alive_transient' );
delete_transient( 'MYOB_keep_alive_run' );
//Clear scheduled events
wp_clear_scheduled_hook('WC_MYOB_keep_alive_transient_cron');
wp_clear_scheduled_hook('WC_MYOB_sync_cron');
wp_clear_scheduled_hook('WC_MYOB_access_cron');
wp_clear_scheduled_hook('myob_process_product_sync');
