<?php
defined( 'ABSPATH' ) || exit;

final class SNP_Admin {
	public function hooks() {
		add_action( 'admin_menu', array( $this, 'menu' ) );
		add_action( 'admin_post_snp_review_post', array( $this, 'review' ) );
		add_action( 'admin_post_snp_resolve_report', array( $this, 'resolve_report' ) );
		add_action( 'admin_post_snp_preview_staged_media', array( $this, 'preview_staged_media' ) );
		add_action( 'admin_notices', array( $this, 'notice' ) );
	}

	public function menu() {
		if ( ! SNP_Permissions::can_moderate() && ! SNP_Permissions::can_review_patient_privacy() && ! SNP_Permissions::can_view_reports() ) {
			return;
		}
		$parent = apply_filters( 'snp_admin_parent_slug', '' );
		$has_review = SNP_Permissions::can_moderate() || SNP_Permissions::can_review_patient_privacy();
		if ( $has_review ) {
			$menu_cap = SNP_Permissions::can_moderate() ? SNP_Permissions::CAP_MODERATE : SNP_Permissions::CAP_PRIVACY;
			if ( $parent ) {
				add_submenu_page( $parent, 'News & Publishing', 'News & Publishing', $menu_cap, 'sabri-news-publishing', array( $this, 'dashboard' ) );
			} else {
				add_menu_page( 'News & Publishing', 'News & Publishing', $menu_cap, 'sabri-news-publishing', array( $this, 'dashboard' ), 'dashicons-megaphone', 28 );
			}
		}
		if ( SNP_Permissions::can_view_reports() ) {
			if ( $has_review ) {
				add_submenu_page( 'sabri-news-publishing', 'Reported Publications', 'Reports', SNP_Permissions::CAP_REPORTS, 'sabri-news-reports', array( $this, 'reports' ) );
			} else {
				add_menu_page( 'Reported Publications', 'Publication Reports', SNP_Permissions::CAP_REPORTS, 'sabri-news-reports', array( $this, 'reports' ), 'dashicons-shield', 29 );
			}
		}
	}

