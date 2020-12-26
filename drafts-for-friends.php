<?php
/*
Plugin Name: Drafts for Friends
Plugin URI: http://automattic.com/
Description: Now you don't need to add friends as users to the blog in order to let them preview your drafts
Author: Firoz Sabaliya
Version: 1.0.0
Author URI: https://profiles.wordpress.org/firoz2456/
License:     GPL2+
License URI:  https://www.gnu.org/licenses/gpl-2.0.html
Text Domain: drafts-for-friends
*/

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Drafts for friends version
 */
define( 'DRAFTSFORFRIENDS_VERSION', '1.0.0' );



/**
 * Class DraftsForFriends
 */
class DraftsForFriends {
	/**
	 * DraftsForFriends constructor.
	 */
	public function __construct() {
		add_action( 'init', array( &$this, 'init' ) );
	}

	/**
	 * Initialize the plugin
	 * @return void
	 */
	public function init() {
		global $current_user;
		add_action( 'admin_menu', array( $this, 'add_admin_pages' ) );
		add_filter( 'the_posts', array( $this, 'the_posts_intercept' ) );
		add_filter( 'posts_results', array( $this, 'posts_results_intercept' ) );
		$this->admin_options = $this->get_admin_options();
		$this->user_options  = ( $current_user->ID > 0 && isset( $this->admin_options[ $current_user->ID ] ) ) ? $this->admin_options[ $current_user->ID ] : array();
		$this->save_admin_options();
		$this->admin_page_init();
	}


	/**
	 * Initialize the Admin page
	 * Added css file and JS file
	 * @return void
	 */
	public function admin_page_init() {
		wp_enqueue_script( 'jquery' );
		add_action( 'admin_enqueue_scripts', array( $this, 'print_admin_css' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'print_admin_js' ) );

	}

	/**
	 * Fetch Admin option named Shared.
	 * @return array
	 */
	public function get_admin_options() {
		$saved_options = get_option( 'shared' );
		return is_array( $saved_options ) ? $saved_options : array();
	}

	/**
	 * Saved the Admin option value.
	 * @return null
	 */
	public function save_admin_options() {
		global $current_user;
		if ( $current_user->ID > 0 ) {
			$this->admin_options[ $current_user->ID ] = $this->user_options;
		}
		update_option( 'shared', $this->admin_options );
	}

	/**
	 * Add plugin Menu name and URL in the Admin panel
	 */
	public function add_admin_pages() {
		add_submenu_page(
			'edit.php',
			__( 'Drafts for Friends', 'draftsforfriends' ),
			__( 'Drafts for Friends', 'draftsforfriends' ),
			'edit_posts',
			'drafts-for-friends',
			array( $this, 'output_existing_menu_sub_admin_page' )
		);
	}

	/**
	 * Calculate expiry and display it in readable format
	 * @param int $params['expires'] timestamp
	 * @return int
	 */
	public function calc( $params ) {
		$exp      = 60;
		$multiply = 60;
		if ( isset( $params['expires'] ) && ( $e = intval( $params['expires'] ) ) ) {
			$exp = $e;
		}
		$mults = array(
			's' => 1,
			'm' => 60,
			'h' => 3600,
			'd' => 24 * 3600,
		);
		if ( $params['measure'] && $mults[ $params['measure'] ] ) {
			$multiply = $mults[ $params['measure'] ];
		}
		return $exp * $multiply;
	}

	/**
	 * Processing the Shared Draft
	 * @param array $params
	 * @return array
	 */
	public function process_post_options( $params ) {
		global $current_user;
		if ( $params['post_id'] ) {
			$p = get_post( $params['post_id'] );
			if ( ! $p ) {
				return array( 'error' => __( 'There is no such post!', 'draftsforfriends' ) );

			}
			if ( 'publish' === get_post_status( $p ) ) {
				return array( 'error' => __( 'The post is published, it cannot be shared.', 'draftsforfriends' ) );
			}
			$this->user_options['shared'][] = array(
				'id'      => $p->ID,
				'created' => time(),
				'expires' => time() + $this->calc( $params ),
				'key'     => 'auto_' . wp_generate_password( 25, false, false ),
			);
			$this->save_admin_options();
			return array( 'success' => __( 'The post is shared', 'draftsforfriends' ) );
		}
	}

	/**
	 * Delete process for shared draft
	 * @param array $params
	 * @return array
	 */
	public function process_delete( $params ) {
		$shared = array();
		foreach ( $this->user_options['shared'] as $share ) {
			if ( $share['key'] === $params['key'] ) {
				continue;
			}
			$shared[] = $share;
		}
		$this->user_options['shared'] = $shared;
		$this->save_admin_options();
		return array( 'error' => __( 'Shared draft deleted', 'draftsforfriends' ) );
	}

