<?php
/**
 * Admin: manually create a subscription for a customer.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$units = sfiler_get_interval_units();
?>
<div class="wrap">
	<h1><?php esc_html_e( 'Add subscription', 'subscript-filter' ); ?></h1>

	<?php if ( isset( $_GET['error'] ) ) : ?>
		<div class="notice notice-error">
			<p>
				<?php
				if ( 'customer_not_found' === $_GET['error'] ) {
					esc_html_e( 'No customer found with that email address.', 'subscript-filter' );
				} elseif ( 'product_not_found' === $_GET['error'] ) {
					esc_html_e( 'That product could not be found.', 'subscript-filter' );
				}
				?>
			</p>
		</div>
	<?php endif; ?>

	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
		<input type="hidden" name="action" value="sfiler_create_subscription" />
		<?php wp_nonce_field( 'sfiler_create_subscription' ); ?>
		<table class="form-table">
			<tr>
				<th><label for="customer_email"><?php esc_html_e( 'Customer email', 'subscript-filter' ); ?></label></th>
				<td><input type="email" class="regular-text" id="customer_email" name="customer_email" required /></td>
			</tr>
			<tr>
				<th><label for="product_id"><?php esc_html_e( 'Product ID', 'subscript-filter' ); ?></label></th>
				<td>
					<input type="number" min="1" id="product_id" name="product_id" required />
					<p class="description"><?php esc_html_e( 'The numeric product ID (visible in Products list or product edit URL).', 'subscript-filter' ); ?></p>
				</td>
			</tr>
			<tr>
				<th><label for="line_total"><?php esc_html_e( 'Recurring amount', 'subscript-filter' ); ?></label></th>
				<td><input type="number" step="0.01" min="0" id="line_total" name="line_total" required /></td>
			</tr>
			<tr>
				<th><label for="interval_count"><?php esc_html_e( 'Interval', 'subscript-filter' ); ?></label></th>
				<td>
					<?php esc_html_e( 'Every', 'subscript-filter' ); ?>
					<input type="number" min="1" step="1" style="width:70px" id="interval_count" name="interval_count" value="1" />
					<select name="interval_unit">
						<?php foreach ( $units as $unit_key => $unit_label ) : ?>
							<option value="<?php echo esc_attr( $unit_key ); ?>" <?php selected( $unit_key, 'month' ); ?>><?php echo esc_html( $unit_label ); ?></option>
						<?php endforeach; ?>
					</select>
				</td>
			</tr>
			<tr>
				<th><label for="next_payment_date"><?php esc_html_e( 'Next payment date (UTC)', 'subscript-filter' ); ?></label></th>
				<td><input type="text" class="regular-text" id="next_payment_date" name="next_payment_date" value="<?php echo esc_attr( gmdate( 'Y-m-d H:i:s', strtotime( '+1 month' ) ) ); ?>" placeholder="YYYY-MM-DD HH:MM:SS" /></td>
			</tr>
		</table>
		<p class="description"><?php esc_html_e( 'The saved Stripe payment method (if any) is pulled automatically from the customer\'s existing payment tokens.', 'subscript-filter' ); ?></p>
		<?php submit_button( __( 'Create subscription', 'subscript-filter' ) ); ?>
	</form>
</div>
