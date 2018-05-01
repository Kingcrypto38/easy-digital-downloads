<?php
/**
 * Plugin Name: Easy Digital Downloads
 * Plugin URI: https://easydigitaldownloads.com
 * Description: The easiest way to sell digital products with WordPress.
 * Author: Easy Digital Downloads
 * Author URI: https://easydigitaldownloads.com
 * Version: 2.9.1
 * Text Domain: easy-digital-downloads
 * Domain Path: languages
 *
 * Easy Digital Downloads is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 2 of the License, or
 * any later version.
 *
 * Easy Digital Downloads is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with Easy Digital Downloads. If not, see <http://www.gnu.org/licenses/>.
 *
 * @package EDD
 * @category Core
 * @author Pippin Williamson
 * @version 2.9.1
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) exit;

if ( ! class_exists( 'Easy_Digital_Downloads' ) ) :

/**
 * Main Easy_Digital_Downloads Class.
 *
 * @since 1.4
 */
final class Easy_Digital_Downloads {
	/** Singleton *************************************************************/

	/**
	 * @var Easy_Digital_Downloads The one true Easy_Digital_Downloads
	 * @since 1.4
	 */
	private static $instance;

	/**
	 * EDD Roles Object.
	 *
	 * @var object|EDD_Roles
	 * @since 1.5
	 */
	public $roles;

	/**
	 * EDD Cart Fees Object.
	 *
	 * @var object|EDD_Fees
	 * @since 1.5
	 */
	public $fees;

	/**
	 * EDD API Object.
	 *
	 * @var object|EDD_API
	 * @since 1.5
	 */
	public $api;

	/**
	 * EDD Utilities Object.
	 *
	 * @var object|EDD_Utilities
	 * @since 3.0
	 */
	public $utils;

	/**
	 * EDD HTML Session Object.
	 *
	 * This holds cart items, purchase sessions, and anything else stored in the session.
	 *
	 * @var object|EDD_Session
	 * @since 1.5
	 */
	public $session;

	/**
	 * EDD HTML Element Helper Object.
	 *
	 * @var object|EDD_HTML_Elements
	 * @since 1.5
	 */
	public $html;

	/**
	 * EDD Emails Object.
	 *
	 * @var object|EDD_Emails
	 * @since 2.1
	 */
	public $emails;

	/**
	 * EDD Email Template Tags Object.
	 *
	 * @var object|EDD_Email_Template_Tags
	 * @since 1.9
	 */
	public $email_tags;

	/**
	 * EDD Cart Object
	 *
	 * @var object|EDD_Cart
	 * @since 2.7
	 */
	public $cart;

	/**
	 * EDD Components array
	 *
	 * @var array
	 * @since 3.0
	 */
	public $components = array();

	/**
	 * EDD Requirements array
	 *
	 * @var array
	 * @since 3.0
	 */
	private $requirements = array(

		// PHP
		'php' => array(
			'minimum' => '5.3.0',
			'current' => false,
			'checked' => false,
			'ok'      => false
		),

		// WordPress
		'wp' => array(
			'minimum' => '4.4.0',
			'current' => false,
			'checked' => false,
			'ok'      => false
		)
	);

	/**
	 * Main Easy_Digital_Downloads Instance.
	 *
	 * Insures that only one instance of Easy_Digital_Downloads exists in memory at any one
	 * time. Also prevents needing to define globals all over the place.
	 *
	 * @since 1.4
	 * @static
	 * @staticvar array $instance
	 * @uses Easy_Digital_Downloads::setup_constants() Setup the constants needed.
	 * @uses Easy_Digital_Downloads::includes() Include the required files.
	 * @uses Easy_Digital_Downloads::load_textdomain() load the language files.
	 * @see EDD()
	 * @return object|Easy_Digital_Downloads The one true Easy_Digital_Downloads
	 */
	public static function instance() {
		if ( ! isset( self::$instance ) && ! ( self::$instance instanceof Easy_Digital_Downloads ) ) {
			self::$instance = new Easy_Digital_Downloads;

			add_action( 'plugins_loaded', array( self::$instance, 'load_textdomain' ) );

			// Bootstrap
			self::$instance->setup_requirements();
			self::$instance->setup_constants();
			self::$instance->setup_files();

			// APIs
			self::$instance->roles         = new EDD_Roles();
			self::$instance->fees          = new EDD_Fees();
			self::$instance->api           = new EDD_API();
			self::$instance->utils         = new EDD_Utilities();
			self::$instance->session       = new EDD_Session();
			self::$instance->html          = new EDD_HTML_Elements();
			self::$instance->emails        = new EDD_Emails();
			self::$instance->email_tags    = new EDD_Email_Template_Tags();
			self::$instance->payment_stats = new EDD_Payment_Stats();
			self::$instance->cart          = new EDD_Cart();

			// Register backwards compatibility hooks
			new EDD\Compat\Customer();
		}

		return self::$instance;
	}

