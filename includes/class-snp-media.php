<?php
defined( 'ABSPATH' ) || exit;

final class SNP_Media {
	const MAX_BYTES  = 5242880;
	const MAX_PIXELS = 24000000;

	public static function stage_request( $field, $post_id, $purpose, $consent_record_id = '' ) {
		if ( empty( $_FILES[ $field ]['name'] ) ) {
			return 0;
		}
		$file = $_FILES[ $field ];
		if ( ! empty( $file['error'] ) || ! is_uploaded_file( $file['tmp_name'] ) ) {
			return new WP_Error( 'snp_upload', 'The image upload could not be read.' );
		}
		if ( (int) $file['size'] < 1 || (int) $file['size'] > self::MAX_BYTES ) {
			return new WP_Error( 'snp_upload_size', 'Each image must be 5 MB or smaller.' );
		}
		$allowed = array( 'jpg|jpeg' => 'image/jpeg', 'png' => 'image/png', 'webp' => 'image/webp' );
		$check   = wp_check_filetype_and_ext( $file['tmp_name'], $file['name'], $allowed );
		if ( empty( $check['type'] ) || ! in_array( $check['type'], array_values( $allowed ), true ) ) {
			return new WP_Error( 'snp_upload_type', 'Only genuine JPG, PNG, and WebP images are accepted.' );
		}
		$dimensions = @getimagesize( $file['tmp_name'] );
		if ( ! is_array( $dimensions ) || empty( $dimensions[0] ) || empty( $dimensions[1] ) ) {
			return new WP_Error( 'snp_upload_image', 'The uploaded file is not a readable image.' );
		}
		if ( (int) $dimensions[0] * (int) $dimensions[1] > self::MAX_PIXELS ) {
			return new WP_Error( 'snp_upload_pixels', 'The image dimensions are too large.' );
		}
		$bytes = file_get_contents( $file['tmp_name'] );
		if ( false === $bytes ) {
			return new WP_Error( 'snp_upload_read', 'The image could not be staged safely.' );
		}
		$encrypted = self::encrypt( $bytes );
		if ( is_wp_error( $encrypted ) ) {
			return $encrypted;
		}
		global $wpdb;
		$inserted = $wpdb->insert(
			$wpdb->prefix . 'snp_media_staging',
			array(
				'post_id'            => absint( $post_id ),
				'owner_user_id'      => get_current_user_id(),
				'purpose'            => sanitize_key( $purpose ),
				'mime_type'          => sanitize_mime_type( $check['type'] ),
				'original_extension' => sanitize_key( pathinfo( $file['name'], PATHINFO_EXTENSION ) ),
				'ciphertext_b64'      => base64_encode( $encrypted['ciphertext'] ),
				'nonce_hex'          => bin2hex( $encrypted['nonce'] ),
				'tag_hex'            => bin2hex( $encrypted['tag'] ),
				'consent_record_id'   => sanitize_text_field( $consent_record_id ),
				'created_at'          => current_time( 'mysql', true ),
			),
			array( '%d', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s' )
		);
		if ( false === $inserted || ! $wpdb->insert_id ) {
			return new WP_Error( 'snp_media_stage', 'The encrypted image could not be staged.' );
		}
		return absint( $wpdb->insert_id );
	}

	private static function encrypt( $bytes ) {
		if ( ! function_exists( 'openssl_encrypt' ) ) {
			return new WP_Error( 'snp_crypto_missing', 'Secure media staging is unavailable.' );
		}
		$key   = hash( 'sha256', wp_salt( 'auth' ) . '|snp-media-v1', true );
		$nonce = random_bytes( 12 );
		$tag   = '';
		$data  = openssl_encrypt( $bytes, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $nonce, $tag, 'snp-media-v1', 16 );
		return false === $data ? new WP_Error( 'snp_crypto', 'Secure media staging failed.' ) : array( 'ciphertext' => $data, 'nonce' => $nonce, 'tag' => $tag );
	}

	private static function decrypt( $row ) {
		$key        = hash( 'sha256', wp_salt( 'auth' ) . '|snp-media-v1', true );
		$ciphertext = base64_decode( $row->ciphertext_b64, true );
		$nonce      = ctype_xdigit( (string) $row->nonce_hex ) ? hex2bin( $row->nonce_hex ) : false;
		$tag        = ctype_xdigit( (string) $row->tag_hex ) ? hex2bin( $row->tag_hex ) : false;
		if ( false === $ciphertext || false === $nonce || false === $tag ) {
			return false;
		}
		return openssl_decrypt( $ciphertext, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $nonce, $tag, 'snp-media-v1' );
	}

	public static function staged_for_post( $post_id ) {
		global $wpdb;
		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT id,post_id,owner_user_id,purpose,mime_type,created_at FROM {$wpdb->prefix}snp_media_staging WHERE post_id=%d ORDER BY id ASC",
				absint( $post_id )
			)
		);
	}

	public static function stream_staged( $staged_id ) {
		global $wpdb;
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}snp_media_staging WHERE id=%d", absint( $staged_id ) ) );
		if ( ! $row || ! in_array( $row->mime_type, array( 'image/jpeg', 'image/png', 'image/webp' ), true ) ) {
			return new WP_Error( 'snp_media_preview', 'The protected image is unavailable.' );
		}
		$post = get_post( absint( $row->post_id ) );
		if ( ! $post || SNP_Content::TYPE !== $post->post_type || (int) $row->owner_user_id !== (int) $post->post_author ) {
			return new WP_Error( 'snp_media_preview_owner', 'Protected image ownership could not be verified.' );
		}
		$bytes = self::decrypt( $row );
		if ( false === $bytes ) {
			return new WP_Error( 'snp_media_preview_decrypt', 'The protected image could not be decrypted.' );
		}
		if ( headers_sent() ) {
			return new WP_Error( 'snp_media_preview_headers', 'The protected image cannot be streamed after output has started.' );
		}
		nocache_headers();
		header( 'Cache-Control: private, no-store, no-cache, must-revalidate, max-age=0', true );
		header( 'Pragma: no-cache', true );
		header( 'X-Robots-Tag: noindex, nofollow, noarchive', true );
		header( 'Referrer-Policy: no-referrer', true );
		header( 'X-Frame-Options: DENY', true );
		header( 'X-Content-Type-Options: nosniff', true );
		header( "Content-Security-Policy: default-src 'none'; img-src 'self' data:", true );
		header( 'Content-Type: ' . $row->mime_type, true );
		header( 'Content-Disposition: inline; filename="protected-publication-image"', true );
		header( 'Content-Length: ' . strlen( $bytes ), true );
		echo $bytes; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		exit;
	}

	public static function materialize_for_publish( $post_id ) {
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/media.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';
		global $wpdb;
		$created    = array();
		$staged_ids = array();
		$rows = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}snp_media_staging WHERE post_id = %d ORDER BY id ASC", absint( $post_id ) ) );
		$post = get_post( absint( $post_id ) );
		foreach ( $rows as $row ) {
			if ( ! $post || (int) $row->owner_user_id !== (int) $post->post_author || ! in_array( $row->purpose, array( 'featured', 'additional' ), true ) ) {
				self::delete_created( $created );
				return new WP_Error( 'snp_media_owner', 'Staged media ownership could not be verified.' );
			}
			$bytes = self::decrypt( $row );
			if ( false === $bytes ) {
				self::delete_created( $created );
				return new WP_Error( 'snp_media_decrypt', 'A staged image could not be decrypted.' );
			}
			$extension = 'image/png' === $row->mime_type ? 'png' : ( 'image/webp' === $row->mime_type ? 'webp' : 'jpg' );
			$upload    = wp_upload_bits( 'publication-' . absint( $post_id ) . '-' . sanitize_key( $row->purpose ) . '-' . wp_generate_uuid4() . '.' . $extension, null, $bytes );
			if ( ! empty( $upload['error'] ) ) {
				self::delete_created( $created );
				return new WP_Error( 'snp_media_write', $upload['error'] );
			}
			$editor = wp_get_image_editor( $upload['file'] );
			if ( is_wp_error( $editor ) ) {
				@unlink( $upload['file'] );
				self::delete_created( $created );
				return new WP_Error( 'snp_media_editor', 'The image could not be safely re-encoded.' );
			}
			$editor->set_quality( 90 );
			$saved = $editor->save( $upload['file'], $row->mime_type );
			if ( is_wp_error( $saved ) ) {
				@unlink( $upload['file'] );
				self::delete_created( $created );
				return new WP_Error( 'snp_media_metadata', 'Image metadata could not be stripped safely.' );
			}
			if ( ! empty( $saved['path'] ) ) {
				$upload['file'] = $saved['path'];
			}
			$attachment_id = wp_insert_attachment(
				array(
					'post_mime_type' => $row->mime_type,
					'post_title'     => 'Publication image',
					'post_status'    => 'inherit',
					'post_parent'    => absint( $post_id ),
				),
				$upload['file'],
				absint( $post_id ),
				true
			);
			if ( is_wp_error( $attachment_id ) ) {
				@unlink( $upload['file'] );
				self::delete_created( $created );
				return $attachment_id;
			}
			$created[] = absint( $attachment_id );
			wp_update_attachment_metadata( $attachment_id, wp_generate_attachment_metadata( $attachment_id, $upload['file'] ) );
			update_post_meta( $attachment_id, '_snp_owned_media', '1' );
			update_post_meta( $attachment_id, '_snp_owner_post_id', absint( $post_id ) );
			update_post_meta( $attachment_id, '_snp_media_purpose', sanitize_key( $row->purpose ) );
			update_post_meta( $attachment_id, '_snp_consent_record_id', sanitize_text_field( $row->consent_record_id ) );
			if ( 'featured' === $row->purpose ) {
				set_post_thumbnail( $post_id, $attachment_id );
			} else {
				update_post_meta( $post_id, '_snp_additional_image_id', $attachment_id );
			}
			$staged_ids[] = absint( $row->id );
		}
		foreach ( $staged_ids as $staged_id ) {
			$wpdb->delete( $wpdb->prefix . 'snp_media_staging', array( 'id' => $staged_id ), array( '%d' ) );
		}
		return true;
	}

	private static function delete_created( array $ids ) {
		foreach ( $ids as $id ) {
			$id = absint( $id );
			$post_id = absint( get_post_meta( $id, '_snp_owner_post_id', true ) );
			if ( $post_id && get_post_thumbnail_id( $post_id ) === $id ) {
				delete_post_thumbnail( $post_id );
			}
			if ( $post_id && absint( get_post_meta( $post_id, '_snp_additional_image_id', true ) ) === $id ) {
				delete_post_meta( $post_id, '_snp_additional_image_id' );
			}
			wp_delete_attachment( $id, true );
		}
	}

	public static function delete_post_media( $post_id ) {
		global $wpdb;
		$post_id = absint( $post_id );
		$wpdb->delete( $wpdb->prefix . 'snp_media_staging', array( 'post_id' => $post_id ), array( '%d' ) );
		$ids = get_posts(
			array(
				'post_type'      => 'attachment',
				'post_status'    => 'any',
				'posts_per_page' => -1,
				'fields'         => 'ids',
				'meta_query'     => array(
					array( 'key' => '_snp_owned_media', 'value' => '1' ),
					array( 'key' => '_snp_owner_post_id', 'value' => $post_id, 'type' => 'NUMERIC' ),
				),
			)
		);
		foreach ( $ids as $id ) {
			if ( $post_id === absint( get_post_meta( $id, '_snp_owner_post_id', true ) ) ) {
				wp_delete_attachment( $id, true );
			}
		}
	}

	public static function cleanup_staging() {
		global $wpdb;
		$cutoff = gmdate( 'Y-m-d H:i:s', time() - 7 * DAY_IN_SECONDS );
		$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->prefix}snp_media_staging WHERE created_at < %s", $cutoff ) );
	}
}
