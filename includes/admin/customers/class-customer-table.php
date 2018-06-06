<?php
/**
 * Customer Reports Table Class
 *
 * @package     EDD
 * @subpackage  Reports
 * @copyright   Copyright (c) 2015, Pippin Williamson
 * @license     http://opensource.org/licenses/gpl-2.0.php GNU Public License
 * @since       1.5
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) exit;

// Load WP_List_Table if not loaded
if ( ! class_exists( 'WP_List_Table' ) ) {
	require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

/**
 * EDD_Customer_Reports_Table Class
 *
 * Renders the Customer Reports table
 *
 * @since 1.5
 */
class EDD_Customer_Reports_Table extends WP_List_Table {

	/**
	 * Number of items per page
	 *
	 * @var int
	 * @since 1.5
	 */
	public $per_page = 30;

	/**
	 * Discount counts, keyed by status
	 *
	 * @var array
	 * @since 3.0
	 */
	public $counts = array(
		'active'   => 0,
		'inactive' => 0,
		'expired'  => 0,
		'total'    => 0
	);

	/**
	 * The arguments for the data set
	 *
	 * @var array
	 * @since  2.6
	 */
	public $args = array();

	/**
	 * Get things started
	 *
	 * @since 1.5
	 * @see WP_List_Table::__construct()
	 */
	public function __construct() {
		parent::__construct( array(
			'singular' => __( 'Customer',  'easy-digital-downloads' ),
			'plural'   => __( 'Customers', 'easy-digital-downloads' ),
			'ajax'     => false
		) );


		$this->get_counts();
	}

	/**
	 * Show the search field
	 *
	 * @since 1.7
	 *
	 * @param string $text Label for the search box
	 * @param string $input_id ID of the search box
	 *
	 * @return void
	 */
	public function search_box( $text, $input_id ) {

		// Bail if no customers and no search
		if ( empty( $_REQUEST['s'] ) && ! $this->has_items() ) {
			return;
		}

		$input_id = $input_id . '-search-input';

		if ( ! empty( $_REQUEST['orderby'] ) ) {
			echo '<input type="hidden" name="orderby" value="' . esc_attr( $_REQUEST['orderby'] ) . '" />';
		}

		if ( ! empty( $_REQUEST['order'] ) ) {
			echo '<input type="hidden" name="order" value="' . esc_attr( $_REQUEST['order'] ) . '" />';
		}

		?>

		<p class="search-box">
			<label class="screen-reader-text" for="<?php echo esc_attr( $input_id ); ?>"><?php echo esc_html( $text ); ?>:</label>
			<input type="search" id="<?php echo esc_attr( $input_id ); ?>" name="s" value="<?php _admin_search_query(); ?>" />
			<?php submit_button( $text, 'button', false, false, array('ID' => 'search-submit') ); ?>
		</p>

		<?php
	}

	/**
	 * Gets the name of the primary column.
	 *
	 * @since 2.5
	 * @access protected
	 *
	 * @return string Name of the primary column.
	 */
	protected function get_primary_column_name() {
		return 'name';
	}

	/**
	 * This function renders most of the columns in the list table.
	 *
	 * @since 1.5
	 *
	 * @param array $item Contains all the data of the customers
	 * @param string $column_name The name of the column
	 *
	 * @return string Column Name
	 */
	public function column_default( $item, $column_name ) {
		switch ( $column_name ) {

			case 'id' :
				$value = $item['id'];
				break;

			case 'order_count' :
				$value = '<a href="' .
					admin_url( '/edit.php?post_type=download&page=edd-payment-history&user=' . urlencode( $item['email'] )
				) . '">' . esc_html( $item['order_count'] ) . '</a>';
				break;

			case 'spent' :
				$value = edd_currency_filter( edd_format_amount( $item[ $column_name ] ) );
				break;

			case 'date_created' :
				$value = edd_date_i18n( $item['date_created'] );
				break;

			case 'status' :
				$customer = edd_get_customer( $item['id'] );
				$value  = edd_user_pending_verification( $customer->user_id )
						? '<em>' . __( 'Pending', 'easy-digital-downloads' ) . '</em>'
						: __( 'Active', 'easy-digital-downloads' );
				break;

			default:
				$value = isset( $item[ $column_name ] )
					? $item[ $column_name ]
					: null;
				break;
		}
		return apply_filters( 'edd_customers_column_' . $column_name, $value, $item['id'] );
	}

