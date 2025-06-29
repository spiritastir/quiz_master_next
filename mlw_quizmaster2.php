<?php
/**
 * Plugin Name: Quiz And Survey Master
 * Description: Easily and quickly add quizzes and surveys to your website.
 * Version: 10.2.2
 * Author: ExpressTech
 * Author URI: https://quizandsurveymaster.com/
 * Plugin URI: https://expresstech.io/
 * Text Domain: quiz-master-next
 *
 * @author QSM Team
 * @package QSM
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'QSM_PLUGIN_PATH', plugin_dir_path( __FILE__ ) );
define( 'QSM_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );
define( 'QSM_SUBMENU', __FILE__ );
define( 'QSM_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'hide_qsm_adv', true );
define( 'QSM_THEME_PATH', WP_PLUGIN_DIR . '/' );
define( 'QSM_THEME_SLUG', plugins_url( '/' ) );
define( 'QSM_PLUGIN_CSS_URL', QSM_PLUGIN_URL . 'css' );
define( 'QSM_PLUGIN_JS_URL', QSM_PLUGIN_URL . 'js' );
define( 'QSM_PLUGIN_PHP_DIR', QSM_THEME_PATH . 'php' );
define( 'QSM_PLUGIN_TXTDOMAIN', 'quiz-master-next' );

/**
 * This class is the main class of the plugin
 *
 * When loaded, it loads the included plugin files and add functions to hooks or filters. The class also handles the admin menu
 *
 * @since 3.6.1
 */
class MLWQuizMasterNext {

	/**
	 * QSM Version Number
	 *
	 * @var string
	 * @since 4.0.0
	 */
	public $version = '10.2.2';

	/**
	 * QSM Alert Manager Object
	 *
	 * @var object
	 * @since 3.7.1
	 */
	public $alertManager;

	/**
	 * QSM Plugin Helper Object
	 *
	 * @var object
	 * @since 4.0.0
	 */
	public $pluginHelper;

	/**
	 * QSM Quiz Creator Object
	 *
	 * @var object
	 * @since 3.7.1
	 */
	public $quizCreator;

	/**
	 * QSM Log Manager Object
	 *
	 * @var object
	 * @since 4.5.0
	 */
	public $log_manager;

	/**
	 * QSM Audit Manager Object
	 *
	 * @var object
	 * @since 4.7.1
	 */
	public $audit_manager;

	/**
	 * QSM Settings Object
	 *
	 * @var object
	 * @since 5.0.0
	 */
	public $quiz_settings;

	/**
	 * QSM theme settings object
	 *
	 * @var object
	 * @since 7.2.0
	 */
	public $theme_settings;

	/**
	 * QSM migration helper object
	 *
	 * @var object
	 * @since 7.3.0
	 */
	public $migrationHelper;

	/**
	 * QSM Check License object
	 *
	 * @var object
	 * @since 8.1.7
	 */
	public $check_license;

	/**
	 * Holds quiz_data
	 *
	 * @var object
	 * @since 7.3.8
	 */
	public $qsm_api;

	/**
	 * Holds quiz_data
	 *
	 * @var object
	 * @since 7.3.8
	 */
	public $quiz = array();

