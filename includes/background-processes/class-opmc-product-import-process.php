<?php

/**
 * Import Customer from MYOB to WooCommerce in background process.
 */
class Opmc_Product_Import_Process extends WP_Background_Process
{

    protected $action = 'cron_product_import_process';

    /**
     * Import product to WooCommerce from MYOB.
     *
     * @param  mixed $item The item to process.
     * @return bool        Status of the process.
     */
    protected function task($item)
    {

        $synced_product = get_option('myob_product_synced_count', 0);
        $local_data = file_get_contents(WC_MYOB_INTEGRATION_PLUGINDIR . 'assets/json/products.json');
        $connector = new Opmc_Myob_Connector();

        $local_file_data = json_decode($local_data, true);

        if (isset($local_file_data[$item])) {
            $product_name = $local_file_data[$item]['Name'];
            $product_sku = $local_file_data[$item]['Number'];

            // Check if the product already exists by SKU
            $existing_product_id = wc_get_product_id_by_sku($product_sku);

            if ($existing_product_id) {
                // Product exists, log it and increment the synced product count
                $connector->create_wc_log('[Product Import] [Skipping] [Product ' . $product_name . ' already exists with SKU: ' . $product_sku . ']');

                // Increment sync count for the existing product
                $synced_product++;
                update_option('myob_product_synced_count', $synced_product);
                $connector->create_wc_log('[Product Import] [Product already exists] [Product count updated to ' . $synced_product . ']');
            } else {
                // Product doesn't exist, attempt to import
                $connector->create_wc_log('[Product Import] [Processing] [' . $product_name . ']');

                // Try to import the product
                $success = $this->process_product_to_woo($local_file_data[$item]);

                // Only increase the count if the import was successful on non-existent products
                if ($success) {
                    $synced_product++;
                    update_option('myob_product_synced_count', $synced_product);
                    $connector->create_wc_log('[Product Import] [Success] [Product count updated to ' . $synced_product . ']');
                } else {
                    $connector->create_wc_log('[Product Import] [Error] [Failed to import product: ' . $product_name . ']');
                }
            }
        } else {
            $connector->create_wc_log('[Product Import] [Warning] [Product ' . $item . ' not found in local file]');
        }

        return false;
    }

    /**
     * Complete the background process.
     */
    protected function complete()
    {
        parent::complete();
        $connector = new Opmc_Myob_Connector();
        $connector->create_wc_log('[Import Product Task BG Process] [Completed] [All products processed]');
    }

