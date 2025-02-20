<?php
/**
 * Plugin Name: Custom Admin Invoice
 * Description: Allows admins to view and email invoices from the backend, with send history, multiple email support, order notes, and customizable invoice templates. Now includes a global invoice template editor under WooCommerce settings with available placeholders.
 * Version: 1.9
 * Author: Jeremy with ChatGPT
 * Text Domain: https://www.menslaveai.com/
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Add a meta box to the order edit page
 */
add_action( 'add_meta_boxes', 'cai_add_invoice_meta_box' );

function cai_add_invoice_meta_box() {
    add_meta_box(
        'cai-invoice-meta-box',
        __( 'Invoice Actions', 'custom-admin-invoice' ),
        'cai_invoice_meta_box_callback',
        'shop_order',
        'side',
        'default'
    );
}

function cai_invoice_meta_box_callback( $post ) {
    $order_id   = $post->ID;
    $invoice_url = admin_url( 'admin.php?page=cai-view-invoice&order_id=' . $order_id );

    echo '<a href="' . esc_url( $invoice_url ) . '" target="_blank" class="button button-primary">' . __( 'View Invoice', 'custom-admin-invoice' ) . '</a>';
}

/**
 * Register admin pages
 */
add_action( 'admin_menu', 'cai_register_invoice_pages' );

function cai_register_invoice_pages() {
    // Hidden page for viewing invoices
    add_submenu_page(
        null,
        __( 'View Invoice', 'custom-admin-invoice' ),
        __( 'View Invoice', 'custom-admin-invoice' ),
        'manage_woocommerce',
        'cai-view-invoice',
        'cai_display_invoice_page'
    );

    // New submenu under WooCommerce for editing the invoice template
    add_submenu_page(
        'woocommerce',
        __( 'Invoice Template', 'custom-admin-invoice' ),
        __( 'Invoice Template', 'custom-admin-invoice' ),
        'manage_woocommerce',
        'cai-invoice-template',
        'cai_display_invoice_template_page'
    );
}

/**
 * Display the invoice template editor page
 */
function cai_display_invoice_template_page() {
    if ( ! current_user_can( 'manage_woocommerce' ) ) {
        wp_die( __( 'You do not have sufficient permissions to access this page.', 'custom-admin-invoice' ) );
    }

    // Handle the form submission for saving the custom invoice template
    if ( isset( $_POST['save_invoice_template'] ) && check_admin_referer( 'cai_save_template', 'cai_nonce_template' ) ) {
        cai_save_custom_template();
    }

    // Retrieve the custom invoice template if it exists
    $custom_template = get_option( 'cai_custom_invoice_template' );

    // If no custom template exists, use the default template
    if ( ! $custom_template ) {
        $custom_template = cai_get_default_invoice_template();
    }

    ?>
    <div class="wrap">
        <h1><?php _e( 'Customize Invoice Template', 'custom-admin-invoice' ); ?></h1>

        <!-- Available Placeholders -->
        <div class="notice notice-info">
            <p><strong><?php _e( 'Available Placeholders:', 'custom-admin-invoice' ); ?></strong></p>
            <ul>
                <li><code>{order_number}</code> - <?php _e( 'The order number', 'custom-admin-invoice' ); ?></li>
                <li><code>{date}</code> - <?php _e( 'The date of the order', 'custom-admin-invoice' ); ?></li>
                <li><code>{total}</code> - <?php _e( 'The total amount of the order', 'custom-admin-invoice' ); ?></li>
                <li><code>{billing_address}</code> - <?php _e( 'The billing address', 'custom-admin-invoice' ); ?></li>
                <li><code>{shipping_address}</code> - <?php _e( 'The shipping address', 'custom-admin-invoice' ); ?></li>
                <li><code>{order_items}</code> - <?php _e( 'The list of order items in a table', 'custom-admin-invoice' ); ?></li>
                <li><code>{customer_note}</code> - <?php _e( 'The customer note', 'custom-admin-invoice' ); ?></li>
                <li><code>{payment_method}</code> - <?php _e( 'The payment method used', 'custom-admin-invoice' ); ?></li>
                <li><code>{shipping_method}</code> - <?php _e( 'The shipping method used', 'custom-admin-invoice' ); ?></li>
            </ul>
        </div>

        <form method="post">
            <?php wp_nonce_field( 'cai_save_template', 'cai_nonce_template' ); ?>
            <?php
            $editor_id = 'cai_invoice_editor';
            $settings = array(
                'textarea_name' => 'cai_custom_template',
                'media_buttons' => true,
                'textarea_rows' => 20,
                'teeny'         => false,
                'tinymce'       => true,
                'quicktags'     => true,
            );
            wp_editor( $custom_template, $editor_id, $settings );
            ?>
            <p class="submit">
                <input type="submit" name="save_invoice_template" id="save_invoice_template" class="button button-primary" value="<?php _e( 'Save Template', 'custom-admin-invoice' ); ?>">
            </p>
        </form>
    </div>
    <?php
}

