<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * This function generates the admin side quiz results page
 *
 * @return void
 * @since 4.4.0
 */
function qsm_generate_admin_results_page() {

	// Makes sure user has the right privledges.
	if ( ! current_user_can( 'view_qsm_quiz_result' ) ) {
		return;
	}

	// Retrieves the current stab and all registered tabs.
	global $mlwQuizMasterNext;
	$active_tab  = strtolower( str_replace( ' ', '-', isset( $_GET['tab'] ) ? sanitize_text_field( wp_unslash( $_GET['tab'] ) ) : __( 'Quiz Results', 'quiz-master-next' ) ) );
	$tab_array   = $mlwQuizMasterNext->pluginHelper->get_admin_results_tabs();
	?>
	<div class="wrap">
		<div>
			<h2 id="result_details">
				<?php esc_html_e( 'Quiz Results', 'quiz-master-next' );
				if ( strtolower( str_replace( ' ', '-', __( 'Quiz Results', 'quiz-master-next' ) ) ) == $active_tab ) { ?>
					<span id="results-screen-option-button" class="button"><?php echo esc_html__( 'Screen Options', 'quiz-master-next' ) ?></span>
				<?php } ?>
			</h2>
		</div>
		<?php $mlwQuizMasterNext->alertManager->showAlerts(); ?>
		<?php qsm_show_adverts(); ?>
		<h2 class="nav-tab-wrapper">
			<?php
			// Cycles through the tabs and creates the navigation.
			foreach ( $tab_array as $tab ) {
				$active_class = '';
				if ( $active_tab === $tab['slug'] ) {
					$active_class = 'nav-tab-active';
				}
				$tab_url = "?page=mlw_quiz_results&tab={$tab['slug']}";
				?>
				<a href="<?php echo esc_url_raw( $tab_url ); ?>" class="nav-tab <?php echo esc_attr( $active_class ); ?>"><?php echo wp_kses_post( $tab['title'] ); ?></a>
				<?php
			}
			?>
		</h2>
		<div class="result-page-wrapper">
			<?php
			// Locates the active tab and calls its content function.
			foreach ( $tab_array as $tab ) {
				if ( $active_tab === $tab['slug'] ) {
					call_user_func( $tab['function'] );
				}
			}
			?>
		</div>
	</div>
	<?php
}

/**
 * Adds Overview Tab To Admin Results Page
 *
 * @since 5.0.0
 * @return void
 */
function qsm_results_overview_tab() {
	global $mlwQuizMasterNext;
	$mlwQuizMasterNext->pluginHelper->register_admin_results_tab( __( 'Quiz Results', 'quiz-master-next' ), 'qsm_results_overview_tab_content', 1 );
	if ( ! class_exists( 'QSM_Exporting' ) ) {
		$mlwQuizMasterNext->pluginHelper->register_admin_results_tab( __( 'Export Results', 'quiz-master-next' ), 'qsm_export_results_tabs_content', 5 );
	}
	if ( ! class_exists( 'QSM_Analysis' ) ) {
		$mlwQuizMasterNext->pluginHelper->register_admin_results_tab( __( 'Reporting And Analysis', 'quiz-master-next' ), 'qsm_reporting_analysis_tabs_content', 10 );
	}
	if ( ! class_exists( 'QSM_Proctoring_Quiz' ) ) {
		$mlwQuizMasterNext->pluginHelper->register_admin_results_tab( __( 'Proctor Reports', 'quiz-master-next' ), 'qsm_proctor_quiz_tabs_content', 12 );
	}
}

add_action( 'init', 'qsm_results_overview_tab' );

/**
 * Generates HTML For Overview Tab
 *
 * @since 5.0.0
 * @return void
 */
