<?php
/**
 * Plugin Name: WC Advanced Bulk Price Updater
 * Description: Safely update thousands of WooCommerce product prices with AJAX progress, status filters, and a 24-hour safety net.
 * Version: 2.4
 * Author: Farzam Ahsan
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// 1. Create the Admin Menu
add_action( 'admin_menu', 'wc_bpu_add_admin_menu' );
function wc_bpu_add_admin_menu() {
    add_submenu_page( 'woocommerce', 'Bulk Price Update', 'Bulk Price Update', 'manage_woocommerce', 'wc-bulk-price-updater', 'wc_bpu_admin_page_html' );
}

// 2. The Admin Page UI
function wc_bpu_admin_page_html() {
    if ( ! current_user_can( 'manage_woocommerce' ) ) { return; }
    
    // Fetch all product categories
    $categories = get_terms( array(
        'taxonomy'   => 'product_cat',
        'hide_empty' => false,
    ) );
    ?>
    <div class="wrap">
        <h1>Bulk Price Updater</h1>
        
        <div id="wc-bpu-setup-form">
            <table class="form-table">
                <tr valign="top">
                    <th scope="row">Percentage Increase (%)</th>
                    <td>
                        <input type="number" step="0.01" id="wc_bpu_percentage" required placeholder="e.g., 25" />
                    </td>
                </tr>
                <tr valign="top">
                    <th scope="row">Product Type</th>
                    <td>
                        <select id="wc_bpu_product_type">
                            <option value="both">Both Simple & Variable</option>
                            <option value="simple">Simple Products Only</option>
                            <option value="variable">Variable Products Only</option>
                        </select>
                    </td>
                </tr>
                <tr valign="top">
                    <th scope="row">Product Status</th>
                    <td>
                        <select id="wc_bpu_product_status">
                            <option value="publish" selected>Published Only</option>
                            <option value="draft">Drafts Only</option>
                            <option value="pending">Pending Review Only</option>
                            <option value="private">Private Only</option>
                            <option value="any">Any Status (Exclude Trash)</option>
                        </select>
                    </td>
                </tr>
                <tr valign="top">
                    <th scope="row">Filter by Category</th>
                    <td>
                        <select id="wc_bpu_category" multiple style="width: 300px; height: 150px;">
                            <option value="all" selected>All Categories</option>
                            <?php foreach ( $categories as $category ) : ?>
                                <option value="<?php echo esc_attr( $category->slug ); ?>"><?php echo esc_html( $category->name ); ?></option>
                            <?php endforeach; ?>
                        </select>
                        <p class="description">Hold CTRL (or CMD) to select multiple. Leave as "All Categories" to update everything.</p>
                    </td>
                </tr>
            </table>
            <button class="button button-primary" id="wc_bpu_start_btn">Start Bulk Update</button>
        </div>

        <div id="wc-bpu-progress-wrapper" style="display:none; margin-top: 30px; max-width: 600px;">
            <h3>Update in Progress... Do not close this tab!</h3>
            <div style="background: #e0e0e0; width: 100%; height: 25px; border-radius: 4px; overflow: hidden; margin-bottom: 10px;">
                <div id="wc-bpu-progress-bar" style="background: #2271b1; width: 0%; height: 100%; transition: width 0.4s ease;"></div>
            </div>
            <p>
                <strong><span id="wc-bpu-processed">0</span> / <span id="wc-bpu-total">0</span></strong> products processed 
                (<span id="wc-bpu-percent-text">0</span>%)
            </p>
            <div id="wc-bpu-success-message" style="display:none;" class="notice notice-success inline"><p>✅ All prices updated successfully!</p></div>
        </div>
    </div>
    <?php
    wc_bpu_javascript(); // Load the JS
}

// 3. The JavaScript (Handles the UI and AJAX loops)
function wc_bpu_javascript() {
    ?>
    <script type="text/javascript">
    jQuery(document).ready(function($) {
        $('#wc_bpu_start_btn').on('click', function(e) {
            e.preventDefault();
            
            let percentage = $('#wc_bpu_percentage').val();
            if (!percentage) { alert('Please enter a percentage.'); return; }

            let pType = $('#wc_bpu_product_type').val();
            let pStatus = $('#wc_bpu_product_status').val();
            let categories = $('#wc_bpu_category').val();

            // Lock UI and show progress bar
            $('#wc-bpu-setup-form').slideUp();
            $('#wc-bpu-progress-wrapper').fadeIn();

            // Step 1: Get Total Count
            $.post(ajaxurl, {
                action: 'wc_bpu_get_count',
                type: pType,
                status: pStatus,
                categories: categories
            }, function(response) {
                if(response.success) {
                    let totalProducts = response.data.total;
                    $('#wc-bpu-total').text(totalProducts);
                    
                    if (totalProducts > 0) {
                        processBatch(0, totalProducts, percentage, pType, pStatus, categories);
                    } else {
                        alert('No products found matching these filters.');
                        location.reload();
                    }
                }
            });
        });

        // Step 2: Recursive Batch Processor
        function processBatch(offset, total, percentage, pType, pStatus, categories) {
            $.post(ajaxurl, {
                action: 'wc_bpu_process_batch',
                offset: offset,
                percentage: percentage,
                type: pType,
                status: pStatus,
                categories: categories
            }, function(response) {
                if(response.success) {
                    let processed = offset + response.data.processed_this_batch;
                    if (processed > total) processed = total; // Cap it for the UI

                    // Update Progress Bar
                    let percent = Math.round((processed / total) * 100);
                    $('#wc-bpu-progress-bar').css('width', percent + '%');
                    $('#wc-bpu-percent-text').text(percent);
                    $('#wc-bpu-processed').text(processed);

                    // Loop or Finish with a 3-second delay to bypass firewalls
                    if (processed < total) {
                        setTimeout(function() {
                            processBatch(processed, total, percentage, pType, pStatus, categories);
                        }, 3000); // 3000ms = 3 second pause
                    } else {
                        $('#wc-bpu-success-message').fadeIn();
                    }
                }
            }).fail(function(xhr, status, error) {
                // Failsafe: if the server does drop the connection, log it and alert you.
                console.error("AJAX Error: " + status + " - " + error);
                alert("The server dropped the connection. Processed " + offset + " out of " + total + " products before stalling. Try refreshing the page.");
            });
        }
    });
    </script>
    <?php
}

// 4. AJAX Handler: Get Total Count
add_action( 'wp_ajax_wc_bpu_get_count', 'wc_bpu_ajax_get_count' );
function wc_bpu_ajax_get_count() {
    // Safely assign variables
    $type       = isset( $_POST['type'] ) ? sanitize_text_field( wp_unslash( $_POST['type'] ) ) : 'both';
    $status     = isset( $_POST['status'] ) ? sanitize_text_field( wp_unslash( $_POST['status'] ) ) : 'publish';
    $categories = isset( $_POST['categories'] ) ? wc_clean( wp_unslash( $_POST['categories'] ) ) : array();

    $args = wc_bpu_build_query_args( $type, $status, $categories );
    $args['limit']  = -1;
    $args['return'] = 'ids'; // Only get IDs so the server doesn't crash counting 11k products

    $query = wc_get_products( $args );
    wp_send_json_success( array( 'total' => count( $query ) ) );
}

// 5. AJAX Handler: Process the Batch
add_action( 'wp_ajax_wc_bpu_process_batch', 'wc_bpu_ajax_process_batch' );
function wc_bpu_ajax_process_batch() {
    // Safely assign and format variables to prevent TypeErrors
    $percentage = isset( $_POST['percentage'] ) ? floatval( wp_unslash( $_POST['percentage'] ) ) : 0;
    $offset     = isset( $_POST['offset'] ) ? intval( wp_unslash( $_POST['offset'] ) ) : 0;
    $type       = isset( $_POST['type'] ) ? sanitize_text_field( wp_unslash( $_POST['type'] ) ) : 'both';
    $status     = isset( $_POST['status'] ) ? sanitize_text_field( wp_unslash( $_POST['status'] ) ) : 'publish';
    $categories = isset( $_POST['categories'] ) ? wc_clean( wp_unslash( $_POST['categories'] ) ) : array();
    
    // REDUCED BATCH SIZE TO BYPASS STRICT SERVER LIMITS
    $batch_size = 20; // Process 20 per AJAX call

    $args = wc_bpu_build_query_args( $type, $status, $categories );
    $args['limit']  = $batch_size;
    $args['offset'] = $offset;

    $products = wc_get_products( $args );

    foreach ( $products as $product ) {
        if ( ! is_a( $product, 'WC_Product' ) ) {
            continue; // Skip if the product object failed to load
        }

        // Handle Variable Products
        if ( $product->is_type( 'variable' ) ) {
            $variations = $product->get_children();
            foreach ( $variations as $variation_id ) {
                $variation = wc_get_product( $variation_id );
                wc_bpu_update_single_price( $variation, $percentage );
            }
        } else {
            // Handle Simple Products
            wc_bpu_update_single_price( $product, $percentage );
        }
    }

    wp_send_json_success( array( 'processed_this_batch' => count( $products ) ) );
}

// 6. Helper Function: Build Query Args based on UI Filters
function wc_bpu_build_query_args( $type, $status, $categories ) {
    $args = array( 'return' => 'objects' );
    
    // Filter by Status
    if ( $status === 'any' ) {
        $args['status'] = array( 'publish', 'draft', 'pending', 'private' );
    } else {
        $args['status'] = $status;
    }

    // Filter by Type
    if ( $type === 'simple' ) { $args['type'] = array( 'simple' ); } 
    elseif ( $type === 'variable' ) { $args['type'] = array( 'variable' ); }

    // Ensure categories is an array before using in_array to prevent Fatal Errors
    if ( ! is_array( $categories ) ) {
        $categories = array();
    }

    // Filter by Category (Strict matching, no children)
    if ( ! empty( $categories ) && ! in_array( 'all', $categories, true ) ) {
        $args['tax_query'] = array(
            array(
                'taxonomy'         => 'product_cat',
                'field'            => 'slug',
                'terms'            => $categories,
                'include_children' => false, // This tells WooCommerce to ignore child categories
            ),
        );
    }
    
    return $args;
}

// 7. Helper Function: The Math & The 24-Hour Safety Net
function wc_bpu_update_single_price( $product, $percentage ) {
    // Final failsafe to guarantee the object passed is a valid WC_Product
    if ( ! is_a( $product, 'WC_Product' ) ) {
        return;
    }

    // SAFETY NET: Check if this product was already updated in the last 24 hours
    $last_run = $product->get_meta( '_wc_bpu_last_run' );
    if ( ! empty( $last_run ) && ( time() - intval( $last_run ) ) < 86400 ) {
        return; // Skip this product, it was updated less than 24 hours ago
    }

    $current_price = $product->get_regular_price();
    
    if ( is_numeric( $current_price ) && $current_price > 0 ) {
        $multiplier = 1 + ( $percentage / 100 );
        $new_price  = round( $current_price * $multiplier, 2 );

        // Update the prices
        $product->set_regular_price( $new_price );
        $product->set_price( $new_price );
        
        // Stamp the product with the current time so it can't be double-charged
        $product->update_meta_data( '_wc_bpu_last_run', time() );
        
        // Save the product (this saves both the price and the new timestamp)
        $product->save();
    }
}