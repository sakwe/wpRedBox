<?php


/*

THIS IS THE PRE-DEVELOPMENT FILE FOR THIS PLUGIN 

IT WILL DESAPEAR WHEN FIRST OPERATIONAL VERSION WILL BE OK


*/

global $importInfo;
global $posts_id;
$posts_id=array();



function do_redbox_action(){
	if (isset($_GET["url_to_import"]) && trim($_GET["url_to_import"])!="") $_GET["action"]="import_url";
			
	switch ($_GET["action"]){
		case "check":
			$importInfo = update_fb_posts_table();
			break;
	
		case "check_forced":
			$importInfo = update_fb_posts_table(strtotime(stripslashes("2013-07-05")));
			break;

		case "import":
			$posts_id[] = import_fb_posts();
			$importInfo = display_posts($posts_id);
			break;

		case "import_forced":
			$posts_id = import_fb_posts(true);
			break;

		case "import_for":
			$posts_id[] = import_fb_post($_GET["post_id_to_import"]);
			$importInfo = display_posts($posts_id);
			break;

		case "import_url":
			$posts_id[] = auto_import_url($_GET["url_to_import"]);
			$target = "http://".$_SERVER["HTTP_HOST"]."/wp-admin/post.php?post=".$posts_id[0]."&action=edit";
			header("Location: ".$target);
			$importInfo = display_posts($posts_id);
			break;

		case "fix_date":
			global $wpdb;
			if ($rows = $wpdb->get_results('SELECT * FROM ' . $wpdb->prefix .'redbox_fb ORDER BY date DESC')){
				foreach($rows as $row){
					$args = array('meta_key' => 'al2fb_facebook_link_id', 'meta_value' => $row->id_fb);
					$exist_post = get_posts( $args );
					foreach($exist_post as $ep){
						$the_post = array(
						'ID' => $ep->ID,
						'post_date' => $row->date,
						'post_modified' => $row->date
						);
						//wp_update_post($the_post);
						$wpdb->get_results('UPDATE wp_posts SET post_date="'.$row->date.'",post_modified="'.$row->date.'" WHERE ID='.$ep->ID)[0];
					}
				}
			}
			break;
	}
}


class wp_fb_import{

