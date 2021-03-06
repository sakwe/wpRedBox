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
		add_action( 'wp_dashboard_setup', array(&$this,'redbox_add_dashboard_widget') );
	}
	
	public function plugin_init(){
		// register and configurate admin sections for the interface
		register_setting( 'redbox_options', 'redbox_options', array(__CLASS__, "validate_fields"));			
		add_settings_section('redbox_wordpress_options', '<hr />'.REDBOX_CONFIGURATION, array(&$this, "redbox_wordpress_options"), 'redbox');
		add_settings_section('redbox_import_options', '<hr />'.REDBOX_OPTIONS, array(&$this, "redbox_import_options"), 'redbox');
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

	public function allow_upload_contributors() {
		$contributor = get_role('contributor');
		$contributor->add_cap('upload_files');
	}

	public function redbox_add_dashboard_widget() {
		wp_add_dashboard_widget(
		'redbox_dashboard_widget',         // Widget slug.
		'RedBox rapide',         // Title.
		array(&$this,'display_redbox_dashboard_widget') // Display function.
		);
		global $wp_meta_boxes;
		$normal_dashboard = $wp_meta_boxes['dashboard']['normal']['core'];
		$widget_backup = array( 'redbox_dashboard_widget' => $normal_dashboard['redbox_dashboard_widget'] );
		unset( $normal_dashboard['redbox_dashboard_widget'] );
		$sorted_dashboard = array_merge( $widget_backup, $normal_dashboard );
		$wp_meta_boxes['dashboard']['normal']['core'] = $sorted_dashboard;
	}

	public function display_redbox_dashboard_widget(){
		if( current_user_can( 'edit_posts' ) ){
			$placeholder="Créer un article à partir de texte, lien vers images, vidéos, articles...";
			$txtBtn = "Créer un post";
		}else{
			$placeholder="Soumettez-nous votre proposition à partir de texte, lien vers images, vidéos, articles...";
			$txtBtn = "Proposer";
		}
		$widget = '<div class="redbox_dashboard_widget">';
		$widget.= '<form name="redbox_form" method="post" style="display:inline-block;text-align:right;width:100%;">';
		$widget.= '<input type="hidden" id="redbox_action" name="redbox_action" value="redbox_submit_from_admin_widget" />';
		$widget.= '<textarea class="redbox_textarea" id="redbox_textarea" placeholder="'.$placeholder.'" name="url_to_import" style="height:150px;">';
		if (isset($_POST['url_to_import'])) $widget.=  stripslashes(trim($_POST['url_to_import']));
		$widget.= '</textarea>';
		$widget.= '<div id="redbox_widget_status" style="float:right;"></div>';
		$widget.= '<input style="display:inline-block;text-align:right;" type="button" class="redbox_button redbox_button-info" onclick="redbox_ajax_do(\'redbox_submit_from_admin_widget\',document.getElementById(\'redbox_textarea\').value,true,true)" value="'.$txtBtn.'" />';
		$widget.= '';
		$widget.= '</form>';
		$widget.= '</div>';
		echo $widget;
	}
	
	public function setup_menus() {
		if ( current_user_can('contributor') && !current_user_can('upload_files') ) {
			add_action('admin_init', array(&$this,'allow_upload_contributors'));
		}
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
			"facebook_import_date" => $_POST['facebook_import_date'],
			"redbox_fb_post_sign" => $_POST['redbox_fb_post_sign'],
			"redbox_crowdfundings" => $_POST['redbox_crowdfundings'],
			"redbox_fallBackUrl" => $_POST['redbox_fallBackUrl'],
			"redbox_to_clean_in_urls" => $_POST['redbox_to_clean_in_urls'],
			"redbox_sub_replace_source" => $_POST['redbox_sub_replace_source'],
			"redbox_to_clean_in_titles" => $_POST['redbox_to_clean_in_titles'],
			"redbox_to_clean_in_texts" => $_POST['redbox_to_clean_in_texts'],
			"redbox_shortcode_to_add" => $_POST['redbox_shortcode_to_add']
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
			<input type="text" name="redbox_page_name" class="redbox_config_imputbox" value="'.$options['redbox_page_name'].'" />
			</li>';
		echo '<li><span>'.REDBOX_FALLBACK_URL.' : </span>
			<input type="text" name="redbox_fallBackUrl" class="redbox_config_imputbox" value="'.$options['redbox_fallBackUrl'].'" />
			</li>';
		echo '<li><span>'.REDBOX_SHORTCODE_TO_ADD.' : </span>
			<textarea name="redbox_shortcode_to_add" class="redbox_config_textarea">'.stripslashes($options['redbox_shortcode_to_add']).'</textarea>
			</li>';
		echo '</ul></div>';
		if ($options['redbox_page_id']==0 && trim($options['redbox_page_name'])!='' ) {
			echo $this->redbox->dispatcher->dialogBox(REDBOX_ERROR_PAGE_NOT_EXIST,REDBOX_ERROR_CONFIGURATION,"warning");
		}
	}


	public function redbox_import_options() {
		$options = get_option('redbox_options');
		echo "<div class=\"wrap\"><div id=\"redbox_wp_options\"><p>".REDBOX_IMPORT_BLOG_TITLE."</p></div>";		
		echo '<ul id="redbox_import_options">';
		echo '<li><span>'.REDBOX_FB_POST_SIGN.' : </span>
			<input type="text" name="redbox_fb_post_sign" class="redbox_config_imputbox" value="'.stripslashes($options['redbox_fb_post_sign']).'" />
			</li>';
		echo '<li><span>'.REDBOX_AUTOTAGS_LIST.' : </span>
			<textarea name="facebook_tags_field" class="redbox_config_textarea">'.stripslashes($options['facebook_tags_for_posts']).'</textarea>
			</li>';
		echo '<li><span>'.REDBOX_CROWDFOUNDING.' : </span>
			<textarea name="redbox_crowdfundings" class="redbox_config_textarea">'.stripslashes($options['redbox_crowdfundings']).'</textarea>
			</li>';
		echo '<li><span>'.REDBOX_TO_CLEAN_IN_URLS.' : </span>
			<textarea name="redbox_to_clean_in_urls" class="redbox_config_textarea">'.stripslashes($options['redbox_to_clean_in_urls']).'</textarea>
			</li>';
		echo '<li><span>'.REDBOX_SUB_REPLACE_SOURCE.' : </span>
			<textarea name="redbox_sub_replace_source" class="redbox_config_textarea">'.stripslashes($options['redbox_sub_replace_source']).'</textarea>
			</li>';
		echo '<li><span>'.REDBOX_TO_CLEAN_IN_TITLES.' : </span>
			<textarea name="redbox_to_clean_in_titles" class="redbox_config_textarea">'.stripslashes($options['redbox_to_clean_in_titles']).'</textarea>
			</li>';
		echo '<li><span>'.REDBOX_TO_CLEAN_IN_TEXTS.' : </span>
			<textarea name="redbox_to_clean_in_texts" class="redbox_config_textarea">'.stripslashes($options['redbox_to_clean_in_texts']).'</textarea>
			</li>';
		echo '</ul></div>';
		
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
