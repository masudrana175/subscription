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
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_admin_assets' ) );
	}

	public static function enqueue_admin_assets( $hook ) {
		if ( ! in_array( $hook, array( 'post.php', 'post-new.php' ), true ) ) {
			return;
		}

		$screen = get_current_screen();
		if ( ! $screen || 'product' !== $screen->post_type ) {
			return;
		}

		wp_enqueue_style( 'sfiler-admin', SFILER_PLUGIN_URL . 'assets/css/sfiler-admin.css', array(), SFILER_VERSION );
	}

	public static function add_product_tab( $tabs ) {
		$tabs['sfiler_subscription'] = array(
			'label'    => __( 'Subscriptions', 'subscript-filter' ),
			'target'   => 'sfiler_subscription_data',
			'class'    => array( 'show_if_simple', 'show_if_variable' ),
			'priority' => 65,
		);
		return $tabs;
	}

	/**
	 * The standard billing frequencies offered in the admin UI and the
	 * storefront dropdown, e.g. 1/2/3/6/12 months.
	 */
	public static function get_preset_frequencies() {
		return array(
			array( 'count' => 1, 'unit' => 'month' ),
			array( 'count' => 2, 'unit' => 'month' ),
			array( 'count' => 3, 'unit' => 'month' ),
			array( 'count' => 6, 'unit' => 'month' ),
			array( 'count' => 12, 'unit' => 'month' ),
		);
	}

	public static function render_panel() {
		global $post;

		$product_id  = $post->ID;
		$enabled     = get_post_meta( $product_id, '_sfiler_enabled', true );
		$discount    = get_post_meta( $product_id, '_sfiler_discount_percent', true );
		$frequencies = get_post_meta( $product_id, '_sfiler_frequencies', true );
		$frequencies = is_array( $frequencies ) ? $frequencies : array();

		$global_discount = (float) get_option( 'sfiler_global_discount_percent', 10 );
		$presets         = self::get_preset_frequencies();
		?>
		<div id="sfiler_subscription_data" class="panel woocommerce_options_panel sfiler-admin-panel">
			<div class="options_group">
				<?php
				woocommerce_wp_checkbox(
					array(
						'id'          => '_sfiler_enabled',
						'label'       => __( 'Enable subscription', 'subscript-filter' ),
						'description' => __( 'Let customers choose to subscribe & save on this product instead of (or alongside) a one-time purchase.', 'subscript-filter' ),
						'value'       => $enabled ? 'yes' : 'no',
					)
				);

				woocommerce_wp_text_input(
					array(
						'id'                => '_sfiler_discount_percent',
						'label'             => __( 'Discount override (%)', 'subscript-filter' ),
						/* translators: %s: the site-wide default discount percentage */
						'description'       => sprintf( __( 'Leave blank to use the site-wide default of %s%%, set on Subscript Filter > Settings.', 'subscript-filter' ), rtrim( rtrim( number_format( $global_discount, 2 ), '0' ), '.' ) ),
						'type'              => 'number',
						'value'             => $discount,
						'custom_attributes' => array(
							'step'        => '0.01',
							'min'         => '0',
							'max'         => '100',
							'placeholder' => rtrim( rtrim( number_format( $global_discount, 2 ), '0' ), '.' ),
						),
					)
				);
				?>
				<p class="form-field sfiler-frequencies-field">
					<label><?php esc_html_e( 'Billing frequency', 'subscript-filter' ); ?></label>
					<span class="sfiler-frequency-options">
						<?php foreach ( $presets as $preset ) : ?>
							<?php
							$checked = false;
							foreach ( $frequencies as $frequency ) {
								if ( (int) $frequency['count'] === $preset['count'] && $frequency['unit'] === $preset['unit'] ) {
									$checked = true;
									break;
								}
							}
							$field_id = 'sfiler_frequency_' . $preset['count'] . '_' . $preset['unit'];
							?>
							<label for="<?php echo esc_attr( $field_id ); ?>" class="sfiler-frequency-option<?php echo $checked ? ' sfiler-checked' : ''; ?>">
								<input type="checkbox"
									id="<?php echo esc_attr( $field_id ); ?>"
									name="sfiler_frequency_presets[]"
									value="<?php echo esc_attr( $preset['count'] . ':' . $preset['unit'] ); ?>"
									<?php checked( $checked ); ?> />
								<?php echo esc_html( sfiler_format_interval( $preset['count'], $preset['unit'] ) ); ?>
							</label>
						<?php endforeach; ?>
					</span>
					<span class="description"><?php esc_html_e( 'Select every frequency customers can choose from at checkout. Leave all unchecked to default to every 1 month.', 'subscript-filter' ); ?></span>
				</p>
			</div>
		</div>
		<script>
		jQuery(function ($) {
			// :has() isn't supported everywhere, so the checked-state highlight
			// is driven by a real class toggle rather than relying on it alone.
			$('.sfiler-frequency-option input[type="checkbox"]').on('change', function () {
				$(this).closest('.sfiler-frequency-option').toggleClass('sfiler-checked', this.checked);
			});
		});
		</script>
		<?php
	}

	public static function save( $product_id ) {
		$enabled = isset( $_POST['_sfiler_enabled'] ) ? 'yes' : 'no';
		update_post_meta( $product_id, '_sfiler_enabled', $enabled );

		if ( isset( $_POST['_sfiler_discount_percent'] ) ) {
			$discount = wp_unslash( $_POST['_sfiler_discount_percent'] );
			update_post_meta( $product_id, '_sfiler_discount_percent', '' === trim( $discount ) ? '' : wc_format_decimal( $discount ) );
		}

		$frequencies    = array();
		$valid_presets  = self::get_preset_frequencies();
		$submitted      = isset( $_POST['sfiler_frequency_presets'] ) && is_array( $_POST['sfiler_frequency_presets'] ) ? wp_unslash( $_POST['sfiler_frequency_presets'] ) : array();

		foreach ( $submitted as $value ) {
			$parts = explode( ':', sanitize_text_field( $value ) );
			if ( count( $parts ) !== 2 ) {
				continue;
			}
			$count = max( 1, (int) $parts[0] );
			$unit  = $parts[1];

			foreach ( $valid_presets as $preset ) {
				if ( $preset['count'] === $count && $preset['unit'] === $unit ) {
					$frequencies[] = array( 'count' => $count, 'unit' => $unit );
					break;
				}
			}
		}

		update_post_meta( $product_id, '_sfiler_frequencies', $frequencies );
	}

	public static function is_enabled( $product_id ) {
		return 'yes' === get_post_meta( $product_id, '_sfiler_enabled', true );
	}

	/**
	 * Product-level discount if one is set, otherwise the site-wide default
	 * (Subscript Filter > Settings).
	 */
	public static function get_discount_percent( $product_id ) {
		$value = get_post_meta( $product_id, '_sfiler_discount_percent', true );
		return '' !== $value ? (float) $value : (float) get_option( 'sfiler_global_discount_percent', 10 );
	}

	public static function get_frequencies( $product_id ) {
		$frequencies = get_post_meta( $product_id, '_sfiler_frequencies', true );
		return is_array( $frequencies ) && ! empty( $frequencies ) ? $frequencies : array( array( 'count' => 1, 'unit' => 'month' ) );
	}
}