	function __construct(){
	
		add_action('admin_head', array(__CLASS__, "admin_header"));
		add_action('admin_enqueue_scripts', array(__CLASS__, "enqueue_admin_scripts"));
		add_action('admin_menu', array(__CLASS__, "setup_menus"));
		add_action('admin_init', array(__CLASS__, "plugin_init"));

		add_action('wp_enqueue_scripts', array(__CLASS__, "enqueue_admin_scripts"));
		add_action( 'post_submitbox_misc_actions', array( &$this, 'show_box_redbox' ) );
		add_action( 'admin_bar_menu', array( $this, 'admin_bar_redbox' ), 4 );
		
		
		if (isset($_GET['p'])){
			add_filter('manage_edit-comments_columns', 'redbox_columns_head');  
			add_filter('manage_comments_custom_column', 'redbox_columns_content', 10, 2);
		}
		
		function redbox_columns_head($defaults){
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
	}
	
	
	function plugin_init() {
		global $importInfo;
		global $posts_id;
		
		do_redbox_action();
				
				
		if( current_user_can( 'manage_options' ) ){
			register_setting( 'redbox_options', 'redbox_options', array(__CLASS__, "validate_fields"));
			
			add_settings_section('redbox_wordpress_options', '<hr />Configuration de RedBox', array(__CLASS__, "redbox_wordpress_options"), 'redbox');
			
			add_settings_section('redbox_profile_option', '<hr />Configuration spécifique à Facebook', array(__CLASS__, "redbox_facebook_options"), 'redbox');
			
			add_settings_section('redbox_import_status', '<hr />Import à partir de votre page Facebook', array(__CLASS__, "redbox_import_buttons"), 'redbox');
			add_settings_field('redbox_import_selections_field', 'Résultat d\'importation', array(__CLASS__, "redbox_import_status"), 'redbox', 'redbox_import_status');	
		
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

	function setup_menus() {	
		//add_options_page('RedBox', 'RedBox', 'manage_options', 'redbox', array(__CLASS__, "plugin_options"));
		if( current_user_can( 'manage_options' ) )
			add_options_page('RedBox', 'RedBox', 'manage_options', 'redbox', array(__CLASS__, "plugin_options"));
		if( current_user_can( 'edit_posts' ) )
			add_menu_page('RedBox', 'RedBox', 'edit_posts', 'redbox-view', array(__CLASS__, "redbox_view"),plugin_dir_url( __FILE__ ).'/img/redbox-ico.png');
	}
	
	function admin_bar_redbox($wp_admin_bar){
		if( ! current_user_can( 'edit_posts' ) )
			return;
		
		$form = self::get_redbox_form( array(
			'form_id'      => 'adminbarredbox',
			'redbox_id'    => 'adminbar-redbox',
			'redbox_class' => 'adminbar-input',
			'submit_class' => 'adminbar-button',
		) );

		$form .= "<style>
				#adminbar-redbox::-webkit-input-placeholder,
				#adminbar-redbox:-moz-placeholder,
				#adminbar-redbox::-moz-placeholder,
				#adminbar-redbox:-ms-input-placeholder {
					text-shadow: none;
				}
			</style>";

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
	static function get_redbox_form( $args = array() ) {
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

		<form title="Importer à partir d'une url" action="<?php echo esc_url( admin_url( 'admin.php' ) ); ?>" method="get" class="<?php echo $form_class; ?>" id="<?php echo $form_id; ?>">
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
	

	function validate_fields() {
				
		return array(
			"facebook_id" => $_POST['redbox_profile_field'],
			"facebook_app_id" => $_POST['facebook_appid_field'],
			"facebook_app_secret" => $_POST['facebook_appsecret_field'],
			"facebook_tags_for_posts" => $_POST['facebook_tags_field'],
			"redbox_page_name" => $_POST['redbox_page_name']
		);
	}

	function admin_header() {
		global $post_type;
		echo '<style>';
		if (($_GET['post_type'] == 'redbox') || ($post_type == 'redbox') || ($_GET['page'] == "redbox") ) :
			echo '#icon-edit { background:transparent url('.WP_PLUGIN_URL.'/redbox/img/ico-menu.png) no-repeat; }';
		endif;
		echo '</style>';
	}

	function enqueue_admin_scripts() {
		wp_enqueue_script( 'jquery', 'http://code.jquery.com/jquery-1.9.1.min.js' );
		wp_enqueue_script( 'wp_redbox_importer_admin', WP_PLUGIN_URL.'/redbox/js/importer.jquery.js' );
		if (isset($_GET['p'])){
			wp_enqueue_script( 'wp_redbox_custom_comments', WP_PLUGIN_URL.'/redbox/js/custom-comments.js' );
		}
		wp_register_style( 'redbox-style', WP_PLUGIN_URL."/redbox/css/redbox.css");
		wp_enqueue_style( 'redbox-style' );
	}

	function plugin_options() {
		?>
			<div class="wrap">
				<div id="icon-edit" class="icon32 icon32-posts-facebook_images">
					<br>
				</div>
				<h2>RedBox</h2>
				<form action="options.php" method="post" id="redbox_config_form">
					<div id="redbox_info_config">
					<p><b>Configuration générale de votre RedBox.</b></p>
					<p>RedBox permet essentiellement d'importer du contenu dans votre Blog. Il permet aux adminitrateurs, éditeurs, auteurs et contributeurs de créer du contenu très facilement à partir d'une URL fournie. Le contenu approprié sera enlevé des métadonnées détectées (titre, description, image et vidéo) pour créer la base d'un post sur votre blog.</p>
					<p>RedBox peut également importer directement les publications qui ne figurent pas dans votre blog à partir de votre page Facebook. </p>
					<p>Enfin, RedBox permet aux abonnés à votre blog de vous proposer simplement des url à publier. Un outil d'administration vous permet de valider ou non un lien proposé et de l'importer dans votre blog.</p>
					</div>
					<p><input name="Submit" type="submit" class="facebookGallerySubmit" value="<?php esc_attr_e('Save Changes'); ?>" /></p>
					<?php settings_fields('redbox_options'); ?>
					<?php do_settings_sections('redbox'); ?>
					<p><input name="Submit" type="submit" class="facebookGallerySubmit" value="<?php esc_attr_e('Save Changes'); ?>" /></p>
				</form>
			</div>
		<?php
	}
	
	function redbox_wordpress_options() {
		echo "<div id=\"redbox_wp_config\"><p>Configurez ici la manière dont RedBox va s'intégrer dans votre blog.</p></div>";
		$options = get_option('redbox_options');
		echo '<ul id="redbox_wordpress_options">';
		echo '<li><span>Page RedBox de votre blog : </span><input type="text" name="redbox_page_name" id="profileIDBox" value="'.$options['redbox_page_name'].'" /></li>';
		echo '<li><span>Auto tags sur les mots suivants: </span><textarea name="facebook_tags_field" id="profileTagsBox">'.$options['facebook_tags_for_posts'].'</textarea></li>';
		echo '</ul>';
		
	}

	function redbox_import_status() {
		global $wpdb;		
		global $importInfo,$posts_id;
		
		if ($rows = $wpdb->get_results('SELECT * FROM ' . $wpdb->prefix .'redbox_fb')){
			$return.= count($rows)." posts recensés depuis la page";
		}
		$return.="<br />";
		$wp_posts_ids = get_wp_posts_array();
		$return.= count($wp_posts_ids)." posts facebook dans worpress";
		$return.="<br />";
		echo $return;
		if ($importInfo!="") echo "<br />".$importInfo."<br /><br />";
		display_posts($posts_id);
	}

	function redbox_import_buttons() {		
		
		echo '<ul id="redbox_fb_sync_buttons">
		<li><a href="'.site_url().'/wp-admin/options-general.php?page=redbox&action=check" class="button" >Vérifier les posts sur la page</a>
		</li>
		<li><a href="'.site_url().'/wp-admin/options-general.php?page=redbox&action=check_forced" class="button" >Forcer la liste à se raffraichir</a>
		</li>
		<li><a href="'.site_url().'/wp-admin/options-general.php?page=redbox&action=import" class="button" >Importer les posts manquants</a>
		</li>
		<li><a href="'.site_url().'/wp-admin/options-general.php?page=redbox&action=import_forced" class="button" >Forcer la mise à jour des posts</a>
		</li>
		</ul>';
		echo "<div id=\"redbox_info_fb_import\">
		<p>RedBox doit d'abord récupérer la liste des posts de votre page. Lors de la première exécution, cette étape prend relativement du temps, en fonction du nombre de posts existants sur votre page</p>
		<p>L'option permettant de \"Forcer la liste à se raffraichir\" reparcourra complètement votre timeline facebook à la recherche de posts manquants.</p>
		<p>Une fois la liste établie, \"Vérifier les posts sur la page\" s'arrêtera de lister les posts au dernier posts importé précédement (ne parcourt par toute la timeline)</p>
		<p>\"Importer les posts manquants\" va importer complètement les post listés et créer des publications sur votre blog à partir des informations récupérées (si \"Post to facebook\" est activité, les commentaires seront également importés). Les posts créés dans votre blog seront datés aux heures de publication trouvées sur votre timeline</p>
		<p>\"Forcer la mise à jour des posts\" sera très long en exécution car il parcourra et réimportera l'entièreté de vos posts sur votre timeline</p>
		</div>";
		return true;
	}

	function redbox_facebook_options() {
		echo "<div id=\"redbox_info_fb_config\"><p>En complément aux plugins qui peuvent poster de votre blog vers votre page Facebook, RedBox permet d'importer les publications de votre page Facebook qui ne sont pas dans votre blog.</p><p>RedBox est compatible avec les Plugin \"Post to Facebook (al2fb)\" et \"Pulicize (compris dans le JetPack)\"</p><p>RedBox permet également d'importer à la demande toute publication publique de Facebook.</p></div>";
		$options = get_option('redbox_options');
		echo '<ul id="redbox_facebook_options">';
		echo '<li><span>Id Facebook de votre page : </span><input type="text" name="redbox_profile_field" id="profileIDBox" value="'.$options['facebook_id'].'" /></li>';
		echo '<li><span>App Id : </span><input type="text" name="facebook_appid_field" id="profileAppBox" value="'.$options['facebook_app_id'].'" /></li>';
		echo '<li><span>App Secret : </span><input type="text" name="facebook_appsecret_field" id="profileSecretBox" value="'.$options['facebook_app_secret'].'" /></li>';
		echo '</ul>';
	
		return true;
	}

}

function get_wp_posts_array(){
	global $wpdb;
	$sql = 'SELECT posts.ID FROM ' . $wpdb->prefix .'redbox_fb rb, ' . $wpdb->prefix .'posts posts, ' . $wpdb->prefix .'postmeta meta 
					WHERE 
					posts.ID = meta.post_id AND 
					meta.meta_key = "al2fb_facebook_link_id" AND 
					meta.meta_value = rb.id_fb';
	$posts=$wpdb->get_results($sql);
	return $posts;
}

function update_fb_posts_table($to_date=null){
	global $wpdb;
	
	$options = get_option('redbox_options');
	$authToken = fetchUrl("https://graph.facebook.com/oauth/access_token?grant_type=client_credentials&client_id={$options['facebook_app_id']}&client_secret={$options['facebook_app_secret']}");

	if (!$to_date && $row=$wpdb->get_results('SELECT MAX(date) as date FROM ' . $wpdb->prefix .'redbox_fb')){
		$to_date = strtotime(stripslashes($row[0]->date));
	}

	$from_date = time();
	$url = "https://graph.facebook.com/{$options['facebook_id']}/posts?{$authToken}&fields=created_time&fields=created_time&limit=25&until=$from_date";
	$feedarray->paging->next = $url;
	while($feedarray->paging->next){
		$url = $feedarray->paging->next;
		$json_object = fetchUrl($url);
		$feedarray = json_decode($json_object);
		foreach ( $feedarray->data as $feed_data ){
			if (strtotime(stripslashes($feed_data->created_time)) < $to_date){
				$feedarray->paging->next=null;
				break;
			}
			if (!$wpdb->get_results('SELECT id FROM ' . $wpdb->prefix .'redbox_fb WHERE id_fb="'.$feed_data->id.'"')){
				$wpdb->insert($wpdb->prefix .'redbox_fb',array('id_fb' => $feed_data->id , 'date' => $feed_data->created_time));
			}
		}
	}	

}


function display_posts($posts_id){
	echo'<ul class="mt-timeline mt-blog-timeline">';
	foreach($posts_id as $id) {		
		query_posts('ID='.$posts_id);
		if(have_posts()){
			echo $id."<br />";	
			the_post();
			echo'<li>';
			echo get_template_part('includes/meta-timeline' );
			echo'<div class="mt-timeline-content">';
			get_template_part( 'includes/format-timeline/' . get_post_format()  );
			echo'</div></li>';
		}
	}
	echo"</ul>";
}

function import_fb_posts($force=false){
	global $wpdb;
	$posts_id = array();
	if ($rows = $wpdb->get_results('SELECT * FROM ' . $wpdb->prefix .'redbox_fb ORDER BY date DESC')){
		foreach($rows as $row){
			if ($force){
				$posts_id[]=import_fb_post($row->id_fb);
			}
			else{
				$args = array('meta_key' => 'al2fb_facebook_link_id', 'meta_value' => $row->id_fb);
				$exist_post = get_posts( $args );
				if (count($exist_post) == 0){
					$posts_id[]=import_fb_post($row->id_fb);
				}
			}
		}
	}
	return $posts_id;
}

function import_fb_post($id_fb){
	global $wpdb;
	$options = get_option('redbox_options');
	$authToken = fetchUrl("https://graph.facebook.com/oauth/access_token?grant_type=client_credentials&client_id={$options['facebook_app_id']}&client_secret={$options['facebook_app_secret']}");
	
	$json_object = fetchUrl("https://graph.facebook.com/".$id_fb."?{$authToken}");
	$feed_data = json_decode($json_object);

	$type = $feed_data->type;
	$feed_data->type = "Articles";
	//echo $id_fb . " : " . $feed_data->id . "...<br />";
	if ($feed_data->message !=""){
		$tags_for_post = array();
		if ($type=='video'){
			$feed_data->type = "Vidéos";
			$datas["infoVideo"] = getVideoInfo($feed_data->link);				
			if ($datas["infoVideo"]['duration'] > 900){
				$feed_data->type = "Documentaires";
			}
			else{
				$feed_data->type = "Courtes vidéos";
			}
			$checkdate = getdate(strtotime(stripslashes($feed_data->created_time)));
			if ($checkdate['hours'] >= 21 && $checkdate['hours'] <= 23){
				$feed_data->type = "Clips musicaux";
			} 
			$feed_data->caption = $datas["infoVideo"]['type'];
		}	
		if ($type=='photo'){
			$feed_data->type = "Photos";
		}	
		
		if ($feed_data->name==""){
			preg_match('/^(?>\S+\s*){1,7}/', $feed_data->message, $match);
			$feed_data->name= $match[0]."...";
		}
		
		$json_object = fetchUrl("https://graph.facebook.com/".$feed_data->object_id."/?{$authToken}");
		$img_data = json_decode($json_object);
		
		$tag=null;
		if($feed_data->caption!=""){
			$slug = suppr_specialchar(suppr_accents($feed_data->caption));
			if (!($tag = get_term_by("name", $feed_data->caption, "post_tag", ARRAY_A))) {
				$tag = wp_insert_term($feed_data->caption, "post_tag", array("description" => $feed_data->caption, "slug" => $slug));
			}
		}
		$slug = suppr_specialchar(suppr_accents($feed_data->type));
		$cat=null;
		if (!($cat = get_term_by("name", $feed_data->type, "category", ARRAY_A))) {
			$cat = wp_insert_term($feed_data->type, "category", array("description" => $feed_data->type, "slug" => $slug));
		}
		$ts = $feed_data->created_time;
		$dt = new DateTime($ts);
		$created = $dt->format('Y-m-d H:i:s');

		// construct the main message content for the post
		$message_to_check_tags = $feed_data->message;
		$feed_data->message.="<br /><br /><br /><br /><!--more-->";
		if ($feed_data->description!=""){
			$feed_data->message.= "<br /><br /><blockquote>".$feed_data->description."</blockquote>";
			$message_to_check_tags.= " " . $feed_data->description;
		}
		
		if (strpos($feed_data->message," ".$feed_data->link) == false) {
			$feed_data->message = str_replace($feed_data->link,"",$feed_data->message);
			
		}
		$feed_data->message = processString($feed_data->message);
		if ($type=='link'){			
			if ($feed_data->caption!=""){
				$feed_data->message.= "<br /><br />Article complet sur <a href=\"".$feed_data->link."\" target=\"_blank\">".$feed_data->caption."</a>";
			}
			else{
				$feed_data->message.= "<br /><br />Article complet <a href=\"".$feed_data->link."\" target=\"_blank\">".$feed_data->link."</a>";
			}
		}
		elseif ($type=='video'){
			$feed_data->message.= "<br /><br />Voir la vidéo sur <a href=\"".$feed_data->link."\" target=\"_blank\">".ucfirst($datas["infoVideo"]['type'])."</a>";		
		}
		$feed_data->message.= "<br />Voir la publication facebook de <a href=\"https://www.facebook.com/".$feed_data->id."\" target=\"_blank\">".$feed_data->from->name."</a>";		

		// set main datas for the post in draft mode before (will publish it after meta datas settings)
		$slug = suppr_specialchar(suppr_accents($feed_data->name));
		$the_post = array(
			'comment_status' => 'open',
			'ping_status' => 'open',
			'post_name' => $slug,
			'post_status' => 'draft',
			'post_title' => $feed_data->name,
			'post_type' => 'post',
			'post_content' => $feed_data->message,
			'post_author' => 2,
			'post_date' => $created
		);

		// add or modify the post
		$args = array('meta_key' => 'al2fb_facebook_link_id', 'meta_value' => $feed_data->id);
		$exist_post = get_posts( $args );
		if (count($exist_post) > 0){
			$the_post['ID']=$exist_post[0]->ID;
			wp_update_post($the_post);
			$postID=$the_post['ID'];
			$args = array( 'post_type' => 'attachment', 'post_parent' => $postID ); 
			$attachments = get_posts($args);
			foreach ( $attachments as $attachment ) {
				wp_delete_attachment($attachment->ID, true);
			}
		}
		else{
			$postID = wp_insert_post($the_post);
		}
		

		// catergory for the post
		if (is_array($cat)){
			$term_reference = ($cat["slug"] != "") ? $cat["slug"] : $cat["term_id"];			
			wp_set_object_terms($postID, $term_reference, "category");
		}

		// detect and add tags for the post
		if (is_array($tag)){
			$term_reference = ($tag["slug"] != "") ? $tag["slug"] : $tag["term_id"];
			$tags_for_post[]= $term_reference;
		}
		$tags_for_post = getTags($postID, $message_to_check_tags, $tags_for_post);
		wp_set_object_terms($postID, $tags_for_post, "post_tag");

		// attach media image to the post
		$video_url_from_site = "";
		if ($img_data->source!="" && $type=='photo'){
			download_image($img_data->source, $postID,$message_to_check_tags);
		}
		else{
			libxml_use_internal_errors(true);
			$doc = new DomDocument();
			$doc->loadHTML(fetchUrl($feed_data->link));
			$xpath = new DOMXPath($doc);
			
			// look for image from the website meta data
			$query = '//*/meta[starts-with(@property, \'og:image\')]';
			$metas = $xpath->query($query);
			foreach ($metas as $meta) {
				if ($meta->getAttribute('property') == "og:image"){
					$feed_data->picture = $meta->getAttribute('content');
					break;
				}
			}	
			
			// check if the only picture we have is from facebook url and get the original
			if (strpos($feed_data->picture,"external.ak.fbcdn.net/safe_image") !== false) {
				$feed_data->picture = urldecode($feed_data->picture);
				$urls = explode("http",$feed_data->picture);
				if ($urls[2]!=""){
					$feed_data->picture = urldecode(trim("http".$urls[2]));
				}
			}

			// finally download the picture we found
			download_image($feed_data->picture, $postID,$message_to_check_tags);
			//$return.=$feed_data->picture;
			// check for embed link for a video in the meta data
			$query = '//*/meta[starts-with(@property, \'og:video\')]';
			$metas = $xpath->query($query);
			foreach ($metas as $meta) {
				if ($meta->getAttribute('property') == "og:video"){
					$video_url_from_site = $meta->getAttribute('content');
					break;
				}
			}
		}

		// set meta dats for the post
		update_post_meta($postID, "redbox_import_link", $feed_data->link);
		update_post_meta($postID, "redbox_sync_fb_id", $feed_data->id);
		update_post_meta($postID, "mt_post_lightbox", "yes");
		update_post_meta($postID, "mt_author_bio_box", "yes");
		update_post_meta($postID, "mt_modify_default_pagetitle", "no");
		update_post_meta($postID, "mt_pagetitle_background_image_position", "top center");
		update_post_meta($postID, "mt_pagetitle_background_image_repeat", "repeat");
		update_post_meta($postID, "mt_pagetitle_background_image_attachment", "scroll");
		update_post_meta($postID, "otw_grid_manager_content", "[]");
		update_post_meta($postID, "al2fb_facebook_link_id", $feed_data->id);
		update_post_meta($postID, "al2fb_facebook_exclude", "1");
		update_post_meta($postID, "al2fb_facebook_excerpt", $feed_data->description);
		update_post_meta($postID, "al2fb_facebook_text", $message_to_check_tags);
		update_post_meta($postID, "al2fb_facebook_link_picture", "");
		
		if ($type=='video'){
			if (trim($video_url_from_site) != ""){
				$tmp_url = explode("?",$video_url_from_site);
				$video_url_from_site = $tmp_url[0];
				$video_embed = '&lt;iframe src=&quot;'.$video_url_from_site.'&quot; frameborder=&quot;0&quot; allowfullscreen=&quot;&quot;&gt;&lt;/iframe&gt;';
			}
			else{
				$video_embed = $datas["infoVideo"]['embed'];
			}
			$tmp_url = explode("?",$video_embed);
			$video_embed = $tmp_url[0];
			
			if (trim($video_embed)=="") $video_embed = $feed_data->source;
			
			if ($datas["infoVideo"]['shortcode']!="")
				update_post_meta($postID, "mt_post_embed_code", $datas["infoVideo"]['shortcode']);
	
			else
				update_post_meta($postID, "mt_post_embed_code", $video_embed);
				
			wp_set_object_terms($postID, "post-format-video", "post_format");
			update_post_meta($postID, "al2fb_facebook_video",  $feed_data->source);
			update_post_meta($postID, "al2fb_facebook_exclude_video", "0");
		}
		// finally pubish the post
		$the_post = array('ID' => $postID , 'post_status' => 'publish','post_modified' => $created,'post_date' => $created);
		wp_update_post($the_post);
		// unsure the date et not automated by wp
		$wpdb->get_results('UPDATE wp_posts SET post_date="'.$created.'",post_modified="'.$created.'",post_status="publish" WHERE ID='.$postID);
		//update_post_meta($postID, "al2fb_facebook_exclude", "0");
	}
	return $postID;
}


function auto_import_url($url,$postID=null,$force=false,$datas=null){
	
	if (!$datas){
		$datas["type_url"] = "photo";
		$datas["type_human"] = "Photos";
		$datas["title_url"] = "";
		$datas["picture_url"] = $url;
		$datas["description_url"] = "";
		$datas["video_url"] = "";
	}
	//first, check if it is only an image
	preg_match('/[^\?]+\.(jpg|JPG|jpe|JPE|jpeg|JPEG|gif|GIF|png|PNG)/', $url, $matches);
	$datas["picture_url"] = basename($matches[0]);
	
	if ($datas["picture_url"]==""){
		$datas["type_url"] = "article";
		$datas["type_human"] = "Articles";
		// else, get the HTML response from the url
		libxml_use_internal_errors(true);
		$doc = new DomDocument();
		$doc->loadHTML(utf8_decode(fetchUrl($url)));
		$xpath = new DOMXPath($doc);
	
		// look for title from the website meta data
		$datas["picture_url"] = $url;
		$query = '//*/meta[starts-with(@property, \'og:title\')]';
		$metas = $xpath->query($query);
		foreach ($metas as $meta) {
			if ($meta->getAttribute('property') == "og:title"){
				$datas["title_url"] = $meta->getAttribute('content');
				break;
			}
		}	
	
		// look for image from the website meta data
		$datas["picture_url"] = $url;
		$query = '//*/meta[starts-with(@property, \'og:image\')]';
		$metas = $xpath->query($query);
		foreach ($metas as $meta) {
			if ($meta->getAttribute('property') == "og:image"){
				$datas["picture_url"] = $meta->getAttribute('content');
				$tmp_url = explode("?",$datas["picture_url"]);
				$datas["picture_url"] = $tmp_url[0];
				break;
			}
		}
		// check for embed link for a video in the meta data
		$query = '//*/meta[starts-with(@property, \'og:video\')]';
		$metas = $xpath->query($query);
		foreach ($metas as $meta) {
			if ($meta->getAttribute('property') == "og:video"){
				$datas["video_url"] = $meta->getAttribute('content');
				$datas["type_url"] = "video";
				$datas["type_human"] = "Vidéos";
				$datas["infoVideo"] = getVideoInfo($datas["video_url"]);				
				if ($datas["infoVideo"]['duration'] > 900){
					$datas["type_human"] = "Documentaires";
				}
				else{
					$datas["type_human"] = "Courtes vidéos";
				}
				$datas["origin_url"] = $datas["infoVideo"]['type'];
				if (trim($datas["infoVideo"]['title'])!="") $datas["title_url"] = $datas["infoVideo"]['title'];
				if (trim($datas["infoVideo"]['description'])!="") $datas["description_url"] = $datas["infoVideo"]['description'];
				break;
			}
		}
		// check for description in the meta data
		$query = '//*/meta[starts-with(@property, \'og:description\')]';
		$metas = $xpath->query($query);
		foreach ($metas as $meta) {
			if ($meta->getAttribute('property') == "og:description"){
				$datas["description_url"] = $meta->getAttribute('content');
				break;
			}
		}
	}

	// check if the only picture we have is from facebook url and get the original url
	if (strpos($datas["picture_url"],"external.ak.fbcdn.net/safe_image") !== false) {
		$datas["picture_url"] = urldecode($datas["picture_url"]);
		$urls = explode("http",$datas["picture_url"]);
		if ($urls[2]!=""){
			$datas["picture_url"] = urldecode(trim("http".$urls[2]));
		}
	}

	// get a title from description if we don't have one
	if (trim($datas["title_url"])==""&& trim($datas["description_url"])!=""){
		preg_match('/^(?>\S+\s*){1,7}/', $datas["description_url"], $match);
		$datas["title_url"]= $match[0]."...";
	}

	$parsed =  parse_url($url);
	// get the origin of the post if we don't have one
	if (trim($parsed['host'])!=""&&trim($datas["origin_url"])==""){
		
		$datas["origin_url"] = $parsed['host'];
	}

	// add a category for the origin url 
	$tag=null;
	if(trim($datas["origin_url"])!=""){
		$slug = suppr_specialchar(suppr_accents($datas["origin_url"]));
		if (!($tag = get_term_by("name", $datas["origin_url"], "post_tag", ARRAY_A))) {
			$tag = wp_insert_term($datas["origin_url"], "post_tag", array("description" => $datas["origin_url"], "slug" => $slug));
		}
	}
	
	// add a category for the type
	$slug = suppr_specialchar(suppr_accents($datas["type_human"]));
	$cat=null;
	if (!($cat = get_term_by("name", $datas["type_human"], "category", ARRAY_A))) {
		$cat = wp_insert_term($datas["type_human"], "category", array("description" => $datas["type_human"], "slug" => $slug));
	}

	// consctruct the base text for the post
	if (trim($datas["description_url"])!=""){
		$message_to_check_tags = $datas["description_url"];
		$datas["description_url"] = "<br />VOTRE TEXTE ICI<br /><br /><br /><!--more--><br /><br /><blockquote>".$datas["description_url"]."</blockquote>";
		
		// remove url from text if we found it in the text
		if (strpos($datas["description_url"]," ".$url) == false) {
			$datas["description_url"] = str_replace($url,"",$datas["description_url"]);			
		}
		
		// transform text url to html link
		$datas["description_url"] = processString($datas["description_url"]);
		if ($datas["type_url"]=='article'){			
			if (trim($datas["origin_url"])!=""){
				$datas["description_url"].= "<br /><br />Article complet sur <a href=\"".$url."\" target=\"_blank\">".$datas["origin_url"]."</a>";
			}
			else{
				$datas["description_url"].= "<br /><br />Article complet <a href=\"".$url."\" target=\"_blank\">".$url."</a>";
			}
		}
		elseif ($datas["type_url"]=='video'){
			if (trim($datas["origin_url"])!=""){
				$datas["description_url"].= "<br /><br />Voir la vidéo sur <a href=\"".$url."\" target=\"_blank\">".$datas["origin_url"]."</a>";			}
			else{
				$datas["description_url"].= "<br /><br />Voir la vidéo sur <a href=\"".$url."\" target=\"_blank\">".ucfirst($datas["infoVideo"]['type'])."</a>";			}	
		}
	}

	// get the post and check for datas that exists (title, content,etc)	
	$this_post = get_post($postID);
	if (trim($this_post->post_title)!="")
		$datas["title_url"] = $this_post->post_title;
	$datas["description_url"] = $this_post->post_content . $datas["description_url"];

	// set main datas for the post in draft mode that we pu in draft mode
	$slug = suppr_specialchar(suppr_accents($datas["title_url"]));
	$the_post = array(
		'comment_status' => 'open',
		'ping_status' => 'open',
		'post_name' => $slug,
		'post_status' => 'draft',
		'post_title' => $datas["title_url"],
		'post_type' => 'post',
		'post_content' => $datas["description_url"],
		'post_author' => get_current_user_id()
	);
	$postID = wp_insert_post($the_post);

	// catergory for the post
	if (is_array($cat)){
		$term_reference = ($cat["slug"] != "") ? $cat["slug"] : $cat["term_id"];			
		wp_set_object_terms($postID, $term_reference, "category");
	}

	// detect and add tags for the post
	if (is_array($tag)){
		$term_reference = ($tag["slug"] != "") ? $tag["slug"] : $tag["term_id"];
		$tags_for_post[]= $term_reference;
	}
	$tags_for_post = getTags($postID, $message_to_check_tags, $tags_for_post);
	wp_set_object_terms($postID, $tags_for_post, "post_tag");

	// attach media image to the post
	if ($datas["picture_url"]!=""){
		download_image($datas["picture_url"], $postID,$message_to_check_tags);
	}

	update_post_meta($postID, "redbox_import_link", $url);
	if ($datas["type_url"]=='video'){
		// get the shortcode or embed part for the video
		if ($datas["infoVideo"]['shortcode']!="")
			update_post_meta($postID, "mt_post_embed_code", $datas["infoVideo"]['shortcode']);

		else
			update_post_meta($postID, "mt_post_embed_code", $datas["infoVideo"]['embed']);
		wp_set_object_terms($postID, "post-format-video", "post_format");
		update_post_meta($postID, "al2fb_facebook_video",  $datas["video_url"]);
		update_post_meta($postID, "al2fb_facebook_exclude_video", "1");
	}
	return $postID;
}


function getTags($postID, $message_to_check_tags,$tags_for_post){
	$message_to_check_tags = strtolower($message_to_check_tags);
	$options = get_option('redbox_options');
	$tags = explode(',',$options['facebook_tags_for_posts']);
	foreach($tags as $tag){	
		$tag = trim($tag);	
		$tag_slug = suppr_specialchar(suppr_accents($tag));
		if (!($wp_tag = get_term_by("name", $tag, "post_tag", ARRAY_A))) {
			$wp_tag = wp_insert_term($tag, "post_tag", array("description" => $tag, "slug" => $tag_slug));
		}		
		if (is_array($wp_tag)){
			if (strpos($message_to_check_tags,$tag) !== false) {
				$term_reference = ($wp_tag["slug"] != "") ? $wp_tag["slug"] : $wp_tag["term_id"];
				$tags_for_post[]=$term_reference;
			}
		}
	}
	return $tags_for_post;
}

function get_url_meta_datas($url,$datas=null){
	// we don't know what is behind the url ..
	$is_image_url = false;
	
	// If we don't receive any data, initialise ours
	if (!$datas){
		$datas["type_url"] = "url";
		$datas["type_human"] = "Lien";
		$datas["title_url"] = "";
		$datas["picture_url"] = "";
		$datas["description_url"] = "";
		$datas["video_url"] = "";
		$datas["infoVideo"] = array();
	}
	
	//first, check if a picture is in the url
	if ($datas["picture_url"]==""){
		preg_match('/[^\?]+\.(jpg|JPG|jpe|JPE|jpeg|JPEG|gif|GIF|png|PNG)/', $url, $matches);
		if ($matches) {
			$datas["picture_url"] = basename($matches[0]);
			$is_image_url = true;
		}
	}
	
	// if it's not a simple picture, let's go deeper in HTML code exploration
	if (!$is_image_url){
		// we consider it's an article from a website
		$datas["type_url"] = "article";
		$datas["type_human"] = "Articles";

		// get the HTML response from the url (HTML code and DOMdoc)
		libxml_use_internal_errors(true);
		$doc = new DomDocument();
		$html = fetchUrl($url);
		$doc->loadHTML(utf8_decode($html));
		$xpath = new DOMXPath($doc);

		// look for title from the website's OpenGraph meta data
		if (trim($datas["title_url"]) ==""){
			$query = '//*/meta[starts-with(@property, \'og:title\')]';
			$metas = $xpath->query($query);
			foreach ($metas as $meta) {
				if ($meta->getAttribute('property') == "og:title"){
					$datas["title_url"] = $meta->getAttribute('content');
					break;
				}
			}
		}

		// if we don't have title, take ifr from the title HTML TAG
		if (trim($datas["title_url"]) ==""){
			$nodes = $doc->getElementsByTagName( "title" );
			$datas["title_url"] = $nodes->item(0)->nodeValue;
		}

		// look for picture from the website's OpenGraph meta data
		$query = '//*/meta[starts-with(@property, \'og:image\')]';
		$metas = $xpath->query($query);
		foreach ($metas as $meta) {
			if ($meta->getAttribute('property') == "og:image"){
				$datas["picture_url"] = $meta->getAttribute('content');
				$tmp_url = explode("?",$datas["picture_url"]);
				$datas["picture_url"] = $tmp_url[0];
				break;
			}
		}

		// if no picture found, get the first "big" picture in html (width>400px)
		if (trim($datas["picture_url"]) ==""){
			$metas = $doc->getElementsByTagName('img');
			for ($i = 0; $i < $metas->length; $i++)	{
				$meta = $metas->item($i);
				$dimensions = null;
				$dimensions = getimagesize( $meta->getAttribute('src') );
				if ($dimensions[0] > 400){
					$datas["picture_url"] = $meta->getAttribute('src');
					break;
				}
			}
		}

		// check for embed link for a video in the meta data
		$query = '//*/meta[starts-with(@property, \'og:video\')]';
		$metas = $xpath->query($query);
		foreach ($metas as $meta) {
			if ($meta->getAttribute('property') == "og:video"){
				$datas["video_url"] = $meta->getAttribute('content');
				$datas["type_url"] = "video";
				$datas["type_human"] = "Vidéos";
				$datas["infoVideo"] = getVideoInfo($datas["video_url"]);				
				if ($datas["infoVideo"]['duration'] > 900){
					$datas["type_human"] = "Documentaires";
				}
				else{
					$datas["type_human"] = "Courtes vidéos";
				}
				$datas["origin_url"] = $datas["infoVideo"]['type'];
				if (trim($datas["infoVideo"]['title'])!="") $datas["title_url"] = $datas["infoVideo"]['title'];
				if (trim($datas["infoVideo"]['description'])!="") $datas["description_url"] = $datas["infoVideo"]['description'];
				break;
			}
		}
		// check for description in the meta data
		$query = '//*/meta[starts-with(@property, \'og:description\')]';
		$metas = $xpath->query($query);
		foreach ($metas as $meta) {
			if ($meta->getAttribute('property') == "og:description"){
				$datas["description_url"] = $meta->getAttribute('content');
				break;
			}
		}
		
		if (trim($datas["description_url"]) ==""){
		$metas = $doc->getElementsByTagName('meta');
			for ($i = 0; $i < $metas->length; $i++)	{
				$meta = $metas->item($i);
				if($meta->getAttribute('name') == 'description')
					$datas["description_url"] = $meta->getAttribute('content');			 	
			}
		}
		if (trim($datas["description_url"]) ==""){
			preg_match_all("/<p>(.*)<\/p>/",$html,$matches);
			foreach($matches as $match){
				if (str_word_count($match[0]) > 20){
					$datas["description_url"] = strip_tags($match[0]);
					break;
				}
			}
		}
	}
	return $datas;
}


function download_image($url, $post,$desc) {
	$tmp = download_url($url);
	preg_match('/[^\?]+\.(jpg|JPG|jpe|JPE|jpeg|JPEG|gif|GIF|png|PNG)/', $url, $matches);
	$file_array['name'] = basename($matches[0]);
	$file_array['tmp_name'] = $tmp;
	
	if ( is_wp_error( $tmp ) ) {
		@unlink($file_array['tmp_name']);
		$file_array['tmp_name'] = '';
	}
	
	$id = media_handle_sideload( $file_array, $post, $desc );
	
	if ( is_wp_error($id) ) {
		@unlink($file_array['tmp_name']);
		return $id;
	}
	
	set_post_thumbnail($post, $id);
	update_post_meta($post, "al2fb_facebook_image_id", $id);	
}


function fetchUrl($url){
	$ch = curl_init();
	curl_setopt($ch, CURLOPT_URL, $url);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
	curl_setopt($ch, CURLOPT_TIMEOUT, 80000);
	//You may need to add the line below
	//curl_setopt($ch,CURLOPT_SSL_VERIFYPEER,false);
	$feedData = curl_exec($ch);
	curl_close($ch); 
	return $feedData;
}



?>