	/*
	* Default MathJax inline scripts.
	*/
	public static $default_MathJax_script = "MathJax = {
		tex: {
		  inlineMath: [['$','$'],['\\\\(','\\\\)']],
		  processEscapes: true
		},
		options: {
		  ignoreHtmlClass: 'tex2jax_ignore|editor-rich-text'
		}
	  };";

	/**
	 * Main Construct Function
	 *
	 * Call functions within class
	 *
	 * @since 3.6.1
	 * @uses MLWQuizMasterNext::load_dependencies() Loads required filed
	 * @uses MLWQuizMasterNext::add_hooks() Adds actions to hooks and filters
	 * @return void
	 */
	public function __construct() {
		$this->load_dependencies();
		$this->add_hooks();
	}

	/**
	 * Check admin capabilities.
	 *
	 * @since 9.0.0
	 * @param string $check_permission permission type
	 *
	 * @return boolean current user has permission
	 */
	public function qsm_is_admin( $check_permission = 'manage_options' ) {
		if ( ! function_exists( 'wp_get_current_user' ) && file_exists( ABSPATH . "wp-includes/pluggable.php" ) ) {
			require_once( ABSPATH . "wp-includes/pluggable.php" );
		}
		if ( ! function_exists( 'current_user_can' ) && file_exists( ABSPATH . "wp-includes/capabilities.php" ) ) {
			require_once( ABSPATH . "wp-includes/capabilities.php" );
		}
		return ( function_exists( 'wp_get_current_user' ) && function_exists( 'current_user_can' ) && current_user_can( $check_permission ) );
	}

	/**
	 * sanitize HTML data.
	 * HTML is saved as encoded and at ouput same as decoded. encoded html may pass though most of
	 * the WordPress sanitization function. This function sanitize it to remove
	 * unfiltered HTML content
	 *
	 * @since 9.0.3
	 * @param HTML $html html data
	 *
	 * @return HTML sanitized HTML
	 */
	public function sanitize_html( $html = '', $kses = true ) {
		if ( empty( $html ) ) {
			return $html;
		}
		return $kses ? wp_kses_post( $html ) : sanitize_text_field( $html );
	}

	/**
	 * Get failed alter qmn table query list.
	 *
	 * @since 9.0.2
	 * @return array  alter qmn table query list
	 */
	public function get_failed_alter_table_queries() {
		$failed_queries = get_option( 'qmn_failed_alter_table_queries', array() );
		return is_array( $failed_queries ) ? $failed_queries : array();
	}

	/**
	 * Execute WP db query and save query if failed to execute
	 *
	 * @since 9.0.2
	 * @param string $query SQL Query
	 *
	 * @return boolean query executed or not
	 */
	public function wpdb_alter_table_query( $query ) {
		// Check if admin or empty query.
		if ( empty( $query ) || ! function_exists( 'is_admin' ) || ! is_admin() ) {
			return false;
		}

		global $wpdb;
		$query = trim( $query );

		// check if a query for qsm tables alter only.
		if ( empty( $wpdb ) || 0 != stripos( $query, 'ALTER TABLE' ) || false === stripos( $query, 'mlw_' ) ) {
			return false;
		}

		// Execute query.
		$res = $wpdb->query( $query );

		// Get failed alter table query list.
		$failed_queries = $this->get_failed_alter_table_queries();

		if ( ! empty( $res ) ) {
			if ( ! empty( $failed_queries ) && in_array( $query, $failed_queries, true ) ) {
				// Remove failed query from list.
				$failed_queries = array_diff( $failed_queries, array( $query ) );
				// Update failed queries list.
				update_option( 'qmn_failed_alter_table_queries', $failed_queries );
			}
			return true;
		} elseif ( empty( $failed_queries ) || ! in_array( $query, $failed_queries, true ) ) {
			// Add query to the list.
			$failed_queries[] = $query;
			// Update failed queries list.
			update_option( 'qmn_failed_alter_table_queries', $failed_queries );
		}

		return false;
	}

	/**
	 * Load File Dependencies
	 *
	 * @since 3.6.1
	 * @return void
	 */
	private function load_dependencies() {

		include_once 'blocks/block.php';

		include_once 'php/classes/class-qsm-install.php';
		include_once 'php/classes/class-qsm-fields.php';

		include_once 'php/classes/class-qmn-log-manager.php';
		$this->log_manager = new QMN_Log_Manager();

		include_once 'php/classes/class-qsm-audit.php';
		$this->audit_manager = new QSM_Audit();

		// In block editor api call is_admin return false so use qsm_is_admin.
		if ( is_admin() || ( ! empty( $_POST['qsm_block_api_call'] ) && $this->qsm_is_admin() ) ) {
			include_once 'php/admin/functions.php';
			include_once 'php/admin/stats-page.php';
			include_once 'php/admin/create-quiz-page.php';
			include_once 'php/admin/quizzes-page.php';
			include_once 'php/admin/admin-dashboard.php';
			include_once 'php/admin/quiz-options-page.php';
			include_once 'php/admin/admin-results-page.php';
			include_once 'php/admin/admin-results-details-page.php';
			include_once 'php/admin/tools-page.php';
			include_once 'php/classes/class-qsm-changelog-generator.php';
			include_once 'php/admin/about-page.php';
			include_once 'php/admin/dashboard-widgets.php';
			include_once 'php/admin/options-page-questions-tab.php';
			include_once 'php/admin/options-page-contact-tab.php';
			include_once 'php/admin/options-page-text-tab.php';
			include_once 'php/admin/options-page-option-tab.php';
			include_once 'php/admin/options-page-email-tab.php';
			include_once 'php/admin/options-page-results-page-tab.php';
			include_once 'php/admin/options-page-style-tab.php';
			include_once 'php/admin/addons-page.php';
			include_once 'php/admin/settings-page.php';
			include_once 'php/classes/class-qsm-tracking.php';
			include_once 'php/classes/class-qmn-review-message.php';
			include_once 'php/gdpr.php';
		}
		include_once 'php/classes/class-qsm-questions.php';
		include_once 'php/classes/class-qsm-contact-manager.php';
		include_once 'php/classes/class-qsm-results-pages.php';
		include_once 'php/classes/class-qsm-emails.php';
		include_once 'php/classes/class-qmn-quiz-manager.php';

		include_once 'php/template-variables.php';
		include_once 'php/adverts-generate.php';
		include_once 'php/question-types.php';
		include_once 'php/default-templates.php';
		include_once 'php/shortcodes.php';

		include_once 'php/classes/class-qmn-alert-manager.php';
		$this->alertManager = new MlwQmnAlertManager();

		include_once 'php/classes/class-qmn-quiz-creator.php';
		$this->quizCreator = new QMNQuizCreator();

		include_once 'php/classes/class-qmn-plugin-helper.php';
		$this->pluginHelper = new QMNPluginHelper();

		include_once 'php/classes/class-qsm-settings.php';
		$this->quiz_settings = new QSM_Quiz_Settings();

		include_once 'php/classes/class-qsm-theme-settings.php';
		$this->theme_settings = new QSM_Theme_Settings();

		include_once 'php/classes/class-qsm-migrate.php';
		$this->migrationHelper = new QSM_Migrate();



		include_once 'php/rest-api.php';
		include_once 'php/classes/class-qsm-quiz-api.php';
		$this->qsm_api = new QSMQuizApi();
	}

	/**
	 * Add Hooks
	 *
	 * Adds functions to relavent hooks and filters
	 *
	 * @since 3.6.1
	 * @return void
	 */
	private function add_hooks() {
		add_action( 'admin_menu', array( $this, 'qsm_add_user_capabilities' ) );
		add_action( 'admin_menu', array( $this, 'setup_admin_menu' ) );
		add_action( 'admin_head', array( $this, 'admin_head' ), 900 );
		add_action( 'init', array( $this, 'register_quiz_post_types' ) );
		if ( empty( get_option('qsm_check_database_structure') ) || ! empty($_GET['qsm_check_database_structure']) ) {
			add_action( 'admin_init', array( $this, 'qsm_check_database_structure' ) );
		}
		add_filter( 'parent_file', array( &$this, 'parent_file' ), 9999, 1 );
		add_action( 'plugins_loaded', array( &$this, 'qsm_load_textdomain' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'qsm_admin_scripts_style' ), 10 );
		add_action( 'admin_init', array( $this, 'qsm_overide_old_setting_options' ) );
		add_action( 'admin_notices', array( $this, 'qsm_admin_notices' ) );
		add_filter( 'manage_edit-qsm_category_columns', array( $this, 'modify_qsm_category_columns' ) );
		add_action( 'wp_ajax_qsm_generate_quiz_with_ai', 'qsm_generate_quiz_with_ai_handler' );
		add_action( 'wp_ajax_qsm_generate_results_with_ai', 'qsm_generate_results_with_ai_handler' );
	}

	/**
	 * Modifies QSM Category taxonomy columns
	 *
	 * @param array $columns
	 * @return array
	 * @since 7.3.0
	 */
	public function modify_qsm_category_columns( $columns ) {
		unset( $columns['posts'] );
		return $columns;
	}

	/**
	 * @since 7.1.4
	 */
	public function qsm_load_textdomain() {
		load_plugin_textdomain( 'quiz-master-next', false, dirname( plugin_basename( __FILE__ ) ) . '/lang/' );
		if ( class_exists('QSM_license') ) {
			include_once 'php/classes/class-qsm-check-license.php';
			$this->check_license = new QSM_Check_License();
		}
	}

	/**
	 * Loads admin scripts and style
	 *
	 * @since 7.1.16
	 * @since 7.3.5 admin scripts consolidated
	 */
	public function qsm_admin_scripts_style( $hook ) {
		global $mlwQuizMasterNext, $wpdb;

		// admin styles
		wp_enqueue_style( 'qsm_admin_style', plugins_url( 'css/qsm-admin.css', __FILE__ ), array(), $this->version );
		if ( is_rtl() ) {
			wp_enqueue_style( 'qsm_admin_style_rtl', plugins_url( 'css/qsm-admin-rtl.css', __FILE__ ), array(), $this->version );
		}
		// dashboard and quiz list pages
		if ( 'toplevel_page_qsm_dashboard' === $hook || 'qsm_page_qmn_addons' === $hook || ('edit.php' == $hook && isset( $_REQUEST['post_type'] ) && 'qsm_quiz' == $_REQUEST['post_type']) ) {
			wp_enqueue_script( 'micromodal_script', plugins_url( 'js/micromodal.min.js', __FILE__ ), array( 'jquery', 'qsm_admin_js' ), $this->version, true );
			wp_enqueue_media();
			wp_enqueue_style( 'qsm_admin_dashboard_css', QSM_PLUGIN_CSS_URL . '/admin-dashboard.css', array(), $this->version );
			wp_style_add_data( 'qsm_admin_dashboard_css', 'rtl', 'replace' );
			wp_enqueue_style( 'qsm_ui_css', QSM_PLUGIN_CSS_URL . '/jquery-ui.min.css', array(), '1.13.0' );
		}
		// dashboard
		if ( 'toplevel_page_qsm_dashboard' === $hook ) {
			wp_enqueue_script( 'dashboard' );
			if ( wp_is_mobile() ) {
				wp_enqueue_script( 'jquery-touch-punch' );
			}
		}
		// result details page
		if ( 'admin_page_qsm_quiz_result_details' === $hook ) {
			wp_enqueue_style( 'qsm_common_style', QSM_PLUGIN_CSS_URL . '/common.css', array(), $this->version );
			wp_style_add_data( 'qsm_common_style', 'rtl', 'replace' );
			wp_enqueue_script( 'jquery-ui-slider' );
			wp_enqueue_script( 'jquery-ui-slider-rtl-js', QSM_PLUGIN_JS_URL . '/jquery.ui.slider-rtl.js', array( 'jquery-ui-core', 'jquery-ui-mouse', 'jquery-ui-slider' ), $this->version, true );
			wp_enqueue_style( 'jquery-ui-slider-rtl-css', QSM_PLUGIN_CSS_URL . '/jquery.ui.slider-rtl.css', array(), $this->version );
			wp_enqueue_script( 'qsm_common', QSM_PLUGIN_JS_URL . '/qsm-common.js', array(), $this->version, true );
			wp_enqueue_style( 'jquery-redmond-theme', QSM_PLUGIN_CSS_URL . '/jquery-ui.css', array(), $this->version );
		}
		// results page
		if ( 'qsm_page_mlw_quiz_results' === $hook ) {
			wp_enqueue_script( 'jquery' );
			wp_enqueue_script( 'jquery-ui-core' );
			wp_enqueue_script( 'jquery-ui-dialog' );
			wp_enqueue_script( 'jquery-ui-button' );
			wp_enqueue_style( 'qmn_jquery_redmond_theme', QSM_PLUGIN_CSS_URL . '/jquery-ui.css', array(), $this->version );
			wp_enqueue_script( 'micromodal_script', QSM_PLUGIN_JS_URL . '/micromodal.min.js', array( 'jquery' ), $this->version, true );
		}
		// stats page
		if ( 'qsm_page_qmn_stats' === $hook ) {
			wp_enqueue_script( 'ChartJS', QSM_PLUGIN_JS_URL . '/chart.min.js', array(), '3.6.0', true );
		}
		// quiz option pages
		if ( 'admin_page_mlw_quiz_options' === $hook ) {
			wp_enqueue_script( 'wp-tinymce' );
			wp_enqueue_script( 'micromodal_script', plugins_url( 'js/micromodal.min.js', __FILE__ ), array( 'jquery', 'qsm_admin_js' ), $this->version, true );
			$current_tab = isset( $_GET['tab'] ) ? sanitize_text_field( wp_unslash( $_GET['tab'] ) ) : 'questions';
			switch ( $current_tab ) {
				case 'questions':
					wp_enqueue_style( 'qsm_admin_question_css', QSM_PLUGIN_CSS_URL . '/qsm-admin-question.css', array(), $this->version );
					if ( is_rtl() ) {
						wp_enqueue_style( 'qsm_admin_question_css_rtl', plugins_url( 'css/qsm-admin-question-rtl.css', __FILE__ ), array(), $this->version );
					}
					wp_enqueue_script( 'math_jax', QSM_PLUGIN_JS_URL . '/mathjax/tex-mml-chtml.js', false, '3.2.0', true );
					wp_add_inline_script( 'math_jax', self::$default_MathJax_script, 'before' );
					wp_enqueue_editor();
					wp_enqueue_media();
					break;
				case 'style':
					wp_enqueue_style( 'wp-color-picker' );
					wp_enqueue_script( 'wp-color-picker');
					wp_enqueue_script( 'wp-color-picker-alpha', QSM_PLUGIN_JS_URL . '/wp-color-picker-alpha.min.js', array( 'wp-color-picker' ), $this->version, true );
					wp_enqueue_media();
					break;
				case 'options':
					wp_enqueue_style( 'qmn_jquery_redmond_theme', QSM_PLUGIN_CSS_URL . '/jquery-ui.css', array(), $this->version );
					wp_enqueue_style( 'qsm_datetime_style', QSM_PLUGIN_CSS_URL . '/jquery.datetimepicker.css', array(), $this->version );
					wp_enqueue_script( 'jquery' );
					wp_enqueue_script( 'jquery-ui-core' );
					wp_enqueue_script( 'jquery-ui-dialog' );
					wp_enqueue_script( 'jquery-ui-button' );
					wp_enqueue_script( 'qmn_datetime_js', QSM_PLUGIN_JS_URL . '/jquery.datetimepicker.full.min.js', array(), $this->version, true );
					wp_enqueue_script( 'jquery-ui-tabs' );
					wp_enqueue_script( 'jquery-effects-blind' );
					wp_enqueue_script( 'jquery-effects-explode' );
					wp_enqueue_media();
					break;
				case 'results-pages':
				case 'emails':
					wp_enqueue_script( 'select2-js',  QSM_PLUGIN_JS_URL.'/jquery.select2.min.js', array( 'jquery' ), $this->version,true);
					wp_enqueue_style( 'select2-css', QSM_PLUGIN_CSS_URL . '/jquery.select2.min.css', array(), $this->version );
					wp_enqueue_editor();
					wp_enqueue_media();
					break;
				default:
					wp_enqueue_editor();
					wp_enqueue_media();
					break;
			}
		}
		// load admin JS after all dependencies are loaded
		/**  Fixed wpApiSettings is not defined js error by using 'wp-api-request' core script to allow the use of localized version of wpApiSettings. **/
		wp_enqueue_script( 'qsm_admin_js', plugins_url( 'js/qsm-admin.js', __FILE__ ), array( 'jquery', 'backbone', 'underscore', 'wp-util', 'jquery-ui-sortable', 'jquery-touch-punch', 'qsm-jquery-multiselect-js', 'wp-api-request' ), $this->version, true );
		wp_enqueue_style( 'jquer-multiselect-css', QSM_PLUGIN_CSS_URL . '/jquery.multiselect.min.css', array(), $this->version );
		wp_enqueue_script( 'qsm-jquery-multiselect-js', QSM_PLUGIN_JS_URL . '/jquery.multiselect.min.js', array( 'jquery' ), $this->version, true );
		wp_enqueue_script( 'micromodal_script', plugins_url( 'js/micromodal.min.js', __FILE__ ), array( 'jquery', 'qsm_admin_js' ), $this->version, true );
		$qsm_variables = function_exists( 'qsm_text_template_variable_list' ) ? qsm_text_template_variable_list() : array();
		$qsm_variables_name = array();
		$qsm_quizzes = $wpdb->get_results("SELECT quiz_id, quiz_name FROM {$wpdb->prefix}mlw_quizzes");
		foreach ( $qsm_variables as $key => $value ) {
			// Iterate over each key of the nested object
			if ( is_array( $value ) && ! empty($value) ) {

				foreach ( $value as $nestedKey => $nestedValue ) {
				// Add the nested key to the array
				$qsm_variables_name[] = $nestedKey;
			    }
			}
}
		$qsm_admin_messages = array(
			'error'                      => __('Error', 'quiz-master-next'),
			'success'                    => __('Success', 'quiz-master-next'),
			'category'                   => __('Category', 'quiz-master-next'),
			'condition'                  => __('Select Condition', 'quiz-master-next'),
			'list'                       => __('List', 'quiz-master-next'),
			'question'                   => __('Question', 'quiz-master-next'),
			'try_again'                  => __('Please try again', 'quiz-master-next'),
			'already_exists_in_database' => __('already exists in database', 'quiz-master-next'),
			'confirm_message'            => __('Are you sure?', 'quiz-master-next'),
			'error_delete_result'        => __('Error to delete the result!', 'quiz-master-next'),
			'copied'                     => __('Copied!', 'quiz-master-next'),
			'set_feature_img'            => __('Set Featured Image', 'quiz-master-next'),
			'set_bg_img'                 => __('Set Background Image', 'quiz-master-next'),
			'insert_img'                 => __('Insert Image', 'quiz-master-next'),
			'upload_img'                 => __('Upload Image', 'quiz-master-next'),
			'use_img'                    => __('Use this image', 'quiz-master-next'),
			'updating_db'                => __('Updating database', 'quiz-master-next'),
			'update_db_success'          => __('Database updated successfully.', 'quiz-master-next'),
			'quiz_submissions'           => __('Quiz Submissions', 'quiz-master-next'),
			'saving_contact_fields'      => __('Saving contact fields...', 'quiz-master-next'),
			'contact_fields_saved'       => __('Your contact fields have been saved!', 'quiz-master-next'),
			'contact_fields_save_error'  => __('There was an error encountered when saving your contact fields.', 'quiz-master-next'),
			'saving_emails'              => __('Saving emails...', 'quiz-master-next'),
			'emails_saved'               => __('Emails were saved!', 'quiz-master-next'),
			'emails_save_error'          => __('There was an error when saving the emails.', 'quiz-master-next'),
			'saving_emails'              => __('Saving emails...', 'quiz-master-next'),
			'saving_results_page'        => __('Saving results pages...', 'quiz-master-next'),
			'results_page_saved'         => __('Results pages were saved!', 'quiz-master-next'),
			'results_page_save_error'    => __('There was an error when saving the results pages.', 'quiz-master-next'),
			'all_categories'             => __('All Categories', 'quiz-master-next'),
			'question_created'           => __('Question created!', 'quiz-master-next'),
			'new_question'               => __('Your new question!', 'quiz-master-next'),
			'creating_question'          => __('Creating question...', 'quiz-master-next'),
			'unlink_question'            => __('Unlink', 'quiz-master-next'),
			'duplicating_question'       => __('Duplicating question...', 'quiz-master-next'),
			'linking_question'           => __('Linking...', 'quiz-master-next'),
			'adding_question'            => __('Adding...', 'quiz-master-next'),
			'add_question'               => __('Add', 'quiz-master-next'),
			'link_question'              => __('Link', 'quiz-master-next'),
			'creating_question'          => __('Creating question...', 'quiz-master-next'),
			'saving_question'            => __('Saving question...', 'quiz-master-next'),
			'question_saved'             => __('Question was saved!', 'quiz-master-next'),
			'load_more_quetions'         => __('Load more questions', 'quiz-master-next'),
			'loading_question'           => __('Loading questions...', 'quiz-master-next'),
			'no_question_selected'       => __('No question is selected.', 'quiz-master-next'),
			'question_reset_message'     => __('All answer will be reset, Do you want to still continue?', 'quiz-master-next'),
			'your_answer'                => __('Your answer', 'quiz-master-next'),
			'insert_image_url'           => __('Insert image URL', 'quiz-master-next'),
			'saving_page_info'           => __('Saving page info', 'quiz-master-next'),
			'saving_page_questions'      => __('Saving pages and questions...', 'quiz-master-next'),
			'saved_page_questions'       => __('Questions and pages were saved!', 'quiz-master-next'),
			'import_question_again'      => __('you want to import this question again?', 'quiz-master-next'),
			'enter_question_title'       => __('Enter Question title or description', 'quiz-master-next'),
			'page_name_required'         => __('Page Name is required!', 'quiz-master-next'),
			'page_name_validation'       => __('Please use only Alphanumeric characters.', 'quiz-master-next'),
			'polar_q_range_error'        => __('Left range and right range should be different', 'quiz-master-next'),
			'range_fields_required'      => __('Range fields are required!', 'quiz-master-next'),
			'points'                     => __('Points', 'quiz-master-next'),
			'left_label'                 => __('Left Label', 'quiz-master-next'),
			'right_label'                => __('Right Label', 'quiz-master-next'),
			'left_range'                 => __('Left Range', 'quiz-master-next'),
			'right_range'                => __('Right Range', 'quiz-master-next'),
			'html_section_empty'         => __('Text/HTML Section cannot be empty', 'quiz-master-next'),
			'blank_number_validation'    => __('Number of <strong>%BLANK%</strong> should be equal to options for sequential matching', 'quiz-master-next'),
			'blank_required_validation'  => __('Atleast one <strong>%BLANK%</strong> and one option is required.', 'quiz-master-next'),
			'polar_options_validation'   => __('You can not add more than 2 answer for Polar Question type', 'quiz-master-next'),
			'hide_advance_options'       => __('Hide advance options «', 'quiz-master-next'),
			'show_advance_options'       => __('Show advance options »', 'quiz-master-next'),
			'category_not_empty'         => __('Category cannot be empty', 'quiz-master-next'),
			'sendy_signup_validation'    => array(
				'required_message'   => __('Please fill in your name and email.', 'quiz-master-next'),
				'email_validation'   => __('Your email address is invalid.', 'quiz-master-next'),
				'list_validation'    => __('Your list ID is invalid.', 'quiz-master-next'),
				'already_subscribed' => __("You're already subscribed!", 'quiz-master-next'),
				'success_message'    => __("Thanks, you are now subscribed to our mailing list!", 'quiz-master-next'),
				'error_message'      => __("Sorry, unable to subscribe. Please try again later!", 'quiz-master-next'),
			),
			'select_category'            => __("Select Category", 'quiz-master-next'),
			'questions_not_found'        => __("Question not found!", 'quiz-master-next'),
			'add_more'                   => __("Save", 'quiz-master-next'),
			'_X_validation_fails'        => __("Please enter an appropriate value for 'X'", 'quiz-master-next'),
			'qsm_variables'              => $qsm_variables,
			'qsm_variables_name'         => $qsm_variables_name,
			'no_variables'               => __("No Variable Found", 'quiz-master-next'),
			'slash_command'              => __("slash command", 'quiz-master-next'),
			'variables'                  => __("Variables", 'quiz-master-next'),
			'insert_variable'            => __("Insert QSM variables", 'quiz-master-next'),
			'select_all'                 => __("Select All", 'quiz-master-next'),
			'select'                     => __("Select", 'quiz-master-next'),
			'qsmQuizzesObject'           => $qsm_quizzes,
			'arrow_up_image'             => esc_url(QSM_PLUGIN_URL . 'assets/arrow-up-s-line.svg'),
			'arrow_down_image'           => esc_url(QSM_PLUGIN_URL . 'assets/arrow-down-s-line.svg'),
			'add_process'                => __('Saving..', 'quiz-master-next'),
			'empty_template_name'        => __('Template name cannot be empty.', 'quiz-master-next'),
			'no_template_selected'       => __('Please selecte a template.', 'quiz-master-next'),
			'empty_template_content'     => __('Template content cannot be empty.', 'quiz-master-next'),
			'template_added'             => __('Template added successfully!', 'quiz-master-next'),
			'template_updated'           => __('Template replaced successfully!', 'quiz-master-next'),
			'template_save_error'        => __('There was an error when saving the template.', 'quiz-master-next'),
			'confirmDeleteTemplate'      => esc_html__( 'Are you sure you want to delete this template?', 'quiz-master-next' ),
			'confirmRemovePage'          => esc_html__( 'Are you sure you want to remove this page?', 'quiz-master-next' ),
			'confirmReplaceTemplate'     => esc_html__( 'This will replace your current template. Continue?', 'quiz-master-next' ),
			'select_template'            => __('Select Template', 'quiz-master-next'),
			'feature_img_placeholder'    => QSM_PLUGIN_URL . 'assets/placeholder.png',
			'delete_confirm'             => esc_html__( 'Are you sure you want to delete?', 'quiz-master-next' ),
        	'delete_alert'               => esc_html__( 'Please select a valid bulk action.', 'quiz-master-next' ),
			'result_template'            => __( 'Thanks for submitting your response! Here are your quiz results. <br>%QUESTIONS_ANSWERS%', 'quiz-master-next' ),
			'error_icon'                 => esc_url(QSM_PLUGIN_URL . 'assets/error-message.png'),
			'success_icon'               => esc_url(QSM_PLUGIN_URL . 'assets/success-message.png'),
			'warning_icon'               => esc_url(QSM_PLUGIN_URL . 'assets/warning-message.png'),
			'info_icon'                  => esc_url(QSM_PLUGIN_URL . 'assets/info-message.png'),
		);
		$qsm_admin_messages = apply_filters( 'qsm_admin_messages_after', $qsm_admin_messages );
		wp_localize_script( 'qsm_admin_js', 'qsm_admin_messages', $qsm_admin_messages );

	}

	/**
	 * Creates Custom Quiz Post Type
	 *
	 * @since 4.1.0
	 * @return void
	 */
	public function register_quiz_post_types() {

		// Checks settings to see if we need to alter the defaults.
		$has_archive    = true;
		$exclude_search = false;
		$cpt_slug       = 'quiz';
		$settings       = (array) get_option( 'qmn-settings' );
		$plural_name    = __( 'Quizzes & Surveys', 'quiz-master-next' );
		$publicly_queryable = ! empty( $settings['disable_quiz_public_link'] ) ? false : true;

		// Checks if admin turned off archive.
		if ( isset( $settings['cpt_archive'] ) && '1' === $settings['cpt_archive'] ) {
			$has_archive = false;
		}

		// Checks if admin turned off search.
		if ( isset( $settings['cpt_search'] ) && '1' === $settings['cpt_search'] ) {
			$exclude_search = true;
		}

		// Checks if admin changed slug.
		if ( isset( $settings['cpt_slug'] ) ) {
			$cpt_slug = trim( strtolower( str_replace( ' ', '-', $settings['cpt_slug'] ) ) );
		}

		// Checks if admin changed plural name.
		if ( isset( $settings['plural_name'] ) ) {
			$plural_name = trim( $settings['plural_name'] );
		}

		// Prepares labels.
		$quiz_labels = array(
			'name'               => $plural_name,
			'singular_name'      => __( 'Quiz', 'quiz-master-next' ),
			'menu_name'          => __( 'Quiz', 'quiz-master-next' ),
			'name_admin_bar'     => __( 'Quiz', 'quiz-master-next' ),
			'add_new'            => __( 'Add New', 'quiz-master-next' ),
			'add_new_item'       => __( 'Add New Quiz', 'quiz-master-next' ),
			'new_item'           => __( 'New Quiz', 'quiz-master-next' ),
			'edit_item'          => __( 'Edit Quiz', 'quiz-master-next' ),
			'view_item'          => __( 'View Quiz', 'quiz-master-next' ),
			'all_items'          => __( 'Quizzes & Surveys', 'quiz-master-next' ),
			'search_items'       => __( 'Search Quizzes', 'quiz-master-next' ),
			'parent_item_colon'  => __( 'Parent Quiz:', 'quiz-master-next' ),
			'not_found'          => __( 'No Quiz Found', 'quiz-master-next' ),
			'not_found_in_trash' => __( 'No Quiz Found In Trash', 'quiz-master-next' ),
		);

		// Prepares post type array.
		$quiz_args = array(
			'public'              => true,
			'show_ui'             => true,
			'show_in_menu'        => 'qsm_dashboard',
			'show_in_nav_menus'   => true,
			'labels'              => $quiz_labels,
			'publicly_queryable'  => $publicly_queryable,
			'exclude_from_search' => $exclude_search,
			'label'               => $plural_name,
			'rewrite'             => array( 'slug' => $cpt_slug ),
			'has_archive'         => $has_archive,
			'supports'            => array( 'title', 'author', 'comments', 'thumbnail' ),
			'capability_type'     => array( 'qsm_quiz', 'qsm_quizzes' ),
			'map_meta_cap'        => true,
		);
		$quiz_args['capabilities'] = array(
			'edit_post'              => 'edit_qsm_quiz',
			'edit_post'              => 'duplicate_qsm_quiz',
			'read_post'              => 'read_qsm_quiz',
			'delete_post'            => 'delete_qsm_quiz',
			'edit_posts'             => 'edit_qsm_quizzes',
			'edit_others_posts'      => 'edit_others_qsm_quizzes',
			'publish_posts'          => 'publish_qsm_quizzes',
			'read_private_posts'     => 'read_private_qsm_quizzes',
			'delete_posts'           => 'delete_qsm_quizzes',
			'delete_private_posts'   => 'delete_private_qsm_quizzes',
			'delete_published_posts' => 'delete_published_qsm_quizzes',
			'delete_others_posts'    => 'delete_others_qsm_quizzes',
			'edit_private_posts'     => 'edit_private_qsm_quizzes',
			'edit_published_posts'   => 'edit_published_qsm_quizzes',
			'create_posts'           => 'create_qsm_quizzes',
			'moderate_comments'      => 'view_qsm_quiz_result',
		);

		// Registers post type.
		register_post_type( 'qsm_quiz', $quiz_args );

		/**
		 * Register Taxonomy
		 */
		$taxonomy_args = array(
			'labels'            => array(
				'menu_name'         => __( 'Question Categories', 'quiz-master-next' ),
				'name'              => __( 'Categories', 'quiz-master-next' ),
				'singular_name'     => __( 'Category', 'quiz-master-next' ),
				'all_items'         => __( 'All Categories', 'quiz-master-next' ),
				'parent_item'       => __( 'Parent Category', 'quiz-master-next' ),
				'parent_item_colon' => __( 'Parent Category:', 'quiz-master-next' ),
				'new_item_name'     => __( 'New Category Name', 'quiz-master-next' ),
				'add_new_item'      => __( 'Add New Category', 'quiz-master-next' ),
				'edit_item'         => __( 'Edit Category', 'quiz-master-next' ),
				'update_item'       => __( 'Update Category', 'quiz-master-next' ),
				'view_item'         => __( 'View Category', 'quiz-master-next' ),
			),
			'hierarchical'      => true,
			'public'            => true,
			'show_ui'           => true,
			'show_admin_column' => true,
			'show_in_nav_menus' => true,
			'show_in_rest'      => true,
			'show_tagcloud'     => false,
			'rewrite'           => false,
			'capabilities'      => array(
				'manage_terms' => 'manage_qsm_quiz_categories',
				'edit_terms'   => 'edit_qsm_quiz_categories',
				'delete_terms' => 'delete_qsm_quiz_categories',
				'assign_terms' => 'assign_qsm_quiz_categories',
			),
		);
		register_taxonomy( 'qsm_category', array( 'qsm-taxonomy' ), $taxonomy_args );
	}

	public function qsm_add_user_capabilities() {
		$administrator_capabilities = array(
			'duplicate_qsm_quiz',
			'delete_qsm_quiz',
			'edit_others_qsm_quizzes',
			'publish_qsm_quizzes',
			'read_private_qsm_quizzes',
			'delete_qsm_quizzes',
			'delete_private_qsm_quizzes',
			'delete_published_qsm_quizzes',
			'delete_others_qsm_quizzes',
			'edit_private_qsm_quizzes',
			'edit_published_qsm_quizzes',
			'manage_qsm_quiz_categories',
			'manage_qsm_quiz_answer_label',
			'view_qsm_quiz_result',
			'edit_qsm_quiz_categories',
			'assign_qsm_quiz_categories',
			'delete_qsm_quiz_categories',
		);
		$editor_capabilities = array(
			'publish_qsm_quizzes',
			'edit_published_qsm_quizzes',
			'edit_others_qsm_quizzes',
			'delete_published_qsm_quizzes',
			'delete_qsm_quiz',
			'delete_qsm_quizzes',
			'manage_qsm_quiz_categories',
			'manage_qsm_quiz_answer_label',
			'view_qsm_quiz_result',
			'edit_qsm_quiz_categories',
			'assign_qsm_quiz_categories',
		);
		$author_capabilities = array(
			'edit_published_qsm_quizzes',
			'publish_qsm_quizzes',
		);
		$contributor_capabilities = array(
			'read_qsm_quiz',
			'edit_qsm_quiz',
			'edit_qsm_quizzes',
			'create_qsm_quizzes',
		);

		$user     = wp_get_current_user();
		if ( empty( $user->roles ) || ! is_array( $user->roles ) ) {
			return;
		}
		$roles    = (array) $user->roles;
		$rolename = $roles[0];
		$role = get_role( $rolename );
		if ( ! $role ) {
			return;
		}

		// Dynamically determine the capabilities to add based on the current user role.
		$capabilities_to_add = isset(${$rolename . '_capabilities'}) ? ${$rolename . '_capabilities'} : array();
		$capabilities_to_add = apply_filters(
			'qsm_default_user_capabilities',
			isset(${$rolename . '_capabilities'}) ? array_unique( array_merge( $capabilities_to_add, $contributor_capabilities ) ) : [],
			$user
		);

		if ( isset( $capabilities_to_add ) ) {
			foreach ( $capabilities_to_add as $cap ) {
				$role->add_cap( $cap );
			}
		}
	}

	public function parent_file( $file_name ) {
		global $menu, $submenu, $parent_file, $submenu_file;
		if ( 'edit-tags.php?taxonomy=qsm_category' === $submenu_file ) {
			$file_name = 'qsm_dashboard';
		}
		return $file_name;
	}

	/**
	 * Setting Menu Position
	 */
	public static function get_free_menu_position( $start, $increment = 0.1 ) {
		foreach ( $GLOBALS['menu'] as $key => $menu ) {
			$menus_positions[] = floatval( $key );
		}
		if ( ! in_array( $start, $menus_positions, true ) ) {
			$start = strval( $start );
			return $start;
		} else {
			$start += $increment;
		}
		/* the position is already reserved find the closet one */
		while ( in_array( $start, $menus_positions, true ) ) {
			$start += $increment;
		}
		$start = strval( $start );
		return $start;
	}

	/**
	 * Setup Admin Menu
	 *
	 * Creates the admin menu and pages for the plugin and attaches functions to them
	 *
	 * @since 3.6.1
	 * @return void
	 */
	public function setup_admin_menu() {
		if ( function_exists( 'add_menu_page' ) ) {
			global $qsm_quiz_list_page;
			$enabled            = get_option( 'qsm_multiple_category_enabled' );
			$menu_position = self::get_free_menu_position(26.1, 0.3);
			$settings = (array) get_option( 'qmn-settings' );

			apply_filters('qsm_user_role_menu_for_subscriber', true);

			$capabilities = array(
				'delete_published_qsm_quizzes',
				'create_qsm_quizzes',
				'delete_others_qsm_quizzes',
				'manage_qsm_quiz_categories',
				'manage_qsm_quiz_answer_label',
				'view_qsm_quiz_result',
				'manage_options',
			);

			add_menu_page( 'Quiz And Survey Master', __( 'QSM', 'quiz-master-next' ), $capabilities[1], 'qsm_dashboard', 'qsm_generate_dashboard_page', 'dashicons-feedback', $menu_position );
			add_submenu_page( 'qsm_dashboard', __( 'Dashboard', 'quiz-master-next' ), __( 'Dashboard', 'quiz-master-next' ), $capabilities[2], 'qsm_dashboard', 'qsm_generate_dashboard_page', 0 );
			if ( $enabled && 'cancelled' !== $enabled ) {
				add_submenu_page( 'qsm_dashboard', __( 'Question Categories', 'quiz-master-next' ), __( 'Question Categories', 'quiz-master-next' ), $capabilities[3], 'edit-tags.php?taxonomy=qsm_category' );
			}
			if ( ! class_exists( 'QSM_Advanced_Assessment' ) ) {
				add_submenu_page( 'qsm_dashboard', __( 'Answer Labels', 'quiz-master-next' ), __( 'Answer Labels', 'quiz-master-next' ), $capabilities[4], 'qsm-answer-label', 'qsm_advanced_assessment_quiz_page_content', 3 );
			}
			add_submenu_page( 'options.php', __( 'Settings', 'quiz-master-next' ), __( 'Settings', 'quiz-master-next' ), $capabilities[1], 'mlw_quiz_options', 'qsm_generate_quiz_options' );
			add_submenu_page( 'qsm_dashboard', __( 'Results', 'quiz-master-next' ), __( 'Results', 'quiz-master-next' ), $capabilities[5], 'mlw_quiz_results', 'qsm_generate_admin_results_page' );

			// Failed Submission.
			if ( ! empty( $settings['enable_qsm_log'] ) && $settings['enable_qsm_log'] ) {
				add_submenu_page( 'qsm_dashboard', __( 'Failed Submission', 'quiz-master-next' ), __( 'Failed Submission', 'quiz-master-next' ), $capabilities[2], 'qsm-quiz-failed-submission', array( $this, 'admin_failed_submission_page' ) );
			}
			// Failed DB Query
			if ( ! empty( $settings['enable_qsm_log'] ) && $settings['enable_qsm_log'] && $this->get_failed_alter_table_queries() ) {
				add_submenu_page( 'qsm_dashboard', __( 'Failed DB Queries', 'quiz-master-next' ), __( 'Failed Database Queries', 'quiz-master-next' ), $capabilities[2], 'qsm-database-failed-queries', array( $this, 'qsm_database_failed_queries' ) );
			}
			add_submenu_page( 'options.php', __( 'Result Details', 'quiz-master-next' ), __( 'Result Details', 'quiz-master-next' ), $capabilities[5], 'qsm_quiz_result_details', 'qsm_generate_result_details' );
			add_submenu_page( 'qsm_dashboard', __( 'Settings', 'quiz-master-next' ), __( 'Settings', 'quiz-master-next' ), $capabilities[6], 'qmn_global_settings', array( 'QMNGlobalSettingsPage', 'display_page' ) );
			add_submenu_page( 'qsm_dashboard', __( 'Tools', 'quiz-master-next' ), __( 'Tools', 'quiz-master-next' ), $capabilities[2], 'qsm_quiz_tools', 'qsm_generate_quiz_tools' );
			add_submenu_page( 'qsm_dashboard', __( 'Stats', 'quiz-master-next' ), __( 'Stats', 'quiz-master-next' ), $capabilities[2], 'qmn_stats', 'qmn_generate_stats_page' );
			add_submenu_page( 'qsm_dashboard', __( 'About', 'quiz-master-next' ), __( 'About', 'quiz-master-next' ), $capabilities[2], 'qsm_quiz_about', 'qsm_generate_about_page' );

			add_submenu_page( 'qsm_dashboard', __( 'Extensions Settings', 'quiz-master-next' ), '<span style="color:#f39c12;">' . __( 'Extensions', 'quiz-master-next' ) . '</span>', $capabilities[2], 'qmn_addons', 'qmn_addons_page', 34 );
			add_submenu_page( 'qsm_dashboard', __( 'Free Add-ons', 'quiz-master-next' ), '<span style="color:#f39c12;">' . esc_html__( 'Free Add-ons', 'quiz-master-next' ) . '</span>', $capabilities[2], 'qsm-free-addon', 'qsm_display_optin_page', 90 );
			add_submenu_page( 'qsm_dashboard', __( 'Create Quiz Page', 'quiz-master-next' ), '', $capabilities[1], 'qsm_create_quiz_page', 'qsm_create_quiz_page_callback' );
		}
	}

	/**
	 * Failed Submission Table
	 *
	 * Display failed submission table.
	 *
	 * @since 9.0.2
	 * @return void
	 */
	public function admin_failed_submission_page() {
		$file_path = trailingslashit( plugin_dir_path( __FILE__ ) ) . 'php/admin/class-failed-submission.php';
		if ( file_exists( $file_path ) ) {
			include_once $file_path;
			if ( ! class_exists( 'QmnFailedSubmissions' ) ) {
				return;
			}
			$QmnFailedSubmissions = new QmnFailedSubmissions();
			$QmnFailedSubmissions->render_list_table();
		}
	}
	/**
     * Check database structure
     *
     * @since 9.1.0
     * @return void
     */
    public function qsm_check_database_structure() {
        global $wpdb;

        // Define the table names
        $quiz_table_name                 = $wpdb->prefix . 'mlw_quizzes';
        $question_table_name             = $wpdb->prefix . 'mlw_questions';
        $results_table_name              = $wpdb->prefix . 'mlw_results';
        $audit_table_name                = $wpdb->prefix . 'mlw_qm_audit_trail';
        $themes_table_name               = $wpdb->prefix . 'mlw_themes';
        $quiz_themes_settings_table_name = $wpdb->prefix . 'mlw_quiz_theme_settings';
        $question_terms_table_name       = $wpdb->prefix . 'mlw_question_terms';

        // List of tables and their columns
        $tables = [
            $quiz_table_name                 => [
                'quiz_id',
				'quiz_name',
				'message_before',
				'message_after',
				'message_comment',
				'message_end_template',
                'user_email_template',
				'admin_email_template',
				'submit_button_text',
				'name_field_text',
				'business_field_text',
                'email_field_text',
				'phone_field_text',
				'comment_field_text',
				'email_from_text',
				'question_answer_template',
                'leaderboard_template',
				'quiz_system',
				'randomness_order',
				'loggedin_user_contact',
				'show_score',
				'send_user_email',
                'send_admin_email',
				'contact_info_location',
				'user_name',
				'user_comp',
				'user_email',
				'user_phone',
				'admin_email',
                'comment_section',
				'question_from_total',
				'total_user_tries',
				'total_user_tries_text',
				'certificate_template',
                'social_media',
				'social_media_text',
				'pagination',
				'pagination_text',
				'timer_limit',
				'quiz_stye',
				'question_numbering',
                'quiz_settings',
				'theme_selected',
				'last_activity',
				'require_log_in',
				'require_log_in_text',
				'limit_total_entries',
                'limit_total_entries_text',
				'scheduled_timeframe',
				'scheduled_timeframe_text',
				'disable_answer_onselect',
				'ajax_show_correct',
                'quiz_views',
				'quiz_taken',
				'deleted',
				'quiz_author_id',
            ],
            $question_table_name             => [
                'question_id',
				'quiz_id',
				'question_name',
				'answer_array',
				'answer_one',
				'answer_one_points',
				'answer_two',
                'answer_two_points',
				'answer_three',
				'answer_three_points',
				'answer_four',
				'answer_four_points',
				'answer_five',
                'answer_five_points',
				'answer_six',
				'answer_six_points',
				'correct_answer',
				'question_answer_info',
				'comments',
                'hints',
				'question_order',
				'question_type',
				'question_type_new',
				'question_settings',
				'category',
				'deleted',
                'deleted_question_bank',
            ],
            $results_table_name              => [
                'result_id',
				'quiz_id',
				'quiz_name',
				'quiz_system',
				'point_score',
				'correct_score',
				'correct',
				'total',
				'name',
                'business',
				'email',
				'phone',
				'user',
				'user_ip',
				'time_taken',
				'time_taken_real',
				'quiz_results',
				'deleted',
                'unique_id',
				'form_type',
				'page_name',
				'page_url',
            ],
            $audit_table_name                => [
                'trail_id',
				'action_user',
				'action',
				'quiz_id',
				'quiz_name',
				'form_data',
				'time',
            ],
            $themes_table_name               => [
                'id',
				'theme',
				'theme_name',
				'default_settings',
				'theme_active',
            ],
            $quiz_themes_settings_table_name => [
                'id',
				'theme_id',
				'quiz_id',
				'quiz_theme_settings',
				'active_theme',
            ],
            $question_terms_table_name       => [
                'id',
				'question_id',
				'quiz_id',
				'term_id',
				'taxonomy',
            ],
        ];
		$response['message'] = "";
        // Check all tables
        $errors = [];
        foreach ( $tables as $table_name => $columns ) {
            $error = $this->qsm_check_table_structure($table_name, $columns);
            if ( $error ) {
                $errors[] = $error;
            }
        }
        if ( ! empty($errors) ) {
			$response['message'] .= esc_html__("Incorrect database structure!", "quiz-master-next") . "<br/>";
            foreach ( $errors as $error ) {
				$response['status'] = "error";
                $response['message'] .= esc_html($error) . "<br>";
            }
            update_option('qsm_check_database_structure', "error");
        } else {
            update_option('qsm_check_database_structure', "success");
			$response['status'] = "success";
			$response['message'] = esc_html__("All tables have the correct structure.", "quiz-master-next");
        }
		if ( ! empty( $_GET['qsm_check_database_structure'] ) ) {
			$this->alertManager->newAlert( $response['message'], $response['status'] );
		}else {
			return $response;
		}
    }

    /**
     * Check if table and columns exist
     *
     * @since 9.1.0
     * @param string $table_name
     * @param array $expected_columns
     * @return string|null
     */
    public function qsm_check_table_structure( $table_name, $expected_columns ) {
        global $wpdb;
        $columns = $wpdb->get_results("SHOW COLUMNS FROM $table_name");
        if ( ! $columns ) {
            return esc_html__("Table ", "quiz-master-next") . $table_name . esc_html__(" does not exist.", "quiz-master-next");
        }
        $existing_columns = array_column($columns, 'Field');
        $missing_columns = [];
        foreach ( $expected_columns as $column ) {
            if ( ! in_array($column, $existing_columns, true) ) {
                $missing_columns[] = $column;
            }
        }
        if ( ! empty($missing_columns) ) {
            return esc_html__("Table ", "quiz-master-next") . $table_name . esc_html__(" is missing columns: ", "quiz-master-next") . implode(', ', $missing_columns);
        }
        return null;
    }

	/**
	 * Failed Database queries
	 *
	 * Display failed Database queries.
	 *
	 * @since 9.0.3
	 * @return void
	 */
	public function qsm_database_failed_queries() {
		?>
		<div class="wrap">
			<div>
				<h2>
					<?php esc_html_e( 'Failed DB Queries', 'quiz-master-next' );?>
				</h2>
			</div>
			<div class="qsm-alerts">
				<?php $this->alertManager->showAlerts(); ?>
			</div>
			<?php qsm_show_adverts(); ?>
			<table class="widefat" aria-label="<?php esc_attr_e( 'Failed DB Query Table', 'quiz-master-next' );?>">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Query', 'quiz-master-next' );?></th>
						<th><?php esc_html_e( 'Action', 'quiz-master-next' );?></th>
					</tr>
				</thead>
				<tbody>
					<?php
					$failed_queries = $this->get_failed_alter_table_queries();
					if ( ! empty( $failed_queries ) && is_array( $failed_queries ) ) {
						foreach ( $failed_queries as $key => $query ) { ?>
							<tr>
								<td>
									<?php echo esc_attr( $query ); ?>
								</td>
								<td>
									<button data-query="<?php echo esc_attr( $key ); ?>" type="button"  data-nonce="<?php echo esc_attr( wp_create_nonce( 'qmn_check_db' ) ); ?>" class="button button-primary qsm-check-db-fix-btn"><?php esc_html_e( 'Check If Already Fixed', 'quiz-master-next' );?></button>
								</td>
							</tr>
						<?php }
					} ?>
				</tbody>
			</table>
		</div>
		<?php
		add_action('admin_footer', 'qsm_quiz_options_notice_template');
	}

	/**
	 * Removes Unnecessary Admin Page
	 *
	 * Removes the update, quiz settings, and quiz results pages from the Quiz Menu
	 *
	 * @since 4.1.0
	 * @return void
	 */
	public function admin_head() {
		remove_submenu_page( 'quiz-master-next/mlw_quizmaster2.php', 'mlw_quiz_options' );
		remove_submenu_page( 'quiz-master-next/mlw_quizmaster2.php', 'qsm_quiz_result_details' );
	}
	/**
	 * Overide Old Quiz Settings Options
	 *
	 * @since 7.1.16
	 * @return void
	 */
	public function qsm_overide_old_setting_options() {
		$settings = (array) get_option( 'qmn-settings' );
		if ( isset( $settings['facebook_app_id'] ) ) {
			$facebook_app_id = $settings['facebook_app_id'];
			if ( '483815031724529' === $facebook_app_id ) {
				$settings['facebook_app_id'] = '594986844960937';
				update_option( 'qmn-settings', $settings );
			}
		} else {
			$settings['facebook_app_id'] = '594986844960937';
			update_option( 'qmn-settings', $settings );
		}
	}

	/**
	 * Displays QSM Admin notices
	 *
	 * @return void
	 * @since 7.3.0
	 */
	public function qsm_admin_notices() {
		$multiple_categories = get_option( 'qsm_multiple_category_enabled' );
		if ( ! $multiple_categories ) {
			?>
			<div class="notice notice-info multiple-category-notice" style="display:none;">
				<h3><?php esc_html_e( 'Database update required', 'quiz-master-next' ); ?></h3>
				<p>
					<?php esc_html_e( 'QSM has been updated!', 'quiz-master-next' ); ?><br>
					<?php esc_html_e( 'We need to upgrade your database so that you can enjoy the latest features.', 'quiz-master-next' ); ?><br>
					<?php
					/* translators: %s: HTML tag */
					echo sprintf( esc_html__( 'Please note that this action %1$s can not be %2$s rolled back. We recommend you to take a backup of your current site before proceeding.', 'quiz-master-next' ), '<b>', '</b>' );
					?>
				</p>
				<p class="category-action">
					<a href="javascrip:void(0)" class="button cancel-multiple-category"><?php esc_html_e( 'Cancel', 'quiz-master-next' ); ?></a>
					&nbsp;&nbsp;&nbsp;<a href="javascript:void(0)" class="button button-primary enable-multiple-category"><?php esc_html_e( 'Update Database', 'quiz-master-next' ); ?></a>
				</p>
			</div>
			<?php
		}

		$settings                        = (array) get_option( 'qmn-settings' );
		$background_quiz_email_process   = isset( $settings['background_quiz_email_process'] ) ? $settings['background_quiz_email_process'] : 1;
		if ( 1 == $background_quiz_email_process && is_plugin_active( 'wpml-string-translation/plugin.php' ) ) {
			?>
			<div class="notice notice-warning">
				<p><?php esc_html_e( '"Process emails in background" option is enabled. WPML string translation may not work as expected for email templates. Please disable this option to send translated strings in emails.', 'quiz-master-next' ); ?></p>
			</div>
			<?php
		}
	}
}

