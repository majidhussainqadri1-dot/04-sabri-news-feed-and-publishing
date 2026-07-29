<?php
defined( 'ABSPATH' ) || exit;

/**
 * Event-only bridge to File 19 — Sabri Unified Notifications.
 * File 04 never stores or delivers notifications itself.
 */
final class SNP_Notification_Adapter {
	public static function emit( $type, array $recipient_ids, array $payload = array() ) {
		$type          = sanitize_key( $type );
		$recipient_ids = array_values( array_unique( array_filter( array_map( 'absint', $recipient_ids ) ) ) );
		$event         = array(
			'source'        => 'file_04',
			'type'          => $type,
			'recipient_ids' => $recipient_ids,
			'payload'       => $payload,
			'created_at'    => current_time( 'mysql', true ),
		);
		do_action( 'sabri_unified_notification_event', $event );
		do_action( 'snp_notification_event', $event );
	}

	public static function editorial_recipients() {
		$ids = apply_filters( 'snp_editorial_recipient_ids', array() );
		return is_array( $ids ) ? array_values( array_unique( array_filter( array_map( 'absint', $ids ) ) ) ) : array();
	}
}