	public function column_name( $item ) {
		$name        = ! empty( $item['name']    ) ? $item['name']    : '&mdash;';
		$user        = ! empty( $item['user_id'] ) ? $item['user_id'] : $item['email'];
		$view_url    = admin_url( 'edit.php?post_type=download&page=edd-customers&view=overview&id=' . $item['id'] );
		$actions     = array(
			'view'   => '<a href="' . $view_url . '">' . __( 'View', 'easy-digital-downloads' ) . '</a>',
			'logs'   => '<a href="' . admin_url( 'edit.php?post_type=download&page=edd-tools&tab=logs&user=' . urlencode( $user ) ) . '">' . __( 'Logs', 'easy-digital-downloads' ) . '</a>',
			'delete' => '<a href="' . admin_url( 'edit.php?post_type=download&page=edd-customers&view=delete&id=' . $item['id'] ) . '">' . __( 'Delete', 'easy-digital-downloads' ) . '</a>'
		);

		return '<a href="' . esc_url( $view_url ) . '">' . $name . '</a>' . $this->row_actions( $actions );
	}

	/**
	 * Retrieve the customer counts
	 *
	 * @access public
	 * @since 3.0
	 * @return void
	 */
	public function get_counts() {
		$this->counts = edd_get_customer_counts();
	}

	/**
	 * Retrieve the view types
	 *
	 * @access public
	 * @since 1.4
	 *
	 * @return array $views All the views available
	 */
	public function get_views() {
		$base          = $this->get_base_url();
		$current       = isset( $_GET['status'] ) ? sanitize_key( $_GET['status'] ) : '';
		$is_all        = empty( $current ) || ( 'all' === $current );
		$total_count   = '&nbsp;<span class="count">(' . esc_html( $this->counts['total']   ) . ')</span>';
		$active_count  = '&nbsp;<span class="count">(' . esc_html( $this->counts['active']  ) . ')</span>';
		$pending_count = '&nbsp;<span class="count">(' . esc_html( $this->counts['pending'] ) . ')</span>';

		return array(
			'all'     => sprintf( '<a href="%s"%s>%s</a>', esc_url( remove_query_arg( 'status', $base         ) ), $is_all                ? ' class="current"' : '', __( 'All',     'easy-digital-downloads' ) . $total_count   ),
			'active'  => sprintf( '<a href="%s"%s>%s</a>', esc_url( add_query_arg( 'status', 'active',  $base ) ), 'active'  === $current ? ' class="current"' : '', __( 'Active',  'easy-digital-downloads' ) . $active_count  ),
			'pending' => sprintf( '<a href="%s"%s>%s</a>', esc_url( add_query_arg( 'status', 'pending', $base ) ), 'pending' === $current ? ' class="current"' : '', __( 'Pending', 'easy-digital-downloads' ) . $pending_count )
		);
	}

	/**
	 * Retrieve the table columns
	 *
	 * @since 1.5
	 * @return array $columns Array of all the list table columns
	 */
	public function get_columns() {
		return apply_filters( 'edd_report_customer_columns', array(
			'id'            => __( 'ID',            'easy-digital-downloads' ),
			'name'          => __( 'Name',          'easy-digital-downloads' ),
			'email'         => __( 'Email',         'easy-digital-downloads' ),
			'order_count'   => __( 'Orders',        'easy-digital-downloads' ),
			'spent'         => __( 'Spent',         'easy-digital-downloads' ),
			'date_created'  => __( 'Date Created',  'easy-digital-downloads' ),
			'status'        => __( 'Status',        'easy-digital-downloads' )
		) );
	}

