<?php
/**
 * "Choose how to buy" box. Available vars: $product, $product_id, $discount, $frequencies, $regular_price.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$subscribe_price = $regular_price * ( 1 - ( $discount / 100 ) );
$savings         = $regular_price - $subscribe_price;
?>
<div class="sfiler-purchase-options" data-regular-price="<?php echo esc_attr( $regular_price ); ?>" data-discount="<?php echo esc_attr( $discount ); ?>">
	<?php if ( $discount > 0 ) : ?>
		<div class="sfiler-discount-badge"><?php echo esc_html( sprintf( __( '%s%% OFF', 'subscript-filter' ), rtrim( rtrim( number_format( $discount, 2 ), '0' ), '.' ) ) ); ?></div>
	<?php endif; ?>

	<div class="sfiler-option sfiler-option-onetime">
		<label>
			<input type="radio" name="sfiler_purchase_type" value="onetime" checked="checked" />
			<span class="sfiler-option-title"><?php esc_html_e( 'One time purchase', 'subscript-filter' ); ?></span>
			<span class="sfiler-option-price">
				<span class="sfiler-price sfiler-price-onetime"><?php echo wc_price( $regular_price ); ?></span>
			</span>
		</label>
	</div>

	<div class="sfiler-option sfiler-option-subscribe">
		<label>
			<input type="radio" name="sfiler_purchase_type" value="subscription" />
			<span class="sfiler-option-title">
				<?php echo esc_html( sprintf( __( 'Subscribe & save %s%%', 'subscript-filter' ), rtrim( rtrim( number_format( $discount, 2 ), '0' ), '.' ) ) ); ?>
			</span>
			<span class="sfiler-option-price">
				<span class="sfiler-price sfiler-price-subscribe"><?php echo wc_price( $subscribe_price ); ?></span>
				<span class="sfiler-regular-price"><del><?php echo wc_price( $regular_price ); ?></del></span>
				<span class="sfiler-savings"><?php echo esc_html( sprintf( __( 'You save %s', 'subscript-filter' ), wc_price( $savings ) ) ); ?></span>
			</span>
		</label>

		<?php
		$unit_labels = sfiler_get_interval_units();
		if ( count( $frequencies ) > 1 ) :
			?>
			<div class="sfiler-frequency-select">
				<label for="sfiler_frequency"><?php esc_html_e( 'Frequency:', 'subscript-filter' ); ?></label>
				<select name="sfiler_frequency" id="sfiler_frequency">
					<?php foreach ( $frequencies as $index => $frequency ) : ?>
						<option value="<?php echo esc_attr( $index ); ?>">
							<?php
							echo esc_html(
								sprintf(
									/* translators: 1: interval count, 2: interval unit label */
									__( 'Every %1$d %2$s', 'subscript-filter' ),
									$frequency['count'],
									strtolower( $unit_labels[ $frequency['unit'] ] )
								)
							);
							?>
						</option>
					<?php endforeach; ?>
				</select>
			</div>
		<?php else : ?>
			<input type="hidden" name="sfiler_frequency" value="0" />
		<?php endif; ?>
	</div>
</div>
