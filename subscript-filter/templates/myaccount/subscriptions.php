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
					<td><?php echo esc_html( sfiler_format_interval( $subscription->interval_count, $subscription->interval_unit ) ); ?></td>
					<td><?php echo esc_html( date_i18n( get_option( 'date_format' ), strtotime( $subscription->next_payment_date ) ) ); ?></td>
					<td><?php echo sfiler_status_badge_html( $subscription->status ); ?></td>
					<td>
						<a class="button" href="<?php echo esc_url( Sfiler_My_Account::get_view_url( $subscription->id ) ); ?>"><?php esc_html_e( 'View', 'subscript-filter' ); ?></a>
					</td>
				</tr>
			<?php endforeach; ?>
		<?php endif; ?>
	</tbody>
</table>