	/**
	 * Throw error on object clone.
	 *
	 * The whole idea of the singleton design pattern is that there is a single
	 * object therefore, we don't want the object to be cloned.
	 *
	 * @since 1.6
	 * @access protected
	 * @return void
	 */
	public function __clone() {
		// Cloning instances of the class is forbidden.
		_doing_it_wrong( __FUNCTION__, __( 'Cheatin&#8217; huh?', 'easy-digital-downloads' ), '1.6' );
	}

	/**
	 * Disable un-serializing of the class.
	 *
	 * @since 1.6
	 * @access protected
	 * @return void
	 */
	public function __wakeup() {
		// Unserializing instances of the class is forbidden.
		_doing_it_wrong( __FUNCTION__, __( 'Cheatin&#8217; huh?', 'easy-digital-downloads' ), '1.6' );
	}

	/**
	 * Backwards compatibility for some database properties
	 *
	 * This is probably still not working right, so don't count on it yet.
	 *
	 * @since 3.0
	 *
	 * @param string $key
	 * @return mixed
	 */
	public function __get( $key = '' ) {
		switch ( $key ) {
			case 'customers' :
			case 'customermeta' :
			case 'customer_meta' :
				return new EDD\Compat\Customer();

			default :
				return isset( $this->{$key} )
					? $this->{$key}
					: null;
		}
	}

	/**
	 * Setup plugin requirements
	 *
	 * @access private
	 * @since 3.0
	 */
	private function setup_requirements() {

		// Loop through requirements
		foreach ( $this->requirements as $dependency => $properties ) {

			// Which dependency are we checking?
			switch ( $dependency ) {

				// PHP
				case 'php' :
					$version = phpversion();
					break;

				// WP
				case 'wp' :
					$version = get_bloginfo( 'version' );
					break;

				// Unknown
				default :
					$version = false;
					break;
			}

			// Merge to original array
			if ( ! empty( $version ) ) {
				$this->requirements[ $dependency ] = array_merge( $this->requirements[ $dependency ], array(
					'current' => $version,
					'checked' => true,
					'ok'      => version_compare( $version, $properties['minimum'], '>=' )
				) );
			}
		}
	}