/**
 * Display the invoice page for a specific order
 */
function cai_display_invoice_page() {
    if ( ! current_user_can( 'manage_woocommerce' ) ) {
        wp_die( __( 'You do not have sufficient permissions to access this page.', 'custom-admin-invoice' ) );
    }

    $order_id = isset( $_GET['order_id'] ) ? intval( $_GET['order_id'] ) : 0;

    if ( ! $order_id ) {
        echo '<h2>' . __( 'Invalid Order ID', 'custom-admin-invoice' ) . '</h2>';
        return;
    }

    $order = wc_get_order( $order_id );

    if ( ! $order ) {
        echo '<h2>' . __( 'Order not found', 'custom-admin-invoice' ) . '</h2>';
        return;
    }

    // Retrieve the invoice template
    $invoice_template = cai_get_invoice_template();

    // Replace placeholders with actual order data
    $invoice_content = cai_replace_placeholders( $invoice_template, $order );

    // Enqueue the JavaScript and CSS for AJAX handling and styling
    cai_enqueue_scripts_and_styles( $order_id );

    // Display the invoice details
    ?>
    <div class="wrap">
        <h1><?php _e( 'Invoice', 'custom-admin-invoice' ); ?> #<?php echo esc_html( $order->get_order_number() ); ?></h1>

        <!-- Go Back Link -->
        <p><a href="<?php echo esc_url( admin_url( 'post.php?post=' . $order_id . '&action=edit' ) ); ?>" class="button button-secondary">&laquo; <?php _e( 'Go Back to Order Details', 'custom-admin-invoice' ); ?></a></p>

        <!-- Invoice Display -->
        <div class="cai-invoice-content">
            <?php echo wp_kses_post( nl2br( $invoice_content ) ); ?>
        </div>

        <!-- Email Invoice Form -->
        <h2><?php _e( 'Email Invoice', 'custom-admin-invoice' ); ?></h2>
        <form method="post" id="cai_send_invoice_form">
            <?php wp_nonce_field( 'cai_send_invoice_nonce', 'cai_nonce' ); ?>
            <table class="form-table">
                <tr>
                    <th><label for="to_email"><?php _e( 'To', 'custom-admin-invoice' ); ?></label></th>
                    <td><input type="text" name="to_email" id="to_email" value="" class="regular-text" placeholder="<?php _e( 'Separate emails with comma or semicolon', 'custom-admin-invoice' ); ?>" required></td>
                </tr>
                <tr>
                    <th><label for="cc_email"><?php _e( 'CC', 'custom-admin-invoice' ); ?></label></th>
                    <td><input type="text" name="cc_email" id="cc_email" value="" class="regular-text" placeholder="<?php _e( 'Separate emails with comma or semicolon', 'custom-admin-invoice' ); ?>"></td>
                </tr>
                <tr>
                    <th><label for="bcc_email"><?php _e( 'BCC', 'custom-admin-invoice' ); ?></label></th>
                    <td><input type="text" name="bcc_email" id="bcc_email" value="" class="regular-text" placeholder="<?php _e( 'Separate emails with comma or semicolon', 'custom-admin-invoice' ); ?>"></td>
                </tr>
            </table>
            <p class="submit">
                <input type="button" name="send_invoice" id="send_invoice" class="button button-primary" value="<?php _e( 'Send Invoice', 'custom-admin-invoice' ); ?>">
            </p>
        </form>

        <!-- Send History -->
        <h2><?php _e( 'Send History', 'custom-admin-invoice' ); ?></h2>
        <div id="cai_send_history_wrapper">
            <table class="widefat fixed striped" id="cai_send_history">
                <thead>
                    <tr>
                        <th><?php _e( 'Date', 'custom-admin-invoice' ); ?></th>
                        <th><?php _e( 'To', 'custom-admin-invoice' ); ?></th>
                        <th><?php _e( 'CC', 'custom-admin-invoice' ); ?></th>
                        <th><?php _e( 'BCC', 'custom-admin-invoice' ); ?></th>
                        <th><?php _e( 'Status', 'custom-admin-invoice' ); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php cai_display_send_history_rows( $order_id ); ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php
}