    /**
     * Process product data to WooCommerce.
     *
     * Imports or updates a WooCommerce product from MYOB data, including
     * tiered pricing via opmc_update_tier_pricing_from_matrix() which
     * handles both the free and Pro versions of Tiered Pricing Table.
     *
     * @param  array $product_data Product details from MYOB.
     * @return bool  Status of the process.
     */
    public function process_product_to_woo( $product_data )
    {
        if ( empty( $product_data['Number'] ) ) {
            return false;
        }

        $connector  = new Opmc_Myob_Connector();
        $product_id = get_post_id_by_meta_key_and_value( '_sku', $product_data['Number'] );

        // Skip if product is already up-to-date.
        if ( $product_id && get_post_meta( $product_id, '_myob_row_version', true ) === $product_data['RowVersion'] ) {
            return false;
        }

        $product_info = array(
            'post_content' => $product_data['Description'],
            'post_status'  => ( 1 == $product_data['IsActive'] ) ? 'publish' : 'draft',
            'post_title'   => $product_data['Name'],
            'post_parent'  => 0,
            'post_type'    => 'product',
            'post_excerpt' => $product_data['Name'],
        );

        $sync_successful = false;

        // Insert or update the product post.
        if ( ! $product_id ) {
            $product_id = wp_insert_post( $product_info );
            if ( is_wp_error( $product_id ) ) {
                $connector->create_wc_log( '[Product Insertion] [Error] [Failed to insert product: ' . $product_id->get_error_message() . ']' );
            } else {
                $connector->create_wc_log( '[Product Insertion] [Success] [Product created: ' . $product_info['post_title'] . ']' );
                $sync_successful = true;
            }
        } else {
            $product_info['ID'] = $product_id;
            $result = wp_update_post( $product_info );
            if ( is_wp_error( $result ) ) {
                $connector->create_wc_log( '[Product Update] [Error] [Failed to update product: ' . $result->get_error_message() . ']' );
            } else {
                $connector->create_wc_log( '[Product Update] [Success] [Product updated: ' . $product_info['post_title'] . ']' );
                $sync_successful = true;
            }
        }

        if ( ! $sync_successful ) {
            return false;
        }

        // ── Core product meta ──────────────────────────────────────────────
        wp_set_object_terms( $product_id, 'simple', 'product_type' );
        update_post_meta( $product_id, '_visibility',        'visible' );
        update_post_meta( $product_id, '_stock_status',      'instock' );
        update_post_meta( $product_id, '_description',       $product_data['Description'] );
        update_post_meta( $product_id, '_sku',               $product_data['Number'] );
        update_post_meta( $product_id, '_myob_number',       $product_data['Number'] );
        update_post_meta( $product_id, '_myob_uid',          $product_data['UID'] );
        update_post_meta( $product_id, '_myob_row_version',  $product_data['RowVersion'] );

        // Set base price from MYOB; preserve any existing WooCommerce sale price.
        update_post_meta( $product_id, '_regular_price', $product_data['BaseSellingPrice'] );
        $existing_sale = get_post_meta( $product_id, '_sale_price', true );
        if ( empty( $existing_sale ) ) {
            update_post_meta( $product_id, '_price', $product_data['BaseSellingPrice'] );
        }

        // ── Tiered / level pricing from MYOB price matrix ─────────────────
        // opmc_update_tier_pricing_from_matrix() handles both Pro (role-based,
        // LevelA-F) and free (single _fixed_price_rules, LevelA only) modes
        // based on the "Tiered Pricing Table Pro" plugin setting.
        if ( ! empty( $product_data['UID'] ) ) {
            $matrix = $connector->get_product_metrix_info( $product_data['UID'] );
            if ( ! empty( $matrix->SellingPrices ) ) {
                opmc_update_tier_pricing_from_matrix( $product_id, $matrix );
            }
        }

        // ── Inventory / stock ──────────────────────────────────────────────
        if ( 1 == $product_data['IsInventoried'] ) {
            $quantity    = (int) $product_data['QuantityOnHand'];
            $stock_status = ( $quantity > 0 ) ? 'instock' : 'outofstock';
            update_post_meta( $product_id, '_manage_stock', 'yes' );
            update_post_meta( $product_id, '_stock',        $quantity );
            update_post_meta( $product_id, '_stock_status', wc_clean( $stock_status ) );
            wp_set_post_terms( $product_id, $stock_status, 'product_visibility', true );
        }

        // ── Product image ──────────────────────────────────────────────────
        if ( ! empty( $product_data['PhotoURI'] ) ) {
            $image_data = $connector->get_product_image_info( $product_data['UID'] );
            if ( ! empty( $image_data->Data ) ) {
                if ( get_post_meta( $product_id, '_myob_image_row_version', true ) !== $image_data->RowVersion ) {
                    $attach_id = $this->save_image( $image_data, $product_data['UID'] );
                    set_post_thumbnail( $product_id, $attach_id );
                    update_post_meta( $product_id, '_myob_image_row_version', $image_data->RowVersion );
                }
            }
        }

        return true;
    }

    /**
     * Save image from MYOB to WooCommerce.
     *
     * @param object $image_data Image data from MYOB.
     * @param string $title      Title for the image.
     * @return int               Attachment ID.
     */
    public function save_image($image_data, $title)
    {

        $base64_img = $image_data->Data;
        $mime_type = $image_data->MimeType;
        $upload_dir = wp_upload_dir();
        $upload_path = str_replace('/', DIRECTORY_SEPARATOR, $upload_dir['path']) . DIRECTORY_SEPARATOR;

        $img = str_replace(' ', '+', $base64_img);
        $decoded = base64_decode($img);
        $filename = $title . mime2ext($mime_type);
        $hashed_filename = md5($filename . microtime()) . '_' . $filename;

        // Save the image in the uploads directory.
        $upload_file = file_put_contents($upload_path . $hashed_filename, $decoded);

        $attachment = array(
            'post_mime_type' => $mime_type,
            'post_title' => preg_replace('/\.[^.]+$/', '', basename($hashed_filename)),
            'post_content' => '',
            'post_status' => 'inherit',
            'guid' => $upload_dir['url'] . '/' . basename($hashed_filename),
        );

        $image_path = $upload_dir['path'] . '/' . $hashed_filename;
        $attach_id = wp_insert_attachment($attachment, $image_path);

        $imagenew = get_post($attach_id);
        $fullsizepath = get_attached_file($imagenew->ID);
        require_once ABSPATH . 'wp-admin/includes/image.php';
        // Generate and save the attachment metas into the database
        $attach_data = wp_generate_attachment_metadata($attach_id, $fullsizepath);
        wp_update_attachment_metadata($attach_id, $attach_data);
        return $attach_id;
    }
}
