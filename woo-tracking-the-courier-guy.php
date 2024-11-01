
<?php
/*
Plugin Name:  Woo Tracking for The Courier Guy
Plugin URI:   http://www.mustache.co.za
Description:  This is a simple plugin to display tracking information for The Courier Guy on your WooCommerce orders page.
Version:      1.0.6
Author:       Mustache
Author URI:   http://www.mustache.co.za
License:      GPL2
License URI:  https://www.gnu.org/licenses/gpl-2.0.html
*/
?>
<?php
// Add meta box
function tcg_tracking_box() {
    add_meta_box(
        'tcg-tracking-modal',
        'The Courier Guy Tracking',
        'tcg_meta_box_callback',
        'shop_order',
        'side',
        'high'
    );
}
add_action( 'add_meta_boxes', 'tcg_tracking_box' );

// Callback
function tcg_meta_box_callback( $post )
{
    $value = get_post_meta( $post->ID, '_tracking_box', true );
    $text = ! empty( $value ) ? esc_attr( $value ) : '';
    echo '<input type="text" name="tracking_box" class="input-text" id="tcg_tracking_box" value="' . $text . '" />';
    echo '<input type="hidden" name="tracking_box_nonce" value="' . wp_create_nonce() . '">';
    echo '<p><a href="https://www.thecourierguy.co.za/tracking_results.php?WaybillNumber=' . $value . '" target="_blank">Open tracking</a></p>';
}

// Saving
function tcg_save_meta_box_data( $post_id ) {

    // Only for shop order
    if ( 'shop_order' != $_POST[ 'post_type' ] )
        return $post_id;

    // Check if our nonce is set (and our cutom field)
    if ( ! isset( $_POST[ 'tracking_box_nonce' ] ) && isset( $_POST['tracking_box'] ) )
        return $post_id;

    $nonce = $_POST[ 'tracking_box_nonce' ];

    // Verify that the nonce is valid.
    if ( ! wp_verify_nonce( $nonce ) )
        return $post_id;

    // Checking that is not an autosave
    if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE )
        return $post_id;

    // Check the user’s permissions (for 'shop_manager' and 'administrator' user roles)
    if ( ! current_user_can( 'edit_shop_order', $post_id ) && ! current_user_can( 'edit_shop_orders', $post_id ) )
        return $post_id;

    // Saving the data
    update_post_meta( $post_id, '_tracking_box', sanitize_text_field( $_POST[ 'tracking_box' ] ) );
}
add_action( 'save_post', 'tcg_save_meta_box_data' );

///////////////////////////////////
// TRACKING//
//////////////////////////////////

class Tracking {

    private $waybillNumber;
    private $username;
    private $password;
    private $token;
    private $salt;

    function __construct($username, $password) {
        $this->serviceURL = 'http://tracking.parcelperfect.com/pptrackservice/v3/Json/';
        $this->ppcust = '2500.2500.3364';
        $this->username = 'something@something.co.za';
        $this->password = 'something';
        $this->token = '2500.2500.3364';
        $this->salt = '';
    }

    public function setWaybillNumber($trackingBox) {
        $this->waybillNumber = $trackingBox;
    }

    function runJsonCallTracking() {

    }

    public function getTrackingDetails() {
        // Get Waybill
        $tokenParams = array();
        $tokenParams['waybillno'] = $this->waybillNumber;
        $response = $this->makeCall('Waybill','getWaybill',$tokenParams, $this->token);

        //check for error
        if ($response['errorcode'] == 0) {
            if(count($response['results']) > 0)
            {
                $this->result['waybill'] = $response['results'][0];
            }
        } else {
            echo "No tracking info";
        }

        // Get Events
        $tokenParams = array();
        $tokenParams['trackno'] = $this->waybillNumber;
        $response = $this->makeCall('Waybill','getEvents',$tokenParams, $this->token);

        //check for error
        if ($response['errorcode'] == 0) { //no error
            if(count($response['results']) > 0)
            {
                $this->result['events'] = $response['results'];
            }
        }
        else {
            echo "Error: ".$response['errormessage'];
        }
    }

