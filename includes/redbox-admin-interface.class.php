<?php
/* Admin options load only
 *
 **/


class RedBoxAdmin{

	public function __construct(&$redbox){
		// connect to the global redbox instance
		$this->redbox = $redbox;

		add_action('admin_init', array(&$this, "plugin_init"));
		add_action('admin_menu', array(&$this, "setup_menus"));
	}
	
	public function plugin_init(){
	
		// register and configurate admin sections for the interface
		register_setting( 'redbox_options', 'redbox_options', array(__CLASS__, "validate_fields"));			
		add_settings_section('redbox_wordpress_options', '<hr />'.REDBOX_CONFIGURATION, array(&$this, "redbox_wordpress_options"), 'redbox');
		add_settings_section('redbox_facebook_option', '<hr />'.REDBOX_CONFIGURATION_FACEBOOK, array(&$this, "redbox_facebook_options"), 'redbox');
			
		// initialise RedBox Sync Table
		global $wpdb;
		$table_name = $wpdb->prefix . "redbox_fb"; 
		$sql = "CREATE TABLE $table_name (
		  id mediumint(9) NOT NULL AUTO_INCREMENT,
		  date datetime DEFAULT '0000-00-00 00:00:00' NOT NULL,
		  id_fb text NOT NULL,
		  type text ,
		  status text ,
		  UNIQUE KEY id (id)
		);";
		require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
		dbDelta( $sql );
	}

	public function setup_menus() {
		add_options_page('RedBox', 'RedBox config', 'manage_options', 'redbox', array(&$this, "plugin_options"));
	}
	
	public function validate_fields() {
		global $wpdb;
		$id=0;
		$sql = 'SELECT ID FROM ' . $wpdb->prefix .'posts WHERE post_name="'.trim($_POST['redbox_page_name']).'"';
		if ($rows = $wpdb->get_results($sql)){
			$id=$rows[0]->ID;
		}
		return array(
			"facebook_id" => $_POST['redbox_profile_field'],
			"facebook_ids" => $_POST['redbox_pages_ids_field'],
			"facebook_app_id" => $_POST['facebook_appid_field'],
			"facebook_app_secret" => $_POST['facebook_appsecret_field'],
			"facebook_tags_for_posts" => $_POST['facebook_tags_field'],
			"redbox_page_name" => $_POST['redbox_page_name'],
			"redbox_page_id" => $id,
			"facebook_import_date" => $_POST['facebook_import_date']
		);
	}
	
	public function plugin_options() {
		?>
		<div class="wrap">
			<div id="icon-redbox" class="icon32">
				<br>
			</div>
			<h2>RedBox</h2>
			<form action="options.php" method="post" id="redbox_config_form">
				<div id="redbox_info_config">
				<?php echo REDBOX_INFO_CONFIG ; ?>
				</div>
				<p><input name="Submit" type="submit" value="<?php esc_attr_e('Save Changes'); ?>" /></p>
				<?php settings_fields('redbox_options'); ?>
				<?php do_settings_sections('redbox'); ?>
				<p><input name="Submit" type="submit" value="<?php esc_attr_e('Save Changes'); ?>" /></p>
			</form>
		</div>
		<?php
	}
	
	public function redbox_wordpress_options() {
		$options = get_option('redbox_options');
		echo "<div class=\"wrap\"><div id=\"redbox_wp_config\"><p>".REDBOX_CONFIGURE_BLOG_TITLE."</p></div>";		
		echo '<ul id="redbox_wordpress_options">';
		echo '<li><span>'.REDBOX_BLOG_PAGE_NAME.' : </span>
			<input type="text" name="redbox_page_name" id="profileIDBox" value="'.$options['redbox_page_name'].'" />
			</li>';
		echo '<li><span>'.REDBOX_AUTOTAGS_LIST.' : </span>
			<textarea name="facebook_tags_field" id="profileTagsBox">'.$options['facebook_tags_for_posts'].'</textarea>
			</li>';
		echo '</ul></div>';
		if ($options['redbox_page_id']==0 && trim($options['redbox_page_name'])!='' ) {
			echo $this->redbox->dispatcher->dialogBox(REDBOX_ERROR_PAGE_NOT_EXIST,REDBOX_ERROR_CONFIGURATION,"warning");
		}
	}

	public function redbox_facebook_options() {
		$options = get_option('redbox_options');
		echo "<div class=\"wrap\"><div id=\"redbox_info_fb_config\">".REDBOX_FACEBOOK_CONFIG_HELP."</div>";
		echo '<ul id="redbox_facebook_options">';
		echo '<li><span>'.REDBOX_FACEBOOK_ID_LABEL.' : </span>';
		echo '<input type="text" name="redbox_profile_field" id="redbox_profile_field" value="'.$options['facebook_id'].'" /></li>';
		echo '<li><span>'.REDBOX_FACEBOOK_APPID_LABEL.' : </span>';
		echo '<input type="text" name="facebook_appid_field" id="facebook_appid_field" value="'.$options['facebook_app_id'].'" /></li>';
		echo '<li><span>'.REDBOX_FACEBOOK_SECRET_LABEL.' : </span>';
		echo '<input type="text" name="facebook_appsecret_field" id="facebook_appsecret_field" value="'.$options['facebook_app_secret'].'" /></li>';
		echo '<li><span>'.REDBOX_FACEBOOK_IMPORT_DATE.' : </span>';
		echo '<input type="text" name="facebook_import_date" id="facebook_import_date" value="'.$options['facebook_import_date'].'" /></li>';
		echo '<li><span>'.REDBOX_FACEBOOK_PAGES_IDS.' : </span>';
		echo '<input type="text" name="redbox_pages_ids_field" id="redbox_pages_ids_field" value="'.$options['facebook_ids'].'" /></li>';
		echo '</ul></div>';
		return true;
	}
}



?>