function qsm_delete_results_attachments( $rows_before_update ) {
    // Loop through each row in the results
    foreach ( $rows_before_update as $row ) {
        // Unserialize the quiz results
        $mlw_qmn_results_array = maybe_unserialize( $row->quiz_results );
        // Ensure the results array exists and has the expected structure
		foreach ( $mlw_qmn_results_array[1] as $key => $value ) {
			// Check if the question type is 11 and user answer is not empty
			if ( 11 == $value['question_type'] && ! empty( $value['user_answer'] ) && isset( $value['user_answer']['file_id'] ) ) {
				$attachment_id = $value['user_answer']['file_id'];
				// Delete the attachment
				wp_delete_attachment( $attachment_id, true );
			}
		}
    }
}
function qsm_results_overview_tab_content() {
	$get_subtabs = apply_filters( 'qsm_ultimate_get_subtabs_options', array() );
	$current_subtab = isset( $_GET['subtab'] ) ? sanitize_key( $_GET['subtab'] ) : '';
	do_action('qsm_before_admin_show_results_list', $get_subtabs, $current_subtab);

	if ( empty( $get_subtabs ) || '' === $current_subtab ) {

	global $wpdb;
	global $mlwQuizMasterNext;

	// If nonce is correct, delete results.
	if ( isset( $_POST['delete_results_nonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['delete_results_nonce'] ) ), 'delete_results' ) ) {

		$mlw_delete_results_id   = isset( $_POST['result_id'] ) ? intval( $_POST['result_id'] ) : 0;
		$mlw_delete_results_name = isset( $_POST['delete_quiz_name'] ) ? sanitize_text_field( wp_unslash( $_POST['delete_quiz_name'] ) ) : '';
		do_action( 'qsm_before_delete_result', $mlw_delete_results_id );
		// Updates table to mark results as deleted.
		$results                 = $wpdb->update( $wpdb->prefix . 'mlw_results', array( 'deleted' => 1 ), array( 'result_id' => $mlw_delete_results_id ), array( '%d' ), array( '%d' ) );
		// Get the row before the update
		$row_before_update = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$wpdb->prefix}mlw_results WHERE result_id = %d",
				$mlw_delete_results_id
			)
		);

		if ( false === $results ) {
			$error = $wpdb->last_error;
			if ( empty( $error ) ) {
				$error = __( 'Unknown error', 'quiz-master-next' );
			}
			/* translators: %s: error */
			$mlwQuizMasterNext->alertManager->newAlert( sprintf( __( 'There was an error when deleting this result. Error from WordPress: %s', 'quiz-master-next' ), $error ), 'error' );
			$mlwQuizMasterNext->log_manager->add( 'Error deleting result', "Tried {$wpdb->last_query} but got $error.", 0, 'error' );
		} else {
			qsm_delete_results_attachments($row_before_update);
			$mlwQuizMasterNext->alertManager->newAlert( __( 'Your results has been deleted successfully.', 'quiz-master-next' ), 'success' );
			$mlwQuizMasterNext->audit_manager->new_audit( "Results Has Been Deleted From:", $mlw_delete_results_name, "" );
		}
	}

	// Check if bulk delete has been selected. If so, verify nonce.
	if ( isset( $_POST["bulk_delete"], $_POST['bulk_delete_nonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['bulk_delete_nonce'] ) ), 'bulk_delete' ) ) {

		// Ensure the POST variable is an array
		if ( isset( $_POST["delete_results"] ) && is_array( $_POST["delete_results"] ) ) {
			$delete_results = array_map( 'sanitize_text_field', wp_unslash( $_POST["delete_results"] ) );
			$table_name = $wpdb->prefix . 'mlw_results';
			$query = $wpdb->prepare(
				"SELECT * FROM $table_name WHERE result_id IN (" . implode(',', array_fill(0, count($delete_results), '%d')) . ")",
				$delete_results
			);
			$row_before_update = $wpdb->get_results($query);

			// Cycle through the POST array which should be an array of the result ids of the results the user wishes to delete
			foreach ( $delete_results as $result ) {

				// Santize by ensuring the value is an int
				$result_id = intval( $result );
				if ( isset( $_POST['bulk_permanent_delete'] ) && 1 === intval( sanitize_text_field( wp_unslash( $_POST['bulk_permanent_delete'] ) ) ) ) {
					$wpdb->delete(
						$wpdb->prefix . "mlw_results", array( 'result_id' => $result_id )
					);
				} else {
					$wpdb->update(
						$wpdb->prefix . "mlw_results", array( 'deleted' => 1 ), array( 'result_id' => $result_id ), array( '%d' ), array( '%d' )
					);
				}
			}
			qsm_delete_results_attachments($row_before_update);
			$mlwQuizMasterNext->audit_manager->new_audit( "Results Have Been Bulk Deleted", "", "" );
		}
	}

	// Prepares the SQL to retrieve the results.
	$table_limit         = 40;
	$search_phrase_sql   = '';
	$delete              = 'deleted=0';
	$delete              = apply_filters( 'qsm_results_delete_clause', $delete );
	$order_by_sql        = 'ORDER BY time_taken_real DESC';
	if ( isset( $_GET['qsm_search_phrase'] ) && ! empty( $_GET['qsm_search_phrase'] ) ) {
		// Sanitizes the search phrase and then uses $wpdb->prepare to properly escape the queries after using $wpdb->esc_like.
		$sanitized_search_phrase = htmlentities( sanitize_text_field( wp_unslash( $_GET['qsm_search_phrase'] ) ) );
		$search_phrase_percents  = '%' . esc_sql( $wpdb->esc_like( $sanitized_search_phrase ) ) . '%';
		$search_phrase_sql       = $wpdb->prepare( ' AND (quiz_name LIKE %s OR name LIKE %s OR business LIKE %s OR email LIKE %s OR phone LIKE %s)', $search_phrase_percents, $search_phrase_percents, $search_phrase_percents, $search_phrase_percents, $search_phrase_percents );
	}
	if ( isset( $_GET['quiz_id'] ) && ! empty( $_GET['quiz_id'] ) ) {
		$quiz_id             = intval( $_GET['quiz_id'] );
		$qsm_results_count   = $wpdb->get_var( "SELECT COUNT(result_id) FROM {$wpdb->prefix}mlw_results WHERE {$delete} AND quiz_id='{$quiz_id}' {$search_phrase_sql}" );
	} else {
		$qsm_results_count = $wpdb->get_var( "SELECT COUNT(result_id) FROM {$wpdb->prefix}mlw_results WHERE {$delete} {$search_phrase_sql}" );
	}

	// Gets the order by arg. Uses switch to create SQL to prevent SQL injection.
	if ( isset( $_GET['qmn_order_by'] ) ) {
		switch ( $_GET['qmn_order_by'] ) {
			case 'quiz_name':
				$order_by        = 'quiz_name';
				$order_by_sql    = ' ORDER BY quiz_name DESC';
				break;
			case 'name':
				$order_by        = 'name';
				$order_by_sql    = ' ORDER BY name DESC';
				break;
			case 'point_score':
				$order_by        = 'point_score';
				$order_by_sql    = ' ORDER BY point_score DESC';
				break;
			case 'correct_score':
				$order_by        = 'correct_score';
				$order_by_sql    = ' ORDER BY correct_score DESC';
				break;
			default:
				$order_by        = 'time_taken_real';
				$order_by_sql    = ' ORDER BY time_taken_real DESC';
		}
	}

	if ( isset( $_GET['qsm_results_page'] ) || isset( $_GET['goto'] ) ) {
		$result_page     = isset( $_GET['qsm_results_page'] ) ? intval( $_GET['qsm_results_page'] ) + 1 : intval( $_GET['goto'] ) - 1;
		$result_begin    = $table_limit * $result_page;
	} else {
		$result_page     = 0;
		$result_begin    = 0;
	}
	$results_left = $qsm_results_count - ( $result_page * $table_limit );
	if ( isset( $_GET['quiz_id'] ) && ! empty( $_GET['quiz_id'] ) ) {
		$quiz_id         = intval( $_GET['quiz_id'] );
		$mlw_quiz_data   = $wpdb->get_results( stripslashes( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}mlw_results WHERE $delete AND quiz_id = %d $search_phrase_sql $order_by_sql LIMIT %d, %d", $quiz_id, $result_begin, $table_limit ) ) );
	} else {
		$mlw_quiz_data = $wpdb->get_results( stripslashes( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}mlw_results WHERE $delete $search_phrase_sql $order_by_sql LIMIT %d, %d", $result_begin, $table_limit ) ) );
	}
	apply_filters('qsm_user_role_permission_page_view_results',true);
	?>
	<div class="tablenav top">
		<div class="alignleft actions bulkactions">
			<select id="qsm_bulk_action_select" class="postform">
				<option value=""><?php esc_html_e( 'Bulk Actions', 'quiz-master-next' ); ?></option>
				<option value="bulk_delete"><?php esc_html_e( 'Bulk Delete', 'quiz-master-next' ); ?></option>
				<option value="bulk_permanent_delete"><?php esc_html_e( 'Bulk Permanent Delete', 'quiz-master-next' ); ?></option>
			</select>
				<button type="button" id="qsm_apply_bulk_action" class="button action"><?php esc_html_e( 'Apply', 'quiz-master-next' ); ?></button>
		</div>
		<div class="tablenav-pages">
			<span class="pagination-links">
				<?php
				$mlw_qmn_previous_page = 0;
				$mlw_current_page = $result_page + 1;
				$mlw_total_pages = ceil( $qsm_results_count / $table_limit );

				$url_query_string = '';
				if ( isset( $_GET['quiz_id'] ) && ! empty( $_GET['quiz_id'] ) ) {
					$url_query_string .= '&quiz_id=' . intval( $_GET['quiz_id'] );
				}

				if ( isset( $_GET['qsm_search_phrase'] ) && ! empty( $_GET['qsm_search_phrase'] ) ) {
					$url_query_string .= "&qsm_search_phrase=$sanitized_search_phrase";
				}

				if ( isset( $_GET['qmn_order_by'] ) && ! empty( $_GET['qmn_order_by'] ) ) {
					$url_query_string .= "&qmn_order_by=$order_by";
				}

				if ( $result_page > 0 ) {
					$mlw_qmn_previous_page = $result_page - 2;
					?>
					<a class="prev-page button" href="<?php echo esc_url_raw( "?page=mlw_quiz_results&qsm_results_page=$mlw_qmn_previous_page$url_query_string" ); ?>"><</a>
					<?php
				} elseif ( 0 == $result_page ) {
					?>
					<a class="prev-page button disable" href=""><</a>
					<?php
				}
				?>
				<span class="paging-input">
					<form action="" method="GET">
						<?php
						$query_params = $_GET;
						unset($query_params['qsm_results_page']);
						foreach ( $query_params as $key => $value ) {
							echo '<input type="hidden" name="' . esc_attr($key) . '" value="' . esc_attr($value) . '">';
						}
						?>
						<input type="text" class="qsm-admin-result-page-number" name="goto" value="<?php echo esc_attr($mlw_current_page); ?>">
					</form>&nbsp;
					<span class="total-entries"> of <?php echo esc_html($mlw_total_pages); ?></span>
				</span>
				<?php
				if ( $results_left > $table_limit ) {
					?>
					<a class="next-page button" href="<?php echo esc_url_raw( "?page=mlw_quiz_results&qsm_results_page=$result_page$url_query_string" ); ?>">></a>
					<?php
				} elseif ( $results_left <= $table_limit ) {
					?>
					<a class="next-page button disable" href="">></a>
					<?php
				}
				?>
			</span>
			<br class="clear">
		</div>
		<form action='' method="get">
			<?php
			if ( isset( $_GET['quiz_id'] ) ) {
				?>
				<input type="hidden" name="quiz_id" value="<?php echo intval( $_GET['quiz_id'] ); ?>" />
				<?php
			}
			$qsm_search_phrase   = ( isset( $_GET['qsm_search_phrase'] ) ) ? sanitize_text_field( wp_unslash( $_GET['qsm_search_phrase'] ) ) : '';
			$qmn_order_by        = ( isset( $_GET['qmn_order_by'] ) && ! empty( $_GET['qmn_order_by'] ) ) ? sanitize_text_field( wp_unslash( $_GET['qmn_order_by'] ) ) : 'default';
			?>
			<input type="hidden" name="page" value="mlw_quiz_results">
			<p class="search-box" style="margin: 0;">
				<label for="qmn_order_by"><?php esc_html_e( 'Order By', 'quiz-master-next' ); ?></label>
				<select id="qmn_order_by" name="qmn_order_by">
					<option value="default" <?php selected( $qmn_order_by, 'default' ); ?>><?php esc_html_e( 'Default (Time)', 'quiz-master-next' ); ?></option>
					<option value="quiz_name" <?php selected( $qmn_order_by, 'quiz_name' ); ?>><?php esc_html_e( 'Quiz Name', 'quiz-master-next' ); ?></option>
					<option value="name" <?php selected( $qmn_order_by, 'name' ); ?>><?php esc_html_e( 'User Name', 'quiz-master-next' ); ?></option>
					<option value="point_score" <?php selected( $qmn_order_by, 'point_score' ); ?>><?php esc_html_e( 'Points', 'quiz-master-next' ); ?></option>
					<option value="correct_score" <?php selected( $qmn_order_by, 'correct_score' ); ?>><?php esc_html_e( 'Correct Percent', 'quiz-master-next' ); ?></option>
				</select>
				<label for="qsm_search_phrase"><?php esc_html_e( 'Search Results', 'quiz-master-next' ); ?></label>
				<input type="search" id="qsm_search_phrase" name="qsm_search_phrase" value="<?php echo esc_attr( $qsm_search_phrase ); ?>">
				<button class="button"><?php esc_html_e( 'Search Results', 'quiz-master-next' ); ?></button>
			</p>
		</form>
		</div>
	<?php
	//process screen options
	$user_id             = get_current_user_id();
	if ( isset( $_POST["results-screen_option_nonce"] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST["results-screen_option_nonce"] ) ), 'results_screen_option' ) ) {
		$results_screen_option['page_url']   = isset( $_POST['page_url'] ) ? sanitize_text_field( wp_unslash( $_POST['page_url'] ) ) : "0";
		$results_screen_option['page_name']  = isset( $_POST['page_name'] ) ? sanitize_text_field( wp_unslash( $_POST['page_name'] ) ) : "0";
		$results_screen_option['view_result_page'] = isset( $_POST['view_result_page'] ) ? sanitize_text_field( wp_unslash( $_POST['view_result_page'] ) ) : "0";
		$results_screen_option['business']   = isset( $_POST['business'] ) ? sanitize_text_field( wp_unslash( $_POST['business'] ) ) : "0";
		$results_screen_option['phone']      = isset( $_POST['phone'] ) ? sanitize_text_field( wp_unslash( $_POST['phone'] ) ) : "0";
		$results_screen_option['ip_address'] = isset( $_POST['ip_address'] ) ? sanitize_text_field( wp_unslash( $_POST['ip_address'] ) ) : "0";
		$results_screen_option['start_date'] = isset( $_POST['start_date'] ) ? sanitize_text_field( wp_unslash( $_POST['start_date'] ) ) : "0";
		$results_screen_option['time_taken'] = isset( $_POST['time_taken'] ) ? sanitize_text_field( wp_unslash( $_POST['time_taken'] ) ) : "0";
		//set screen option as user meta
		update_user_meta( $user_id, 'results_screen_option', $results_screen_option );
	} else {
		$results_screen_option   = get_user_meta( $user_id, 'results_screen_option', true );
		$results_screen_option   = ! empty( $results_screen_option ) ? $results_screen_option : '';
		$default_results_screen_option = array(
			'page_url'         => '0',
			'view_result_page' => '0',
			'page_name'        => '0',
			'business'         => '1',
			'phone'            => '1',
			'ip_address'       => '1',
		);
		$results_screen_option = wp_parse_args( $results_screen_option, $default_results_screen_option );
		if ( ! isset( $results_screen_option['start_date'] ) ) {
			$results_screen_option['start_date'] = "0";
		}
		if ( ! isset( $results_screen_option['time_taken'] ) ) {
			$results_screen_option['time_taken'] = "0";
		}
	}
	?>

	<form action="" method="post" name="bulk_delete_form">
		<input type="hidden" name="bulk_delete" value="confirmation" />
		<input type="hidden" name="bulk_permanent_delete" id="bulk_permanent_delete" value="0" />
		<?php
		wp_nonce_field( 'bulk_delete', 'bulk_delete_nonce' );

		$th_elements = apply_filters( 'mlw_qmn_admin_results_page_headings', array(
			'score'            => __( 'Score', 'quiz-master-next' ),
			'time_complete'    => __( 'Time To Complete', 'quiz-master-next' ),
			'name'             => __( 'Name', 'quiz-master-next' ),
			'business'         => __( 'Business', 'quiz-master-next' ),
			'email'            => __( 'Email', 'quiz-master-next' ),
			'phone'            => __( 'Phone', 'quiz-master-next' ),
			'user'             => __( 'User', 'quiz-master-next' ),
			'start_date'       => __( 'Start Date', 'quiz-master-next' ),
			'time_taken'       => __( 'End Date', 'quiz-master-next' ),
			'ip'               => __( 'IP Address', 'quiz-master-next' ),
			'page_name'        => __( 'Page Name', 'quiz-master-next' ),
			'page_url'         => __( 'Page URL', 'quiz-master-next' ),
			'view_result_page' => __( 'Result Page', 'quiz-master-next' ),
		) );

		$values      = $quiz_infos   = [];
		foreach ( $th_elements as $key => $th ) {
			$values[ $key ]['title'] = $th;
			$values[ $key ]['style'] = "";
		}

		$display_none = ' style=display:none; ';
		if ( "0" === $results_screen_option['page_url'] ) {
			$values['page_url']['style'] = $display_none;
		}
		if ( "0" === $results_screen_option['page_name'] ) {
			$values['page_name']['style'] = $display_none;
		}
		if ( "0" === $results_screen_option['business'] ) {
			$values['business']['style'] = $display_none;
		}
		if ( "0" === $results_screen_option['phone'] ) {
			$values['phone']['style'] = $display_none;
		}
		if ( "0" === $results_screen_option['ip_address'] ) {
			$values['ip']['style'] = $display_none;
		}
		if ( "0" === $results_screen_option['start_date'] ) {
			$values['start_date']['style'] = $display_none;
		}
		if ( "0" === $results_screen_option['time_taken'] ) {
			$values['time_taken']['style'] = $display_none;
		}
		if ( "0" === $results_screen_option['view_result_page'] ) {
			$values['view_result_page']['style'] = $display_none;
		}
		if ( ! class_exists( 'QSM_Proctoring_Quiz' ) ) {
			$proctor_class = "qsm-quiz-proctor-addon";
		}else {
			$proctor_class = "";
		}
		if ( $mlw_quiz_data ) {
			foreach ( $mlw_quiz_data as $mlw_quiz_info ) {
				$mlw_quiz_info->proctor_report_class = $proctor_class;
				if ( "" == $proctor_class ) {
					$mlw_quiz_info->proctor_report_link = "admin.php?page=qsm_quiz_result_details&tab=proctor-results&quiz_id=$mlw_quiz_info->quiz_id&result_id=$mlw_quiz_info->result_id";
				}else {
					$mlw_quiz_info->proctor_report_link = "#";
				}
				$quiz_infos[]            = $mlw_quiz_info;
				$mlw_complete_time       = '';
				$mlw_qmn_results_array   = maybe_unserialize( $mlw_quiz_info->quiz_results );
				$hidden_questions        = ! empty( $mlw_qmn_results_array['hidden_questions'] ) && is_array($mlw_qmn_results_array['hidden_questions']) ? count( $mlw_qmn_results_array['hidden_questions'] ) : 0;
				if ( is_array( $mlw_qmn_results_array ) ) {
					$mlw_complete_hours = floor( $mlw_qmn_results_array[0] / 3600 );
					if ( $mlw_complete_hours > 0 ) {
						$mlw_complete_time .= "$mlw_complete_hours hours ";
					}
					$mlw_complete_minutes = floor( ( $mlw_qmn_results_array[0] % 3600 ) / 60 );
					if ( $mlw_complete_minutes > 0 ) {
						$mlw_complete_time .= "$mlw_complete_minutes minutes ";
					}
					$mlw_complete_seconds    = $mlw_qmn_results_array[0] % 60;
					$mlw_complete_time       .= "$mlw_complete_seconds seconds";
				}

				$out_of_q    = $mlw_quiz_info->total - $hidden_questions;
				$form_type   = isset( $mlw_quiz_info->form_type ) ? $mlw_quiz_info->form_type : 0;

				if ( isset( $values['score'] ) ) {
					if ( 1 === intval( $form_type ) || 2 === intval( $form_type ) ) {
						$values['score']['content'][] = esc_html__( 'Not Graded', 'quiz-master-next' );
					} else {
						if ( 0 === intval( $mlw_quiz_info->quiz_system ) ) {
							$values['score']['content'][] = sprintf( '%1$s %2$s %3$s %4$s %5$s', esc_html( $mlw_quiz_info->correct ), esc_html__( 'out of', 'quiz-master-next' ), esc_html( $out_of_q ), esc_html__( 'or', 'quiz-master-next' ), esc_html( $mlw_quiz_info->correct_score ) );
						} elseif ( 1 === intval( $mlw_quiz_info->quiz_system ) ) {
							$values['score']['content'][] = sprintf( '%1$s %2$s', esc_html( $mlw_quiz_info->point_score ), esc_html__( 'Points', 'quiz-master-next' ) );
						} elseif ( 3 === intval( $mlw_quiz_info->quiz_system ) ) {
							$values['score']['content'][] = sprintf( '%1$s %2$s %3$s %4$s %5$s <br /> %6$s %7$s', esc_html( $mlw_quiz_info->correct ), esc_html__( 'out of', 'quiz-master-next' ), esc_html( $out_of_q ), esc_html__( 'or', 'quiz-master-next' ), esc_html( $mlw_quiz_info->correct_score ), esc_html( $mlw_quiz_info->point_score ), esc_html__( 'Points', 'quiz-master-next' ) );
						} else {
							$values['score']['content'][] = esc_html__( 'Not Graded', 'quiz-master-next' );
						}
					}
				}

				if ( isset( $values['time_complete'] ) ) {
					$values['time_complete']['content'][] = $mlw_complete_time;
				}

				if ( isset( $values['name'] ) ) {
					$values['name']['content'][] = $mlw_quiz_info->name;
				}

				if ( isset( $values['business'] ) ) {
					$values['business']['content'][] = $mlw_quiz_info->business;
				}

				if ( isset( $values['email'] ) ) {
					$values['email']['content'][] = $mlw_quiz_info->email;
				}

				if ( isset( $values['phone'] ) ) {
					$values['phone']['content'][] = $mlw_quiz_info->phone;
				}

				if ( isset( $values['user'] ) ) {
					if ( 0 === intval( $mlw_quiz_info->user ) ) {
						$values['user']['content'][] = esc_html__( 'Visitor', 'quiz-master-next' );
					} else {
						$values['user']['content'][] = '<a href="' . esc_url( admin_url( 'user-edit.php?user_id=' . $mlw_quiz_info->user ) ) . '">' . esc_html( $mlw_quiz_info->user ) . '</a>';
					}
				}

				if ( isset( $values['start_date'] ) ) {
					if ( isset($mlw_qmn_results_array['quiz_start_date']) ) {
						$sdate    = gmdate( get_option( 'date_format' ), strtotime( $mlw_qmn_results_array['quiz_start_date'] ) );
						$stime    = gmdate( "h:i:s A", strtotime( $mlw_qmn_results_array['quiz_start_date'] ) );
						$values['start_date']['content'][] = $sdate .' '. $stime;
					} else {
						$values['start_date']['content'][] = ' ';
					}
				}

				$date    = gmdate( get_option( 'date_format' ), strtotime( $mlw_quiz_info->time_taken ) );
				$time    = gmdate( "h:i:s A", strtotime( $mlw_quiz_info->time_taken ) );

				if ( isset( $values['time_taken'] ) ) {
					$values['time_taken']['content'][] = $date .' '. $time;
				}
				if ( isset( $values['ip'] ) ) {
					$values['ip']['content'][] = $mlw_quiz_info->user_ip;
				}
				if ( isset( $values['page_name'] ) ) {
					$values['page_name']['content'][] = $mlw_quiz_info->page_name;
				}
				if ( isset( $values['page_url'] ) ) {
					$quiz_page_url = $mlw_quiz_info->page_url;
					if ( ! empty( $mlw_qmn_results_array['page_url'] ) ) {
						$quiz_page_url = $mlw_qmn_results_array['page_url'];
					}
					$values['page_url']['content'][] = '<a href="' . esc_url( $quiz_page_url ) . '">' . esc_html( $quiz_page_url ) . '</a>';
				}
				if ( isset( $values['view_result_page']) ) {
					$quiz_page_url = $mlw_quiz_info->page_url;
					$unique_id = $mlw_quiz_info->unique_id;
					$values['view_result_page']['content'][] = '<a target="_blank" class="button" href="' . esc_url( $quiz_page_url ) . '?result_id=' . esc_attr( $unique_id ) . '">' . esc_html__( 'View', 'quiz-master-next' ) . '</a>';
				}
				foreach ( $values as $k => $v ) {
					if ( ! in_array( $k, [ 'score', 'time_complete', 'name', 'business', 'email', 'phone', 'user', 'time_taken', 'ip', 'page_name', 'page_url', 'view_result_page' ], true ) ) {
						$content = apply_filters( 'mlw_qmn_admin_results_page_column_content', '', $mlw_quiz_info, $k );
						if ( isset( $values[ $k ] ) && ! empty( $content ) ) {
							$values[ $k ]['content'][] = $content;
						}
					}
				}
			}
		}
		?>

		<table class="widefat" aria-label="<?php esc_html_e( 'Results Table', 'quiz-master-next' ); ?>">
			<thead>
				<tr>
					<th style="text-align: center;"><input type="checkbox" id="qmn_check_all" /></th>
					<th><?php esc_html_e( 'Quiz Name', 'quiz-master-next' ); ?></th>
					<?php
					foreach ( $values as $k => $v ) {
						if ( ! empty( $v['content'] ) ) {
							echo '<th' . esc_html( $v['style'] ) . '>' . esc_html( $v['title'] ) . '</th>';
						}
					}
					?>
				</tr>
			</thead>
			<tbody id="the-list">
				<?php
				$co = ! empty( $quiz_infos ) ? count( $quiz_infos ) : 0;
				if ( $co > 0 ) {
					for ( $x = 0; $x < $co; $x++ ) {
						?>
						<tr>
							<td style="text-align: center;">
								<input type="checkbox" class="qmn_delete_checkbox" name="delete_results[]" value="<?php echo esc_attr( $quiz_infos[ $x ]->result_id ); ?>" />
							</td>
							<td class="<?php echo apply_filters( 'qsm_results_quiz_name_class', '', $quiz_infos[ $x ]->result_id ); ?>">
								<span style="font-size: 16px;"><?php echo esc_html( $quiz_infos[ $x ]->quiz_name ); ?></span>
								<div class="row-actions">
									<span style="color: green; font-size: 16px;">
									<?php do_action('qsm_admin_quiz_results_page_rowactions_before', $quiz_infos[ $x ]);
									if ( ( current_user_can( 'view_qsm_quiz_result' ) && get_current_user_id() == $quiz_infos[ $x ]->user ) || current_user_can( 'delete_others_qsm_quizzes' ) ) {
									?>
										<a href="admin.php?page=qsm_quiz_result_details&result_id=<?php echo esc_attr( $quiz_infos[ $x ]->result_id ); ?>"><?php esc_html_e( 'View Results', 'quiz-master-next' ); ?></a> |
									<?php } ?>
										<a style="color: red;" class="delete_table_quiz_results_item" data-quiz-id="<?php echo esc_attr( $quiz_infos[ $x ]->result_id ); ?>" data-quiz-name="<?php echo esc_attr( $quiz_infos[ $x ]->quiz_name ); ?>" href='#'><?php esc_html_e( 'Delete', 'quiz-master-next' ); ?></a> |
										<a class="<?php echo esc_attr( $quiz_infos[ $x ]->proctor_report_class ); ?>" href='<?php echo esc_attr( $quiz_infos[ $x ]->proctor_report_link ); ?>'><?php esc_html_e( 'Proctor Reports', 'quiz-master-next' ); ?></a>
									<?php do_action('qsm_admin_quiz_results_page_rowactions_after', $quiz_infos[ $x ]); ?>
									</span>
								</div>
							</td>
							<?php
							foreach ( $values as $k => $v ) {
								if ( isset( $v['content'][ $x ] ) ) {
									echo '<td' . esc_html( $v['style'] ) . '><span style="font-size:16px;">' . wp_kses_post( apply_filters( 'mlw_qmn_admin_results_page_result', $v['content'][ $x ], $quiz_infos[ $x ], $k ) ) . '</span></td>';
								}
							}
							?>
						</tr><?php
					}
				} else {
					?>
					<tr>
						<td colspan="12" style="text-align: center;"><?php esc_html_e( 'No record found.', 'quiz-master-next' ); ?></td>
					</tr><?php }
				?>
			</tbody>
		</table>
	</form>

	<div class="qsm-popup qsm-popup-slide qsm-standard-popup " id="qsm-delete-result-page-popup" aria-hidden="false"  style="display:none">
		<div class="qsm-popup__overlay" tabindex="-1" data-micromodal-close>
			<div class="qsm-popup__container" role="dialog" aria-modal="true">
				<form action='' method='post'>
					<header class="qsm-popup__header qsm-delete-result-page-popup-header">
						<div class="qsm-popup__title qsm-upgrade-box-title" id="modal-2-title"></div>
						<a class="qsm-popup__close qsm-popup-upgrade-close" aria-label="Close modal" data-micromodal-close></a>
					</header>
					<main class="qsm-popup__content" id="modal-2-content">
						<div class="qsm-result-page-delete-message"><?php esc_html_e( 'Are you sure you want to delete these results?', 'quiz-master-next' ); ?></div>
							<?php wp_nonce_field( 'delete_results', 'delete_results_nonce' ); ?>
							<input type='hidden' id='result_id' name='result_id' value='' />
							<input type='hidden' id='delete_quiz_name' name='delete_quiz_name' value='' />
					</main>
					<footer class="qsm-popup__footer">
						<button class="qsm-popup__btn" data-micromodal-close aria-label="Close this dialog window"><?php esc_html_e( 'Cancel', 'quiz-master-next' ); ?></button>
						<button type="submit" class="qsm-popup__btn qsm-delete-result-popup-btn"><span class="dashicons dashicons-warning"></span><?php esc_html_e( 'Delete Result', 'quiz-master-next' ); ?></button>
					</footer>
				</form>
			</div>
		</div>
	</div>

	<!-- Popup for screen options -->
	<div class="qsm-popup qsm-popup-slide" id="modal-results-screen-option" aria-hidden="true">
		<div class="qsm-popup__overlay" tabindex="-1" data-micromodal-close>
			<div class="qsm-popup__container" role="dialog" aria-modal="true" aria-labelledby="modal-results-screen-option-title">
				<header class="qsm-popup__header">
					<h2 class="qsm-popup__title" id="modal-results-screen-option-title"><?php esc_html_e( 'Choose Columns to hide/show', 'quiz-master-next' ); ?></h2>
					<a class="qsm-popup__close" aria-label="Close modal" data-micromodal-close></a>
				</header>
				<main class="qsm-popup__content" id="modal-results-screen-option-content">
					<form action='' method='post' id="results-screen-option-form" style="display:flex; flex-direction:column;">
						<label>
							<input type="checkbox" value="1" name="page_url" <?php checked( $results_screen_option['page_url'], "1", true ) ?>/>
							<?php esc_html_e( 'Page URL', 'quiz-master-next' ); ?>
						</label>
						<label>
							<input type="checkbox" value="1" name="view_result_page" <?php checked( $results_screen_option['view_result_page'], "1", true ) ?>/>
							<?php esc_html_e( 'Result Page', 'quiz-master-next' ); ?>
						</label>
						<label>
							<input type="checkbox" name="page_name" value="1" <?php checked( $results_screen_option['page_name'], "1", true ) ?>/>
							<?php esc_html_e( 'Page Name', 'quiz-master-next' ); ?>
						</label>
						<label>
							<input type="checkbox" value="1" name="business" <?php checked( $results_screen_option['business'], "1", true ) ?>/>
							<?php esc_html_e( 'Business', 'quiz-master-next' ); ?>
						</label>
						<label>
							<input type="checkbox" name="phone" value="1" <?php checked( $results_screen_option['phone'], "1", true ) ?>/>
							<?php esc_html_e( 'Phone', 'quiz-master-next' ); ?>
						</label>
						<label>
							<input type="checkbox" name="ip_address" value="1" <?php checked( $results_screen_option['ip_address'], "1", true ) ?>/>
							<?php esc_html_e( 'IP Address', 'quiz-master-next' ); ?>
						</label>
						<label>
							<input type="checkbox" name="start_date" value="1" <?php checked( $results_screen_option['start_date'], "1", true ) ?>/>
							<?php esc_html_e( 'Start Date', 'quiz-master-next' ); ?>
						</label>
						<label>
							<input type="checkbox" name="time_taken" value="1" <?php checked( $results_screen_option['time_taken'], "1", true ) ?>/>
							<?php esc_html_e( 'End Date', 'quiz-master-next' ); ?>
						</label>
						<?php wp_nonce_field( 'results_screen_option', 'results-screen_option_nonce' ); ?>
					</form>
				</main>
				<footer class="qsm-popup__footer">
					<button id="save-results-screen-option-button" class="qsm-popup__btn qsm-popup__btn-primary"><?php esc_html_e( 'Save', 'quiz-master-next' ); ?></button>
					<button class="qsm-popup__btn" data-micromodal-close aria-label="Close this dialog window"><?php esc_html_e( 'Cancel', 'quiz-master-next' ); ?></button>
				</footer>
			</div>
		</div>
	</div>

	<?php
	if ( ! class_exists( 'QSM_Proctoring_Quiz' ) ) {
		$qsm_pop_up_arguments = array(
			"id"           => 'modal-proctor-quiz',
			"title"        => __('Secure Your Quizzes with Proctoring', 'quiz-master-next'),
			"description"  => __('Monitor and prevent cheating with the Quiz Proctor Addon.', 'quiz-master-next'),
			"chart_image"  => plugins_url('', dirname(__FILE__)) . '/images/proctor_quiz_chart.png',
			"information"  => __('QSM Addon Bundle is the best way to get all our add-ons at a discount. Upgrade to save 95% today OR you can buy Quiz Proctor Addon separately.', 'quiz-master-next'),
			"buy_btn_text" => __('Buy Quiz Proctor Addon', 'quiz-master-next'),
			"doc_link"     => qsm_get_plugin_link( 'docs/add-ons/quiz-proctor/', 'quiz-documentation', 'plugin', 'quiz-proctor', 'qsm_plugin_upsell' ),
			"upgrade_link" => qsm_get_plugin_link( 'pricing', 'quiz-documentation', 'plugin', 'quiz-proctor', 'qsm_plugin_upsell' ),
			"addon_link"   => qsm_get_plugin_link( 'downloads/quiz-proctor', 'quiz-documentation', 'plugin', 'quiz-proctor', 'qsm_plugin_upsell' ),
			"list_items"   => array(
				__("Detect tab switching & enforce full-screen mode", "quiz-master-next"),
				__("Capture test-taker images during the quiz", "quiz-master-next"),
				__("Ensure fair and secure assessments", "quiz-master-next"),
			),
		);
		qsm_admin_upgrade_popup($qsm_pop_up_arguments);
	}
	}
	do_action('qsm_ultimate_display_actiivity_report_section');
}

function qsm_export_results_tabs_content() {
	$args = array(
		"id"           => 'export-results',
		"title"        => __( 'Effortlessly Export Quiz Data', 'quiz-master-next' ),
		"description"  => __( 'Manage quiz results with the Export Results Addon.', 'quiz-master-next' ),
		"chart_image"  => plugins_url( '', dirname( __FILE__ ) ) . '/images/export_results.png',
		"warning"      => __( 'Export Results Addon required', 'quiz-master-next' ),
		"information"  => __( 'QSM Addon Bundle is the best way to get all our add-ons at a discount. Upgrade to save 95% today. OR you can buy Export Results Addon separately.', 'quiz-master-next' ),
		"buy_btn_text" => __( 'Buy Export Results Addon', 'quiz-master-next' ),
		"doc_link"     => qsm_get_plugin_link( 'docs/add-ons/export-results', 'result_page', 'export_results', 'result-exportresults-upsell_read_documentation', 'qsm_plugin_upsell' ),
		"upgrade_link" => qsm_get_plugin_link( 'pricing', 'result_page', 'export_results', 'result-exportresults-upsell_upgrade', 'qsm_plugin_upsell' ),
		"addon_link"   => qsm_get_plugin_link( 'downloads/export-results', 'result_page', 'export_results', 'result-exportresults-upsell_buy_addon', 'qsm_plugin_upsell' ),
		"list_items"   => array(
			__("Export responses to CSV for analysis", "quiz-master-next"),
			__("Allow participants to download results as PDFs", "quiz-master-next"),
			__("Simplify reporting & record-keeping", "quiz-master-next"),
		),
	);
	qsm_admin_upgrade_popup( $args, 'page' );
}
function qsm_reporting_analysis_tabs_content() {
	$args = array(
		"id"           => 'reporting-analysis',
		"title"        => __( 'Gain Powerful Insights with In-Depth Reports', 'quiz-master-next' ),
		"description"  => __( 'Analyze performance trends with the Reporting & Analysis Addon.', 'quiz-master-next' ),
		"chart_image"  => plugins_url( '', dirname( __FILE__ ) ) . '/images/report_analysis_chart.png',
		"warning"      => __( 'Reporting & Analysis Addon required', 'quiz-master-next' ),
		"information"  => __( 'QSM Addon Bundle is the best way to get all our add-ons at a discount. Upgrade to save 95% today. OR you can buy Reporting & Analysis Addon separately.', 'quiz-master-next' ),
		"buy_btn_text" => __( 'Buy Reporting & Analysis Addon', 'quiz-master-next' ),
		"doc_link"     => qsm_get_plugin_link( 'docs/add-ons/reporting-analysis', 'result_page', 'result_analysis', 'result-reportanalysis-upsell_read_documentation', 'qsm_plugin_upsell' ),
		"upgrade_link" => qsm_get_plugin_link( 'pricing', 'result_page', 'result_analysis', 'result-reportanalysis-upsell_upgrade', 'qsm_plugin_upsell' ),
		"addon_link"   => qsm_get_plugin_link( 'downloads/results-analysis', 'result_page', 'result_analysis', 'result-reportanalysis-ups_buy_addon', 'qsm_plugin_upsell' ),
		"list_items"   => array(
			__("Track quiz performance over time", "quiz-master-next"),
			__("Customize reports for deeper insights", "quiz-master-next"),
			__("Optimize quiz results with data-driven decisions", "quiz-master-next"),
		),
	);
	qsm_admin_upgrade_popup( $args, 'page' );
}

function qsm_proctor_quiz_tabs_content() {
	$args = array(
		"id"           => 'proctoring-quiz',
		"title"        => __('Secure Your Quizzes with Proctoring', 'quiz-master-next'),
		"description"  => __('Monitor and prevent cheating with the Quiz Proctor Addon.', 'quiz-master-next'),
		"chart_image"  => plugins_url( '', dirname( __FILE__ ) ) . '/images/proctor_quiz_chart.png',
		"warning"      => __( 'Missing Feature - Quiz Proctor Add-on required', 'quiz-master-next' ),
		"information"  => __( 'QSM Addon Bundle is the best way to get all our add-ons at a discount. Upgrade to save 95% today. OR you can buy Proctoring Quiz Addon separately.', 'quiz-master-next' ),
		"buy_btn_text" => __( 'Buy Quiz Proctor Addon', 'quiz-master-next' ),
		"doc_link"     => qsm_get_plugin_link( 'docs/add-ons/quiz-proctor', 'quiz-documentation', 'plugin', 'quiz-proctor', 'qsm_plugin_upsell' ),
		"upgrade_link" => qsm_get_plugin_link( 'pricing', 'quiz-documentation', 'plugin', 'quiz-proctor', 'qsm_plugin_upsell' ),
		"addon_link"   => qsm_get_plugin_link( 'downloads/quiz-proctor', 'quiz-documentation', 'plugin', 'quiz-proctor', 'qsm_plugin_upsell' ),
		"list_items"   => array(
			__("Detect tab switching & enforce full-screen mode", "quiz-master-next"),
			__("Capture test-taker images during the quiz", "quiz-master-next"),
			__("Ensure fair and secure assessments", "quiz-master-next"),
		),
	);
	qsm_admin_upgrade_popup( $args, 'page' );
}
