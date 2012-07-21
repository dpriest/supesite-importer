<?php
/*
Plugin Name: SupeSite Importer
Plugin URI: http://wp4d.sinaapp.com/
Description: Import posts and comments from SupeSite.
Author: dpriest
Author URI: http://wp4d.sinaapp.com/
Author Email:wenhaoz100@gmail.com
Version: 0.1
Stable tag: 0.1
License: GPL version 2 or later - http://www.gnu.org/licenses/old-licenses/gpl-2.0.html
*/

add_action( 'wp_ajax_supesite_importer', 'supesite_import_ajax_handler' );

function supesite_import_ajax_handler() {
	global $ss_api_import;
	check_ajax_referer( 'ss-api-import' );
	if ( !current_user_can( 'publish_posts' ) )
		die('-1');
	if ( empty( $_POST['step'] ) )
		die( '-1' );
	define('WP_IMPORTING', true);
	$result = $ss_api_import->{ 'step' . ( (int) $_POST['step'] ) }();
	if ( is_wp_error( $result ) )
		echo $result->get_error_message();
	die;
}

if ( !defined('WP_LOAD_IMPORTERS') && !defined( 'DOING_AJAX' ) )
	return;

// Load Importer API
require_once ABSPATH . 'wp-admin/includes/import.php';

if ( !class_exists( 'WP_Importer' ) ) {
	$class_wp_importer = ABSPATH . 'wp-admin/includes/class-wp-importer.php';
	if ( file_exists( $class_wp_importer ) )
		require_once $class_wp_importer;
}

/**
 * SupeSite Importer
 *
 * @package WordPress
 * @subpackage Importer
 */
