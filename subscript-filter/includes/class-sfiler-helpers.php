<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Shared helper functions used across product settings, cron math, and templates.
 */
function sfiler_get_interval_units() {
	return array(
		'day'   => __( 'Day(s)', 'subscript-filter' ),
		'week'  => __( 'Week(s)', 'subscript-filter' ),
		'month' => __( 'Month(s)', 'subscript-filter' ),
		'year'  => __( 'Year(s)', 'subscript-filter' ),
	);
}

function sfiler_calculate_next_payment_date( $from_timestamp, $interval_count, $interval_unit ) {
	$interval_count = max( 1, (int) $interval_count );
	$modifier       = '+' . $interval_count . ' ' . $interval_unit;
	return date( 'Y-m-d H:i:s', strtotime( $modifier, (int) $from_timestamp ) );
}

function sfiler_get_statuses() {
	return array(
		'active'         => __( 'Active', 'subscript-filter' ),
		'on-hold'        => __( 'On hold', 'subscript-filter' ),
		'pending-cancel' => __( 'Pending cancellation', 'subscript-filter' ),
		'cancelled'      => __( 'Cancelled', 'subscript-filter' ),
		'expired'        => __( 'Expired', 'subscript-filter' ),
	);
}

function sfiler_format_interval( $count, $unit ) {
	$units = sfiler_get_interval_units();
	$label = isset( $units[ $unit ] ) ? $units[ $unit ] : $unit;
	return sprintf(
		/* translators: 1: interval count, 2: interval unit label */
		__( 'Every %1$d %2$s', 'subscript-filter' ),
		$count,
		strtolower( $label )
	);
}