	/**
	 * Setup plugin constants.
	 *
	 * @access private
	 * @since 1.4
	 * @return void
	 */
	private function setup_constants() {

		// Plugin version.
		if ( ! defined( 'EDD_VERSION' ) ) {
			define( 'EDD_VERSION', '2.9.1' );
		}

		// Plugin Folder Path.
		if ( ! defined( 'EDD_PLUGIN_DIR' ) ) {
			define( 'EDD_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
		}

		// Plugin Folder URL.
		if ( ! defined( 'EDD_PLUGIN_URL' ) ) {
			define( 'EDD_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
		}

		// Plugin Root File.
		if ( ! defined( 'EDD_PLUGIN_FILE' ) ) {
			define( 'EDD_PLUGIN_FILE', __FILE__ );
		}

		// Make sure CAL_GREGORIAN is defined.
		if ( ! defined( 'CAL_GREGORIAN' ) ) {
			define( 'CAL_GREGORIAN', 1 );
		}
	}

	/**
	 * Include required files.
	 *
	 * @access private
	 * @since 1.4
	 * @return void
	 */
	private function setup_files() {
		$this->setup_options();
		$this->setup_utilities();
		$this->setup_reports();
		$this->setup_components();
		$this->setup_backcompat();
		$this->setup_objects();
		$this->setup_functions();

		// Admin
		if ( is_admin() || ( defined( 'WP_CLI' ) && WP_CLI ) ) {
			$this->setup_admin();
		} else {
			$this->setup_frontend();
		}
	}

	/**
	 * Setup all of the custom database tables
	 *
	 * This method invokes all of the classes for each custom database table,
	 * and returns them in an array for easier testing.
	 *
	 * In a normal request, this method is called extremely early in EDD's load
	 * order, to ensure these tables have been created & upgraded before any
	 * other utility occurs on them (query, migration, etc...)
	 *
	 * @access public
	 * @since 3.0
	 * @return array
	 */
	private function setup_components() {

		// Component helpers are loaded before everything
		require_once EDD_PLUGIN_DIR . 'includes/interface-edd-exception.php';
		require_once EDD_PLUGIN_DIR . 'includes/component-functions.php';
		require_once EDD_PLUGIN_DIR . 'includes/class-component.php';
		require_once EDD_PLUGIN_DIR . 'includes/database/class-base.php';

		// Database Schemas
		require_once EDD_PLUGIN_DIR . 'includes/database/schemas/class-base.php';
		require_once EDD_PLUGIN_DIR . 'includes/database/schemas/class-column.php';
		require_once EDD_PLUGIN_DIR . 'includes/database/schemas/class-customers.php';
		require_once EDD_PLUGIN_DIR . 'includes/database/schemas/class-discounts.php';
		require_once EDD_PLUGIN_DIR . 'includes/database/schemas/class-logs.php';
		require_once EDD_PLUGIN_DIR . 'includes/database/schemas/class-logs-api-requests.php';
		require_once EDD_PLUGIN_DIR . 'includes/database/schemas/class-logs-file-downloads.php';
		require_once EDD_PLUGIN_DIR . 'includes/database/schemas/class-notes.php';
		require_once EDD_PLUGIN_DIR . 'includes/database/schemas/class-orders.php';
		require_once EDD_PLUGIN_DIR . 'includes/database/schemas/class-order-items.php';
		require_once EDD_PLUGIN_DIR . 'includes/database/schemas/class-order-adjustments.php';

		// Database Objects
		require_once EDD_PLUGIN_DIR . 'includes/database/objects/class-base.php';
		require_once EDD_PLUGIN_DIR . 'includes/database/objects/class-customer.php';
		require_once EDD_PLUGIN_DIR . 'includes/database/objects/class-discount.php';
		require_once EDD_PLUGIN_DIR . 'includes/database/objects/class-log.php';
		require_once EDD_PLUGIN_DIR . 'includes/database/objects/class-log-api-request.php';
		require_once EDD_PLUGIN_DIR . 'includes/database/objects/class-log-file-download.php';
		require_once EDD_PLUGIN_DIR . 'includes/database/objects/class-note.php';
		require_once EDD_PLUGIN_DIR . 'includes/database/objects/class-order.php';
		require_once EDD_PLUGIN_DIR . 'includes/database/objects/class-order-item.php';
		require_once EDD_PLUGIN_DIR . 'includes/database/objects/class-order-adjustment.php';

		// Database Tables
		require_once EDD_PLUGIN_DIR . 'includes/database/tables/class-base.php';
		require_once EDD_PLUGIN_DIR . 'includes/database/tables/class-customers.php';
		require_once EDD_PLUGIN_DIR . 'includes/database/tables/class-discounts.php';
		require_once EDD_PLUGIN_DIR . 'includes/database/tables/class-logs.php';
		require_once EDD_PLUGIN_DIR . 'includes/database/tables/class-logs-api-requests.php';
		require_once EDD_PLUGIN_DIR . 'includes/database/tables/class-logs-file-downloads.php';
		require_once EDD_PLUGIN_DIR . 'includes/database/tables/class-notes.php';
		require_once EDD_PLUGIN_DIR . 'includes/database/tables/class-orders.php';
		require_once EDD_PLUGIN_DIR . 'includes/database/tables/class-order-items.php';
		require_once EDD_PLUGIN_DIR . 'includes/database/tables/class-order-adjustments.php';
		require_once EDD_PLUGIN_DIR . 'includes/database/tables/class-order-adjustment-meta.php';
		require_once EDD_PLUGIN_DIR . 'includes/database/tables/class-customer-meta.php';
		require_once EDD_PLUGIN_DIR . 'includes/database/tables/class-discount-meta.php';
		require_once EDD_PLUGIN_DIR . 'includes/database/tables/class-log-meta.php';
		require_once EDD_PLUGIN_DIR . 'includes/database/tables/class-note-meta.php';
		require_once EDD_PLUGIN_DIR . 'includes/database/tables/class-order-meta.php';
		require_once EDD_PLUGIN_DIR . 'includes/database/tables/class-order-item-meta.php';

		// Database Table Query Interfaces
		require_once EDD_PLUGIN_DIR . 'includes/database/queries/class-base.php';
		require_once EDD_PLUGIN_DIR . 'includes/database/queries/class-customer.php';
		require_once EDD_PLUGIN_DIR . 'includes/database/queries/class-discount.php';
		require_once EDD_PLUGIN_DIR . 'includes/database/queries/class-log.php';
		require_once EDD_PLUGIN_DIR . 'includes/database/queries/class-log-api-request.php';
		require_once EDD_PLUGIN_DIR . 'includes/database/queries/class-log-file-download.php';
		require_once EDD_PLUGIN_DIR . 'includes/database/queries/class-note.php';
		require_once EDD_PLUGIN_DIR . 'includes/database/queries/class-order.php';
		require_once EDD_PLUGIN_DIR . 'includes/database/queries/class-order-item.php';
		require_once EDD_PLUGIN_DIR . 'includes/database/queries/class-order-adjustment.php';

		// Call the component setup function
		edd_setup_components();
	}

	/**
	 * Setup all EDD settings & options
	 *
	 * @since 3.0
	 */
	private function setup_options() {
		require_once EDD_PLUGIN_DIR . 'includes/admin/settings/register-settings.php';
		$GLOBALS['edd_options'] = edd_get_settings();
	}

	/**
	 * Setup utilities
	 *
	 * @since 3.0
	 */
	private function setup_utilities() {
		require_once EDD_PLUGIN_DIR . 'includes/class-edd-utilities.php';
		require_once EDD_PLUGIN_DIR . 'includes/class-base-object.php';
	}

	/**
	 * Setup all EDD settings & options
	 *
	 * @since 3.0
	 */
	private function setup_reports() {
		require_once EDD_PLUGIN_DIR . 'includes/reports/class-init.php';
	}

	/**
	 * Setup backwards compatibility
	 *
	 * @since 3.0
	 */
	private function setup_backcompat() {

		// Backwards Compatibility
		require_once EDD_PLUGIN_DIR . 'includes/compat/class-base.php';
		require_once EDD_PLUGIN_DIR . 'includes/compat/class-customer.php';

		// Original Classes
		require_once EDD_PLUGIN_DIR . 'includes/class-edd-customer.php';
		require_once EDD_PLUGIN_DIR . 'includes/class-edd-customer-query.php';
		require_once EDD_PLUGIN_DIR . 'includes/class-edd-discount.php';
		require_once EDD_PLUGIN_DIR . 'includes/class-edd-download.php';
		require_once EDD_PLUGIN_DIR . 'includes/class-edd-cache-helper.php';
		require_once EDD_PLUGIN_DIR . 'includes/class-edd-register-meta.php';

		// Classes
		require_once EDD_PLUGIN_DIR . 'includes/class-edd-cron.php';
		require_once EDD_PLUGIN_DIR . 'includes/class-edd-db.php';
		require_once EDD_PLUGIN_DIR . 'includes/class-edd-fees.php';
		require_once EDD_PLUGIN_DIR . 'includes/class-edd-html-elements.php';
		require_once EDD_PLUGIN_DIR . 'includes/class-edd-license-handler.php';
		require_once EDD_PLUGIN_DIR . 'includes/class-edd-logging.php';
		require_once EDD_PLUGIN_DIR . 'includes/class-edd-session.php';
		require_once EDD_PLUGIN_DIR . 'includes/class-edd-stats.php';
		require_once EDD_PLUGIN_DIR . 'includes/class-edd-roles.php';

		// Deprecated Functions
		if ( file_exists( EDD_PLUGIN_DIR . 'includes/deprecated-functions.php' ) ) {
			require_once EDD_PLUGIN_DIR . 'includes/deprecated-functions.php';
		}
	}

	/**
	 * Setup objects
	 *
	 * @since 3.0
	 */
	private function setup_objects() {

		// CLI
		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			require_once EDD_PLUGIN_DIR . 'includes/class-edd-cli.php';
		}

		// API
		require_once EDD_PLUGIN_DIR . 'includes/api/class-edd-api.php';

		// Checkout
		require_once EDD_PLUGIN_DIR . 'includes/checkout/template.php';
		require_once EDD_PLUGIN_DIR . 'includes/checkout/functions.php';

		// Cart
		require_once EDD_PLUGIN_DIR . 'includes/cart/class-edd-cart.php';
		require_once EDD_PLUGIN_DIR . 'includes/cart/functions.php';
		require_once EDD_PLUGIN_DIR . 'includes/cart/template.php';
		require_once EDD_PLUGIN_DIR . 'includes/cart/actions.php';

		// Gateways
		require_once EDD_PLUGIN_DIR . 'includes/gateways/actions.php';
		require_once EDD_PLUGIN_DIR . 'includes/gateways/functions.php';
		require_once EDD_PLUGIN_DIR . 'includes/gateways/amazon-payments.php';
		require_once EDD_PLUGIN_DIR . 'includes/gateways/paypal-standard.php';
		require_once EDD_PLUGIN_DIR . 'includes/gateways/manual.php';

		// Logs
		require_once EDD_PLUGIN_DIR . 'includes/logs/class-api-request-log.php';
		require_once EDD_PLUGIN_DIR . 'includes/logs/class-file-download-log.php';
		require_once EDD_PLUGIN_DIR . 'includes/logs/class-log.php';
		require_once EDD_PLUGIN_DIR . 'includes/logs/functions.php';
		require_once EDD_PLUGIN_DIR . 'includes/logs/meta.php';

		// Notes
		require_once EDD_PLUGIN_DIR . 'includes/notes/class-note.php';
		require_once EDD_PLUGIN_DIR . 'includes/notes/functions.php';
		require_once EDD_PLUGIN_DIR . 'includes/notes/meta.php';

		// Payments
		require_once EDD_PLUGIN_DIR . 'includes/payments/functions.php';
		require_once EDD_PLUGIN_DIR . 'includes/payments/actions.php';
		require_once EDD_PLUGIN_DIR . 'includes/payments/class-payment-stats.php';
		require_once EDD_PLUGIN_DIR . 'includes/payments/class-payments-query.php';
		require_once EDD_PLUGIN_DIR . 'includes/payments/class-edd-payment.php';

		// Emails
		require_once EDD_PLUGIN_DIR . 'includes/emails/class-edd-emails.php';
		require_once EDD_PLUGIN_DIR . 'includes/emails/class-edd-email-tags.php';
		require_once EDD_PLUGIN_DIR . 'includes/emails/functions.php';
		require_once EDD_PLUGIN_DIR . 'includes/emails/template.php';
		require_once EDD_PLUGIN_DIR . 'includes/emails/actions.php';
	}

	/**
	 * Setup functions
	 *
	 * @since 3.0
	 */
	private function setup_functions() {
		require_once EDD_PLUGIN_DIR . 'includes/actions.php';
		require_once EDD_PLUGIN_DIR . 'includes/mime-types.php';
		require_once EDD_PLUGIN_DIR . 'includes/formatting.php';
		require_once EDD_PLUGIN_DIR . 'includes/widgets.php';
		require_once EDD_PLUGIN_DIR . 'includes/scripts.php';
		require_once EDD_PLUGIN_DIR . 'includes/post-types.php';
		require_once EDD_PLUGIN_DIR . 'includes/plugin-compatibility.php';
		require_once EDD_PLUGIN_DIR . 'includes/error-tracking.php';
		require_once EDD_PLUGIN_DIR . 'includes/ajax-functions.php';
		require_once EDD_PLUGIN_DIR . 'includes/template-functions.php';
		require_once EDD_PLUGIN_DIR . 'includes/template-actions.php';
		require_once EDD_PLUGIN_DIR . 'includes/country-functions.php';
		require_once EDD_PLUGIN_DIR . 'includes/date-functions.php';
		require_once EDD_PLUGIN_DIR . 'includes/misc-functions.php';
		require_once EDD_PLUGIN_DIR . 'includes/discount-functions.php';
		require_once EDD_PLUGIN_DIR . 'includes/download-functions.php';
		require_once EDD_PLUGIN_DIR . 'includes/customer-functions.php';
		require_once EDD_PLUGIN_DIR . 'includes/user-functions.php';
		require_once EDD_PLUGIN_DIR . 'includes/query-filters.php';
		require_once EDD_PLUGIN_DIR . 'includes/tax-functions.php';
		require_once EDD_PLUGIN_DIR . 'includes/process-purchase.php';
		require_once EDD_PLUGIN_DIR . 'includes/login-register.php';
		require_once EDD_PLUGIN_DIR . 'includes/shortcodes.php';
		require_once EDD_PLUGIN_DIR . 'includes/install.php';

		// Must always be loaded to ensure cron runs
		require_once EDD_PLUGIN_DIR . 'includes/admin/tracking.php';
	}

	/**
	 * Setup administration
	 *
	 * @since 3.0
	 */
	private function setup_admin() {
		require_once EDD_PLUGIN_DIR . 'includes/admin/add-ons.php';
		require_once EDD_PLUGIN_DIR . 'includes/admin/admin-footer.php';
		require_once EDD_PLUGIN_DIR . 'includes/admin/admin-actions.php';
		require_once EDD_PLUGIN_DIR . 'includes/admin/class-edd-notices.php';
		require_once EDD_PLUGIN_DIR . 'includes/admin/admin-pages.php';
		require_once EDD_PLUGIN_DIR . 'includes/admin/dashboard-widgets.php';
		require_once EDD_PLUGIN_DIR . 'includes/admin/thickbox.php';
		require_once EDD_PLUGIN_DIR . 'includes/admin/upload-functions.php';
		require_once EDD_PLUGIN_DIR . 'includes/admin/downloads/dashboard-columns.php';
		require_once EDD_PLUGIN_DIR . 'includes/admin/customers/customers.php';
		require_once EDD_PLUGIN_DIR . 'includes/admin/customers/customer-functions.php';
		require_once EDD_PLUGIN_DIR . 'includes/admin/customers/customer-actions.php';
		require_once EDD_PLUGIN_DIR . 'includes/admin/downloads/metabox.php';
		require_once EDD_PLUGIN_DIR . 'includes/admin/downloads/contextual-help.php';
		require_once EDD_PLUGIN_DIR . 'includes/admin/discounts/contextual-help.php';
		require_once EDD_PLUGIN_DIR . 'includes/admin/discounts/discount-actions.php';
		require_once EDD_PLUGIN_DIR . 'includes/admin/discounts/discount-codes.php';
		require_once EDD_PLUGIN_DIR . 'includes/admin/discounts/discount-functions.php';
		require_once EDD_PLUGIN_DIR . 'includes/admin/import/import-actions.php';
		require_once EDD_PLUGIN_DIR . 'includes/admin/import/import-functions.php';
		require_once EDD_PLUGIN_DIR . 'includes/admin/payments/actions.php';
		require_once EDD_PLUGIN_DIR . 'includes/admin/payments/payments-history.php';
		require_once EDD_PLUGIN_DIR . 'includes/admin/payments/contextual-help.php';
		require_once EDD_PLUGIN_DIR . 'includes/admin/reporting/contextual-help.php';
		require_once EDD_PLUGIN_DIR . 'includes/admin/reporting/export/export-functions.php';
		require_once EDD_PLUGIN_DIR . 'includes/admin/reporting/reports.php';
		require_once EDD_PLUGIN_DIR . 'includes/admin/reporting/class-edd-graph.php';
		require_once EDD_PLUGIN_DIR . 'includes/admin/reporting/class-edd-pie-graph.php';
		require_once EDD_PLUGIN_DIR . 'includes/admin/reporting/graphing.php';
		require_once EDD_PLUGIN_DIR . 'includes/admin/settings/display-settings.php';
		require_once EDD_PLUGIN_DIR . 'includes/admin/settings/contextual-help.php';
		require_once EDD_PLUGIN_DIR . 'includes/admin/tools.php';
		require_once EDD_PLUGIN_DIR . 'includes/admin/plugins.php';
		require_once EDD_PLUGIN_DIR . 'includes/admin/upgrades/upgrade-functions.php';
		require_once EDD_PLUGIN_DIR . 'includes/admin/upgrades/upgrades.php';
		require_once EDD_PLUGIN_DIR . 'includes/admin/class-edd-heartbeat.php';
		require_once EDD_PLUGIN_DIR . 'includes/admin/tools/tools-actions.php';
	}

	/**
	 * Setup front-end specific code
	 *
	 * @since 3.0
	 */
	private function setup_frontend() {
		require_once EDD_PLUGIN_DIR . 'includes/process-download.php';
		require_once EDD_PLUGIN_DIR . 'includes/theme-compatibility.php';
	}

	/**
	 * Loads the plugin language files.
	 *
	 * @since 1.4
	 * @return void
	 */
	public function load_textdomain() {

		/*
		 * Due to the introduction of language packs through translate.wordpress.org,
		 * loading our textdomain is complex.
		 *
		 * In v2.4.6, our textdomain changed from "edd" to "easy-digital-downloads".
		 *
		 * To support existing translation files from before the change, we must
		 * look for translation files in several places and under several names.
		 *
		 * - wp-content/languages/plugins/easy-digital-downloads (introduced with language packs)
		 * - wp-content/languages/edd/ (custom folder we have supported since 1.4)
		 * - wp-content/plugins/easy-digital-downloads/languages/
		 *
		 * In wp-content/languages/edd/ we must look for:
		 * - "easy-digital-downloads-{lang}_{country}.mo"
		 *
		 * In wp-content/languages/edd/ we must look for:
		 * - "edd-{lang}_{country}.mo" as that was the old file naming convention
		 *
		 * In wp-content/languages/plugins/easy-digital-downloads/ we only need to look for:
		 * - "easy-digital-downloads-{lang}_{country}.mo" as that is the new structure
		 *
		 * In wp-content/plugins/easy-digital-downloads/languages/, we must look for:
		 * - both naming conventions. This is done by filtering "load_textdomain_mofile"
		 */
		add_filter( 'load_textdomain_mofile', array( $this, 'load_old_textdomain' ), 10, 2 );

		// Set filter for plugin's languages directory.
		$edd_lang_dir = dirname( plugin_basename( EDD_PLUGIN_FILE ) ) . '/languages/';
		$edd_lang_dir = apply_filters( 'edd_languages_directory', $edd_lang_dir );
		$get_locale   = function_exists( 'get_user_locale' )
			? get_user_locale()
			: get_locale();

		/**
		 * Defines the plugin language locale used in AffiliateWP.
		 *
		 * @var $get_locale The locale to use. Uses get_user_locale()` in WordPress 4.7 or greater,
		 *                  otherwise uses `get_locale()`.
		 */
		$locale = apply_filters( 'plugin_locale',  $get_locale, 'easy-digital-downloads' );
		$mofile = sprintf( '%1$s-%2$s.mo', 'easy-digital-downloads', $locale );

		// Look for wp-content/languages/edd/easy-digital-downloads-{lang}_{country}.mo
		$mofile_global1 = WP_LANG_DIR . "/edd/easy-digital-downloads-{$locale}.mo";

		// Look for wp-content/languages/edd/edd-{lang}_{country}.mo
		$mofile_global2 = WP_LANG_DIR . "/edd/edd-{$locale}.mo";

		// Look in wp-content/languages/plugins/easy-digital-downloads
		$mofile_global3 = WP_LANG_DIR . "/plugins/easy-digital-downloads/{$mofile}";

		// Try to load from first global location
		if ( file_exists( $mofile_global1 ) ) {
			load_textdomain( 'easy-digital-downloads', $mofile_global1 );

		// Try to load from next global location
		} elseif ( file_exists( $mofile_global2 ) ) {
			load_textdomain( 'easy-digital-downloads', $mofile_global2 );

		// Try to load from next global location
		} elseif ( file_exists( $mofile_global3 ) ) {
			load_textdomain( 'easy-digital-downloads', $mofile_global3 );

		// Load the default language files
		} else {
			load_plugin_textdomain( 'easy-digital-downloads', false, $edd_lang_dir );
		}
	}

	/**
	 * Load a .mo file for the old textdomain if one exists.
	 *
	 * @see https://github.com/10up/grunt-wp-plugin/issues/21#issuecomment-62003284
	 */
	function load_old_textdomain( $mofile, $textdomain ) {

		if ( $textdomain === 'easy-digital-downloads' && ! file_exists( $mofile ) ) {
			$mofile = dirname( $mofile ) . DIRECTORY_SEPARATOR . str_replace( $textdomain, 'edd', basename( $mofile ) );
		}

		return $mofile;
	}
}
endif; // End if class_exists check.

/**
 * The main function for that returns Easy_Digital_Downloads
 *
 * The main function responsible for returning the one true Easy_Digital_Downloads
 * Instance to functions everywhere.
 *
 * Use this function like you would a global variable, except without needing
 * to declare the global.
 *
 * Example: <?php $edd = EDD(); ?>
 *
 * @since 1.4
 * @return object|Easy_Digital_Downloads The one true Easy_Digital_Downloads Instance.
 */
function EDD() {
	return Easy_Digital_Downloads::instance();
}

// Get EDD Running.
EDD();