global $mlwQuizMasterNext;
$mlwQuizMasterNext = new MLWQuizMasterNext();
register_activation_hook( __FILE__, array( 'QSM_Install', 'install' ) );

/**
 * Displays QSM Admin bar menu
 *
 * @return void
 * @since 7.3.8
 */
function qsm_edit_quiz_admin_option() {
	global $wp_admin_bar, $pagenow, $wpdb;
	if ( 'qsm_quiz' == get_post_type() && 'edit.php' != $pagenow ) {
		$post_id = get_the_ID();
		$quiz_id = get_post_meta( $post_id, 'quiz_id', true );
		if ( ! empty( $quiz_id ) ) {
			$wp_admin_bar->remove_menu('edit');
			$wp_admin_bar->add_menu(
				array(
					'id'    => 'edit-quiz',
					'title' => '<span class="ab-icon dashicons dashicons-edit"></span><span class="ab-label">' . __( 'Edit Quiz', 'quiz-master-next' ) . '</span>',
					'href'  => admin_url() . 'admin.php?page=mlw_quiz_options&quiz_id=' . $quiz_id,
				)
			);
		}
	}
}

add_action( 'admin_bar_menu', 'qsm_edit_quiz_admin_option', 999 );

/**
 * Add inline QSM template
 *
 * @return void
 * @since 7.3.14
 */
