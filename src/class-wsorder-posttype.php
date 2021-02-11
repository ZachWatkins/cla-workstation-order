<?php
/**
 * The file that defines the Order post type
 *
 * @link       https://github.com/zachwatkins/cla-workstation-order/blob/master/src/class-wsorder-posttype.php
 * @since      1.0.0
 * @package    cla-workstation-order
 * @subpackage cla-workstation-order/src
 */

namespace CLA_Workstation_Order;

/**
 * Add assets
 *
 * @package cla-workstation-order
 * @since 1.0.0
 */
class WSOrder_PostType {

	/**
	 * Initialize the class
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function __construct() {

		// Register_post_types.
		add_action( 'init', array( $this, 'register_post_type' ) );
		add_action( 'acf/init', array( $this, 'register_custom_fields' ) );
		add_action( 'post_submitbox_misc_actions', array( $this, 'post_status_add_to_dropdown' ) );
		add_filter( 'display_post_states', array( $this, 'display_status_state' ) );
		add_action( 'admin_print_scripts-post-new.php', array( $this, 'admin_script' ), 11 );
		add_action( 'admin_print_scripts-post.php', array( $this, 'admin_script' ), 11 );
		// Add columns to dashboard post list screen.
		add_filter( 'manage_wsorder_posts_columns', array( $this, 'add_list_view_columns' ) );
		add_action( 'manage_wsorder_posts_custom_column', array( $this, 'output_list_view_columns' ), 10, 2 );
		// Manipulate post title to force it to a certain format.
		add_filter( 'default_title', array( $this, 'default_post_title' ), 11, 2 );
		// Allow programs to link to a list of associated orders in admin.
		add_filter( 'parse_query', array( $this, 'admin_list_posts_filter' ) );
		// Notify parties of changes to order status.
		add_action( 'transition_post_status', array( $this, 'notify_users' ), 10, 3 );

	}

	/**
	 * Determine if business approval is required.
	 *
	 * @var int $post_id The wsorder post ID to check.
	 */
	private function order_requires_business_approval( $post_id ) {

  	// Get order subtotal.
  	$subtotal = (float) get_field( 'products_subtotal', $post_id );
  	// Get order program post ID.
  	$program_id = get_field( 'program', $post_id );

  	if ( ! empty( $program_id ) ) {

			// Get order's program allocation threshold.
			$program_threshold = (float) get_field( 'threshold', $program_id );

			if ( $subtotal > $program_threshold ) {

				return true;

			} else {

				return false;

			}

  	} else {

  		return true;

  	}

	}

	/**
	 * Register the post type.
	 *
	 * @return void
	 */
	public function register_post_type() {

		require_once CLA_WORKSTATION_ORDER_DIR_PATH . 'src/class-posttype.php';
		require_once CLA_WORKSTATION_ORDER_DIR_PATH . 'src/class-taxonomy.php';

		new \CLA_Workstation_Order\PostType(
			array(
				'singular' => 'Order',
				'plural'   => 'Orders',
			),
			'wsorder',
			array(),
			'dashicons-portfolio',
			array( 'title' ),
			array(
				'capabilities' => array(
	        'edit_post'                 => 'edit_wsorder',
	        'read_post'                 => 'read_wsorder',
	        'delete_post'               => 'delete_wsorder',
	        'create_posts'              => 'create_wsorders',
	        'delete_posts'              => 'delete_wsorders',
	        'delete_others_posts'       => 'delete_others_wsorders',
	        'delete_private_posts'      => 'delete_private_wsorders',
	        'delete_published_posts'    => 'delete_published_wsorders',
	        'edit_posts'                => 'edit_wsorders',
	        'edit_others_posts'         => 'edit_others_wsorders',
	        'edit_private_posts'        => 'edit_private_wsorders',
	        'edit_published_posts'      => 'edit_published_wsorders',
	        'publish_posts'             => 'publish_wsorders',
	        'read_private_posts'        => 'read_private_wsorders'
				),
				'map_meta_cap' => true,
				'publicly_queryable' => false,
			)
		);

		register_post_status( 'action_required', array(
			'label'                     => _x( 'Action Required', 'post' ),
			'public'                    => true,
      'exclude_from_search'       => false,
			'show_in_admin_all_list'    => true,
			'show_in_admin_status_list' => true,
			'label_count'               => _n_noop( 'Action Required <span class="count">(%s)</span>', 'Action Required <span class="count">(%s)</span>' )
		) );

		register_post_status( 'returned', array(
			'label'                     => _x( 'Returned', 'post' ),
			'public'                    => true,
      'exclude_from_search'       => false,
			'show_in_admin_all_list'    => true,
			'show_in_admin_status_list' => true,
			'label_count'               => _n_noop( 'Returned <span class="count">(%s)</span>', 'Returned <span class="count">(%s)</span>' )
		) );

		register_post_status( 'completed', array(
			'label'                     => _x( 'Completed', 'post' ),
			'public'                    => true,
      'exclude_from_search'       => false,
			'show_in_admin_all_list'    => true,
			'show_in_admin_status_list' => true,
			'label_count'               => _n_noop( 'Completed <span class="count">(%s)</span>', 'Completed <span class="count">(%s)</span>' )
		) );

		register_post_status( 'awaiting_another', array(
			'label'                     => _x( 'Awaiting Another', 'post' ),
			'public'                    => true,
      'exclude_from_search'       => false,
			'show_in_admin_all_list'    => true,
			'show_in_admin_status_list' => true,
			'label_count'               => _n_noop( 'Awaiting Another <span class="count">(%s)</span>', 'Awaiting Another <span class="count">(%s)</span>' )
		) );

	}