	public function dashboard() {
		$this->guard_review();
		$state = isset( $_GET['state'] ) ? sanitize_key( wp_unslash( $_GET['state'] ) ) : 'pending';
		if ( ! in_array( $state, SNP_Publication_State::states(), true ) ) {
			$state = 'pending';
		}
		$posts = get_posts(
			array(
				'post_type'      => SNP_Content::TYPE,
				'post_status'    => array( 'draft', 'pending', 'private', 'publish' ),
				'posts_per_page' => 100,
				'orderby'        => 'date',
				'order'          => 'DESC',
				'meta_key'       => SNP_Publication_State::STATE_META,
				'meta_value'     => $state,
			)
		);
		?>
		<div class="wrap snp-admin"><h1>Publication Review</h1><p>File 04 cannot create editorial authority. File 00 capabilities, File 09 doctor eligibility, and protected Patient Case review are mandatory.</p>
		<nav class="snp-admin-tabs"><?php foreach ( array( 'pending' => 'Pending', 'privacy_review' => 'Privacy Review', 'approved' => 'Approved', 'rejected' => 'Rejected', 'hidden' => 'Hidden', 'withdrawn' => 'Consent Withdrawn' ) as $key => $label ) : ?><a class="<?php echo $state === $key ? 'is-active' : ''; ?>" href="<?php echo esc_url( add_query_arg( array( 'page' => 'sabri-news-publishing', 'state' => $key ), admin_url( 'admin.php' ) ) ); ?>"><?php echo esc_html( $label ); ?></a><?php endforeach; ?></nav>
		<table class="widefat striped"><thead><tr><th>Publication</th><th>Author / topic</th><th>Gate state</th><th>Review</th></tr></thead><tbody>
		<?php if ( $posts ) : foreach ( $posts as $post ) :
			$is_patient = 'patient-cases' === SNP_Content::topic_slug( $post->ID );
			$is_protected = $is_patient && 'privacy_review' === SNP_Publication_State::state( $post->ID ) && ! SNP_Permissions::can_review_patient_privacy();
			$can_preview_media = ( $is_patient && SNP_Permissions::can_review_patient_privacy() ) || ( ! $is_patient && SNP_Permissions::can_moderate() ) || ( $is_patient && ! $is_protected && SNP_Permissions::can_moderate() );
		?>
		<tr><td><?php if ( $is_protected ) : ?><strong>Protected Patient Case awaiting privacy review</strong><p>Content, title, excerpt, consent evidence, and pending media are restricted until privacy approval.</p><?php else : ?><strong><?php echo esc_html( $post->post_title ); ?></strong><p><?php echo esc_html( wp_trim_words( $post->post_excerpt, 24 ) ); ?></p><details><summary>Review full content</summary><div class="snp-admin-content"><?php echo wp_kses_post( wpautop( $post->post_content ) ); ?></div></details><?php endif; ?></td><td><?php echo esc_html( get_the_author_meta( 'display_name', $post->post_author ) ); ?><br><?php echo esc_html( SNP_Content::topic_name( $post->ID ) ?: 'Missing approved topic' ); ?><?php if ( $is_patient && SNP_Permissions::can_review_patient_privacy() ) : ?><details><summary>Protected consent evidence</summary><p>Record: <?php echo esc_html( get_post_meta( $post->ID, '_snp_case_consent_record_id', true ) ); ?><br>Version: <?php echo esc_html( get_post_meta( $post->ID, '_snp_case_consent_version', true ) ); ?><br>Obtained: <?php echo esc_html( get_post_meta( $post->ID, '_snp_case_consent_obtained_at', true ) ); ?></p><p><strong>Scope:</strong> <?php echo esc_html( get_post_meta( $post->ID, '_snp_case_consent_scope', true ) ); ?></p><p><strong>Redaction:</strong> <?php echo esc_html( get_post_meta( $post->ID, '_snp_case_redaction_summary', true ) ); ?></p></details><?php endif; ?><?php if ( $can_preview_media ) : $media_rows = SNP_Media::staged_for_post( $post->ID ); if ( $media_rows ) : ?><details><summary>Protected pending media</summary><ul><?php foreach ( $media_rows as $media_row ) : $preview_url = wp_nonce_url( add_query_arg( array( 'action' => 'snp_preview_staged_media', 'media_id' => absint( $media_row->id ) ), admin_url( 'admin-post.php' ) ), 'snp_preview_media_' . absint( $media_row->id ) ); ?><li><a href="<?php echo esc_url( $preview_url ); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html( ucfirst( $media_row->purpose ) . ' image' ); ?></a></li><?php endforeach; ?></ul></details><?php endif; endif; ?></td><td><?php echo esc_html( SNP_Publication_State::state( $post->ID ) ); ?><br>Author eligible: <?php echo SNP_Permissions::author_eligible( $post->post_author ) ? 'Yes' : 'No'; ?><br>Snapshot current: <?php echo SNP_Publication_State::snapshot_matches( $post->ID ) ? 'Yes' : 'No'; ?></td><td><?php echo $this->review_form( $post ); ?></td></tr>
		<?php endforeach; else : ?><tr><td colspan="4">No publications in this state.</td></tr><?php endif; ?></tbody></table></div>
		<?php
	}