function qsm_add_inline_tmpl( $handle, $id, $tmpl ) {
	// Collect input data
	static $data            = array();
	$data[ $handle ][ $id ] = $tmpl;

	// Append template for relevant script handle
	add_filter(
		'script_loader_tag',
		function( $tag, $hndl ) use ( &$data, $id ) {
			// Nothing to do if no match
			if ( ! isset( $data[ $hndl ][ $id ] ) ) {
				return $tag;
			}

			// Script tag replacement aka wp_add_inline_script()
			if ( false !== stripos( $data[ $hndl ][ $id ], '</script>' ) ) {
				$data[ $hndl ][ $id ] = trim(
					preg_replace(
						'#<script[^>]*>(.*)</script>#is',
						'$1',
						$data[ $hndl ][ $id ]
					)
				);
			}

			// Append template
			$tag .= sprintf(
				"<script type='text/template' id='%s'>\n%s\n</script>" . PHP_EOL,
				esc_attr( $id ),
				$data[ $hndl ][ $id ]
			);

			return $tag;
		},
		10,
		3
	);
}

// Helper function to generate questions with AI (OpenAI or other providers)
function qsm_generate_questions_with_ai($ai_guidance) {
	$endpoint = 'https://api.openai.com/v1/chat/completions';
    $settings = get_option('qmn-settings', array());
    $api_key = isset($settings['openai_api_key']) ? trim($settings['openai_api_key']) : '';
    if (empty($api_key)) {
        return array('success' => false, 'error' => 'No OpenAI API key set in plugin settings.');
    }
	
	$data = [
        'model' => 'gpt-3.5-turbo',
        'messages' => [
            ['role' => 'system', 'content' => 'You are a helpful quiz generator.'],
            ['role' => 'user', 'content' => $ai_guidance]
        ],
        'max_tokens' => 1500,
        'temperature' => 0.7,
    ];
    $response = wp_remote_post($endpoint, [
        'headers' => [
            'Content-Type' => 'application/json',
            'Authorization' => 'Bearer ' . $api_key,
        ],
        'body' => json_encode($data),
        'timeout' => 60,
    ]);
    if (is_wp_error($response)) {
        return array('success' => false, 'error' => 'HTTP request error: ' . $response->get_error_message());
    }
    $body = wp_remote_retrieve_body($response);
    $result = json_decode($body, true);
    if (!isset($result['choices'][0]['message']['content'])) {
        return array('success' => false, 'error' => 'OpenAI API did not return expected content. Raw response: ' . $body);
    }
    $json = $result['choices'][0]['message']['content'];
    // Try to decode the JSON from the response
    $questions = json_decode($json, true);
    if (!is_array($questions)) {
        return array('success' => false, 'error' => 'Failed to parse questions JSON. Raw content: ' . $json);
    }
    return array('success' => true, 'questions' => $questions);
}

