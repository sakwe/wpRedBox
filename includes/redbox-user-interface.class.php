<?php
/* Users options load only
 *
 **/


class RedBoxUser{

	public function __construct(){
		add_action('admin_menu', array(__CLASS__, "setup_menus"));
		add_action( 'admin_bar_menu', array( __CLASS__, 'redbox_admin_bar' ), 4 );
		add_action( 'post_submitbox_misc_actions', array( __CLASS__, 'redbox_show_box_edit' ) );
		add_action("template_redirect", array(__CLASS__, 'redbox_theme_redirect'));
		
		if (isset($_GET['p'])){
			add_filter('manage_edit-comments_columns', array(__CLASS__, "redbox_columns_head"));  
			add_filter('manage_comments_custom_column', array(__CLASS__, "redbox_columns_content"), 10, 2);
		}
		
	}
	
	
	public function setup_menus() {
		add_menu_page('RedBox', 'RedBox', 'edit_posts', 'redbox-view', array(__CLASS__, "redbox_view"),plugin_dir_url( __FILE__ ).'/img/redbox-ico.png');
	}
	
	public function validate_fields() {
		// nothing to do for users
	}
	
	public function redbox_admin_bar($wp_admin_bar){
		if( ! current_user_can( 'edit_posts' ) )
			return;
		
		$form = self::get_import_bar( array(
			'form_id'      => 'adminbarredbox',
			'redbox_id'    => 'adminbar-redbox',
			'redbox_class' => 'adminbar-input',
			'submit_class' => 'adminbar-button',
		) );
		$wp_admin_bar->add_menu( array(
			'parent' => false,
			'id'     => 'input',
			'title'  => $form,
			'meta'   => array(
				'class'    => 'admin-bar-redbox',
				'tabindex' => -1
			)
		) );
	
	}

	// this is the form that display the imput capabilities for the user
	static function get_redbox_import_bar( $args = array() ) {
		$defaults = array(
			'form_id'            => null,
			'form_class'         => null,
			'redbox_class'       => null,
			'redbox_id'          => null,
			'redbox_value'       => isset( $_REQUEST['url_to_import'] ) ? $_REQUEST['url_to_import'] : null,
			'redbox_placeholder' => __( 'Importer n\'importe quoi !', 'redbox' ),
			'submit_class'       => 'button',
			'submit_value'       => __( 'Import', 'redbox-view' ),
			'alternate_submit'   => false,
		);
		extract( array_map( 'esc_attr', wp_parse_args( $args, $defaults ) ) );

		$rand = rand();
		if( empty( $form_id ) )
			$form_id = "redbox_form_$rand";
		if( empty( $redbox_id ) )
			$redbox_id = "redbox_search_$rand";

		ob_start();
		?>

		<form title="Importer à partir d'une url" action="<?php echo esc_url( admin_url( 'admin.php' ) ); ?>" method="post" class="<?php echo $form_class; ?>" id="<?php echo $form_id; ?>">
			<input type="hidden" name="page" value="redbox-view" />
			<input name="url_to_import" type="search" class="<?php echo $redbox_class; ?>" id="<?php echo $redbox_id; ?>" value="<?php echo $redbox_value; ?>" placeholder="<?php echo $redbox_placeholder; ?>" />
			<?php if ( $alternate_submit ) : ?>
				<button type="submit" title="Importer à partir d'une url" class="<?php echo $submit_class; ?>"><span><?php echo $submit_value; ?></span></button>
			<?php else : ?>
				<input type="submit" title="Importer à partir d'une url" class="<?php echo $submit_class; ?>" value="<?php echo $submit_value; ?>" />
			<?php endif; ?>
		</form>

		<?php
		return apply_filters( 'get_redbox_form', ob_get_clean(), $args, $defaults );
	}
	
	public function redbox_columns_head($defaults){
			$defaults["redbox_admin"] = "RedBox";
			return $defaults;
		}
	
	function redbox_columns_content($column_name, $comment_ID){
		if ($column_name == 'redbox_admin') {
			$url = "";
			$comment = get_comment($comment_ID);
			preg_match_all('!https?://[\S]+!', $comment->comment_content, $match);
			foreach ($match[0] as $an_url) $url = $an_url;
			if (trim($url)!=""){
				$datas = get_url_meta_datas($url);
				$redbox_column.= '<div class="redbox_column_type"><span>'.$datas["type_human"].' : </span></div>';
				$redbox_column.= '<div class="redbox_column_title"><span>'.$datas["title_url"].'</span></div>';
				$redbox_column.= '<div class="redbox_column_picture"><img src="'.$datas["picture_url"].'"/></div>';
				preg_match('/^(?>\S+\s*){1,30}/', $datas["description_url"], $match);
				$datas["description_url"]= $match[0]."...";
				$redbox_column.= '<div class="redbox_column_description"><span>'.$datas["description_url"].'</span></div>';
				if (trim($datas["video_url"])!=""){
					$redbox_column.= '<div class="redbox_column_video">Voir la <a target="_blank" href="'.$datas["video_url"].'">vidéo</a></div>';
				}
				else{
					$redbox_column.= '<div class="redbox_column_video">Suivre la <a target="_blank" href="'.$url.'">source</a></div>';
				}
				$redbox_column.= '<div class="redbox_column_import">Créer un post à partir de cette <a target="_blank" href="http://'.$_SERVER["HTTP_HOST"].'/wp-admin/admin.php?page=redbox-view&url_to_import='.$url.'">proposition</a></div>';
			}
			else{
				$redbox_column.= "Pas de proposition valide";
			}			
			echo $redbox_column;  
		}
	}
	
	public function redbox_show_box_edit() {
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
	
	// redbox theme integration 
	public function redbox_theme_redirect(){
		global $wp;
		$options = get_option('redbox_options');
		//if ($wp->query_vars["post_name"] == $options['redbox_page_name']) {
			$template_path = WP_PLUGIN_DIR.'/redbox/theme/redbox-page-theme.php';
			RedBoxUser::do_theme_redirect($template_path);
		//}
	}	
	public function do_theme_redirect($url) {
		global $post, $wp_query;
		if (have_posts()) {
			include($url);
			die();
		} 
		else {
		$wp_query->is_404 = true;
		}
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