	private function review_form( $post ) {
		ob_start();
		?>
		<form class="snp-review-form" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" method="post"><input type="hidden" name="action" value="snp_review_post"><input type="hidden" name="post_id" value="<?php echo absint( $post->ID ); ?>"><?php wp_nonce_field( 'snp_review_' . $post->ID ); ?><select name="review_action">
		<?php if ( 'patient-cases' === SNP_Content::topic_slug( $post->ID ) && SNP_Permissions::can_review_patient_privacy() ) : ?><option value="privacy_approve">Approve privacy evidence</option><option value="withdraw_consent">Record consent withdrawal</option><?php endif; ?>
		<?php if ( SNP_Permissions::can_moderate() ) : ?><option value="approve">Approve and publish</option><option value="reject">Reject</option><option value="hide">Hide</option><?php endif; ?>
		<?php if ( SNP_Permissions::can_feature() ) : ?><option value="feature">Toggle featured</option><option value="pin">Toggle pinned</option><?php endif; ?>
		</select><textarea name="note" rows="3" placeholder="Required review reason" required></textarea><button class="button button-primary" type="submit">Apply reviewed action</button></form>
		<?php
		return ob_get_clean();
	}

	public function review() {
		$post_id = isset( $_POST['post_id'] ) ? absint( $_POST['post_id'] ) : 0;
		check_admin_referer( 'snp_review_' . $post_id );
		$post   = get_post( $post_id );
		$action = isset( $_POST['review_action'] ) ? sanitize_key( wp_unslash( $_POST['review_action'] ) ) : '';
		$note   = isset( $_POST['note'] ) ? sanitize_textarea_field( wp_unslash( $_POST['note'] ) ) : '';
		$note   = function_exists( 'mb_substr' ) ? mb_substr( $note, 0, 2000 ) : substr( $note, 0, 2000 );
		if ( ! $post || SNP_Content::TYPE !== $post->post_type || ! $note ) {
			wp_die( esc_html__( 'A valid publication and mandatory review reason are required.', 'sabri-news-publishing' ), '', array( 'response' => 400 ) );
		}
		if ( 'privacy_approve' === $action ) {
			if ( ! SNP_Permissions::can_review_patient_privacy() || 'patient-cases' !== SNP_Content::topic_slug( $post_id ) || get_current_user_id() === (int) $post->post_author ) {
				$this->deny();
			}
			update_post_meta( $post_id, '_snp_case_privacy_reviewer_id', get_current_user_id() );
			update_post_meta( $post_id, '_snp_case_privacy_reviewed_at', current_time( 'mysql', true ) );
			SNP_Publication_State::set_state( $post_id, 'pending' );
			SNP_Audit::record( $post_id, 'privacy_approved', $note );
		} elseif ( 'withdraw_consent' === $action ) {
			if ( ! SNP_Permissions::can_review_patient_privacy() || 'patient-cases' !== SNP_Content::topic_slug( $post_id ) ) {
				$this->deny();
			}
			update_post_meta( $post_id, '_snp_case_consent_withdrawn', '1' );
			SNP_Media::delete_post_media( $post_id );
			wp_update_post( array( 'ID' => $post_id, 'post_status' => 'draft' ) );
			SNP_Publication_State::set_state( $post_id, 'withdrawn' );
			SNP_Audit::record( $post_id, 'consent_withdrawn', $note );
		} elseif ( 'approve' === $action ) {
			if ( ! SNP_Permissions::can_moderate() ) {
				$this->deny();
			}
			$result = SNP_Publication_State::approve( $post_id, $note );
			if ( is_wp_error( $result ) ) {
				wp_die( esc_html( $result->get_error_message() ), '', array( 'response' => 400 ) );
			}
		} elseif ( in_array( $action, array( 'reject', 'hide' ), true ) ) {
			if ( ! SNP_Permissions::can_moderate() ) {
				$this->deny();
			}
			wp_update_post( array( 'ID' => $post_id, 'post_status' => 'draft', 'comment_status' => 'closed' ) );
			SNP_Media::delete_post_media( $post_id );
			SNP_Publication_State::set_state( $post_id, 'reject' === $action ? 'rejected' : 'hidden' );
			SNP_Audit::record( $post_id, $action, $note );
		} elseif ( in_array( $action, array( 'feature', 'pin' ), true ) ) {
			if ( ! SNP_Permissions::can_feature() || ! SNP_Publication_State::public_eligible( $post_id ) ) {
				$this->deny();
			}
			$key = 'feature' === $action ? '_snp_featured' : '_snp_pinned';
			update_post_meta( $post_id, $key, '1' === get_post_meta( $post_id, $key, true ) ? '0' : '1' );
			SNP_Audit::record( $post_id, $action, $note );
			SNP_Interactions::recalculate( $post_id );
		} else {
			wp_die( esc_html__( 'Invalid review action.', 'sabri-news-publishing' ), '', array( 'response' => 400 ) );
		}
		do_action( 'snp_editorial_action', $post_id, $action );
		wp_safe_redirect( add_query_arg( array( 'page' => 'sabri-news-publishing', 'updated' => '1' ), admin_url( 'admin.php' ) ) );
		exit;
	}