/**
 * Enqueue JavaScript and CSS for AJAX functionality and styling
 */
function cai_enqueue_scripts_and_styles( $order_id ) {
    // Enqueue jQuery if not already loaded
    wp_enqueue_script( 'jquery' );

    // Inline JavaScript
    ?>
    <style>
    .cai-status-pending::after {
        content: '';
        display: inline-block;
        width: 12px;
        height: 12px;
        border: 2px solid #ccc;
        border-top-color: #000;
        border-radius: 50%;
        animation: spin 1s linear infinite;
        margin-left: 5px;
    }
    @keyframes spin {
        to { transform: rotate(360deg); }
    }
    .cai-status-success {
        color: green;
    }
    .cai-status-failed {
        color: red;
    }
    </style>
    <script type="text/javascript">
    jQuery(document).ready(function($) {
        $('#send_invoice').on('click', function(e) {
            e.preventDefault();

            // Get the input values
            var toEmail = $('#to_email').val();
            var ccEmail = $('#cc_email').val();
            var bccEmail = $('#bcc_email').val();
            var orderId = <?php echo intval( $order_id ); ?>;

            // Ensure required fields are filled
            if (!toEmail) {
                alert('<?php _e("Please enter at least one recipient email address.", "custom-admin-invoice"); ?>');
                return;
            }

            // Disable the send button to prevent multiple clicks
            $('#send_invoice').prop('disabled', true);

            // Get current date and time
            var currentDate = new Date().toLocaleString();

            // Prepare data for display, replacing empty values with 'N/A'
            var displayTo = toEmail || '<?php _e("N/A", "custom-admin-invoice"); ?>';
            var displayCc = ccEmail || '<?php _e("N/A", "custom-admin-invoice"); ?>';
            var displayBcc = bccEmail || '<?php _e("N/A", "custom-admin-invoice"); ?>';

            // Add a new row at the top of the send history table
            var newRow = $('<tr>' +
                '<td>' + currentDate + '</td>' +
                '<td>' + displayTo + '</td>' +
                '<td>' + displayCc + '</td>' +
                '<td>' + displayBcc + '</td>' +
                '<td class="status-cell cai-status-pending"><?php _e("Pending", "custom-admin-invoice"); ?></td>' +
                '</tr>');
            $('#cai_send_history tbody').prepend(newRow);

            // AJAX request
            $.ajax({
                url: ajaxurl, // WordPress AJAX handler
                type: 'POST',
                data: {
                    action: 'cai_send_invoice', // Name of the AJAX action
                    order_id: orderId,
                    to_email: toEmail,
                    cc_email: ccEmail,
                    bcc_email: bccEmail,
                    nonce: '<?php echo wp_create_nonce('cai_send_invoice_nonce'); ?>'
                },
                success: function(response) {
                    // Enable the send button
                    $('#send_invoice').prop('disabled', false);

                    // Update the status cell in the new row
                    var statusCell = newRow.find('.status-cell');
                    if (response.success) {
                        statusCell.text('<?php _e("Sent", "custom-admin-invoice"); ?>');
                        statusCell.removeClass('cai-status-pending').addClass('cai-status-success');
                    } else {
                        statusCell.text('<?php _e("Failed", "custom-admin-invoice"); ?>');
                        statusCell.removeClass('cai-status-pending').addClass('cai-status-failed');
                    }

                    // If status is blank, fill with 'Unknown'
                    if (statusCell.text().trim() === '') {
                        statusCell.text('<?php _e("Unknown", "custom-admin-invoice"); ?>');
                    }
                },
                error: function() {
                    // Enable the send button
                    $('#send_invoice').prop('disabled', false);
                    // Update the status cell in the new row
                    var statusCell = newRow.find('.status-cell');
                    statusCell.text('<?php _e("Failed", "custom-admin-invoice"); ?>');
                    statusCell.removeClass('cai-status-pending').addClass('cai-status-failed');

                    // If status is blank, fill with 'Unknown'
                    if (statusCell.text().trim() === '') {
                        statusCell.text('<?php _e("Unknown", "custom-admin-invoice"); ?>');
                    }
                }
            });
        });
    });
    </script>
    <?php
}

