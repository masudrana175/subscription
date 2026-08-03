<?php
/**
 * Admin single-subscription view/edit. Vars: $subscription, $notes, $orders, $units, $statuses.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="wrap">
	<h1><?php echo esc_html( sprintf( __( 'Subscription #%d', 'subscript-filter' ), $subscription->id ) ); ?></h1>
	<a href="<?php echo esc_url( admin_url( 'admin.php?page=sfiler-subscriptions' ) ); ?>">&larr; <?php esc_html_e( 'Back to subscriptions', 'subscript-filter' ); ?></a>

	<div id="poststuff" style="margin-top: 1em;">
		<div id="post-body" class="metabox-holder columns-2">
			<div id="post-body-content">
				<div class="postbox">
					<h2 class="hndle"><span><?php esc_html_e( 'Details', 'subscript-filter' ); ?></span></h2>
					<div class="inside">
						<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
							<input type="hidden" name="action" value="sfiler_update_subscription" />
							<input type="hidden" name="subscription_id" value="<?php echo esc_attr( $subscription->id ); ?>" />
							<?php wp_nonce_field( 'sfiler_update_subscription' ); ?>
							<table class="form-table">
								<tr>
									<th><?php esc_html_e( 'Customer', 'subscript-filter' ); ?></th>
									<td>
										<?php
										$user = get_userdata( $subscription->customer_id );
										echo $user ? esc_html( $user->display_name . ' (' . $user->user_email . ')' ) : esc_html__( 'Guest', 'subscript-filter' );
										?>
									</td>
								</tr>
								<tr>
									<th><?php esc_html_e( 'Product', 'subscript-filter' ); ?></th>
									<td><?php echo esc_html( $subscription->product_name ); ?></td>
								</tr>
								<tr>
									<th><label for="line_total"><?php esc_html_e( 'Recurring amount', 'subscript-filter' ); ?></label></th>
									<td><input type="number" step="0.01" min="0" id="line_total" name="line_total" value="<?php echo esc_attr( $subscription->line_total ); ?>" /> <?php echo esc_html( $subscription->currency ); ?></td>
								</tr>
								<tr>
									<th><label for="interval_count"><?php esc_html_e( 'Interval', 'subscript-filter' ); ?></label></th>
									<td>
										<?php esc_html_e( 'Every', 'subscript-filter' ); ?>
										<input type="number" min="1" step="1" style="width:70px" id="interval_count" name="interval_count" value="<?php echo esc_attr( $subscription->interval_count ); ?>" />
										<select name="interval_unit">
											<?php foreach ( $units as $unit_key => $unit_label ) : ?>
												<option value="<?php echo esc_attr( $unit_key ); ?>" <?php selected( $subscription->interval_unit, $unit_key ); ?>><?php echo esc_html( $unit_label ); ?></option>
											<?php endforeach; ?>
										</select>
									</td>
								</tr>
								<tr>
									<th><label for="next_payment_date"><?php esc_html_e( 'Next payment date (UTC)', 'subscript-filter' ); ?></label></th>
									<td><input type="text" class="regular-text" id="next_payment_date" name="next_payment_date" value="<?php echo esc_attr( $subscription->next_payment_date ); ?>" placeholder="YYYY-MM-DD HH:MM:SS" /></td>
								</tr>
								<tr>
									<th><label for="status"><?php esc_html_e( 'Status', 'subscript-filter' ); ?></label></th>
									<td>
										<select id="status" name="status">
											<?php foreach ( $statuses as $status_key => $status_label ) : ?>
												<option value="<?php echo esc_attr( $status_key ); ?>" <?php selected( $subscription->status, $status_key ); ?>><?php echo esc_html( $status_label ); ?></option>
											<?php endforeach; ?>
										</select>
									</td>
								</tr>
								<tr>
									<th><label for="stripe_customer_id"><?php esc_html_e( 'Stripe customer ID', 'subscript-filter' ); ?></label></th>
									<td><input type="text" class="regular-text" id="stripe_customer_id" name="stripe_customer_id" value="<?php echo esc_attr( $subscription->stripe_customer_id ); ?>" /></td>
								</tr>
								<tr>
									<th><label for="stripe_payment_method_id"><?php esc_html_e( 'Stripe payment method ID', 'subscript-filter' ); ?></label></th>
									<td><input type="text" class="regular-text" id="stripe_payment_method_id" name="stripe_payment_method_id" value="<?php echo esc_attr( $subscription->stripe_payment_method_id ); ?>" /></td>
								</tr>
								<tr>
									<th><?php esc_html_e( 'Failed payments', 'subscript-filter' ); ?></th>
									<td><?php echo esc_html( $subscription->failed_payment_count ); ?></td>
								</tr>
							</table>
							<?php submit_button( __( 'Save changes', 'subscript-filter' ) ); ?>
						</form>
					</div>
				</div>

				<div class="postbox">
					<h2 class="hndle"><span><?php esc_html_e( 'Related orders', 'subscript-filter' ); ?></span></h2>
					<div class="inside">
						<?php if ( $subscription->parent_order_id ) : ?>
							<p>
								<strong><?php esc_html_e( 'Initial order:', 'subscript-filter' ); ?></strong>
								<a href="<?php echo esc_url( admin_url( 'post.php?post=' . $subscription->parent_order_id . '&action=edit' ) ); ?>">#<?php echo esc_html( $subscription->parent_order_id ); ?></a>
							</p>
						<?php endif; ?>
						<?php if ( empty( $orders ) ) : ?>
							<p><?php esc_html_e( 'No renewal orders yet.', 'subscript-filter' ); ?></p>
						<?php else : ?>
							<table class="widefat striped">
								<thead>
									<tr>
										<th><?php esc_html_e( 'Order', 'subscript-filter' ); ?></th>
										<th><?php esc_html_e( 'Date', 'subscript-filter' ); ?></th>
										<th><?php esc_html_e( 'Total', 'subscript-filter' ); ?></th>
										<th><?php esc_html_e( 'Status', 'subscript-filter' ); ?></th>
									</tr>
								</thead>
								<tbody>
									<?php foreach ( $orders as $order ) : ?>
										<tr>
											<td><a href="<?php echo esc_url( admin_url( 'post.php?post=' . $order->get_id() . '&action=edit' ) ); ?>">#<?php echo esc_html( $order->get_id() ); ?></a></td>
											<td><?php echo esc_html( wc_format_datetime( $order->get_date_created() ) ); ?></td>
											<td><?php echo wp_kses_post( $order->get_formatted_order_total() ); ?></td>
											<td><?php echo esc_html( wc_get_order_status_name( $order->get_status() ) ); ?></td>
										</tr>
									<?php endforeach; ?>
								</tbody>
							</table>
						<?php endif; ?>
					</div>
				</div>
			</div>

			<div id="postbox-container-1" class="postbox-container">
				<div class="postbox">
					<h2 class="hndle"><span><?php esc_html_e( 'Activity log', 'subscript-filter' ); ?></span></h2>
					<div class="inside">
						<?php if ( empty( $notes ) ) : ?>
							<p><?php esc_html_e( 'No activity yet.', 'subscript-filter' ); ?></p>
						<?php else : ?>
							<ul style="margin:0;">
								<?php foreach ( $notes as $note ) : ?>
									<li style="border-bottom:1px solid #eee; padding:0.5em 0;">
										<div><?php echo esc_html( $note->note ); ?></div>
										<small style="color:#777;"><?php echo esc_html( date_i18n( 'Y-m-d H:i', strtotime( $note->created_at ) ) ); ?></small>
									</li>
								<?php endforeach; ?>
							</ul>
						<?php endif; ?>
					</div>
				</div>
			</div>
		</div>
	</div>
</div>