function qsm_generate_quiz_with_ai_handler() {
    // Check nonce
    check_ajax_referer('qsm_installer_nonce', 'nonce');

    // Check permissions
    if (!current_user_can('create_qsm_quizzes')) {
        wp_send_json_error(array('message' => 'Permission denied'));
        wp_die();
    }

    // Get quiz details
    $quiz_name = isset($_POST['quiz_name']) ? sanitize_text_field(wp_unslash($_POST['quiz_name'])) : '';
    $quiz_description = isset($_POST['quiz_description']) ? sanitize_textarea_field(wp_unslash($_POST['quiz_description'])) : '';
    $num_questions = isset($_POST['num_questions']) ? max(1, min(20, intval($_POST['num_questions']))) : 10;
	$quiz_options = get_option('qsm-quiz-settings', array());
	$default_ai_guidance = isset($quiz_options['default_ai_guidance']) ? $quiz_options['default_ai_guidance'] : '';
	$ai_guidance = isset($_POST['ai_guidance']) ? trim(wp_unslash($_POST['ai_guidance'])) : $default_ai_guidance;
	$ai_guidance = str_replace('[QUIZ_DESCRIPTION]', $quiz_description, $ai_guidance);
	$ai_guidance = str_replace('[NUM_QUESTIONS]', $num_questions, $ai_guidance);

    if (empty($quiz_name) || empty($quiz_description)) {
        wp_send_json_error(array('message' => 'Quiz name and description are required'));
        wp_die();
    }

    // Generate questions with AI
    $ai_result = qsm_generate_questions_with_ai($ai_guidance);
    if (!$ai_result['success'] || !isset($ai_result['questions']) || !is_array($ai_result['questions'])) {
        $error_message = isset($ai_result['error']) ? $ai_result['error'] : 'Failed to generate questions with AI. Please try again.';
        wp_send_json_error(array('message' => $error_message));
        wp_die();
    }
    $questions = $ai_result['questions'];

    global $wpdb;

    // Check if a quiz with this name already exists
    $existing_quiz = $wpdb->get_var($wpdb->prepare(
        "SELECT quiz_id FROM {$wpdb->prefix}mlw_quizzes WHERE quiz_name = %s AND deleted = 0",
        $quiz_name
    ));

    if ($existing_quiz) {
        wp_send_json_error(array('message' => 'A quiz with this name already exists'));
        wp_die();
    }

    // First create the WordPress post
    $quiz_post = array(
        'post_title'   => $quiz_name,
        'post_content' => $quiz_description,
        'post_status'  => 'publish',
        'post_type'    => 'qsm_quiz'
    );

    $quiz_post_id = wp_insert_post($quiz_post);

    if (is_wp_error($quiz_post_id)) {
        wp_send_json_error(array('message' => 'Failed to create quiz post'));
        wp_die();
    }

    // Create the quiz in the plugin's table with all required fields
    $quiz_data = array(
		'quiz_name' => $quiz_name,
        'quiz_system' => 1, // Points
        'theme_selected' => 'default',
        'submit_button_text' => 'Submit',
        'last_activity' => current_time('mysql'),
        'quiz_author_id' => get_current_user_id()
    );

    $result = $wpdb->insert($wpdb->prefix . 'mlw_quizzes', $quiz_data);
    
    if ($result === false) {
        // If quiz creation fails, delete the post
        wp_delete_post($quiz_post_id, true);
        error_log('QSM Quiz Creation Error: ' . $wpdb->last_error);
        wp_send_json_error(array(
            'message' => 'Failed to create quiz in database',
            'error' => $wpdb->last_error
        ));
        wp_die();
    }
    
    $quiz_id = $wpdb->insert_id;

    if (!$quiz_id) {
        // If no quiz ID, delete the post
        wp_delete_post($quiz_post_id, true);
        error_log('QSM Quiz Creation Error: No quiz ID returned after insertion');
        wp_send_json_error(array('message' => 'Failed to create quiz in database - no ID returned'));
        wp_die();
    }

    // Check if this quiz_id already exists in post meta for any post
    $existing_post = $wpdb->get_var($wpdb->prepare(
        "SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = 'quiz_id' AND meta_value = %d",
        $quiz_id
    ));

    if ($existing_post) {
        // If quiz_id already exists in post meta, delete both the new post and the quiz
        wp_delete_post($quiz_post_id, true);
        $wpdb->delete($wpdb->prefix . 'mlw_quizzes', array('quiz_id' => $quiz_id));
        error_log('QSM Quiz Creation Error: Quiz ID already exists in post meta');
        wp_send_json_error(array('message' => 'Failed to create quiz - ID conflict detected'));
        wp_die();
    }

    // Store quiz ID in post meta
    update_post_meta($quiz_post_id, 'quiz_id', $quiz_id);

    // Insert generated questions
	$log = [];
    $order = 1;
    foreach ($questions as $q) {
        if (!isset($q['question']) || !isset($q['description']) || !isset($q['answers']) || !is_array($q['answers'])) {
			error_log('QSM Quiz Generation Error: Missing required fields for question: ' . print_r($q, true));
			continue;
		}

        if (count($q['answers']) < 2) {
			error_log('QSM Quiz Generation Error: Not enough answer options for question: ' . print_r($q, true));
			continue;
		}

        $question_data = array(
            'quiz_id' => $quiz_id,
            'question_name' => $q['question'],
            'answer_array' => serialize($q['answers']), // Required for backward compatibility
            'question_answer_info' => '',
            'comments' => 1,
            'question_order' => $order,
            'question_type' => 0,
            'question_type_new' => 0,
            'question_settings' => serialize(array(
                'question_title' => $q['question'],
                'question_type' => 0,
                'question_description' => $q['description'],
                'answers' => $q['answers']
            ))
        );
        $wpdb->insert($wpdb->prefix . 'mlw_questions', $question_data);

		$log[] = array(
            'quiz_id' => $quiz_id,
            'question_name' => $q['question'],
            'answer_array' => $q['answers'], // Skip serialization for log
            'question_answer_info' => '',
            'comments' => 1,
            'question_order' => $order,
            'question_type' => 0,
            'question_type_new' => 0,
            'question_settings' => array( // Skip serialization for log
                'question_title' => $q['question'],
                'question_type' => 0,
                'question_description' => $q['description'],
                'answers' => $q['answers']
            )
        );
        $order++;
    }

    $question_ids = $wpdb->get_col($wpdb->prepare(
        "SELECT question_id FROM {$wpdb->prefix}mlw_questions WHERE quiz_id = %d ORDER BY question_order ASC",
        $quiz_id
    ));
    // Create a single page with all questions
    $pages = array($question_ids);
    $qpages = array(array(
        'id' => 1,
        'quizID' => $quiz_id,
        'pagekey' => uniqid(),
        'hide_prevbtn' => 0,
        'questions' => $question_ids,
    ));
    global $mlwQuizMasterNext;
    $mlwQuizMasterNext->quiz_settings->prepare_quiz($quiz_id);
    $mlwQuizMasterNext->pluginHelper->update_quiz_setting('pages', $pages);
    $mlwQuizMasterNext->pluginHelper->update_quiz_setting('qpages', $qpages);
	
    // Save the raw questions JSON for later AI use
    $mlwQuizMasterNext->pluginHelper->update_quiz_setting('ai_questions_json', json_encode($questions));

    // Set form_type to Survey and system to Points in quiz settings
    global $mlwQuizMasterNext;
    $mlwQuizMasterNext->quiz_settings->prepare_quiz($quiz_id);
    $quiz_settings = $mlwQuizMasterNext->pluginHelper->get_quiz_setting('quiz_options');
    $quiz_settings['form_type'] = 1; // Survey
    $quiz_settings['system'] = 1; // Points
    $quiz_settings['enable_quick_correct_answer_info'] = 0; // Never Display correct answer info
    $quiz_settings['enable_quick_result_mc'] = 0; // Disable real-time results per question
    $mlwQuizMasterNext->pluginHelper->update_quiz_setting('quiz_options', $quiz_settings);

    // Redirect to quiz edit page
    $redirect_url = admin_url('admin.php?page=mlw_quiz_options&quiz_id=' . $quiz_id);
    wp_send_json_success(array(
        'redirect_url' => $redirect_url,
        'log' => $log,
    ));
    wp_die();
}

