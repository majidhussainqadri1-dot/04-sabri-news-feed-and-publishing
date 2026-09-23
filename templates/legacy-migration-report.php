<?php
defined( 'ABSPATH' ) || exit;

$data = SNFLA_Cross_File_Contracts::report_data();

if ( function_exists( 'get_header' ) ) {
	get_header();
}
?>
<main id="primary" class="site-main snfla-report-page" tabindex="-1">
	<section class="snfla-report-card" aria-labelledby="snfla-report-title">
		<h1 id="snfla-report-title"><?php echo esc_html__( 'File 04 Legacy Migration Report', SNFLA_TEXT_DOMAIN ); ?></h1>
		<p><?php echo esc_html__( 'Restricted, privacy-minimized migration and compatibility diagnostics. Canonical publishing remains owned by File 21.', SNFLA_TEXT_DOMAIN ); ?></p>
		<pre class="snfla-json"><?php echo esc_html( wp_json_encode( $data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) ); ?></pre>
	</section>
</main>
<?php
if ( function_exists( 'get_footer' ) ) {
	get_footer();
}
