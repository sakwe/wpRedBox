<?php
/* Users options load only
 *
 **/


class RedBoxUser{

	public function __construct(){
		add_action('admin_init', array(__CLASS__, "plugin_init"));
		add_action('admin_menu', array(__CLASS__, "setup_menus"));
		add_action('admin_head', array(__CLASS__, "admin_header"));
		add_action('admin_enqueue_scripts', array(__CLASS__, "enqueue_admin_scripts"));
	}
	
	public function plugin_init(){

		
	}

	public function setup_menus() {
		add_menu_page('RedBox', 'RedBox', 'edit_posts', 'redbox-view', array(__CLASS__, "redbox_view"),plugin_dir_url( __FILE__ ).'/img/redbox-ico.png');
	}
	
	public function validate_fields() {
		// nothing to do for users
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
		wp_enqueue_script( 'jquery', 'http://code.jquery.com/jquery-1.9.1.min.js' );
		wp_enqueue_script( 'wp_redbox_importer_admin', WP_PLUGIN_URL.'/redbox/js/importer.jquery.js' );
		if (isset($_GET['p'])){
			wp_enqueue_script( 'wp_redbox_custom_comments', WP_PLUGIN_URL.'/redbox/js/custom-comments.js' );
		}
		wp_register_style( 'redbox-style', WP_PLUGIN_URL."/redbox/css/redbox.css");
		wp_enqueue_style( 'redbox-style' );
	}
	
	public function plugin_options() {
		//
	}
	
	function show_box_redbox() {
		global $meta_box_posts_link, $post;

		echo '<input type="hidden" name="meta_box_redbox" value="', wp_create_nonce(basename(__FILE__)), '" />';
	 
		echo '<hr /><table class="form-table"><tr">
			<td style="width:25%"><strong>Consignes de publication</strong></td>
			</tr>
			<td><ul>
			<li>Choisir un titre accrocheur et relativement court</li>
			<li>Vérifier si le format est bien adapté ("Par défaut" ou "Vidéo")</li>
			<li>Réaliser un aperçu avant publication</li>
			<li>Relecture orthographique obligatoire</li>
			</ul>
			</td></tr></table><hr />';
	}
	
	function redbox_view(){
		global $wpdb;
		if( current_user_can( 'read' ) && (($_GET['post_type'] == 'redbox-view') || ($post_type == 'redbox-view') || ($_GET['page'] == "redbox-view"))) {
			$options = get_option('redbox_options');
			$sql = 'SELECT ID FROM ' . $wpdb->prefix .'posts WHERE post_name="'.$options['redbox_page_name'].'"';
			$rows = $wpdb->get_results($sql);
			foreach($rows as $row){
				$target = "http://".$_SERVER["HTTP_HOST"]."/wp-admin/edit-comments.php?p=".$row->ID;
				echo "<script>window.location=\"".$target."\";</script>";
				exit;
			}
		}
	}
}



?>
