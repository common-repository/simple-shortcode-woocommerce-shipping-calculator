<?php
/**
 * Plugin Name: Simple Shortcode - WooCommerce Shipping Calculator
 * Plugin URI: http://www.softwarehtec.com/
 * Description: Simple Shortcode - WooCommerce shipping calculator which allow you to place the WooCommerce shipping calculator on any page. The shipping method and price will be based on current cart.
 * Version: 1.0.6
 * Author: softwarehtec.com
 * Author URI: http://www.softwarehtec.com
 * License: GPL-3.0+
 * License URI: http://www.gnu.org/licenses/gpl-3.0.html
 * Domain Path: /lang
 * Text Domain: softwarehtec-ss-woo-shipping-calculator
 */ 


global $shortcode_times;
add_action( 'wp', 'ss_woo_shipping_calculator_ajax' );


function ss_woo_shipping_calculator_ajax() {



    if( $_POST["action"] != "ss_woo_shipping_calculator")
        return false;
 

    $result = array();

    try {

        WC()->shipping->load_shipping_methods();
        WC()->shipping->reset_shipping();
        $country  = wc_clean( $_POST['country'] );
        $state    = wc_clean( isset( $_POST['state'] ) ? $_POST['state'] : '' );
        $postcode = apply_filters( 'woocommerce_shipping_calculator_enable_postcode', true ) ? wc_clean( $_POST['postcode'] ) : '';
        $city     = apply_filters( 'woocommerce_shipping_calculator_enable_city', false ) ? wc_clean( $_POST['city'] ) : '';

        if ( $postcode && ! WC_Validation::is_postcode( $postcode, $country ) ) {
            throw new Exception( __( 'Please enter a valid postcode / ZIP.', 'woocommerce' ) );
        } elseif ( $postcode ) {
            $postcode = wc_format_postcode( $postcode, $country );
        }

        if ( $country ) {
 
            WC()->customer->set_location( $country, $state, $postcode, $city );
            WC()->customer->set_shipping_location( $country, $state, $postcode, $city );
        } else {
            WC()->customer->set_to_base();
            WC()->customer->set_shipping_to_base();
        }

        WC()->customer->set_calculated_shipping( true );
        WC()->customer->save();

 

        do_action( 'woocommerce_calculated_shipping' );

 
        $cu = get_woocommerce_currency_symbol();
        WC()->shipping->calculate_shipping( WC()->cart->get_shipping_packages() );
	$packages = WC()->shipping->get_packages();

        if(count($packages ) > 0){
            $result["result"] = 1;
            $message = "<ul>";
            foreach ( $packages as $i => $package ) {
                if(count($package['rates']) > 0){
                    foreach($package['rates'] as $k => $v){
                        $message .= "<li>".$v->label." <span> - ".$cu." ".$v->cost."</span></li>";
                    }
                }
            } 
            $result["message"] = $message ;
        }
    } catch ( Exception $e ) {

        $result["result"] = 0;
        $result["message"] = $e->getMessage() ;
    }

    echo json_encode($result);
    die();
}



