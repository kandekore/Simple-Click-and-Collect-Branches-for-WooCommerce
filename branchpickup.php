<?php
/*
Plugin Name: Simple Click and Collect Branches for WooCommerce
Description: Manage branches and enable pickup location selection for Click and Collect, should be used in conjunction with Simple click & Collect for WooCommerce.
Version: 1.0
Author: Darren Kandekore
Author URI: https://darrenk.uk
License: GPL v2 or later
License URI:       https://www.gnu.org/licenses/gpl-2.0.html
Update URI:        https://wordpresswizard.net/clickandcollectbranches
*/

// Add admin settings page
add_action('admin_menu', 'click_collect_branches_add_custom_admin_menu');

function click_collect_branches_add_custom_admin_menu() {
    add_menu_page(
        'Click & Collect Branches', 
        'Click & Collect Branches', 
        'manage_options', 
        'click-collect-branches', 
        'click_collect_branches_display_main_menu_content', 
        'dashicons-store', 
        30
    );
    
    add_submenu_page(
        'click-collect-branches', 
        'Branches', 
        'Branches', 
        'manage_options', 
        'branches', 
        'click_collect_branches_display_branches_page'
    );
}

// Main menu page content
function click_collect_branches_display_main_menu_content() {
    // Display content for the main menu page here
    echo '<div class="wrap">';
    echo '<h1>Main Menu Page Content</h1>';
    echo '</div>';
}

// Branches admin settings page
function click_collect_branches_display_branches_page() {
    // check user capabilities
    if (!current_user_can('manage_options')) {
        return;
    }
    
    // Update branch information if form submitted
    if ($_SERVER['REQUEST_METHOD'] == 'POST') {
        click_collect_branches_save_branch_information();
    }

    // Fetch branch information from the database
    $branches = click_collect_branches_get_all_branches();

    ?>
    <div class="wrap">
        <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
        <form action="" method="post">
            <?php wp_nonce_field('click_collect_branches_settings', 'click_collect_branches_nonce'); ?>

            <table class="form-table" role="presentation">
                <tbody>
                <tr>
                    <th scope="row"><label for="branch_name">Branch Name:</label></th>
                    <td><input name="branch_name" type="text" id="branch_name" class="regular-text"></td>
                </tr>
                <tr>
                    <th scope="row"><label for="branch_address">Branch Address:</label></th>
                    <td><textarea name="branch_address" id="branch_address" class="regular-text"></textarea></td>
                </tr>
                </tbody>
            </table>
            <?php
            submit_button('Add Branch');
            ?>
        </form>

        <?php if (!empty($branches)) : ?>
            <h2>Branches:</h2>
            <table class="wp-list-table widefat fixed striped">
                <thead>
                <tr>
                    <th>Branch Name</th>
                    <th>Branch Address</th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($branches as $branch) : ?>
                    <tr>
                        <td><?php echo esc_html($branch['name']); ?></td>
                        <td><?php echo esc_html($branch['address']); ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
    <?php
}

// Save branch information to the database
function click_collect_branches_save_branch_information() {
    if (isset($_POST['branch_name']) && isset($_POST['branch_address'])) {
        $name = sanitize_text_field($_POST['branch_name']);
        $address = sanitize_textarea_field($_POST['branch_address']);
        $branch = array(
            'name' => $name,
            'address' => $address,
        );

        // Save branch to the database
        $branches = click_collect_branches_get_all_branches();
        $branches[] = $branch;
        update_option('click_collect_branches_branches', $branches);
    }
}

// Retrieve all branches from the database
function click_collect_branches_get_all_branches() {
    $branches = get_option('click_collect_branches_branches', array());
    return $branches;
}

// Display pickup locations on the checkout page
add_action('woocommerce_before_order_notes', 'click_collect_branches_display_pickup_locations_checkout');