    // function for making calls to the webservice
    function makeCall($class, $method, $params, $token = null) {

        $jsonParams = json_encode($params);

        $serviceCall = $this->serviceURL.'?params='.$jsonParams."&method=$method&class=$class";
        if ($token != null) {
            $serviceCall.='&token_id='.$token;
        }

        $session = curl_init($serviceCall);

        curl_setopt($session, CURLOPT_HEADER, false);
        curl_setopt($session, CURLOPT_RETURNTRANSFER, true);
        $response = curl_exec($session);
        curl_close($session);

        return (array) json_decode($response);
    }
}

// Display To My Account view Order
function tcg_display_tracking_box_in_order_view( $order ) {
    $ex = new Tracking(get_option('tcg_option')['tcg_username'], get_option('tcg_option')['tcg_password']);
    $ex->runJsonCallTracking();
    $tracking_box = get_post_meta( $order->get_id(), '_tracking_box', true );
    $ex->setWaybillNumber($tracking_box);
    $ex->getTrackingDetails();
    ?>
    <?php if (!empty($tracking_box)) : ?>
        <div class="woo-tracking-the-courier-guy">
            <h3>Shipping Details</h3>
            <table class="table table-sm" width="100%">
                <thead>
                <tr>
                    <th align="left" width="30%">Waybill Number</th>
                    <th align="left" width="30%">Date</th>
                    <th align="left" width="40%">Customer Name</th>
                </tr>
                </thead>
                <tbody>
                <tr>
                    <td align="left"><?php echo $ex->result['waybill']->waybill; ?></td>
                    <td align="left"><?php echo $ex->result['waybill']->waydate; ?></td>
                    <td align="left"><?php echo $ex->result['waybill']->destpers; ?></td>
                </tr>
                </tbody>
            </table>
            <h3>Tracking Events</h3>
            <table class="table table-sm table-striped" width="100%">
                <thead>
                <tr>
                    <th align="left" width="30%">Date</th>
                    <th align="left" width="30%">Time</th>
                    <th align="left" width="40%">Details</th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($ex->result['events'] as $event) { ?>
                    <tr>
                        <td align="left"><?php echo $event->eventdate; ?></td>
                        <td align="left"><?php echo $event->eventtime; ?></td>
                        <td align="left"><?php echo $event->scanrule; ?></td>
                    </tr>
                <?php } ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
<?php }
add_action( 'woocommerce_order_details_after_order_table', 'tcg_display_tracking_box_in_order_view', 10, 1 );
?>
<?php
// Display tracking number in email
function tcg_display_tracking_box_in_email($order)
{
    $tracking_box = get_post_meta( $order->get_id(), '_tracking_box', true );
    if (empty($tracking_box) === false) {
        echo '<p>';
        echo '<a href="https://www.thecourierguy.co.za/tracking_results.php?WaybillNumber=' . $tracking_box . '" style="width: 100%; display: block; padding: 10px; background: #000; color: #FFF; text-align: center;"><strong>Click here to track your order</strong></a>';
        echo '<p>';
    }
}
add_action( 'woocommerce_email_before_order_table', 'tcg_display_tracking_box_in_email', 10, 1 );
// Display tracking button on orders listing
//function tcg_display_tracking_button( $actions, $order )
//{
//    $tracking_box = get_post_meta( $order->get_id(), '_tracking_box', true );
//    if ((!empty($tracking_box)) && is_account_page()) :
//    $actions['track'] = array(
//        'url'   => 'https://www.thecourierguy.co.za/tracking_results.php?WaybillNumber=' . $tracking_box,
//        'name'  => __( 'Track', 'woocommerce' )
//    );
//    return $actions;
//    endif;
//}
//add_action( 'woocommerce_my_account_my_orders_actions', 'tcg_display_tracking_button', 50, 2 );
// Tracking button in admin order list page
add_filter('woocommerce_admin_order_actions', 'add_track_order_actions_button', 100, 2);
function add_track_order_actions_button( $actions, $order )
{
    $tracking_box = get_post_meta( $order->get_id(), '_tracking_box', true );
    if (empty($tracking_box) === false) {
        $actions['track_button'] = array(
            'url' => 'https://www.thecourierguy.co.za/tracking_results.php?WaybillNumber=' . $tracking_box,
            'name' => __('Track', 'woocommerce'),
            'action' => 'view tracking'
        );
    }
    return $actions;
}
//Add CSS
function add_tracking_order_actions_button_css() {
    echo '<style>.view.tracking::after { content: "\f174" !important; }</style>';
}
add_action( 'admin_head', 'add_tracking_order_actions_button_css' );