	/**
	 * Process to extend time of Shared draft
	 * @param array $params
	 * @return array
	 */
	public function process_extend( $params ) {
		$shared = array();
		foreach ( $this->user_options['shared'] as $share ) {
			if ( $share['key'] === $params['key'] ) {
				if ( $share['expires'] >= time() ) {
					$share['expires'] += $this->calc( $params );
				} else {
					$share['expires'] = time() + $this->calc( $params );
				}
			}
			$shared[] = $share;
		}
		$this->user_options['shared'] = $shared;
		$this->save_admin_options();
		return array( 'success' => __( 'Shared draft extended', 'draftsforfriends' ) );
	}

	/**
	 * Get all drafts post, schedule posts and pending posts
	 * @return array list of post title
	 */
	public function get_drafts() {
		global $current_user;
		$my_drafts    = $this->get_users_drafts($current_user->ID);
		$my_scheduled = $this->get_users_future($current_user->ID);
		$pending      = $this->get_users_pending($current_user->ID);
		$ds           = array(
			array(
				__( 'Your Drafts:', 'draftsforfriends' ),
				count( $my_drafts ),
				$my_drafts,
			),
			array(
				__( 'Your Scheduled Posts:', 'draftsforfriends' ),
				count( $my_scheduled ),
				$my_scheduled,
			),
			array(
				__( 'Pending Review:', 'draftsforfriends' ),
				count( $pending ),
				$pending,
			),
		);
		return $ds;
	}

	/**
	 * Get all drafts post list
	 * @return array
	 */
	public function get_users_drafts($user_id) {
		$args     = array( 'author' => $user_id, 
			'post_status' => 'draft' );
		$my_query = new WP_Query( $args );
		return $my_query->posts;
	}

	/**
	 * Get all scheduled post list
	 * @return array
	 */
	public function get_users_future($user_id) {
		$args     = array( 'author' => $user_id, 'post_status' => 'future' );
		$my_query = new WP_Query( $args );
		return $my_query->posts;
	}

	/**
	 * Get all schedule post list
	 * @return array
	 *
	 */
	public function get_users_pending($user_id) {
		$args     = array('author' => $user_id, 'post_status' => 'pending' );
		$my_query = new WP_Query( $args );
		return $my_query->posts;
	}

	/**
	 * Convert timestamp into human readable format
	 * @param int $timestamp
	 * @return string
	 */
	public function expires_after( $timestamp ) {
		$output    = array();
		$time_left = $timestamp - time();
		if ( $time_left <= 0 ) {
			return __( 'Expired', 'draftsforfriends' );
		}
		if ( 86400 <= $time_left ) {
			$days_left = floor( $time_left / 86400 );
			if ( 0 < $days_left ) {
				/* translators: %d: days */
				$output[] = sprintf( _n( '%d day', '%d days', $days_left, 'draftsforfriends' ), $days_left );
			}
		}
		if ( 3600 <= $time_left ) {
			$hours_left = floor( ( $time_left % 86400 ) / 3600 );
			if ( 0 < $hours_left ) {
				/* translators: %d: hours */
				$output[] = sprintf( _n( '%d hour', '%d hours', $hours_left, 'draftsforfriends' ), $hours_left );
			}
		}
		if ( 60 <= $time_left ) {
			$minutes_left = floor( ( $time_left % 3600 ) / 60 );
			if ( 0 < $minutes_left ) {
				/* translators: %d: minutes */
				$output[] = sprintf( _n( '%d minute', '%d minutes', $minutes_left, 'draftsforfriends' ), $minutes_left );
			}
		} else {
			/* translators: %d: seconds */
			$output[] = sprintf( _n( '%d second', '%d seconds', $time_left, 'draftsforfriends' ), $time_left );
		}
		return 'In ' . implode( ', ', $output ) . '.';
	}

	/**
	 * Checked if shared value is available into option table
	 * @return array
	 */
	public function get_shared() {
		return isset( $this->user_options['shared'] ) ? array_reverse($this->user_options['shared']) : array();
	}