/**
 * Get the default invoice HTML template with placeholders
 */
function cai_get_default_invoice_template() {
    ob_start();
    ?>
    <h1><?php _e( 'Invoice', 'custom-admin-invoice' ); ?> #{order_number}</h1>
    <p><strong><?php _e( 'Date:', 'custom-admin-invoice' ); ?></strong> {date}</p>
    <p><strong><?php _e( 'Total:', 'custom-admin-invoice' ); ?></strong> {total}</p>

    <h2><?php _e( 'Billing Address', 'custom-admin-invoice' ); ?></h2>
    <address>
        {billing_address}
    </address>

    <h2><?php _e( 'Shipping Address', 'custom-admin-invoice' ); ?></h2>
    <address>
        {shipping_address}
    </address>

    <h2><?php _e( 'Order Items', 'custom-admin-invoice' ); ?></h2>
    <table border="1" cellpadding="6" cellspacing="0" style="border-collapse: collapse; width:100%;">
        <thead>
            <tr>
                <th><?php _e( 'Product', 'custom-admin-invoice' ); ?></th>
                <th><?php _e( 'Quantity', 'custom-admin-invoice' ); ?></th>
                <th><?php _e( 'Price', 'custom-admin-invoice' ); ?></th>
            </tr>
        </thead>
        <tbody>
            {order_items}
        </tbody>
    </table>
    <?php
    return ob_get_clean();
}

/**
 * Get the invoice template (either custom or default)
 */
function cai_get_invoice_template() {
    $custom_template = get_option( 'cai_custom_invoice_template' );
    if ( ! $custom_template ) {
        $custom_template = cai_get_default_invoice_template();
    }
    return $custom_template;
}

/**
 * Replace placeholders with actual order data
 */
function cai_replace_placeholders( $template, $order ) {
    // Prepare order items HTML
    $items_html = '';
    foreach ( $order->get_items() as $item_id => $item ) {
        $items_html .= '<tr>';
        $items_html .= '<td>' . esc_html( $item->get_name() ) . '</td>';
        $items_html .= '<td>' . esc_html( $item->get_quantity() ) . '</td>';
        $items_html .= '<td>' . wc_price( $item->get_total() ) . '</td>';
        $items_html .= '</tr>';
    }

    // Replace placeholders
    $placeholders = array(
        '{order_number}'     => $order->get_order_number(),
        '{date}'             => wc_format_datetime( $order->get_date_created() ),
        '{total}'            => $order->get_formatted_order_total(),
        '{billing_address}'  => $order->get_formatted_billing_address(),
        '{shipping_address}' => $order->get_formatted_shipping_address(),
        '{order_items}'      => $items_html,
        '{customer_note}'    => $order->get_customer_note(),
        '{payment_method}'   => $order->get_payment_method_title(),
        '{shipping_method}'  => $order->get_shipping_method(),
    );

    $content = str_replace( array_keys( $placeholders ), array_values( $placeholders ), $template );

    return $content;
}

/**
 * Display Send History Rows
 */
function cai_display_send_history_rows( $order_id ) {
    $send_history = get_post_meta( $order_id, '_cai_send_history', true );

    if ( ! $send_history || ! is_array( $send_history ) ) {
        echo '<tr><td colspan="5">' . __( 'No invoices have been sent for this order yet.', 'custom-admin-invoice' ) . '</td></tr>';
        return;
    }

    // Reverse the array to show the latest entries on top
    $send_history = array_reverse( $send_history );

    foreach ( $send_history as $send ) {
        $status_class = '';
        if ( strtolower( $send['status'] ) === 'sent' ) {
            $status_class = 'cai-status-success';
        } elseif ( strtolower( $send['status'] ) === 'failed' ) {
            $status_class = 'cai-status-failed';
        }

        // Prepare display values, replacing empty fields with 'N/A'
        $display_to   = ! empty( $send['to'] ) ? implode( ', ', $send['to'] ) : __( 'N/A', 'custom-admin-invoice' );
        $display_cc   = ! empty( $send['cc'] ) ? implode( ', ', $send['cc'] ) : __( 'N/A', 'custom-admin-invoice' );
        $display_bcc  = ! empty( $send['bcc'] ) ? implode( ', ', $send['bcc'] ) : __( 'N/A', 'custom-admin-invoice' );
        $display_status = ! empty( $send['status'] ) ? ucfirst( $send['status'] ) : __( 'Unknown', 'custom-admin-invoice' );

        echo '<tr>';
        echo '<td>' . esc_html( date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), strtotime( $send['date'] ) ) ) . '</td>';
        echo '<td>' . esc_html( $display_to ) . '</td>';
        echo '<td>' . esc_html( $display_cc ) . '</td>';
        echo '<td>' . esc_html( $display_bcc ) . '</td>';
        echo '<td class="' . esc_attr( $status_class ) . '">' . esc_html( $display_status ) . '</td>';
        echo '</tr>';
    }
}