function ss_woo_shipping_calculator_shortcode() {
    global $shortcode_times;
    $shortcode_times++;

    if($shortcode_times > 1){
        return "";
    }

    wp_enqueue_script(  'wc-cart' );
    ob_start();

    do_action( 'woocommerce_before_shipping_calculator' ); 
?>

<form>



	<section class="shipping-calculator-form-shortcode" >

		<p class="form-row form-row-wide" id="calc_shipping_country_field">
			<select name="calc_shipping_country" id="calc_shipping_country" class="country_to_state" rel="calc_shipping_state">
				<option value=""><?php _e( 'Select a country&hellip;', 'woocommerce' ); ?></option>
				<?php
					foreach ( WC()->countries->get_shipping_countries() as $key => $value )
						echo '<option value="' . esc_attr( $key ) . '"' . selected( WC()->customer->get_shipping_country(), esc_attr( $key ), false ) . '>' . esc_html( $value ) . '</option>';
				?>
			</select>
		</p>

		<p class="form-row form-row-wide" id="calc_shipping_state_field">
			<?php
				$current_cc = WC()->customer->get_shipping_country();
				$current_r  = WC()->customer->get_shipping_state();
				$states     = WC()->countries->get_states( $current_cc );
				// Hidden Input
				if ( is_array( $states ) && empty( $states ) ) {
					?><input type="hidden" name="calc_shipping_state" id="calc_shipping_state" placeholder="<?php esc_attr_e( 'State / County', 'woocommerce' ); ?>" /><?php
				// Dropdown Input
				} elseif ( is_array( $states ) ) {
					?><span>
						<select name="calc_shipping_state" id="calc_shipping_state" placeholder="<?php esc_attr_e( 'State / County', 'woocommerce' ); ?>">
							<option value=""><?php esc_html_e( 'Select a state&hellip;', 'woocommerce' ); ?></option>
							<?php
								foreach ( $states as $ckey => $cvalue )
									echo '<option value="' . esc_attr( $ckey ) . '" ' . selected( $current_r, $ckey, false ) . '>' . esc_html( $cvalue ) . '</option>';
							?>
						</select>
					</span><?php
				// Standard Input
				} else {
					?><input type="text" class="input-text" value="<?php echo esc_attr( $current_r ); ?>" placeholder="<?php esc_attr_e( 'State / County', 'woocommerce' ); ?>" name="calc_shipping_state" id="calc_shipping_state" /><?php
				}
			?>
		</p>

		<?php if ( apply_filters( 'woocommerce_shipping_calculator_enable_city', false ) ) : ?>

			<p class="form-row form-row-wide" id="calc_shipping_city_field">
				<input type="text" class="input-text" value="<?php echo esc_attr( WC()->customer->get_shipping_city() ); ?>" placeholder="<?php esc_attr_e( 'City', 'woocommerce' ); ?>" name="calc_shipping_city" id="calc_shipping_city" />
			</p>

		<?php endif; ?>

		<?php if ( apply_filters( 'woocommerce_shipping_calculator_enable_postcode', true ) ) : ?>

			<p class="form-row form-row-wide" id="calc_shipping_postcode_field">
				<input type="text" class="input-text" value="<?php echo esc_attr( WC()->customer->get_shipping_postcode() ); ?>" placeholder="<?php esc_attr_e( 'Postcode / ZIP', 'woocommerce' ); ?>" name="calc_shipping_postcode" id="calc_shipping_postcode" />
			</p>

		<?php endif; ?>

		<p><button  value="1" class="button ss-woo-shipping-calculator"><?php _e( 'Update totals', 'woocommerce' ); ?></button><span id="ss-woo-shipping-calculator-loading" style="display:none"><img src='<?php echo plugins_url( '/default.gif', __FILE__ ) ?>' /></span></p>

		<?php wp_nonce_field( 'woocommerce-cart' ); ?>
                <div id="ss-woo-shipping-result">

                </div>
	</section>
</form>
<script type="text/javascript">
var $s = jQuery.noConflict();
$s(document).ready(function($) {
    $(".ss-woo-shipping-calculator").click(function(){
        var ajaxurl = "<?php echo admin_url('admin-ajax.php'); ?>";
        var country = $(this).parent().parent().find("#calc_shipping_country").val();
        var state = $(this).parent().parent().find("#calc_shipping_state").val();
        var city = $(this).parent().parent().find("#calc_shipping_city").val();
        var postcode = $(this).parent().parent().find("#calc_shipping_postcode").val();
        $("#ss-woo-shipping-calculator-loading").show();
        var data = {'action': 'ss_woo_shipping_calculator','country': country,'state': state,'city': city,'postcode': postcode};
        $.post("<?php echo get_home_url(); ?>", data, function(response) {
            $("#ss-woo-shipping-calculator-loading").hide();
            response = JSON.parse(response);
            if(response.result == 1){
                $("#ss-woo-shipping-result").html(response.message);
            }else{
                alert(response.message);
                $("#ss-woo-shipping-result").html("");
            }

            return false;
        });
        return false;
    });
});
</script>
<?php
do_action( 'woocommerce_after_shipping_calculator' ); 

$out = ob_get_contents();
ob_end_clean();

return $out;

}
add_shortcode('ss_woo_shipping_calculator', 'ss_woo_shipping_calculator_shortcode');