	/**
	 * Display all the shared draft detail
	 */
	public function output_existing_menu_sub_admin_page() {
		// Check wp nouce if it is valid
		if ( isset( $_POST['_wpnonce'] ) ) { // Input var okay
			if ( wp_verify_nonce( sanitize_key( $_POST['_wpnonce'] ), 'draftsforfriends-share-nonce' ) ) { // Input var okay
				$t = $this->process_post_options( array_map( 'esc_attr', $_POST ) );  // Input var okay				
			} else {
				$t = array( 'error' => __( 'Nonce verification failed. Try Again.', 'draftsforfriends' ) );
			}
		}
		// Check if action is extend
		if ( isset( $_POST['action'] ) && ( 'extend' === $_POST['action'] ) ) { // Input var okay
			$t = $this->process_extend( array_map( 'esc_attr', $_POST ) ); // Input var okay
		} elseif ( isset( $_GET['action'] ) && ( 'delete' === $_GET['action'] ) ) { // Input var okay
			$t = $this->process_delete( array_map( 'esc_attr', $_GET ) ); // Input var okay
		}

		$ds = $this->get_drafts();

		?>
	<div class="wrap">
	<h2><?php esc_html_e( 'Drafts for Friends', 'draftsforfriends' ); ?></h2>
		<?php if ( ! empty( $t['success'] ) ) : ?>
	<div id="message" class="updated fade success"><?php echo esc_html( $t['success'] ); ?></div>
		<?php elseif ( ! empty( $t['error'] ) ) : ?>
	<div id="message" class="updated fade error"><?php echo esc_html( $t['error'] ); ?></div>
	<?php else : ?>
	<div id="message" class="updated" style="display: none;"></div>
		<?php endif; ?>

		<?php if ( ! empty( $ds[0][2] ) || ! empty( $ds[1][2] ) || ! empty( $ds[2][2] ) ) : ?>
				<h3><?php esc_html_e( 'Drafts for Friends', 'draftsforfriends' ); ?></h3>
			<?php //Form to share the draft ?>
				<form id="draftsforfriends-share" 
				action="<?php echo esc_url( admin_url( 'edit.php?page=drafts-for-friends' ) ); ?>" method="post">
			<?php wp_nonce_field( 'draftsforfriends-share-nonce' ); ?>
				<p>
				<select id="draftsforfriends-postid"    name="post_id">
					<option value=""><?php esc_html_e( 'Choose a draft', 'draftsforfriends' ); ?></option>
					<?php
					foreach ( $ds as $dt ) :
						if ( $dt[1] ) :
						?>
					<option value="" disabled="disabled"></option>
					<option value="" disabled="disabled"><?php echo esc_html( $dt[0] ); ?></option>
					<?php
					foreach ( $dt[2] as $d ) :
						if ( empty( $d->post_title ) ) {
							continue;
						}
						?>
						<option value="<?php echo esc_html( $d->ID ); ?>"><?php echo esc_html( $d->post_title ); ?></option>
						<?php
						endforeach;
						endif;
						endforeach;
						?>
				</select>
			</p>
			<p>
			<input id="draftsforfriends_submit" type="button" class="button" name="draftsforfriends_submit"
				value="<?php esc_html_e( 'Share it', 'draftsforfriends' ); ?>" />
				<?php esc_html_e( 'for', 'draftsforfriends' ); ?>
				<input name="expires" type="text" value="2" size="4" />
				<select name="measure">
					<option value="s"><?php esc_html_e( 'seconds', 'draftsforfriends' ); ?></option>
					<option value="m"><?php esc_html_e( 'minutes', 'draftsforfriends' ); ?></option>
					<option value="h" selected="selected"><?php esc_html_e( 'hours', 'draftsforfriends' ); ?></option>
					<option value="d"><?php esc_html_e( 'days', 'draftsforfriends' ); ?></option>
					</select>   
			</p>
	</form>
		<?php endif; ?>

	   <h3><?php esc_html_e( 'Currently shared drafts', 'draftsforfriends' ); ?></h3>
		<?php //Output as a table format ?>
	   <table class="widefat">
		  <thead>
			<tr>
				<th><?php esc_html_e( 'ID', 'draftsforfriends' ); ?></th>
				<th><?php esc_html_e( 'Date of Creation', 'draftsforfriends' ); ?></th>
				<th><?php esc_html_e( 'Title', 'draftsforfriends' ); ?></th>
				<th><?php esc_html_e( 'Link', 'draftsforfriends' ); ?></th>
				<th><?php esc_html_e( 'Expires After', 'draftsforfriends' ); ?></th>
				<th colspan="2" class="actions"><?php esc_html_e( 'Actions', 'draftsforfriends' ); ?></th>
			</tr>
		  </thead>
		  <tbody>
				<?php
				$s = $this->get_shared();
				foreach ( $s as $share ) :
					$p       = get_post( $share['id'] );
					$url     = get_bloginfo( 'url' ) . '/?p=' . $p->ID . '&draftsforfriends=' . $share['key'];
					$created = gmdate( 'd-M-Y H:i:s', $share['created'] );
				?>
			 <tr>
				<td><?php echo esc_html( $p->ID ); ?></td>
				<td><?php echo esc_html( $created ); ?></td>
				<td><?php echo esc_html( $p->post_title ); ?></td>
				<td><a href="<?php echo esc_url( $url ); ?>"><?php echo esc_html( $url ); ?></a></td>
				<td><?php echo esc_html( $this->expires_after( $share['expires'] ) ); ?></td>
				<td class="actions">
				   <a class="draftsforfriends-extend edit" id="draftsforfriends-extend-link-<?php echo esc_html( $share['key'] ); ?>"
					  href="javascript:draftsforfriends.toggle_extend('<?php echo esc_html( $share['key'] ); ?>');">
					<?php esc_html_e( 'Extend', 'draftsforfriends' ); ?>
				   </a>
					<?php //HTML form to extend the time ?>
				   <form class="draftsforfriends-extend" id="draftsforfriends-extend-form-<?php echo esc_html( $share['key'] ); ?>"
						  action="<?php echo esc_url( admin_url( 'edit.php?page=drafts-for-friends' ) ); ?>" 
						  method="post">    
						  <input type="hidden" name="action" value="extend" />
						  <input type="hidden" name="key" value="<?php echo esc_html( $share['key'] ); ?>" />
						  <input type="submit" class="button" name="draftsforfriends_extend_submit"
						value="<?php esc_html_e( 'Extend', 'draftsforfriends' ); ?>"/>
						<?php esc_html_e( 'by', 'draftsforfriends' ); ?> 
						<input name="expires" type="text" value="2" size="4" />
						<select name="measure">
							<option value="s"><?php esc_html_e( 'seconds', 'draftsforfriends' ); ?></option>
							<option value="m"><?php esc_html_e( 'minutes', 'draftsforfriends' ); ?></option>
							<option value="h" selected="selected"><?php esc_html_e( 'hours', 'draftsforfriends' ); ?></option>
							<option value="d"><?php esc_html_e( 'days', 'draftsforfriends' ); ?></option>
						</select>            
						  <a class="draftsforfriends-extend-cancel"
						href="javascript:draftsforfriends.cancel_extend('<?php echo esc_html( $share['key'] ); ?>');">
						<?php esc_html_e( 'Cancel', 'draftsforfriends' ); ?>
						  </a>
				   </form>
				</td>
				<td class="actions">
					<?php //Alert user for deletion. ?>
				   <a class="delete" onclick="return confirm('Are you sure to delete this item?')" href="edit.php?page=drafts-for-friends&amp;action=delete&amp;key=<?php echo esc_html( $share['key'] ); ?>"><?php esc_html_e( 'Delete', 'draftsforfriends' ); ?></a>
				</td>
			</tr>
				<?php
				endforeach;
				if ( empty( $s ) ) :
				?>
			<tr>
				<td colspan="5"><?php esc_html_e( 'No shared drafts!', 'draftsforfriends' ); ?></td>
			</tr>
				<?php
				endif;
				?>
					  </tbody>
				   </table>
	</div>
<?php
	}

