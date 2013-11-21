<?php
/* Blog integration
 *
 **/


class RedBoxBlog{

	public function __construct(){
		add_action('admin_head', array(__CLASS__, "admin_header"));
		add_action('admin_enqueue_scripts', array(__CLASS__, "enqueue_wp_scripts"));
		add_action('wp_enqueue_scripts', array(__CLASS__, "enqueue_wp_scripts"));
	}
	
	
	public function admin_header() {
		global $post_type;
		echo '<style>';
		if (($_GET['post_type'] == 'redbox') || ($post_type == 'redbox') || ($_GET['page'] == "redbox") ) :
			echo '#icon-redbox { background:transparent url('.WP_PLUGIN_URL.'/redbox/img/ico-menu.png) no-repeat; }';
		endif;
		echo '</style>';
	}
	
	public function enqueue_wp_scripts() {
		wp_enqueue_script( 'jquery', 'http://code.jquery.com/jquery-1.9.1.min.js' );
		wp_enqueue_script( 'wp_redbox_importer_admin', WP_PLUGIN_URL.'/redbox/js/importer.jquery.js' );
		wp_register_style( 'redbox-style', WP_PLUGIN_URL."/redbox/css/redbox.css");
		wp_enqueue_style( 'redbox-style' );
		wp_register_style( 'redbox-blog-style', WP_PLUGIN_URL."/redbox/css/redbox-blog.css");
		wp_enqueue_style( 'redbox-blog-style' );
		if (isset($_GET['p'])){
			wp_enqueue_script( 'wp_redbox_custom_comments', WP_PLUGIN_URL.'/redbox/js/custom-comments.js' );
		}
	}
	
	
}



?>