/**
 * Send Invoice Email
 */
function cai_send_invoice_email( $order, $to_input, $cc_input = '', $bcc_input = '' ) {
    // Parse and validate email addresses
    $to_emails  = cai_parse_email_addresses( $to_input );
    $cc_emails  = cai_parse_email_addresses( $cc_input );
    $bcc_emails = cai_parse_email_addresses( $bcc_input );

    // Validate 'To' emails
    if ( empty( $to_emails ) ) {
        return false;
    }

    // Build the email subject
    $subject = __( 'Invoice for Order', 'custom-admin-invoice' ) . ' #' . $order->get_order_number();

    // Retrieve the invoice template
    $custom_template = cai_get_invoice_template();

    // Replace placeholders with actual order data
    $message = cai_replace_placeholders( $custom_template, $order );

    // Set content type to HTML
    add_filter( 'wp_mail_content_type', 'cai_set_html_content_type' );

    // Build the email headers
    $headers = array();

    if ( ! empty( $cc_emails ) ) {
        $headers[] = 'Cc: ' . implode( ', ', $cc_emails );
    }

    if ( ! empty( $bcc_emails ) ) {
        $headers[] = 'Bcc: ' . implode( ', ', $bcc_emails );
    }

    // Send the email
    $mail_sent = wp_mail( $to_emails, $subject, $message, $headers );

    // Reset content type to avoid conflicts
    remove_filter( 'wp_mail_content_type', 'cai_set_html_content_type' );

    // Prepare data for storage, replacing empty arrays with 'N/A'
    $to_emails_db   = ! empty( $to_emails ) ? $to_emails : array( __( 'N/A', 'custom-admin-invoice' ) );
    $cc_emails_db   = ! empty( $cc_emails ) ? $cc_emails : array( __( 'N/A', 'custom-admin-invoice' ) );
    $bcc_emails_db  = ! empty( $bcc_emails ) ? $bcc_emails : array( __( 'N/A', 'custom-admin-invoice' ) );
    $status_db      = $mail_sent ? 'sent' : 'failed';

    // Record the send history
    cai_record_send_history( $order->get_id(), $to_emails_db, $cc_emails_db, $bcc_emails_db, $status_db );

    if ( $mail_sent ) {
        // Add order note for successful send
        cai_add_order_note( $order, $to_emails_db, $cc_emails_db, $bcc_emails_db );
        return true;
    } else {
        // Add order note for failed send
        cai_add_order_note_failed( $order, $to_emails_db, $cc_emails_db, $bcc_emails_db );
        return false;
    }
}

/**
 * Parse and sanitize multiple email addresses
 */
function cai_parse_email_addresses( $input ) {
    if ( empty( $input ) ) {
        return array();
    }

    // Split the input by comma or semicolon
    $emails = preg_split( '/[;,]+/', $input );

    $valid_emails = array();

    foreach ( $emails as $email ) {
        $email = trim( $email );
        if ( is_email( $email ) ) {
            $valid_emails[] = $email;
        }
    }

    return $valid_emails;
}

/**
 * Record Send History
 */
function cai_record_send_history( $order_id, $to, $cc, $bcc, $status ) {
    // Get existing send history
    $send_history = get_post_meta( $order_id, '_cai_send_history', true );

    if ( ! is_array( $send_history ) ) {
        $send_history = array();
    }

    // Add the new send record
    $send_history[] = array(
        'date'   => current_time( 'mysql' ),
        'to'     => $to,
        'cc'     => $cc,
        'bcc'    => $bcc,
        'status' => $status,
    );

    // Update the order meta
    update_post_meta( $order_id, '_cai_send_history', $send_history );
}

/**
 * Add Order Note on Successful Email Send
 */
