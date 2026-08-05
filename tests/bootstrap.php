<?php
if ( ! defined( 'ABSPATH' ) ) { define( 'ABSPATH', __DIR__ . '/' ); }
$GLOBALS['snfla_test_options'] = array();

class WP_Error {
	private $code; private $message; private $data;
	public function __construct( $code = '', $message = '', $data = null ) { $this->code = $code; $this->message = $message; $this->data = $data; }
	public function get_error_code() { return $this->code; }
	public function get_error_message() { return $this->message; }
	public function get_error_data() { return $this->data; }
}
function is_wp_error( $value ) { return $value instanceof WP_Error; }
function sanitize_key( $value ) { return strtolower( preg_replace( '/[^a-z0-9_\-]/', '', (string) $value ) ); }
function sanitize_text_field( $value ) { return trim( strip_tags( (string) $value ) ); }
function absint( $value ) { return abs( (int) $value ); }
function get_option( $key, $default = false ) { return array_key_exists( $key, $GLOBALS['snfla_test_options'] ) ? $GLOBALS['snfla_test_options'][ $key ] : $default; }
function update_option( $key, $value, $autoload = null ) { unset( $autoload ); $GLOBALS['snfla_test_options'][ $key ] = $value; return true; }
function wp_json_encode( $value, $flags = 0 ) { return json_encode( $value, $flags ); }
function wp_salt( $scheme = 'auth' ) { return 'test-salt-' . $scheme; }
function wp_generate_uuid4() { return '00000000-0000-4000-8000-' . str_pad( (string) count( SNFLA_Audit::$events ), 12, '0', STR_PAD_LEFT ); }

final class SNFLA_Database {
	public static function acquire_lock( $name, $timeout = 5 ) { unset( $name, $timeout ); return true; }
	public static function release_lock( $name ) { unset( $name ); }
}

final class SNFLA_Audit {
	public static $events = array();
	public static $fail = false;
	public static function record( $action, $actor_id, $context = array(), $object_ref = '' ) { if ( self::$fail ) { return false; } self::$events[] = compact( 'action', 'actor_id', 'context', 'object_ref' ); return true; }
	public static function actor_digest( $actor_id ) { return hash( 'sha256', 'actor|' . (int) $actor_id ); }
}