if ( class_exists( 'WP_Importer' ) ) {
class SS_API_Import extends WP_Importer {

	var $DB_Conn;
	var $username;
	var $password;

	function header() {
		echo '<div class="wrap">';
		screen_icon();
		echo '<h2>' . __( '导入cic博文' , 'supesite-importer') . '</h2>';
	}

	function footer() {
		echo '</div>';
	}

	function greet() {
		?>
		<div class="narrow">
		<form action="admin.php?import=supesite" method="post">
		<?php wp_nonce_field( 'ss-api-import' ) ?>
			<input type="hidden" name="step" value="1" />
			<input type="hidden" name="login" value="true" />
			<p><?php _e( '你好，该博客导入程序能为您导入旧版cic博客里的博文' , 'supesite-importer') ?></p>
			<p><?php _e( '输入你的cic博客帐号密码就可以导入：' , 'supesite-importer') ?></p>

			<table class="form-table">

			<tr>
			<th scope="row"><label for="ss_username"><?php _e( 'cic用户名' , 'supesite-importer') ?></label></th>
			<td><input type="text" name="ss_username" id="ss_username" class="regular-text" /></td>
			</tr>

			<tr>
			<th scope="row"><label for="ss_password"><?php _e( 'cic密码' , 'supesite-importer') ?></label></th>
			<td><input type="password" name="ss_password" id="ss_password" class="regular-text" /></td>
			</tr>

			</table>

			<p class="submit">
				<input type="submit" class="button" value="<?php esc_attr_e( '开始执行导入' , 'supesite-importer') ?>" />
			</p>
		</form>
		</div>
		<?php
	}

	function ss_DB_Conn() {
		$args = func_get_args();
        $method = array_shift( $args );
		if ( isset( $args[0] ) )
			$params = array_merge( $params, $args[0] );
		if ( $method == 'login' ) {	
			include_once '../wp-content/plugins/ucenter-integration/client/client.php';
			return uc_user_login($this->username, $this->password);
		} else {
			return false;
		}
	}

	function dispatch() {
		if ( empty( $_REQUEST['step'] ) )
			$step = 0;
		else
			$step = (int) $_REQUEST['step'];

		$this->header();

		switch ( $step ) {
			case -1 :
				$this->cleanup();
				// Intentional no break
			case 0 :
				$this->greet();
				break;
			case 1 :
				check_admin_referer( 'ss-api-import' );
				$result = $this->{ 'step' . $step }();
				if ( is_wp_error( $result ) ) {
					$this->throw_error( $result, $step );
				}
				break;
		}

		$this->footer();
	}

	// Technically the first half of step 1, this is separated to allow for AJAX
	// calls. Sets up some variables and options and confirms authentication.
	function setup() {
		global $verified;
		// Get details from form or from DB
		if ( !empty( $_POST['ss_username'] ) && !empty( $_POST['ss_password'] ) ) {
			// Store details for later
			$this->username = $_POST['ss_username'];
			$this->password = $_POST['ss_password'];
			update_option( 'ssapi_username', $this->username );
			update_option( 'ssapi_password', $this->password );
		} else {
			$this->username = get_option( 'ssapi_username' );
			$this->password = get_option( 'ssapi_password' );
		}

		// Log in to confirm the details are correct
		if ( empty( $this->username ) || empty( $this->password ) ) {
			?>
			<p><?php _e( '你输入您的帐号和密码' , 'supesite-importer') ?></p>
			<p><a href="<?php echo esc_url($_SERVER['PHP_SELF'] . '?import=supesite&amp;step=-1&amp;_wpnonce=' . wp_create_nonce( 'ss-api-import' ) . '&amp;_wp_http_referer=' . esc_attr( str_replace( '&step=1', '', $_SERVER['REQUEST_URI'] ) ) ) ?>"><?php _e( '再输一次' , 'supesite-importer') ?></a></p>
			<?php
			return false;
		}
		$verified = $this->ss_DB_Conn( 'login' );
		if ( count($verified)<5 ) {
			if ( 100 == $this->DB_Conn->getErrorCode() || 101 == $this->DB_Conn->getErrorCode() ) {
				delete_option( 'ssapi_username' );
				delete_option( 'ssapi_password' );
				delete_option( 'ssapi_protected_password' );
				?>
				<p><?php _e( 'Logging in to SupeSite failed. Check your username and password and try again.' , 'supesite-importer') ?></p>
				<p><a href="<?php echo esc_url($_SERVER['PHP_SELF'] . '?import=supesite&amp;step=-1&amp;_wpnonce=' . wp_create_nonce( 'ss-api-import' ) . '&amp;_wp_http_referer=' . esc_attr( str_replace( '&step=1', '', $_SERVER['REQUEST_URI'] ) ) ) ?>"><?php _e( 'Start again' , 'supesite-importer') ?></a></p>
				<?php
				return false;
			} else {
				return $verified;
			}
		} else {
			update_option( 'ssapi_verified', 'yes' );
		}

		// Set up some options to avoid them autoloading (these ones get big)
		add_option( 'ssapi_sync_item_times',  '', '', 'no' );
		add_option( 'ssapi_usermap',          '', '', 'no' );
		update_option( 'ssapi_comment_batch', 0 );

		return true;
	}

	// Re-thread comments already in the DB
	function step1() {
		global $verified;

		do_action( 'import_start' );

		set_time_limit( 0 );
		update_option( 'ssapi_step', 1 );
		$this->_create_DB_Conn_client();
		
		if ( empty( $_POST['login'] ) ) {
			// We're looping -- load some details from DB
			$this->username = get_option( 'ssapi_username' );
			$this->password = get_option( 'ssapi_password' );
		} else {
			// First run (non-AJAX)
			$setup = $this->setup();
			if ( !$setup ) {
				return false;
			} else if ( is_wp_error( $setup ) ) {
				$this->throw_error( $setup, 1 );
				return false;
			}
		}
		//先从wordpress里面读数据
		mysql_select_db('wordpress');
		global $wpdb;
		$sql = "SELECT max(ID) FROM $wpdb->posts";
		$maxid = $wpdb->get_var( $wpdb->prepare( $sql ) );

		mysql_select_db('online');
		$sql = 'select subject, message, dateline, lastpost from blog_spaceblogs join blog_spaceitems on blog_spaceitems.itemid=blog_spaceblogs.itemid where username="'.$this->username.'" and type="blog"';
		$result = mysql_query($sql, $this->DB_Conn);
		$rows = array();
		$user = wp_get_current_user();
		while ($row = mysql_fetch_assoc($result)) {
			$maxid++;
			$post_date = date('Y-m-d H:i:s', $row['dateline']);
			$post_content = addslashes(iconv('GBK', 'UTF-8', $row['message']));
			$post_modified = date('Y-m-d H:i:s', $row['lastpost']);
			$post_title = addslashes(iconv('GBK', 'UTF-8', $row['subject']));
			$post_name = str_replace('.', '-', preg_replace('/\s+/', '-', $post_title));
			$post_name = strtolower(urlencode($post_name));
			$rows[] = "($maxid, $user->ID, '$post_date', '$post_date', '$post_content', '$post_title', '$post_name', '$post_modified', '$post_modified', 'http://wp.hfutonline.net/?p=$maxid')";
		}

		mysql_select_db('wordpress');
		mysql_query('SET NAMES UTF8;');
		// Handle all the metadata for this post
		$sql = "insert into $wpdb->posts (ID, post_author, post_date, post_date_gmt, post_content, post_title, post_name, post_modified, post_modified_gmt, guid) values ".implode(', ', $rows);
		$wpdb->query( $sql);
		$wpdb->flush();

		echo '<h3>';
		printf( __( '导入成功' , 'supesite-importer'), get_option( 'home' ) );
		echo '</h3>';
		echo '</div>';
		$this->cleanup();
	}

	// Remove all options used during import process and
	// set wp_comments entries back to "normal" values
	function cleanup() {
		global $wpdb;

		delete_option( 'ssapi_username' );
		delete_option( 'ssapi_password' );
		delete_option( 'ssapi_protected_password' );
		delete_option( 'ssapi_verified' );
		delete_option( 'ssapi_total' );
		delete_option( 'ssapi_count' );
		delete_option( 'ssapi_lastsync' );
		delete_option( 'ssapi_last_sync_count' );
		delete_option( 'ssapi_sync_item_times' );
		delete_option( 'ssapi_lastsync_posts' );
		delete_option( 'ssapi_post_batch' );
		delete_option( 'ssapi_imported_count' );
		delete_option( 'ssapi_maxid' );
		delete_option( 'ssapi_usermap' );
		delete_option( 'ssapi_highest_id' );
		delete_option( 'ssapi_highest_comment_id' );
		delete_option( 'ssapi_comment_batch' );
		delete_option( 'ssapi_step' );
	}

	function _create_DB_Conn_client() {
		if ( !$this->DB_Conn ) {
			$this->DB_Conn = mysql_connect('localhost', 'root', 'sushi');
			mysql_select_db('online', $this->DB_Conn);
		}
	}
}

$ss_api_import = new SS_API_Import();

register_importer( 'supesite', __( 'SupeSite' , 'supesite-importer'), __( 'Import posts from SupeSite using their API.' , 'supesite-importer'), array( $ss_api_import, 'dispatch' ) );

} // class_exists( 'WP_Importer' )

function supesite_importer_init() {
	load_plugin_textdomain( 'supesite-importer', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
}
add_action( 'init', 'supesite_importer_init' );
