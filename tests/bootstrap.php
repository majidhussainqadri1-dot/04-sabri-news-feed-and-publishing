<?php
error_reporting( E_ALL );
define( 'ABSPATH', __DIR__ . '/' );
define( 'DAY_IN_SECONDS', 86400 );
define( 'HOUR_IN_SECONDS', 3600 );
define( 'MINUTE_IN_SECONDS', 60 );

final class WP_Error {
    private $code;
    private $message;
    private $data;
    public function __construct( $code = '', $message = '', $data = null ) { $this->code = $code; $this->message = $message; $this->data = $data; }
    public function get_error_code() { return $this->code; }
    public function get_error_message() { return $this->message; }
    public function get_error_data() { return $this->data; }
}
class WP_Post {}
class WP_Comment {}
class WP_Term {}
function is_wp_error( $value ) { return $value instanceof WP_Error; }
function absint( $value ) { return abs( (int) $value ); }
function sanitize_key( $value ) { return strtolower( preg_replace( '/[^a-z0-9_\-]/', '', (string) $value ) ); }
function sanitize_text_field( $value ) { return trim( preg_replace( '/[\x00-\x1F\x7F]/u', '', strip_tags( (string) $value ) ) ); }
function wp_json_encode( $value, $flags = 0 ) { return json_encode( $value, $flags | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ); }
function wp_salt( $scheme = 'auth' ) { return 'test-salt-' . $scheme; }
function get_option( $name, $default = false ) { global $snfla_test_options; return array_key_exists( $name, $snfla_test_options ) ? $snfla_test_options[ $name ] : $default; }
function update_option( $name, $value, $autoload = null ) { global $snfla_test_options; unset( $autoload ); $snfla_test_options[ $name ] = $value; return true; }
function wp_generate_uuid4() { return '11111111-2222-4333-8444-555555555555'; }
function current_time( $type, $gmt = false ) { unset( $type, $gmt ); return gmdate( 'Y-m-d H:i:s' ); }

$snfla_test_options = array();
require_once dirname( __DIR__ ) . '/includes/class-snfla-checksum.php';
require_once dirname( __DIR__ ) . '/includes/class-snfla-integrity.php';
require_once dirname( __DIR__ ) . '/includes/class-snfla-audit.php';
require_once dirname( __DIR__ ) . '/includes/class-snfla-schema.php';
