<?php
/**
 * My Account single-subscription view. Vars: $subscription, $units, $orders, $frequencies, $tokens.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$nonce_url = function ( $action ) use ( $subscription ) {
	return wp_nonce_url(
		add_query_arg(
			array(
				'sfiler_action'    => $action,
				'subscription_id'  => $subscription->id,
			),
			wc_get_account_endpoint_url( 'subscriptions' )
		),
		'sfiler_action_' . $subscription->id
	);
};
?>
<p><a href="<?php echo esc_url( wc_get_account_endpoint_url( 'subscriptions' ) ); ?>">&larr; <?php esc_html_e( 'Back to subscriptions', 'subscript-filter' ); ?></a></p>

<h2><?php echo esc_html( $subscription->product_name ); ?></h2>

<table class="woocommerce-table shop_table">
	<tbody>
		<tr>
			<th><?php esc_html_e( 'Amount', 'subscript-filter' ); ?></th>
			<td><?php echo wc_price( $subscription->line_total, array( 'currency' => $subscription->currency ) ); ?></td>
		</tr>
		<tr>
			<th><?php esc_html_e( 'Frequency', 'subscript-filter' ); ?></th>
			<td><?php echo esc_html( sfiler_format_interval( $subscription->interval_count, $subscription->interval_unit ) ); ?></td>
		</tr>
		<tr>
			<th><?php esc_html_e( 'Next payment', 'subscript-filter' ); ?></th>
			<td><?php echo esc_html( date_i18n( get_option( 'date_format' ), strtotime( $subscription->next_payment_date ) ) ); ?></td>
		</tr>
		<tr>
			<th><?php esc_html_e( 'Status', 'subscript-filter' ); ?></th>
			<td><?php echo sfiler_status_badge_html( $subscription->status ); ?></td>
		</tr>
	</tbody>
</table>

<p>
	<?php if ( 'active' === $subscription->status ) : ?>
		<a class="button sfiler-btn" href="<?php echo esc_url( $nonce_url( 'pause' ) ); ?>"><?php esc_html_e( 'Pause', 'subscript-filter' ); ?></a>
	<?php elseif ( in_array( $subscription->status, array( 'on-hold', 'cancelled' ), true ) ) : ?>
		<a class="button sfiler-btn" href="<?php echo esc_url( $nonce_url( 'reactivate' ) ); ?>"><?php esc_html_e( 'Reactivate', 'subscript-filter' ); ?></a>
	<?php endif; ?>

	<?php if ( in_array( $subscription->status, array( 'active', 'on-hold' ), true ) ) : ?>
		<a class="button" onclick="return confirm('<?php echo esc_js( __( 'Cancel this subscription?', 'subscript-filter' ) ); ?>');" href="<?php echo esc_url( $nonce_url( 'cancel' ) ); ?>"><?php esc_html_e( 'Cancel', 'subscript-filter' ); ?></a>
	<?php endif; ?>
</p>

<?php if ( count( $frequencies ) > 1 && 'cancelled' !== $subscription->status ) : ?>
	<h3><?php esc_html_e( 'Change billing frequency', 'subscript-filter' ); ?></h3>
	<form method="post" action="<?php echo esc_url( wc_get_account_endpoint_url( 'subscriptions' ) ); ?>">
		<input type="hidden" name="sfiler_action" value="change_frequency" />
		<input type="hidden" name="subscription_id" value="<?php echo esc_attr( $subscription->id ); ?>" />
		<?php wp_nonce_field( 'sfiler_action_' . $subscription->id ); ?>
		<select name="frequency_index">
			<?php foreach ( $frequencies as $index => $frequency ) : ?>
				<option value="<?php echo esc_attr( $index ); ?>" <?php selected( $frequency['count'], $subscription->interval_count ); selected( $frequency['unit'], $subscription->interval_unit ); ?>>
					<?php echo esc_html( sfiler_format_interval( $frequency['count'], $frequency['unit'] ) ); ?>
				</option>
			<?php endforeach; ?>
		</select>
		<button type="submit" class="button sfiler-btn"><?php esc_html_e( 'Update frequency', 'subscript-filter' ); ?></button>
	</form>
<?php endif; ?>

<?php if ( 'cancelled' !== $subscription->status ) : ?>
	<h3><?php esc_html_e( 'Payment method', 'subscript-filter' ); ?></h3>
	<?php if ( empty( $tokens ) ) : ?>
		<p>
			<?php esc_html_e( 'No saved payment methods found.', 'subscript-filter' ); ?>
			<a href="<?php echo esc_url( wc_get_account_endpoint_url( 'payment-methods' ) ); ?>"><?php esc_html_e( 'Add one here.', 'subscript-filter' ); ?></a>
		</p>
	<?php else : ?>
		<form method="post" action="<?php echo esc_url( wc_get_account_endpoint_url( 'subscriptions' ) ); ?>">
			<input type="hidden" name="sfiler_action" value="change_payment_method" />
			<input type="hidden" name="subscription_id" value="<?php echo esc_attr( $subscription->id ); ?>" />
			<?php wp_nonce_field( 'sfiler_action_' . $subscription->id ); ?>
			<select name="payment_token_id">
				<?php foreach ( $tokens as $token ) : ?>
					<option value="<?php echo esc_attr( $token->get_id() ); ?>" <?php selected( $token->get_token(), $subscription->stripe_payment_method_id ); ?>>
						<?php echo esc_html( $token->get_display_name() ); ?>
					</option>
				<?php endforeach; ?>
			</select>
			<button type="submit" class="button sfiler-btn"><?php esc_html_e( 'Use this payment method', 'subscript-filter' ); ?></button>
		</form>
	<?php endif; ?>
<?php endif; ?>

<h3><?php esc_html_e( 'Order history', 'subscript-filter' ); ?></h3>
<?php if ( ! $subscription->parent_order_id && empty( $orders ) ) : ?>
	<p><?php esc_html_e( 'No orders yet.', 'subscript-filter' ); ?></p>
<?php else : ?>
	<table class="woocommerce-orders-table shop_table shop_table_responsive">
		<thead>
			<tr>
				<th><?php esc_html_e( 'Order', 'subscript-filter' ); ?></th>
				<th><?php esc_html_e( 'Date', 'subscript-filter' ); ?></th>
				<th><?php esc_html_e( 'Total', 'subscript-filter' ); ?></th>
				<th><?php esc_html_e( 'Status', 'subscript-filter' ); ?></th>
			</tr>
		</thead>
		<tbody>
			<?php if ( $subscription->parent_order_id && $parent_order = wc_get_order( $subscription->parent_order_id ) ) : ?>
				<tr>
					<td><a href="<?php echo esc_url( $parent_order->get_view_order_url() ); ?>">#<?php echo esc_html( $parent_order->get_order_number() ); ?></a></td>
					<td><?php echo esc_html( wc_format_datetime( $parent_order->get_date_created() ) ); ?></td>
					<td><?php echo wp_kses_post( $parent_order->get_formatted_order_total() ); ?></td>
					<td><?php echo esc_html( wc_get_order_status_name( $parent_order->get_status() ) ); ?></td>
				</tr>
			<?php endif; ?>
			<?php foreach ( $orders as $order ) : ?>
				<tr>
					<td><a href="<?php echo esc_url( $order->get_view_order_url() ); ?>">#<?php echo esc_html( $order->get_order_number() ); ?></a></td>
					<td><?php echo esc_html( wc_format_datetime( $order->get_date_created() ) ); ?></td>
					<td><?php echo wp_kses_post( $order->get_formatted_order_total() ); ?></td>
					<td><?php echo esc_html( wc_get_order_status_name( $order->get_status() ) ); ?></td>
				</tr>
			<?php endforeach; ?>
		</tbody>
	</table>
<?php endif; ?>
