<?php
/**
 * My Account > Subscriptions. Available vars: $subscriptions, $units.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<table class="woocommerce-orders-table shop_table shop_table_responsive sfiler-account-subscriptions">
	<thead>
		<tr>
			<th><?php esc_html_e( 'Product', 'subscript-filter' ); ?></th>
			<th><?php esc_html_e( 'Amount', 'subscript-filter' ); ?></th>
			<th><?php esc_html_e( 'Frequency', 'subscript-filter' ); ?></th>
			<th><?php esc_html_e( 'Next payment', 'subscript-filter' ); ?></th>
			<th><?php esc_html_e( 'Status', 'subscript-filter' ); ?></th>
			<th><?php esc_html_e( 'Actions', 'subscript-filter' ); ?></th>
		</tr>
	</thead>
	<tbody>
		<?php if ( empty( $subscriptions ) ) : ?>
			<tr>
				<td colspan="6"><?php esc_html_e( 'You have no subscriptions yet.', 'subscript-filter' ); ?></td>
			</tr>
		<?php else : ?>
			<?php foreach ( $subscriptions as $subscription ) : ?>
				<tr>
					<td><?php echo esc_html( $subscription->product_name ); ?></td>
					<td><?php echo wc_price( $subscription->line_total, array( 'currency' => $subscription->currency ) ); ?></td>
					<td><?php echo esc_html( sprintf( __( 'Every %1$d %2$s', 'subscript-filter' ), $subscription->interval_count, strtolower( $units[ $subscription->interval_unit ] ) ) ); ?></td>
					<td><?php echo esc_html( date_i18n( get_option( 'date_format' ), strtotime( $subscription->next_payment_date ) ) ); ?></td>
					<td><?php echo esc_html( ucfirst( str_replace( '-', ' ', $subscription->status ) ) ); ?></td>
					<td>
						<?php if ( 'active' === $subscription->status ) : ?>
							<a class="button" href="<?php echo esc_url( wp_nonce_url( add_query_arg( array( 'sfiler_action' => 'pause', 'subscription_id' => $subscription->id ), wc_get_account_endpoint_url( 'subscriptions' ) ), 'sfiler_action_' . $subscription->id ) ); ?>"><?php esc_html_e( 'Pause', 'subscript-filter' ); ?></a>
						<?php elseif ( 'on-hold' === $subscription->status ) : ?>
							<a class="button" href="<?php echo esc_url( wp_nonce_url( add_query_arg( array( 'sfiler_action' => 'resume', 'subscription_id' => $subscription->id ), wc_get_account_endpoint_url( 'subscriptions' ) ), 'sfiler_action_' . $subscription->id ) ); ?>"><?php esc_html_e( 'Resume', 'subscript-filter' ); ?></a>
						<?php endif; ?>

						<?php if ( in_array( $subscription->status, array( 'active', 'on-hold' ), true ) ) : ?>
							<a class="button" onclick="return confirm('<?php echo esc_js( __( 'Cancel this subscription?', 'subscript-filter' ) ); ?>');" href="<?php echo esc_url( wp_nonce_url( add_query_arg( array( 'sfiler_action' => 'cancel', 'subscription_id' => $subscription->id ), wc_get_account_endpoint_url( 'subscriptions' ) ), 'sfiler_action_' . $subscription->id ) ); ?>"><?php esc_html_e( 'Cancel', 'subscript-filter' ); ?></a>
						<?php endif; ?>
					</td>
				</tr>
			<?php endforeach; ?>
		<?php endif; ?>
	</tbody>
</table>
