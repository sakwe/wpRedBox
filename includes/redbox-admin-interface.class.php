<?php
/* Admin options load only
 *
 **/


class RedBoxAdmin{

	public function __construct(){
		add_action('admin_init', array(__CLASS__, "plugin_init"));
		add_action('admin_menu', array(__CLASS__, "setup_menus"));
		add_action('admin_head', array(__CLASS__, "admin_header"));
		add_action('admin_enqueue_scripts', array(__CLASS__, "enqueue_admin_scripts"));
	}
	
	public function plugin_init(){

		// register and configurate admin sections for the interface
		register_setting( 'redbox_options', 'redbox_options', array(__CLASS__, "validate_fields"));			
		add_settings_section('redbox_wordpress_options', '<hr />'.REDBOX_CONFIGURATION, array(__CLASS__, "redbox_wordpress_options"), 'redbox');
		add_settings_section('redbox_facebook_option', '<hr />'.REDBOX_CONFIGURATION_FACEBOOK, array(__CLASS__, "redbox_facebook_options"), 'redbox');
		add_settings_section('redbox_import_status', '<hr />'.REDBOX_IMPORT_FROM_FACEBOOK, array(__CLASS__, "redbox_import_buttons"), 'redbox');
		add_settings_field('redbox_import_selections_field', '<hr />'.REDBOX_IMPORT_RESULTS, array(__CLASS__, "redbox_import_status"), 'redbox', 'redbox_import_status');
	
		// initialise RedBox Sync Table
		global $wpdb;
		$table_name = $wpdb->prefix . "redbox_fb"; 
		$sql = "CREATE TABLE $table_name (
		  id mediumint(9) NOT NULL AUTO_INCREMENT,
		  date datetime DEFAULT '0000-00-00 00:00:00' NOT NULL,
		  id_fb text NOT NULL,
		  UNIQUE KEY id (id)
		);";
		require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
		dbDelta( $sql );
	}

	public function setup_menus() {
		add_options_page('RedBox', 'RedBox config', 'manage_options', 'redbox', array(__CLASS__, "plugin_options"));		
	}
	
	public function validate_fields() {
				
		return array(
			"facebook_id" => $_POST['redbox_profile_field'],
			"facebook_app_id" => $_POST['facebook_appid_field'],
			"facebook_app_secret" => $_POST['facebook_appsecret_field'],
			"facebook_tags_for_posts" => $_POST['facebook_tags_field'],
			"redbox_page_name" => $_POST['redbox_page_name']
		);
	}
	
	public function admin_header() {
		global $post_type;
		echo '<style>';
		if (($_GET['post_type'] == 'redbox') || ($post_type == 'redbox') || ($_GET['page'] == "redbox") ) :
			echo '#icon-edit { background:transparent url('.WP_PLUGIN_URL.'/redbox/img/ico-menu.png) no-repeat; }';
		endif;
		echo '</style>';
	}
	
	public function enqueue_admin_scripts() {
		// nothing more to do than for users ("enqueue_admin_scripts" in redbox-user-interface)
	}
	
	public function plugin_options() {
		?>
		<div class="wrap">
			<div id="icon-edit" class="icon32">
				<br>
			</div>
			<h2>RedBox</h2>
			<form action="options.php" method="post" id="redbox_config_form">
				<div id="redbox_info_config">
				<?php echo REDBOX_INFO_CONFIG ; ?>
				</div>
				<p><input name="Submit" type="submit" class="facebookGallerySubmit" value="<?php esc_attr_e('Save Changes'); ?>" /></p>
				<?php settings_fields('redbox_options'); ?>
				<?php do_settings_sections('redbox'); ?>
				<p><input name="Submit" type="submit" class="facebookGallerySubmit" value="<?php esc_attr_e('Save Changes'); ?>" /></p>
			</form>
		</div>
		<?php
	}
	
	public function redbox_wordpress_options() {
		echo "<div class=\"wrap\"><div id=\"redbox_wp_config\"><p>".REDBOX_CONFIGURE_BLOG_TITLE."</p></div>";
		$options = get_option('redbox_options');
		echo '<ul id="redbox_wordpress_options">';
		echo '<li><span>'.REDBOX_BLOG_PAGE_NAME.' : </span>
			<input type="text" name="redbox_page_name" id="profileIDBox" value="'.$options['redbox_page_name'].'" />
			</li>';
		echo '<li><span>'.REDBOX_AUTOTAGS_LIST.' : </span>
			<textarea name="facebook_tags_field" id="profileTagsBox">'.$options['facebook_tags_for_posts'].'</textarea>
			</li>';
		echo '</ul></div>';
		
	}

	function redbox_import_status() {
		global $wpdb;		
		global $importInfo,$posts_id;
		
		if ($rows = $wpdb->get_results('SELECT * FROM ' . $wpdb->prefix .'redbox_fb')){
			$return.= count($rows)." ".REDBOX_FACEBOOK_TMP;
		}
		$return.="<br />";
		$wp_posts_ids = get_wp_posts_array();
		$return.= count($wp_posts_ids)." ".REDBOX_FACEBOOK_INWP;
		$return.="<br />";
		echo $return;
		if ($importInfo!="") echo "<br />".$importInfo."<br /><br />";
		if (count($posts_id) > 0 ) display_posts($posts_id);
	}

	function redbox_import_buttons() {		
		$base_url = site_url().'/wp-admin/options-general.php?page=redbox&action=';
		echo '<div class=\"wrap\"><ul id="redbox_fb_sync_buttons">
		<li><a href="'.$base_url.'check" class="button" >'.REDBOX_CHECK_FACEBOOK.'</a></li>
		<li><a href="'.$base_url.'action=check_forced" class="button" >'.REDBOX_CHECK_FACEBOOK_FORCED.'</a></li>
		<li><a href="'.$base_url.'import" class="button" >'.REDBOX_IMPORT_FACEBOOK_NEEDED.'</a></li>
		<li><a href="'.$base_url.'import_forced" class="button" >'.REDBOX_IMPORT_FACEBOOK_FORCED.'</a></li>
		</ul>';
		echo "<div id=\"redbox_info_fb_import\">".REDBOX_IMPORT_BUTTON_HELP."</div></div>";
		return true;
	}

	function redbox_facebook_options() {
		echo "<div class=\"wrap\"><div id=\"redbox_info_fb_config\">".REDBOX_FACEBOOK_CONFIG_HELP."</div>";
		$options = get_option('redbox_options');
		echo '<ul id="redbox_facebook_options">';
		echo '<li><span>'.REDBOX_FACEBOOK_ID_LABEL.' : </span>';
		echo '<input type="text" name="redbox_profile_field" id="profileIDBox" value="'.$options['facebook_id'].'" /></li>';
		echo '<li><span>'.REDBOX_FACEBOOK_APPID_LABEL.' : </span>';
		echo '<input type="text" name="facebook_appid_field" id="profileAppBox" value="'.$options['facebook_app_id'].'" /></li>';
		echo '<li><span>'.REDBOX_FACEBOOK_SECRET_LABEL.' : </span>';
		echo '<input type="text" name="facebook_appsecret_field" id="profileSecretBox" value="'.$options['facebook_app_secret'].'" /></li>';
		echo '</ul></div>';	
		return true;
	}
}



?>
