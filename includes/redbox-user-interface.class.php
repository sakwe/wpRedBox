<?php
/* Users options load only
 *
 **/


class RedBoxUser{

	public function __construct(&$redbox){
		// connect to the global redbox instance
		$this->redbox = $redbox;

		add_action('admin_menu', array(&$this, "setup_menus"));
		add_action( 'admin_bar_menu', array( &$this, 'redbox_admin_bar' ), 4 );
		add_action( 'post_submitbox_misc_actions', array( &$this, 'redbox_show_box_edit' ) );
		
		if (isset($_GET['p'])){
			add_filter('manage_edit-comments_columns', array(&$this, "redbox_columns_head"));  
			add_filter('manage_comments_custom_column', array(&$this, "redbox_columns_content"), 10, 2);
		}
		
	}
	
	// feed the admin menu with the user options
	public function setup_menus() {
		global $wpdb;
		$options = get_option('redbox_options');
		$sql=null;
		if ($options['redbox_page_id']>0){
			$sql = 'SELECT count(*) AS nb_prop FROM ' . $wpdb->prefix .'comments WHERE comment_approved=0 AND comment_post_ID='.$options['redbox_page_id'];
		}
		if ($sql && $rows = $wpdb->get_results($sql) ){
			if ($rows[0]->nb_prop > 0){
				$redbox_title = 'RedBox' . '<span class="ab-label awaiting-mod count-1"><span class="pending-count">'.$rows[0]->nb_prop.'</span></span>';
			}
			else{
				$redbox_title = 'RedBox';
			}
		}
		else{
			$redbox_title = 'RedBox';
		}
		
		add_menu_page('RedBox', $redbox_title, 'edit_posts', 'redbox-view', array(&$this, "redbox_view"),plugins_url().'/redbox/img/redbox-ico.png');
		if( current_user_can( 'edit_others_posts' ) ){
			add_submenu_page( 'redbox-view', 'Propositions', 'Propositions', 'edit_posts', 'redbox-view', array(&$this, "redbox_view") );
			add_submenu_page( 'redbox-view', 'Import via FanPage Facebook', 'Facebook', 'edit_posts', 'redbox-facebook', array(&$this->redbox->facebook, "redbox_view_facebook") );
		}
	}
	