function cai_add_order_note( $order, $to, $cc, $bcc ) {
    $to_list  = ! empty( $to ) ? implode( ', ', $to ) : __( 'N/A', 'custom-admin-invoice' );
    $cc_list  = ! empty( $cc ) ? implode( ', ', $cc ) : __( 'N/A', 'custom-admin-invoice' );
    $bcc_list = ! empty( $bcc ) ? implode( ', ', $bcc ) : __( 'N/A', 'custom-admin-invoice' );

    $admin = wp_get_current_user();
    $admin_name = $admin->display_name;

    $note = sprintf(
        __( 'Invoice emailed by %1$s on %2$s. To: %3$s. CC: %4$s. BCC: %5$s.', 'custom-admin-invoice' ),
        $admin_name,
        date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), current_time( 'timestamp' ) ),
        $to_list,
        $cc_list,
        $bcc_list
    );

    // Add order note
    $order->add_order_note( $note, false ); // false to prevent as customer note
}

/**
 * Add Order Note on Failed Email Send
 */
function cai_add_order_note_failed( $order, $to, $cc, $bcc ) {
    $to_list  = ! empty( $to ) ? implode( ', ', $to ) : __( 'N/A', 'custom-admin-invoice' );
    $cc_list  = ! empty( $cc ) ? implode( ', ', $cc ) : __( 'N/A', 'custom-admin-invoice' );
    $bcc_list = ! empty( $bcc ) ? implode( ', ', $bcc ) : __( 'N/A', 'custom-admin-invoice' );

    $admin = wp_get_current_user();
    $admin_name = $admin->display_name;

    $note = sprintf(
        __( 'Failed to email invoice by %1$s on %2$s. To: %3$s. CC: %4$s. BCC: %5$s.', 'custom-admin-invoice' ),
        $admin_name,
        date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), current_time( 'timestamp' ) ),
        $to_list,
        $cc_list,
        $bcc_list
    );

    // Add order note
    $order->add_order_note( $note, false ); // false to prevent as customer note
}

/**
 * Save Custom Invoice Template
 */
function cai_save_custom_template() {
    if ( ! isset( $_POST['cai_custom_template'] ) ) {
        return;
    }

    $custom_template = wp_kses_post( wp_unslash( $_POST['cai_custom_template'] ) );

    // Update the option with the custom template
    update_option( 'cai_custom_invoice_template', $custom_template );

    echo '<div class="notice notice-success is-dismissible"><p>' . __( 'Invoice template saved successfully.', 'custom-admin-invoice' ) . '</p></div>';
}

/**
 * Set email content type to HTML
 */
function cai_set_html_content_type() {
    return 'text/html';
}

/**
 * AJAX Handler for Sending Invoice
 */
add_action( 'wp_ajax_cai_send_invoice', 'cai_send_invoice_ajax' );

function cai_send_invoice_ajax() {
    // Check nonce for security
    if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( $_POST['nonce'], 'cai_send_invoice_nonce' ) ) {
        wp_send_json_error( array( 'message' => __( 'Nonce verification failed', 'custom-admin-invoice' ) ) );
    }

    // Retrieve and sanitize form data
    $order_id = isset( $_POST['order_id'] ) ? intval( $_POST['order_id'] ) : 0;
    $to_email = isset( $_POST['to_email'] ) ? sanitize_text_field( wp_unslash( $_POST['to_email'] ) ) : '';
    $cc_email = isset( $_POST['cc_email'] ) ? sanitize_text_field( wp_unslash( $_POST['cc_email'] ) ) : '';
    $bcc_email = isset( $_POST['bcc_email'] ) ? sanitize_text_field( wp_unslash( $_POST['bcc_email'] ) ) : '';

    if ( ! $order_id || empty( $to_email ) ) {
        wp_send_json_error( array( 'message' => __( 'Invalid data', 'custom-admin-invoice' ) ) );
    }

    // Fetch the order
    $order = wc_get_order( $order_id );
    if ( ! $order ) {
        wp_send_json_error( array( 'message' => __( 'Order not found', 'custom-admin-invoice' ) ) );
    }

    // Call the existing `cai_send_invoice_email` function to send the invoice
    $result = cai_send_invoice_email( $order, $to_email, $cc_email, $bcc_email );

    // Return success or failure response
    if ( $result ) {
        wp_send_json_success( array( 'message' => __( 'Invoice sent successfully', 'custom-admin-invoice' ) ) );
    } else {
        wp_send_json_error( array( 'message' => __( 'Failed to send invoice', 'custom-admin-invoice' ) ) );
    }

    wp_die();
}