	public function preview_staged_media() {
		$media_id = isset( $_GET['media_id'] ) ? absint( $_GET['media_id'] ) : 0;
		check_admin_referer( 'snp_preview_media_' . $media_id );
		global $wpdb;
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT post_id FROM {$wpdb->prefix}snp_media_staging WHERE id=%d", $media_id ) );
		if ( ! $row ) {
			wp_die( esc_html__( 'The protected image is unavailable.', 'sabri-news-publishing' ), '', array( 'response' => 404 ) );
		}
		$is_patient = 'patient-cases' === SNP_Content::topic_slug( $row->post_id );
		$state      = SNP_Publication_State::state( $row->post_id );
		$allowed    = $is_patient
			? ( SNP_Permissions::can_review_patient_privacy() || ( 'privacy_review' !== $state && SNP_Permissions::can_moderate() ) )
			: SNP_Permissions::can_moderate();
		if ( ! $allowed ) {
			$this->deny();
		}
		SNP_Audit::record( $row->post_id, 'protected_media_previewed', 'An authorized reviewer opened encrypted pending media.', array( 'media_id' => $media_id ) );
		$result = SNP_Media::stream_staged( $media_id );
		if ( is_wp_error( $result ) ) {
			wp_die( esc_html( $result->get_error_message() ), '', array( 'response' => 400 ) );
		}
		exit;
	}

	public function reports() {
		if ( ! SNP_Permissions::can_view_reports() ) {
			$this->deny();
		}
		global $wpdb;
		$reports = $wpdb->get_results( "SELECT * FROM {$wpdb->prefix}snp_reports WHERE status IN ('open','appealed') ORDER BY created_at DESC LIMIT 200" );
		?><div class="wrap snp-admin"><h1>Protected Publication Reports</h1><table class="widefat striped"><thead><tr><th>Publication</th><th>Status</th><th>Reporter</th><th>Reason</th><th>Details</th><th>Resolution</th></tr></thead><tbody><?php if ( $reports ) : foreach ( $reports as $report ) : ?><tr><td><?php echo esc_html( get_the_title( $report->post_id ) ); ?></td><td><?php echo esc_html( $report->status ); ?></td><td><?php echo esc_html( SNP_Permissions::can_view_sensitive_reports() ? get_the_author_meta( 'display_name', $report->user_id ) : 'Protected reporter' ); ?></td><td><?php echo esc_html( ucwords( str_replace( '-', ' ', $report->reason ) ) ); ?></td><td><?php echo esc_html( SNP_Permissions::can_view_sensitive_reports() ? $report->details . ( $report->appeal_note ? ' Appeal: ' . $report->appeal_note : '' ) : 'Sensitive details require elevated report access.' ); ?></td><td><form action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" method="post"><input type="hidden" name="action" value="snp_resolve_report"><input type="hidden" name="report_id" value="<?php echo absint( $report->id ); ?>"><?php wp_nonce_field( 'snp_resolve_' . $report->id ); ?><select name="outcome" required><option value="dismissed">Dismissed</option><option value="corrected">Content corrected</option><option value="hidden">Publication hidden</option><option value="escalated">Escalated</option></select><textarea name="resolution_note" required placeholder="Required resolution reason"></textarea><button class="button" type="submit">Resolve</button></form></td></tr><?php endforeach; else : ?><tr><td colspan="6">No open reports.</td></tr><?php endif; ?></tbody></table></div><?php
	}