	/**
	 * Get the sortable columns
	 *
	 * @since 2.1
	 * @return array Array of all the sortable columns
	 */
	public function get_sortable_columns() {
		return array(
			'date_created'  => array( 'date_created',   true  ),
			'name'          => array( 'name',           true  ),
			'order_count'   => array( 'purchase_count', false ),
			'spent'         => array( 'purchase_value', false )
		);
	}

	/**
	 * Outputs the reporting views
	 *
	 * @since 1.5
	 * @return string Empty. No bulk actions for Customers (yet!)
	 */
	public function bulk_actions( $which = '' ) {
		$which = '';
		return $which;
	}

	/**
	 * Retrieve the current page number
	 *
	 * @since 1.5
	 * @return int Current page number
	 */
	public function get_paged() {
		return isset( $_GET['paged'] )
			? absint( $_GET['paged'] )
			: 1;
	}

	/**
	 * Retrieves the search query string
	 *
	 * @since 1.7
	 * @return mixed string If search is present, false otherwise
	 */
	public function get_search() {
		return ! empty( $_GET['s'] )
			? urldecode( trim( $_GET['s'] ) )
			: false;
	}

	/**
	 * Build all the reports data
	 *
	 * @since 1.5
	  * @global object $wpdb Used to query the database using the WordPress
	 *   Database API
	 * @return array $reports_data All the data for customer reports
	 */
	public function reports_data() {
		$data    = array();
		$paged   = $this->get_paged();
		$offset  = $this->per_page * ( $paged - 1 );
		$search  = $this->get_search();
		$order   = isset( $_GET['order'] )   ? sanitize_text_field( $_GET['order'] )   : 'DESC';
		$orderby = isset( $_GET['orderby'] ) ? sanitize_text_field( $_GET['orderby'] ) : 'id';

		$args    = array(
			'limit'   => $this->per_page,
			'offset'  => $offset,
			'order'   => $order,
			'orderby' => $orderby
		);

		if( is_email( $search ) ) {
			$args['email'] = $search;
		} elseif( is_numeric( $search ) ) {
			$args['id']    = $search;
		} elseif( strpos( $search, 'user:' ) !== false ) {
			$args['user_id'] = trim( str_replace( 'user:', '', $search ) );
		} else {
			$args['name']  = $search;
		}

		$this->args = $args;
		$customers  = edd_get_customers( $args );

		if ( $customers ) {

			foreach ( $customers as $customer ) {

				$user_id = ! empty( $customer->user_id ) ? intval( $customer->user_id ) : 0;

				$data[] = array(
					'id'            => $customer->id,
					'user_id'       => $user_id,
					'name'          => $customer->name,
					'email'         => $customer->email,
					'order_count'   => $customer->purchase_count,
					'spent'         => $customer->purchase_value,
					'date_created'  => $customer->date_created
				);
			}
		}

		return $data;
	}

	/**
	 * Setup the final data for the table
	 *
	 * @since 1.5
	 * @uses EDD_Customer_Reports_Table::get_columns()
	 * @uses WP_List_Table::get_sortable_columns()
	 * @uses EDD_Customer_Reports_Table::get_pagenum()
	 * @uses EDD_Customer_Reports_Table::get_total_customers()
	 * @return void
	 */
	public function prepare_items() {

		$columns  = $this->get_columns();
		$hidden   = array(); // No hidden columns
		$sortable = $this->get_sortable_columns();

		$this->_column_headers = array( $columns, $hidden, $sortable );
		$this->items            = $this->reports_data();

		$status = isset( $_GET['status'] )
			? sanitize_key( $_GET['status'] )
			: 'any';

		// Switch statuses
		switch ( $status ) {
			case 'active':
				$total_items = $this->counts['active'];
				break;

			case 'pending':
				$total_items = $this->counts['pending'];
				break;

			case 'any':
			default:
				$total_items = $this->counts['total'];
				break;
		}

		// Setup pagination
		$this->set_pagination_args( array(
			'total_items' => $total_items,
			'per_page'    => $this->per_page,
			'total_pages' => ceil( $total_items / $this->per_page )
		) );
	}
}