	public function redbox_admin_bar($wp_admin_bar){
		if( ! current_user_can( 'read' ) )
			return;
		
		$form = self::get_redbox_import_bar( array(
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

	// this is the form that display the input capabilities int he admin bar for the user
	static function get_redbox_import_bar( $args = array() ) {
		if( current_user_can( 'edit_posts' )){
			$placeholder = 'Importer n\'importe quoi !';
		}
		else{
			$placeholder = 'Proposer n\'importe quoi !';
		}
		$defaults = array(
			'form_id'            => null,
			'form_class'         => null,
			'redbox_class'       => null,
			'redbox_id'          => null,
			'redbox_value'       => isset( $_REQUEST['url_to_import'] ) ? $_REQUEST['url_to_import'] : null,
			'redbox_placeholder' => __( $placeholder, 'redbox' ),
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

		<form title="Importer à partir d'une url" name="redbox_form" method="post" class="<?php echo $form_class; ?>" id="<?php echo $form_id; ?>">
			<input type="hidden" name="page" value="redbox-view" />
			<input type="hidden" id="redbox_action" name="redbox_action" value="redbox_submit_from_adminbar" />
			<input name="url_to_import" type="search" class="<?php echo $redbox_class; ?>" id="<?php echo $redbox_id; ?>" value="<?php echo $redbox_value; ?>" placeholder="<?php echo $redbox_placeholder; ?>" />
			<?php if ( $alternate_submit ) : ?>
				<button type="submit" class="<?php echo $submit_class; ?>"><span><?php echo $submit_value; ?></span></button>
			<?php else : ?>
				<input type="submit" class="<?php echo $submit_class; ?>" value="<?php echo $submit_value; ?>" />
			<?php endif; ?>
		</form>

		<?php
		return apply_filters( 'get_redbox_form', ob_get_clean(), $args, $defaults );
	}
	
	// add the RedBox column to the comment list interface
	public function redbox_columns_head($defaults){
			$defaults["redbox_admin"] = "RedBox";
			return $defaults;
		}
	
	// feed the comlumn with RedBox Propositions
	function redbox_columns_content($column_name, $comment_ID){
		global $wpdb;
		if ($column_name == 'redbox_admin') {
			$url = "";
			$comment = get_comment($comment_ID);
			$post=null;
			$sql = 'SELECT * FROM ' . $wpdb->prefix .'commentmeta WHERE meta_key="redbox_data_container" AND comment_id='.$comment_ID;
			if ($rows = $wpdb->get_results($sql)){
				$list_datas = unserialize($rows[0]->meta_value);
				$sql = 'SELECT * FROM ' . $wpdb->prefix .'commentmeta WHERE meta_key="redbox_post_id" AND comment_id='.$comment_ID;
				$rows = $wpdb->get_results($sql);
				foreach ($rows as $row){
					if ($post = get_post($row->meta_value)) break;
				}
			}
			else{
				preg_match_all('!https?://[\S]+!', $comment->comment_content, $match);
				$list_datas = $this->redbox->retriever->get_datas($match[0]);
				$this->redbox->manager->set_redbox_comment_datas($comment_ID,$list_datas);
			}
			$redbox_column = $this->redbox->blog->get_datas_mini_viewer($list_datas,array('comment'=>$comment,'post'=>$post));
			echo $redbox_column;
		}
	}
	
	// this is the info box displayed when a user edit a post
	public function redbox_show_box_edit() {
		global $post,$wpdb;
		echo '<div class="meta_box_redbox">';
		echo '<input type="hidden" name="meta_box_redbox" value="', wp_create_nonce(basename(__FILE__)), '" />';
	 
		echo '<hr /><table><tr>
			<td><ul>
			<li><strong>Consignes de publication</strong></li>
			<li>Choisir un titre accrocheur et relativement court</li>
			<li>Vérifier si le format est bien adapté ("Par défaut" ou "Vidéo")</li>
			<li>Réaliser un aperçu avant publication</li>
			<li>Relecture orthographique obligatoire</li>
			</ul>
			</td></tr></table>';
		
		$sql = 'SELECT * FROM ' . $wpdb->prefix .'postmeta 
			WHERE post_id='.$post->ID.' AND meta_key="al2fb_facebook_link_id"';
		if ($rows = $wpdb->get_results($sql)){
			echo '<div class="redbox_buttons" id="redbox_post_box_'.$rows[0]->meta_value.'" >';
				echo '<div id="redbox_resync_post_'.$rows[0]->meta_value.'" >';
				echo '<input type="button" 
					onclick="redbox_ajax_do(\'redbox_resync_post\',\''.$rows[0]->meta_value.'\',true,true)" 
					class="redbox_button redbox_button-warning" value="'.RESYNC.'"
					title="'.REDBOX_RESYNCING_POST.'"/>';
				echo '</div>';
			echo '</div>';
		}
		echo '</div>';
	}
	
	// redirect redbox-view to the comment list for RedBox blog page
	public function redbox_view(){
		global $wpdb,$post_id;
		if( current_user_can( 'read' ) && (($_GET['post_type'] == 'redbox-view') || ($post_type == 'redbox-view') || ($_GET['page'] == "redbox-view"))) {
			$options = get_option('redbox_options');
			$target = "http://".$_SERVER["HTTP_HOST"]."/wp-admin/edit-comments.php?p=".$options['redbox_page_id'];
			//$post_id=$options['redbox_page_id'];
			//$_GET['p']=$options['redbox_page_id'];
			//include (ABSPATH . "wp-admin/edit-comments.php");
			
			echo "<script>window.location=\"".$target."\";</script>";
			die;
		}
	}
}



?>