	public function admin_script() {
		global $post_type;
		if( 'wsorder' == $post_type ) {
			wp_enqueue_script( 'cla-workstation-order-admin-script' );
		}
	}

	public function post_status_add_to_dropdown() {

		global $post;
		if( $post->post_type != 'wsorder' ) {
			return false;
		}
		$status = '';
		switch ( $post->post_status ) {
			case 'action_required':
				$status = "jQuery( '#post-status-display' ).text( 'Action Required' ); jQuery(
						'select[name=\"post_status\"]' ).val('action_required')";
				break;

			case 'returned':
				$status = "jQuery( '#post-status-display' ).text( 'Returned' ); jQuery(
						'select[name=\"post_status\"]' ).val('returned')";
				break;

			case 'completed':
				$status = "jQuery( '#post-status-display' ).text( 'Completed' ); jQuery(
						'select[name=\"post_status\"]' ).val('completed')";
				break;

			case 'awaiting_another':
				$status = "jQuery( '#post-status-display' ).text( 'Awaiting Another' ); jQuery(
						'select[name=\"post_status\"]' ).val('awaiting_another')";
				break;

			default:
				break;
		}
		echo "<script>
			jQuery(document).ready( function() {
				jQuery( 'select[name=\"post_status\"]' ).append( '<option value=\"action_required\">Action Required</option><option value=\"returned\">Returned</option><option value=\"completed\">Completed</option><option value=\"awaiting_another\">Awaiting Another</option>' );
				".$status."
			});
		</script>";

	}

	public function display_status_state( $states ) {

		global $post;
		$arg = get_query_var( 'post_status' );
		switch ( $post->post_status ) {
			case 'action_required':
				if ( $arg != $post->post_status ) {
					return array( 'Action Required' );
				}
				break;

			case 'returned':
				if ( $arg != $post->post_status ) {
					return array( 'Returned' );
				}
				break;

			case 'completed':
				if ( $arg != $post->post_status ) {
					return array( 'Completed' );
				}
				break;

			case 'awaiting_another':
				if ( $arg != $post->post_status ) {
					return array( 'Awaiting Another' );
				}
				break;

			default:
				return $states;
				break;
		}

	}

	/**
	 * Register custom fields
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function register_custom_fields() {

		require_once CLA_WORKSTATION_ORDER_DIR_PATH . 'fields/wsorder-fields.php';
		// require_once CLA_WORKSTATION_ORDER_DIR_PATH . 'fields/wsorder-admin-fields.php';
		require_once CLA_WORKSTATION_ORDER_DIR_PATH . 'fields/wsorder-return-to-user-fields.php';
		require_once CLA_WORKSTATION_ORDER_DIR_PATH . 'fields/it-rep-status-order-fields.php';
		require_once CLA_WORKSTATION_ORDER_DIR_PATH . 'fields/business-staff-status-order-fields.php';
		require_once CLA_WORKSTATION_ORDER_DIR_PATH . 'fields/it-logistics-status-order-fields.php';
		require_once CLA_WORKSTATION_ORDER_DIR_PATH . 'fields/order-department-comments-fields.php';

	}

	private function get_last_order_id( $program_post_id ) {

		$args              = array(
			'post_type'      => 'wsorder',
			'post_status'    => 'any',
			'posts_per_page' => 1,
			'fields'         => 'ids',
			'meta_query'     => array(
				array(
					'key'     => 'order_id',
					'compare' => 'EXISTS'
				),
				array(
					'key'     => 'program',
					'compare' => '=',
					'value'   => $program_post_id,
				)
			),
		);
		$the_query = new \WP_Query( $args );
		$posts = $the_query->posts;
		if ( ! empty( $posts ) ) {
			$last_wsorder_id = (int) get_post_meta( $posts[0], 'order_id', true );
		} else {
			$last_wsorder_id = 0;
		}

		return $last_wsorder_id;

	}

	/**
	 * Make post title using current program ID and incremented order ID from last order.
	 */
	public function default_post_title( $post_title, $post ) {

		if ('wsorder' === $post->post_type) {

			// Get current program meta.
			$current_program_post      = get_field( 'current_program', 'option' );
			if ( ! empty( $current_program_post ) ) {
				$current_program_id        = $current_program_post->ID;
				$current_program_post_meta = get_post_meta( $current_program_id );
				$current_program_prefix    = $current_program_post_meta['prefix'][0];

				// Get last order ID.
				$last_wsorder_id = $this->get_last_order_id( $current_program_id );
				$wsorder_id = $last_wsorder_id + 1;

				// Push order ID value to post details.
				$post_title = "{$current_program_prefix}-{$wsorder_id}";
			}
		}

		return $post_title;

	}

	public function add_list_view_columns( $columns ){

	  $status = array('status' => '');
	  $columns = array_merge( $status, $columns );

	  $columns['amount']           = 'Amount';
	  $columns['it_status']        = 'IT';
	  $columns['business_status']  = 'Business';
	  $columns['logistics_status'] = 'Logistics';
    return $columns;

	}

	public function output_list_view_columns( $column_name, $post_id ) {

		if ( 'status' === $column_name ) {
			$status = get_post_status( $post_id );
			echo "<div class=\"status-color-key {$status}\"></div>";
		} else if( 'amount' === $column_name ) {
      $number = (float) get_post_meta( $post_id, 'products_subtotal', true );
      if ( class_exists('NumberFormatter') ) {
				$formatter = new \NumberFormatter('en_US', \NumberFormatter::CURRENCY);
				echo $formatter->formatCurrency($number, 'USD');
			} else {
				echo '$' . number_format($number, 2,'.', ',');
			}
    } else if ( 'it_status' === $column_name ) {
    	$status = get_field( 'it_rep_status', $post_id );
    	if ( empty( $status['confirmed'] ) ) {
    		echo '<span class="approval not-confirmed">Not yet confirmed</span>';
    	} else {
    		echo '<span class="approval confirmed">Confirmed</span><br>';
    		echo $status['it_rep']['display_name'];
    	}
    } else if ( 'business_status' === $column_name ) {
    	// Determine status message.
    	$requires_business_approval = $this->order_requires_business_approval( $post_id );
    	if ( $requires_business_approval ) {
	    	$status = get_field( 'business_staff_status', $post_id );
	    	if ( empty( $status['confirmed'] ) ) {
	    		echo '<span class="approval not-confirmed">Not yet confirmed</span>';
	    	} else {
	    		echo '<span class="approval confirmed">Confirmed</span><br>';
	    		echo $status['business_staff']['display_name'];
	    	}
    	} else {
    		echo '<span class="approval">Not required</span>';
    	}
    } else if ( 'logistics_status' === $column_name ) {
    	$status = get_field( 'it_logistics_status', $post_id );
    	if ( empty( $status['confirmed'] ) ) {
    		echo '<span class="approval not-confirmed">Not yet confirmed</span>';
    	} else {
    		echo '<span class="approval confirmed">Confirmed</span> ';
	    	if ( empty( $status['ordered'] ) ) {
	    		echo '<span class="approval not-fully-ordered">Not fully ordered</span>';
	    	} else {
	    		echo '<span class="approval ordered">Ordered</span>';
	    	}
    	}
    }
	}

	public function admin_list_posts_filter( $query ){
		global $pagenow;
		$type = 'post';
		if (isset($_GET['post_type'])) {
	    $type = $_GET['post_type'];
		}
		if ( 'wsorder' == $type && is_admin() && $pagenow=='edit.php') {
	    $meta_query = array(); // Declare meta query to fill after
	    if (isset($_GET['program']) && $_GET['program'] != '') {
        // first meta key/value
        $meta_query = array (
          'key'      => 'program',
          'value'    => $_GET['program']
        );
		    $query->query_vars['meta_query'][] = $meta_query; // add meta queries to $query
		    $query->query_vars['name'] = '';
	    }

	    // Prevent subscribers from seeing other orders.
	    $current_user = wp_get_current_user();
	    if ( user_can( $current_user, 'subscriber' ) ) {
	    	$query->query_vars['author'] = $current_user->ID;
	    }
		}
	}

	private function get_program_business_admin_user_id( $program_id, $user_department_post_id ) {

		// Get users assigned to active user's department for current program, as array.
		$program_meta_keys_departments = array(
			'assign_political_science_department_post_id',
			'assign_sociology_department_post_id',
			'assign_philosophy_humanities_department_post_id',
			'assign_performance_studies_department_post_id',
			'assign_international_studies_department_post_id',
			'assign_history_department_post_id',
			'assign_hispanic_studies_department_post_id',
			'assign_english_department_post_id',
			'assign_economics_department_post_id',
			'assign_communication_department_post_id',
			'assign_anthropology_department_post_id',
			'assign_psychology_department_post_id',
			'assign_dean_department_post_id',
		);
		$current_program_post_meta     = get_post_meta( $program_id );
		$value                         = 0;

		foreach ( $program_meta_keys_departments as $meta_key ) {
			$assigned_dept = (int) $current_program_post_meta[ $meta_key ][0];
			if ( $user_department_post_id === $assigned_dept ) {
				$base_key                     = preg_replace( '/_department_post_id$/', '', $meta_key );
				$dept_assigned_business_admin = unserialize( $current_program_post_meta[ "{$base_key}_business_admins" ][0] );
				$value                        = $dept_assigned_business_admin[0];
				break;
			}
		}

		return $value;

	}

	/**
	 * Notify users based on their association to the wsorder post and the new status of the post.
	 *
	 * @since 0.1.0
	 * @param string  $new_status The post's new status.
	 * @param string  $old_status The post's old status.
	 * @param WP_Post $post       The post object.
	 * @return void
	 */
	public function notify_users( $new_status, $old_status, $post ) {
		error_log( $old_status . ' -> ' . $new_status );
		if ( $old_status === $new_status || 'wsorder' !== $post->post_type || $new_status === 'auto-draft' || ! array_key_exists( 'acf', $_POST ) ) {
			return;
		}

	    // [acf] => Array
      //   (
      //       [field_5ffcc0a806823] =>
      //       [field_60074b5ee982b] =>
      //       [field_5ffcc10806825] =>
      //       [field_5ffcc16306826] =>
      //       [field_6009bcb19bba2] =>
      //       [field_5ffcc21406828] =>
      //       [field_5ffcc22006829] =>
      //       [field_60187cc11415d] => 0
      //       [field_5ffcc22d0682a] =>
      //       [field_5ffcc2590682b] => 240
      //       [field_5ffcc2b90682c] =>
      //       [field_600858275c27f] =>
      //       [field_5fff6b46a22af] => Array
      //           (
      //               [field_5fff703a5289f] =>
      //               [field_5fff6b71a22b0] => 0
      //           )

      //       [field_5fff6ec0e2f7e] => Array
      //           (
      //               [field_5fff70b84ffe4] =>
      //               [field_5fff6ec0e4385] => 0
      //           )

      //       [field_5fff6f3cee555] => Array
      //           (
      //               [field_5fff6f3cef757] => 0
      //               [field_60074e2222cee] => 0
      //           )

      //   )
    	// [ID] => 2828
		// echo '<pre>';
		// print_r($_POST);
		// echo '</pre>';
		// $message              = '';
		// $message .= serialize( $_POST ); //phpcs:ignore
		// $message .= serialize( $post ); //phpcs:ignore
		// if ( isset( $_POST['acf'] ) ) {
		// 	$it_rep_user_id       = $_POST['acf']['field_5fff6b46a22af']['field_5fff703a5289f']; //phpcs:ignore
		// 	$it_rep_user_id_saved = get_post_meta( $post->ID, 'it_rep_status_it_rep' );
		// 	$message              .= $it_rep_user_id . ' : ' . $it_rep_user_id_saved;
		// }
		// wp_mail( 'zwatkins2@tamu.edu', 'order published', $message );
		// if (
		// 	( 'publish' === $new_status && 'publish' !== $old_status )
		// 	&& 'wsorder' === $post->post_type
		// ) {
		// 	$message  = serialize( $_GET ); //phpcs:ignore
		// 	$message .= serialize( $_POST ); //phpcs:ignore
		// 	$message .= serialize( $post ); //phpcs:ignore
		// 	wp_mail( 'zwatkins2@tamu.edu', 'order published', $message );
		// }
		$post_id                 = $post->ID;
		$order_id                = get_the_title( $post_id );
		$end_user                = get_user_by( $post->post_author );
		$end_user_email          = $end_user->user_email;
		$user_id                 = $post->post_author;
		$user_department_post    = get_field( 'department', "user_{$user_id}" );
		$user_department_post_id = $user_department_post ? $user_department_post->ID : 0;
		$department_abbreviation = get_field( 'abbreviation', $user_department_post_id );
		$order_program = get_field( 'program', $post_id );
		if ( $order_program ) {
			$order_program_id = $order_program ? $order_program->ID : 0;

			if ( isset( $user_department_post_id ) ) {
				$business_admin_id       = $this->get_program_business_admin_user_id( $order_program_id, $user_department_post_id );
				if ( 0 === $business_admin_id ) {
					$business_admin_email = false;
				} else {
					$business_admin_obj   = get_userdata( $business_admin_id );
					$business_admin_email = $business_admin_obj->user_email;
				}
			}
		} else {
			return;
		}
		// Get email headers.
		$headers                = array('Content-Type: text/html; charset=UTF-8');
		// Get contribution amount.
		$contribution_amount    = get_field( 'contribution_amount', $post_id );
		// Get logistics email settings.
		$logistics_email        = get_field( 'logistics_email', 'option' );
		$enable_logistics_email = get_field( 'enable_emails_to_logistics', 'option' );
		// Get confirmation statuses.
		$old_post_it_confirm  = (int) get_post_meta( $post_id, 'it_rep_status_confirmed', true );
		$new_post_it_confirm    = (int) $_POST['acf']['field_5fff6b46a22af']['field_5fff6b71a22b0'];
		$old_post_log_confirm = (int) get_post_meta( $post_id, 'it_logistics_status_confirmed', true );
		$new_post_log_confirm   = (int) $_POST['acf']['field_5fff6f3cee555']['field_5fff6f3cef757'];
		$old_post_bus_confirm = (int) get_post_meta( $post_id, 'business_staff_status_confirmed', true );
		if ( array_key_exists( 'field_5fff6ec0e4385', $_POST['acf']['field_5fff6ec0e2f7e'] ) ) {
			$new_post_bus_confirm = (int) $_POST['acf']['field_5fff6ec0e2f7e']['field_5fff6ec0e4385'];
		} else {
			$new_post_bus_confirm = 0;
		}
		// Get current user information.
		$current_user      = wp_get_current_user();
		$current_user_id   = $current_user->ID;
		$current_user_name = $current_user->display_name;
		// Get IT Rep user information.
		$it_rep_user_id = (int) get_post_meta( $post_id, 'it_rep_status_it_rep', true );

		/**
		 * Once IT Rep has confirmed, if business approval needed ->
		 * subject: [{$order_id}] Workstation Order Approval - {$department_abbreviation} - {$end_user_name}
		 * to: department's business admin for the order's program
		 * body: email_body_it_rep_to_business( $post->ID, $_POST['acf'] )
		 */
		if (
			$old_post_it_confirm === 0
			&& $new_post_it_confirm === 1
			&& $current_user_id === $it_rep_user_id
			&& ! empty( $contribution_amount )
			&& isset( $business_admin_email )
			&& false !== $business_admin_email
		) {

			$to      = $business_admin_email;
			$title   = "[{$order_id}] Workstation Order Approval - {$department_abbreviation} - {$end_user_name}";
			$message = $this->email_body_it_rep_to_business( $post_id, $_POST['acf'] );
			wp_mail( $to, $title, $message, $headers );

		}

		/**
		 * Once IT Rep has confirmed, if business approval NOT needed ->
		 * subject: [{$order_id}] Workstation Order Approval - {$department_abbreviation} - {$end_user_name}
		 * to: logistics user
		 * body: email_body_to_logistics( $post->ID, $_POST['acf'] )
		 */
		if (
			$old_post_it_confirm === 0
			&& $new_post_it_confirm === 1
			&& empty( $contribution_amount )
			&& $enable_logistics_email === 1
		) {

			$to      = $logistics_email;
			$title   = "[{$order_id}] Workstation Order Approval - {$department_abbreviation} - {$end_user_name}";
			$message = $this->email_body_it_rep_to_business( $post_id, $_POST['acf'] );
			wp_mail( $to, $title, $message, $headers );

		}

		/**
		 * If business approval needed, once Business Staff has confirmed ->
		 * subject: [{$order_id}] Workstation Order Approval - {$department_abbreviation} - {$end_user_name}
		 * to: logistics user
		 * body: email_body_to_logistics( $post->ID, $_POST['acf'] )
		 */

		if (
			! empty( $contribution_amount )
			&& $old_post_bus_confirm === 0
			&& $new_post_bus_confirm === 1
		) {

			$to      = $logistics_email;
			$title   = "[{$order_id}] Workstation Order Approval - {$department_abbreviation} - {$end_user_name}";
			$message = $this->email_body_to_logistics( $post_id, $_POST['acf'] );
			wp_mail( $to, $title, $message, $headers );

		}

		/** ><
		 * IT Logistics checks their "Confirmed" checkbox and user is emailed with "order approval completed email"
		 * subject: [{$order_id}] Workstation Order Approval - {$department_abbreviation} - {$end_user_name}
		 * to: end user
		 * body: email_body_order_approved( $post->ID, $_POST['acf'] );
		 */
		if (
			$old_post_log_confirm === 0
			&& $new_post_log_confirm === 1
		) {

			$to      = $end_user_email;
			$title   = "[{$order_id}] Workstation Order Approval - {$department_abbreviation} - {$end_user_name}";
			$message = $this->email_body_order_approved( $post_id, $_POST['acf'] );
			wp_mail( $to, $title, $message, $headers );

		}

		/**
		 * If status changed to "Returned" ->
		 * subject: [{$order_id}] Returned Workstation Order - {$department_abbreviation} - {$end_user_name}
		 * to: end user
		 * cc: whoever set it to return
		 * body: email_body_return_to_user( $post->ID, $_POST['acf'] );
		 */

		/**
		 * If status changed to "Returned" ->
		 * subject: [{$order_id}] Returned Workstation Order - {$department_abbreviation} - {$order.user_name}
		 * to: if it_rep is assigned and approved, email them; if business_admin is assigned and approved, email them
  	 * body: email_body_return_to_user_forward( $post->ID, $_POST['acf'] );
  	 */

	}

	private function email_body_it_rep_to_business( $order_post_id, $acf_data ) {

		$program_name    = get_the_title( $acf_data['field_5ffcc2590682b'] );
		$addfund_amount  = $acf_data['field_5ffcc10806825'];
		$addfund_account = $acf_data['field_5ffcc16306826'];
		$admin_order_url = admin_url() . "post.php?post={$order_post_id}&action=edit";
		$message         = "<p>
  Howdy<br />
  <strong>There is a new {$program_name} order that requires your attention for financial resolution.</strong></p>
<p>
  {$user_name} elected to contribute additional funds toward their order in the amount of {$addfund_amount}. An account reference of \"{$addfund_account}\" needs to be confirmed or replaced with the correct account number that will be used on the official requisition.
</p>
<p>
  You can view the order at this link: {$admin_order_url}.
</p>
<p>
  Have a great day!<br />
  <em>- Liberal Arts IT</em>
</p>
<p><em>This email was sent from an unmonitored email address. Please do not reply to this email.</em></p>";

  	return $message;

	}

	private function email_body_to_logistics( $order_post_id, $acf_data ) {

		$program_name    = get_the_title( $acf_data['field_5ffcc2590682b'] );
		$admin_order_url = admin_url() . "post.php?post={$order_post_id}&action=edit";
		$message         = "<p><strong>There is a new {$program_name} order that requires your approval.</strong></p>
<p>
  Please review this order carefully for any errors or omissions, then approve order for purchasing.
</p>
<p>
  You can view the order at this link: {$admin_order_url}.
</p>
<p>
  Have a great day!<br />
  <em>- Liberal Arts IT</em>
</p>
<p><em>This email was sent from an unmonitored email address. Please do not reply to this email.</em></p>";

		return $message;

	}

	private function email_body_return_to_user( $order_post_id, $acf_data ) {

		$program_name     = get_the_title( $acf_data['field_5ffcc2590682b'] );
		$actor_user       = get_current_user();
		$actor_name       = $actor_user->display_name;
		$returned_comment = '';
		$admin_order_url  = admin_url() . "post.php?post={$order_post_id}&action=edit";
		$message          = "<p>
  Howdy,
</p>
<p>
  Your {$program_name} order has been returned by {$actor_name}. This could be because it was missing some required information, missing a necessary part, or could not be fulfilled as is. An explanation should appear below in the comments.
</p>
<p>
  Comments from {$actor_name}: {$returned_comment}
</p>
<p>
  Next step is to resolve your order's issue with the person who returned it (who has been copied on this email for your convenience), then correct the existing order. You may access your order online at any time using this link: {$admin_order_url}.
</p>

<p>
	Have a great day!<br />
	<em>- Liberal Arts IT</em>
</p>
<p><em>This email was sent from an unmonitored email address. Please do not reply to this email.</em></p>";

		return $message;

	}

	private function email_body_return_to_user_forward( $order_post_id, $acf_data ) {

		$program_name     = get_the_title( $acf_data['field_5ffcc2590682b'] );
		$actor_user       = get_current_user();
		$actor_name       = $actor_user->display_name;
		$user             = get_userdata( $acf_data['field_601d4a61e8ace'] );
		$user_name        = $user->display_name;
		$returned_comment = $acf_data['field_601d52f2e5418'];
		$admin_order_url  = admin_url() . "post.php?post={$order_post_id}&action=edit";
		$message          = "<p>
  Howdy,
</p>
<p>
  The {$program_name} order for {$user_name} has been returned by {$actor_name}. An explanation should appear below in the comments.
</p>
<p>
  Comments from {$actor_name}: {$returned_comment}
</p>
<p>
  {$user_name} will correct the order and resubmit.
</p>
<p>
  You can view the order at this link: {$admin_order_url}.
</p>
<p>
  Have a great day!<br />
  <em>- Liberal Arts IT</em>
</p>
<p><em>This email was sent from an unmonitored email address. Please do not reply to this email.</em></p>";

		return $message;

	}

	private function email_body_order_approved( $order_post_id, $acf_data ) {

		$program_name = get_the_title( $acf_data['field_5ffcc2590682b'] );
		$user         = get_userdata( $acf_data['field_601d4a61e8ace'] );
		$user_name    = $user->display_name;
		$message      = "<p>
	Howdy,
</p>
<p>
	The {$program_name} order for {$user_name} has been approved.
</p>
<p>
  Have a great day!<br />
  <em>- Liberal Arts IT</em>
</p>
<p><em>This email was sent from an unmonitored email address. Please do not reply to this email.</em></p>";

		return $message;

	}
}
