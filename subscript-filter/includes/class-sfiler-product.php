<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Adds subscription settings to the Product Data panel for simple & variable products.
 */
class Sfiler_Product {

	public static function init() {
		add_filter( 'woocommerce_product_data_tabs', array( __CLASS__, 'add_product_tab' ) );
		add_action( 'woocommerce_product_data_panels', array( __CLASS__, 'render_panel' ) );
		add_action( 'woocommerce_process_product_meta', array( __CLASS__, 'save' ) );
	}

	public static function add_product_tab( $tabs ) {
		$tabs['sfiler_subscription'] = array(
			'label'    => __( 'Subscript Filter', 'subscript-filter' ),
			'target'   => 'sfiler_subscription_data',
			'class'    => array( 'show_if_simple', 'show_if_variable' ),
			'priority' => 65,
		);
		return $tabs;
	}

	public static function render_panel() {
		global $post;

		$product_id      = $post->ID;
		$enabled         = get_post_meta( $product_id, '_sfiler_enabled', true );
		$discount        = get_post_meta( $product_id, '_sfiler_discount_percent', true );
		$signup_fee      = get_post_meta( $product_id, '_sfiler_signup_fee', true );
		$frequencies     = get_post_meta( $product_id, '_sfiler_frequencies', true );
		$frequencies     = is_array( $frequencies ) ? $frequencies : array();

		if ( empty( $frequencies ) ) {
			$frequencies = array( array( 'count' => 1, 'unit' => 'month' ) );
		}

		$units = sfiler_get_interval_units();
		?>
		<div id="sfiler_subscription_data" class="panel woocommerce_options_panel">
			<div class="options_group">
				<?php
				woocommerce_wp_checkbox(
					array(
						'id'          => '_sfiler_enabled',
						'label'       => __( 'Enable subscription', 'subscript-filter' ),
						'description' => __( 'Allow customers to subscribe & save on this product instead of (or in addition to) a one-time purchase.', 'subscript-filter' ),
						'value'       => $enabled ? 'yes' : 'no',
					)
				);

				woocommerce_wp_text_input(
					array(
						'id'                => '_sfiler_discount_percent',
						'label'             => __( 'Subscription discount (%)', 'subscript-filter' ),
						'description'       => __( 'Percentage off the regular price when a customer chooses to subscribe.', 'subscript-filter' ),
						'type'              => 'number',
						'value'             => $discount !== '' ? $discount : 5,
						'custom_attributes' => array(
							'step' => '0.01',
							'min'  => '0',
							'max'  => '100',
						),
					)
				);

				woocommerce_wp_text_input(
					array(
						'id'                => '_sfiler_signup_fee',
						'label'             => __( 'Sign-up fee', 'subscript-filter' ) . ' (' . get_woocommerce_currency_symbol() . ')',
						'description'       => __( 'One-time fee charged on the first order only, on top of the subscription price. Leave 0 for none.', 'subscript-filter' ),
						'type'              => 'number',
						'value'             => $signup_fee !== '' ? $signup_fee : 0,
						'custom_attributes' => array(
							'step' => '0.01',
							'min'  => '0',
						),
					)
				);
				?>
				<p class="form-field">
					<label><?php esc_html_e( 'Available frequencies', 'subscript-filter' ); ?></label>
				</p>
				<table class="widefat sfiler-frequency-table" id="sfiler-frequency-table">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Every', 'subscript-filter' ); ?></th>
							<th><?php esc_html_e( 'Unit', 'subscript-filter' ); ?></th>
							<th></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $frequencies as $index => $frequency ) : ?>
							<tr>
								<td>
									<input type="number" min="1" step="1"
										name="sfiler_frequency_count[]"
										value="<?php echo esc_attr( $frequency['count'] ); ?>" />
								</td>
								<td>
									<select name="sfiler_frequency_unit[]">
										<?php foreach ( $units as $unit_key => $unit_label ) : ?>
											<option value="<?php echo esc_attr( $unit_key ); ?>" <?php selected( $frequency['unit'], $unit_key ); ?>>
												<?php echo esc_html( $unit_label ); ?>
											</option>
										<?php endforeach; ?>
									</select>
								</td>
								<td>
									<button type="button" class="button sfiler-remove-frequency">&times;</button>
								</td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
				<p><button type="button" class="button" id="sfiler-add-frequency"><?php esc_html_e( '+ Add frequency', 'subscript-filter' ); ?></button></p>
			</div>
		</div>
		<script>
		jQuery(function($){
			$('#sfiler-add-frequency').on('click', function(){
				var row = $('#sfiler-frequency-table tbody tr:first').clone();
				row.find('input').val('1');
				$('#sfiler-frequency-table tbody').append(row);
			});
			$('#sfiler-frequency-table').on('click', '.sfiler-remove-frequency', function(){
				if ($('#sfiler-frequency-table tbody tr').length > 1) {
					$(this).closest('tr').remove();
				}
			});
		});
		</script>
		<?php
	}

	public static function save( $product_id ) {
		$enabled = isset( $_POST['_sfiler_enabled'] ) ? 'yes' : 'no';
		update_post_meta( $product_id, '_sfiler_enabled', $enabled );

		if ( isset( $_POST['_sfiler_discount_percent'] ) ) {
			update_post_meta( $product_id, '_sfiler_discount_percent', wc_format_decimal( wp_unslash( $_POST['_sfiler_discount_percent'] ) ) );
		}

		if ( isset( $_POST['_sfiler_signup_fee'] ) ) {
			update_post_meta( $product_id, '_sfiler_signup_fee', wc_format_decimal( wp_unslash( $_POST['_sfiler_signup_fee'] ) ) );
		}

		$frequencies = array();
		if ( isset( $_POST['sfiler_frequency_count'], $_POST['sfiler_frequency_unit'] )
			&& is_array( $_POST['sfiler_frequency_count'] ) && is_array( $_POST['sfiler_frequency_unit'] ) ) {
			$counts = wp_unslash( $_POST['sfiler_frequency_count'] );
			$units  = wp_unslash( $_POST['sfiler_frequency_unit'] );
			$valid_units = array_keys( sfiler_get_interval_units() );

			foreach ( $counts as $index => $count ) {
				$count = max( 1, (int) $count );
				$unit  = isset( $units[ $index ] ) && in_array( $units[ $index ], $valid_units, true ) ? $units[ $index ] : 'month';
				$frequencies[] = array( 'count' => $count, 'unit' => $unit );
			}
		}

		update_post_meta( $product_id, '_sfiler_frequencies', $frequencies );
	}

	public static function is_enabled( $product_id ) {
		return 'yes' === get_post_meta( $product_id, '_sfiler_enabled', true );
	}

	public static function get_discount_percent( $product_id ) {
		$value = get_post_meta( $product_id, '_sfiler_discount_percent', true );
		return $value !== '' ? (float) $value : 0.0;
	}

	public static function get_signup_fee( $product_id ) {
		$value = get_post_meta( $product_id, '_sfiler_signup_fee', true );
		return $value !== '' ? (float) $value : 0.0;
	}

	public static function get_frequencies( $product_id ) {
		$frequencies = get_post_meta( $product_id, '_sfiler_frequencies', true );
		return is_array( $frequencies ) && ! empty( $frequencies ) ? $frequencies : array( array( 'count' => 1, 'unit' => 'month' ) );
	}
}