function click_collect_branches_display_pickup_locations_checkout($checkout) {
    $branches = click_collect_branches_get_all_branches();
    
    // Check if the "Collection Date" field is present using JavaScript/jQuery
    ?>
    <script>
    jQuery(function($) {
        var collectionDateField = $('input#collection_date');
        if (collectionDateField.length > 0) {
            if (!collectionDateField.prop('required')) {
                collectionDateField.prop('required', true);
                collectionDateField.closest('.form-row').addClass('validate-required');
            }
            if ($('#pickup-location-box').length === 0 && <?php echo json_encode(!empty($branches)); ?>) {
                var pickupLocationBox = $('<p class="form-row form-row-wide validate-required" id="pickup_location_field" data-priority=""><label for="pickup_location" class="">Pickup Location<abbr class="required" title="required">*</abbr></label><select name="pickup_location" id="pickup_location" class="form-select" required><option value="">Select Pickup Location</option><?php foreach ($branches as $branch) { echo '<option value="' . esc_attr($branch['name']) . '">' . esc_html($branch['name']) . '</option>'; } ?></select></p>');
                pickupLocationBox.insertBefore(collectionDateField.closest('.form-row'));
                pickupLocationBox.find('select').addClass('form-row-wide');
            }
        }
    });
    </script>
    <?php
}

// Validate pickup location before placing the order
add_action('woocommerce_checkout_process', 'click_collect_branches_validate_pickup_location');

function click_collect_branches_validate_pickup_location() {
    if (isset($_POST['pickup_location']) && empty($_POST['pickup_location'])) {
        wc_add_notice(__('Please select a pickup location.'), 'error');
    }
}



// Display selected pickup location on the order-received page
add_action('woocommerce_thankyou', 'click_collect_branches_display_pickup_location_order_received', 10, 1);

function click_collect_branches_display_pickup_location_order_received($order_id) {
    $pickup_location = get_post_meta($order_id, 'Pickup Location', true);
    $branch_address = get_post_meta($order_id, 'Branch Address', true);

    if (!empty($pickup_location)) {
        echo '<h2>Pickup Location:</h2>';
        echo '<p>' . esc_html($pickup_location) . '</p>';
    }

    if (!empty($branch_address)) {
        echo '<strong>Branch Address:</strong>';
        echo '<p>' . esc_html($branch_address) . '</p>';
    }
}


// Save selected pickup location during checkout
add_action('woocommerce_checkout_create_order', 'click_collect_branches_save_selected_pickup_location');

function click_collect_branches_save_selected_pickup_location($order) {
    if (isset($_POST['pickup_location'])) {
        $pickup_location = sanitize_text_field($_POST['pickup_location']);

        // Get the branch information based on the selected pickup location
        $branches = click_collect_branches_get_all_branches();
        $selected_branch = array();
        foreach ($branches as $branch) {
            if ($branch['name'] === $pickup_location) {
                $selected_branch = $branch;
                break;
            }
        }

        // Save the selected pickup location and its address to the order
        if (!empty($selected_branch)) {
            $order->update_meta_data('Pickup Location', $pickup_location);
            $order->update_meta_data('Branch Address', $selected_branch['address']);
        }
    }
}


// Display pickup location in order details on the admin order page
add_action('woocommerce_admin_order_data_after_shipping_address', 'click_collect_branches_display_pickup_location_admin_order_meta', 10, 1);

function click_collect_branches_display_pickup_location_admin_order_meta($order) {
    $pickup_location = $order->get_meta('Pickup Location');
    $branch_address = $order->get_meta('Branch Address');

    if (!empty($pickup_location)) {
        echo '<p><strong>Pickup Location:</strong> ' . esc_html($pickup_location) . '</p>';
        echo '<p><strong>Branch Address:</strong> ' . esc_html($branch_address) . '</p>';
    }
}




function plugin_enqueue_styles() {
    // Enqueue your CSS file
    wp_enqueue_style('plugin-styles', plugin_dir_url(__FILE__) . 'css/style.css', array(), '1.0.0');
}
add_action('wp_enqueue_scripts', 'plugin_enqueue_styles');