	public function resolve_report() {
		if ( ! SNP_Permissions::can_view_reports() ) {
			$this->deny();
		}
		global $wpdb;
		$id      = isset( $_POST['report_id'] ) ? absint( $_POST['report_id'] ) : 0;
		$outcome = isset( $_POST['outcome'] ) ? sanitize_key( wp_unslash( $_POST['outcome'] ) ) : '';
		$note    = isset( $_POST['resolution_note'] ) ? sanitize_textarea_field( wp_unslash( $_POST['resolution_note'] ) ) : '';
		$note    = function_exists( 'mb_substr' ) ? mb_substr( $note, 0, 2000 ) : substr( $note, 0, 2000 );
		check_admin_referer( 'snp_resolve_' . $id );
		if ( ! in_array( $outcome, array( 'dismissed', 'corrected', 'hidden', 'escalated' ), true ) || ! $note ) {
			wp_die( esc_html__( 'A valid outcome and resolution reason are required.', 'sabri-news-publishing' ), '', array( 'response' => 400 ) );
		}
		$report = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}snp_reports WHERE id=%d AND status IN ('open','appealed')", $id ) );
		if ( ! $report ) {
			wp_die( esc_html__( 'The report is not open.', 'sabri-news-publishing' ), '', array( 'response' => 404 ) );
		}
		$appeal_status = 'appealed' === $report->status ? 'resolved' : '';
		$wpdb->update(
			$wpdb->prefix . 'snp_reports',
			array( 'status' => 'resolved', 'outcome' => $outcome, 'resolution_note' => $note, 'resolver_id' => get_current_user_id(), 'resolved_at' => current_time( 'mysql', true ), 'open_key' => null, 'appeal_status' => $appeal_status ),
			array( 'id' => $id ),
			array( '%s', '%s', '%s', '%d', '%s', '%s', '%s' ),
			array( '%d' )
		);
		if ( 'hidden' === $outcome ) {
			SNP_Media::delete_post_media( $report->post_id );
			wp_update_post( array( 'ID' => absint( $report->post_id ), 'post_status' => 'draft' ) );
			SNP_Publication_State::set_state( $report->post_id, 'hidden' );
		}
		SNP_Audit::record( $report->post_id, 'report_resolved', $note, array( 'report_id' => $id, 'outcome' => $outcome ) );
		do_action( 'snp_report_resolved', $id, $outcome, absint( $report->user_id ) );
		SNP_Notification_Adapter::emit( 'report_resolved', array( absint( $report->user_id ) ), array( 'report_id' => $id, 'post_id' => absint( $report->post_id ), 'outcome' => $outcome ) );
		wp_safe_redirect( admin_url( 'admin.php?page=sabri-news-reports' ) );
		exit;
	}

	private function guard_review() {
		if ( ! SNP_Permissions::can_moderate() && ! SNP_Permissions::can_review_patient_privacy() ) {
			$this->deny();
		}
	}

	private function deny() {
		wp_die( esc_html__( 'You are not authorized for this editorial action.', 'sabri-news-publishing' ), '', array( 'response' => 403 ) );
	}

	public function notice() {
		if ( SNP_Permissions::can_moderate() && get_transient( 'snp_activation_notice' ) ) {
			delete_transient( 'snp_activation_notice' );
			echo '<div class="notice notice-success is-dismissible"><p><strong>Sabri News Feed and Publishing 0.2.0 is active.</strong> Editorial authority is supplied by File 00 and public feed rendering belongs to File 21.</p></div>';
		}
	}
}