function qsm_generate_results_with_ai_handler() {
    // Check nonce
    check_ajax_referer('wp_rest', 'nonce');

    // Check permissions
    if (!current_user_can('create_qsm_quizzes')) {
        wp_send_json_error(array('message' => 'Permission denied'));
        wp_die();
    }

	$endpoint = 'https://api.openai.com/v1/chat/completions';
	
    $settings = get_option('qmn-settings', array());
    $api_key = isset($settings['openai_api_key']) ? trim($settings['openai_api_key']) : '';
    if (empty($api_key)) {
        wp_send_json_error(array('message' => 'No OpenAI API key set in plugin settings.'));
        wp_die();
    }

    $quiz_id = isset($_POST['quiz_id']) ? intval($_POST['quiz_id']) : 0;
    if (!$quiz_id) {
        wp_send_json_error(array('message' => 'Invalid quiz ID'));
        wp_die();
    }

    global $mlwQuizMasterNext;
    $mlwQuizMasterNext->quiz_settings->prepare_quiz($quiz_id);
    
    // Get previously generated questions for context
    $questions_json = $mlwQuizMasterNext->pluginHelper->get_quiz_setting('ai_questions_json', '');
    if (empty($questions_json)) {
        wp_send_json_error(array('message' => 'No questions JSON found for this quiz.'));
        wp_die();
    }

    // Get quiz data for context
    global $wpdb;
    $quiz_data = $wpdb->get_row($wpdb->prepare(
        "SELECT quiz_name, quiz_settings FROM {$wpdb->prefix}mlw_quizzes WHERE quiz_id = %d",
        $quiz_id
    ));

    if (!$quiz_data) {
        wp_send_json_error(array('message' => 'Quiz not found'));
        wp_die();
    }

    // Get quiz settings
    $quiz_settings = maybe_unserialize($quiz_data->quiz_settings);
    $total_points = isset($quiz_settings['total_points']) ? intval($quiz_settings['total_points']) : 0;

    // Load AI results guidance from plugin settings, with a default fallback
    $quiz_options = get_option('qsm-quiz-settings', array());
    $ai_results_guidance = isset($quiz_options['ai_results_guidance']) ? $quiz_options['ai_results_guidance'] : '';
    $ai_guidance = str_replace('[QUESTIONS_JSON]', $questions_json, $ai_results_guidance);
    $ai_guidance = str_replace('[QUIZ_NAME]', $quiz_data->quiz_name, $ai_guidance);
    $ai_guidance = str_replace('[TOTAL_POINTS]', $total_points, $ai_guidance);
    $data = [
        'model' => 'gpt-3.5-turbo',
        'messages' => [
            ['role' => 'system', 'content' => 'You are a helpful assistant that generates quiz result messages.'],
            ['role' => 'user', 'content' => $ai_guidance]
        ],
        'max_tokens' => 1500,
        'temperature' => 0.7,
    ];
    $response = wp_remote_post($endpoint, array(
        'headers' => array(
            'Content-Type' => 'application/json',
            'Authorization' => 'Bearer ' . $api_key,
        ),
        'body' => json_encode($data),
        'timeout' => 60,
    ));
    if (is_wp_error($response)) {
        wp_send_json_error(array('message' => 'Failed to connect to OpenAI: ' . $response->get_error_message()));
        wp_die();
    }
    $body = wp_remote_retrieve_body($response);
    $result = json_decode($body, true);
    if (!isset($result['choices'][0]['message']['content'])) {
        wp_send_json_error(array(
            'message' => 'Invalid response from OpenAI',
            'debug' => array(
                'body' => $body,
                'result' => $result
            )
        ));
        wp_die();
    }

    // Parse AI response
    $raw_response = $result['choices'][0]['message']['content'];

    // Remove code block markers
    $clean_response = preg_replace('/^```json\\s*|```$/m', '', $raw_response);

    // Try to extract the first JSON array or object from the response
    if (preg_match('/(\\[.*\\]|\\{.*\\})/s', $clean_response, $matches)) {
        $json_candidate = $matches[1];
    } else {
        $json_candidate = $clean_response;
    }

    $results = json_decode($json_candidate, true);
    if (!is_array($results)) {
        wp_send_json_error(array(
            'message' => 'Invalid JSON from AI response',
            'debug' => array(
                'raw_response' => $raw_response,
                'clean_response' => $clean_response,
                'json_candidate' => $json_candidate,
                'json_error' => json_last_error_msg()
            )
        ));
        wp_die();
    }

    // Save results to database
    $results_array = array();
    foreach ($results as $result) {
        if (!isset($result['min_score']) || !isset($result['max_score']) || !isset($result['message'])) {
            continue;
        }
        $results_array[] = array(
            'id' => uniqid(),
            'conditions' => array(
                array(
                    'category' => 'quiz',
                    'extra_condition' => '',
                    'criteria' => 'points',
                    'operator' => 'greater-equal',
                    'value' => strval($result['min_score']),
                ),
                array(
                    'category' => 'quiz',
                    'extra_condition' => '',
                    'criteria' => 'points',
                    'operator' => 'less-equal',
                    'value' => strval($result['max_score']),
                ),
            ),
            'page' => sanitize_textarea_field($result['message']),
            'redirect' => false,
            'default_mark' => false,
            'is_updated' => 1,
        );
    }
    $quiz_settings['results_pages'] = $results_array;

    // Update quiz settings (optional, for reference)
    $wpdb->update(
        $wpdb->prefix . 'mlw_quizzes',
        array('quiz_settings' => maybe_serialize($quiz_settings)),
        array('quiz_id' => $quiz_id),
        array('%s'),
        array('%d')
    );

    // Update message_after so the UI can load the new results pages
    $wpdb->update(
        $wpdb->prefix . 'mlw_quizzes',
        array('message_after' => maybe_serialize($results_array)),
        array('quiz_id' => $quiz_id),
        array('%s'),
        array('%d')
    );

    wp_send_json_success(array(
        'message' => 'Results pages generated successfully',
        'raw_response' => $raw_response,
		'clean_response' => $clean_response,
		'json_candidate' => $json_candidate,
        'results' => $results_array,
    ));
    wp_die();
}