	/**
	 * Check if user can view the post by matching the generated key and checking the key expiry
	 *
	 * @access private
	 * @param int $pid Post's ID
	 * @return bool True if the user can view, false otherwise
	 */
	private function can_view( $pid ) {
		foreach ( $this->admin_options as $option ) {
			$shares = $option['shared'];
			foreach ( $shares as $share ) {
				if ( isset( $_GET['draftsforfriends'] ) && sanitize_text_field( wp_unslash( $_GET['draftsforfriends'] ) ) === $share['key'] ) { // Input var okay.
					if ( $share['id'] === $pid && ( $share['expires'] > time() ) ) {
						return true;
					}
				}
			}
		}
		return false;
	}

	/**
	 * Intercept posts_results to check if user can view the post
	 * @param $pp
	 * @return mixed
	 */

	public function posts_results_intercept( $pp ) {
		if ( 1 !== count( $pp ) ) {
			return $pp;
		}
		$p      = $pp[0];
		$status = get_post_status( $p );
		if ( 'publish' !== $status && $this->can_view( $p->ID ) ) {
			$this->shared_post = $p;
		}
		return $pp;
	}

	/**
	 * Intercept the post results to show the shared draft post that we retrieved in posts_results_intercept()
	 * @param $pp
	 * @return array
	 */
	public function the_posts_intercept( $pp ) {
		if ( empty( $pp ) && ( ! empty( $this->shared_post ) && ! is_null( $this->shared_post ) ) ) {
			return array( $this->shared_post );
		} else {
			$this->shared_post = null;
			return $pp;
		}
	}

	/**
	 * Include the css file
	 */
	public function print_admin_css( $hook ) {
		if ( isset( $hook ) && 'posts_page_drafts-for-friends' !== $hook ) {
			return;
		}
		wp_enqueue_style( 'drafts-for-friends', plugins_url( 'css/drafts-for-friends-admin.css', __FILE__ ) );

	}

	/**
	 * Include JS file
	 */
	public function print_admin_js( $hook ) {
		if ( isset( $hook ) && 'posts_page_drafts-for-friends' !== $hook ) {
			return;
		}
		wp_enqueue_script( 'drafts-for-friends', plugins_url( 'js/drafts-for-friends-admin.js', __FILE__ ) );

	}
}

new draftsforfriends();