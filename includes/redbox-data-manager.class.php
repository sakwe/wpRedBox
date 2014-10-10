<?
/* The RedBoxDataImporter class
 * Author: Gregory Wojtalik
 * Package RedBox (For WordPress)
 *
 **/
 

if(!function_exists('download_url')) {
	include_once(ABSPATH . "wp-admin/includes/file.php"); 
}
if(!function_exists('media_handle_sideload')) {
	include_once(ABSPATH . "wp-admin/includes/media.php"); 
}
if(!function_exists('wp_generate_attachment_metadata')) {
	include_once(ABSPATH . "wp-admin/includes/image.php"); 
}

class RedBoxDataManager{

	public function __construct(&$redbox){
		// connect to the global redbox instance
		$this->redbox = $redbox;
		
		// this will call comment check for redbox propositions
		add_action( 'wp_insert_comment', array(&$this, 'redbox_proposition_to_comment'), '99', 2 );
		add_filter( 'pre_comment_approved' , array(&$this, 'redbox_approve_comment') , '99', 2 );
		
		// this will call post check for redbox post save/update_comment_meta
		add_action( 'save_post', array(&$this, 'redbox_save_post') );
	}


	public function redbox_proposition_to_comment($comment_id, $comment){
		global $post;
		$clones = null;
		$options = get_option('redbox_options');
		$mode = 'propose';
		if ($post->post_name == $options['redbox_page_name']) {
			preg_match_all('!https?://[\S]+!', $comment->comment_content, $match);
			$list_datas = $this->redbox->retriever->get_datas($match);
			$urls = array();
			foreach($list_datas as $datas) echo $urls[] = $datas->source;
			$clones = $this->redbox->manager->check_clones($urls,$mode);
			if (!$clones){
				$this->redbox->manager->set_redbox_comment_datas($comment_id,$list_datas);
			}
			else{
				$_SESSION['dialogs'] = $this->redbox->dispatcher->clone_dialog($clones,$mode);
			}
		} else {
			$this->redbox->xmpp->send_notification_for_comment($commentID);
		}
	}
	
	public function redbox_approve_comment(){
		global $post;
		$options = get_option('redbox_options');
		if ($post->post_name == $options['redbox_page_name']) {
			return '0';
		}
		else{
			return '1';
		}
	}
	
	public function insert_redbox_proposition($list_datas,$proposition_from_facebook=false){
		if( current_user_can( 'edit_posts' ) && !$proposition_from_facebook){
			$approved='1';
		}
		else{
			$approved='0';
		}
		global $wpdb;
		$time = current_time('mysql');
		$user = wp_get_current_user();
		$options = get_option('redbox_options');
		$sql = 'SELECT ID FROM ' . $wpdb->prefix .'posts WHERE post_name="'.$options['redbox_page_name'].'"';
		if ($rows = $wpdb->get_results($sql)){
			$post_id = $rows[0]->ID;
		}
		
		$urls = "";
		if (count($list_datas)>0) $urls = $list_datas[0]->message;
		foreach ($list_datas as $datas) $urls.= "\n".$datas->url."\n";
		
		if ($proposition_from_facebook){
			$comment_author = $list_datas[0]->author_name;
			$comment_author_email = $list_datas[0]->fb_id_author."@facebook.com";
			$comment_author_url = $list_datas[0]->url;
			$comment_agent = "AL2FB";
			$time = $list_datas[0]->created;
			$user_id = '';
		}
		else{
			$comment_author = $user->display_name;
			$comment_author_email = $user->user_email;
			$comment_author_url = $user->user_url;
			$comment_agent = "";
			$user_id = $user->ID;
		}
		
		$the_comment = array(
			'comment_post_ID' => $post_id,
			'comment_author' => $comment_author,
			'comment_author_email' => $comment_author_email,
			'comment_author_url' => $comment_author_url,
			'comment_content' => $urls,
			'comment_agent' => $comment_agent,
			'comment_type' => '',
			'comment_parent' => 0,
			'user_id' => $user_id,
			'comment_date' => $time,
			'comment_approved' => $approved,
		);
	
		$comment_id = wp_insert_comment($data);
		$datas = $this->redbox->retriever->get_proposed_import($list_datas);
		
		// add or modify the comment
		$exist_comment = array();
		if (trim($datas->fb_id!='')){
			$sql = 'SELECT comment_id AS ID FROM ' . $wpdb->prefix .'commentmeta 
				WHERE meta_key="al2fb_facebook_link_id" AND meta_value="'.trim($datas->fb_id).'"';
			$exist_comment = $wpdb->get_results($sql);
		}
		if (count($exist_comment) > 0){
			$the_comment['comment_ID']=$exist_comment[0]->ID;
			$commentID=$the_comment['comment_ID'];
			wp_update_comment($the_comment);
		}
		else{
			$commentID = wp_insert_comment($the_comment);
		}
		if ($datas->fb_id != ''){
			update_comment_meta($commentID, "al2fb_facebook_link_id", trim($datas->fb_id));
		}
		
		$this->set_redbox_comment_datas($commentID,$list_datas,$proposition_from_facebook);
		
		// Send the notification via XMPP 
		$this->redbox->xmpp->send_notification_for_proposition($commentID);
		
		return $commentID;
	}
	
	public function set_redbox_comment_datas($comment_id,$list_datas,$proposition_from_facebook=null){
		global $wpdb;
		$sql = 'SELECT * FROM ' . $wpdb->prefix .'commentmeta 
			WHERE comment_id='.$comment_id.' AND meta_key="redbox_data_container"';
		if ($rows = $wpdb->get_results($sql)){	
			$sql = 'UPDATE ' . $wpdb->prefix .'commentmeta 
				SET meta_value="'.addslashes(serialize($list_datas)).'" 
				WHERE comment_id='.$comment_id.' AND meta_key="redbox_data_container"';
			$wpdb->get_results($sql);
		}
		else{
			$sql = 'INSERT INTO ' . $wpdb->prefix .'commentmeta 
				SET comment_id='.$comment_id.', meta_key="redbox_data_container" , meta_value="'.addslashes(serialize($list_datas)).'"';
			$wpdb->get_results($sql);
		}
		foreach($list_datas as $datas){
			$sql = 'SELECT * FROM ' . $wpdb->prefix .'commentmeta WHERE 
				comment_id='.$comment_id.' AND meta_key="redbox_source" AND meta_value="'.$datas->source.'"';
			if (!$rows = $wpdb->get_results($sql)){
				if (trim($datas->source)!=''){
					$sql = 'INSERT INTO ' . $wpdb->prefix .'commentmeta SET comment_id='.$comment_id.', meta_key="redbox_source" , meta_value="'.$datas->source.'"';
					$wpdb->get_results($sql);
				}
			}
		}
		if ($proposition_from_facebook) update_comment_meta($comment_id, "redbox_proposition_fb_id", $proposition_from_facebook);
	}
	
	public function insert_redbox_post($list_datas){
		
		global $wpdb;
		$options = get_option('redbox_options');
		
		$postID=null;
		$datas = $this->redbox->retriever->get_proposed_import($list_datas);
		
		
		//check if we have a fallback to set
		foreach($list_datas as $d){
			if ($d->fallBack) {
				$this->redbox->fallBack = true;
				break;
			}
		}
		
		if ($datas && ($datas->title!='' || $datas->type == 'picture')){
		
			if ($datas->type == 'gallery'){
				$post_type='portfolio';
				$taxonomy = 'portfolio-categories';
			}
			else{
				$post_type='post';
				$taxonomy = 'category';
			}
			if (!(stripos($datas->description,"<!--more-->") > 0)){
				$datas->description="VOTRE TEXTE ICI<br />\n\n<!--more--><br />".$datas->description;
			}
			// set main datas for the post in draft mode before (will publish it after meta datas settings)
			$slug = suppr_specialchar(suppr_accents($datas->title));
			$the_post = array(
				'comment_status' => 'open',
				'ping_status' => 'open',
				'post_name' => $slug,
				'post_status' => 'draft',
				'post_title' => $datas->title,
				'post_type' => $post_type,
				'post_content' => $datas->description,
				'post_date' => $datas->created,
				'ping_status' => 'closed'
			);

			if ($datas->fb_id != ''){
				$the_post['post_author'] = 2;
			}

			// add or modify the post
			$exist_post = array();
			if (trim($datas->fb_id!='')){
				$sql = 'SELECT post_id AS ID FROM ' . $wpdb->prefix .'postmeta 
					WHERE meta_key="al2fb_facebook_link_id" AND meta_value="'.trim($datas->fb_id).'"';
				$exist_post = $wpdb->get_results($sql);
			}
			if (count($exist_post) > 0){
				$the_post['ID']=$exist_post[0]->ID;
				wp_update_post($the_post);
				$postID=$the_post['ID'];
				$args = array( 'post_type' => 'attachment', 'post_parent' => $postID ); 
				$attachments = get_posts($args);
				foreach ( $attachments as $attachment ) {
					wp_delete_attachment($attachment->ID, true);
				}
				$args = array( 'post_type' => 'attachment', 'post_parent' => $postID ); 
				$attachments = get_posts($args);
				foreach ( $attachments as $attachment ) {
					wp_delete_attachment($attachment->ID, true);
				}
			}
			else{
				$postID = wp_insert_post($the_post);
			}
			//exit;
			// check for new tags and categories
			$cats_for_post = array();
			foreach ($list_datas as $d){
				$tag=null;
				if($d->origin!="" && $d->origin!="facebook.com"){
					$slug = suppr_specialchar(suppr_accents($d->origin));
					if (!($tag = get_term_by("name", $d->origin, "post_tag", ARRAY_A))) {
						$tag = wp_insert_term($d->origin, "post_tag", array("description" => $d->origin, "slug" => $slug));
					}
					$tags_for_post[]= $slug;
					$tags_for_post = $this->getTags($d->message, $tags_for_post);
					$tags_for_post = $this->getTags($d->description, $tags_for_post);
				}
				$slug = suppr_specialchar(suppr_accents($d->category));
				$cat=null;
				if (!($cat = get_term_by("name", $d->category, $taxonomy, ARRAY_A))) {
					$cat = wp_insert_term($d->category, $taxonomy, array("description" => $d->category, "slug" => $slug));
				}
				$cats_for_post[] = $slug;
			}
			$tags_for_post = $this->getTags($datas->message, $tags_for_post);
			$tags_for_post = $this->getTags($datas->description, $tags_for_post);

			wp_set_object_terms($postID, $tags_for_post, "post_tag");
			wp_set_object_terms($postID, $cats_for_post, $taxonomy);
			
			// get medias for the post...
			$l_photos = '';
			$l_gallery= '';
			for ($i=0;$i<=(count($datas->pictures)-1);$i++){
				if ($i==0) $thumb=true;else $thumb=false;
				$picture = $datas->pictures[$i];
				if (trim($picture->url)!=''){
					if (trim($picture->title)!='') $pict_title = $picture->title;
					else $pict_title = $datas->title;					
					$id = $this->download_image($picture->url, $postID,$pict_title,$thumb);
					if (!is_wp_error($id)){
						if ($datas->type == 'gallery' && $id) $l_photos.= $id.',';
						if ($picture->in_gallery && $id) $l_gallery.= $id.',';
					} 
				}
			}
			//exit;
			// complete the post content if we have a gallery in
			if ($l_gallery!=''){
				$gallery_content = '[gallery link="file" columns="3" type="rectangular" ids="'.$l_gallery.'"]'."\n";
				$the_post = array(
				'ID' => $postID,
				'post_content' => $datas->description ."\n".$gallery_content
				);
				wp_update_post($the_post);
			}
		
			// complete the post content with the picture gallery type (portfolio)
			if ($l_photos!=''){
				$gallery_content = '[mt_section paddingt="80px" paddingb="80px"]'."\n";
				//$gallery_content.= '[gallery link="file" type="slideshow" ids="'.$l_photos.'"]'."\n";
				$gallery_content.= $datas->description ."\n". '[/mt_section]'."\n";
				$gallery_content.= '[mt_section style="dark" paddingt="80px" paddingb="80px" bgusecolor="yes" bgcolor="333"]'."\n";
				$gallery_content.= '[gallery link="file" columns="3" type="rectangular" ids="'.$l_photos.'"]'."\n";
				$gallery_content.= '[/mt_section]';
				$the_post = array(
				'ID' => $postID,
				'post_content' => $gallery_content
				);
				wp_update_post($the_post);
				update_post_meta($postID, "mt_related_items_onoff", "yes");
				update_post_meta($postID, "mt_portfolio_page_with_sections", "yes");
			}
			
			// set meta dats for the post
			update_post_meta($postID, "mt_post_lightbox", "yes");
			update_post_meta($postID, "mt_author_bio_box", "yes");
			update_post_meta($postID, "mt_modify_default_pagetitle", "no");
			update_post_meta($postID, "mt_pagetitle_background_image_position", "top center");
			update_post_meta($postID, "mt_pagetitle_background_image_repeat", "repeat");
			update_post_meta($postID, "mt_pagetitle_background_image_attachment", "scroll");
			update_post_meta($postID, "otw_grid_manager_content", "[]");
			if ($datas->fb_id != ''){
				update_post_meta($postID, "al2fb_facebook_link_id", trim($datas->fb_id));
				update_post_meta($postID, "_wpas_done_all", "1");
			}
			update_post_meta($postID, "al2fb_facebook_exclude", "1");
			if ($datas->message != ''){
				$fb_text = $datas->message;
			}
			else{
				$fb_text = $datas->short_description;
			}
			$fb_text = br2nl($fb_text);
			$fb_text = strip_tags($fb_text);
			$fb_text.= "\n\n" . $this->redbox->configuration->fb_post_sign;
			update_post_meta($postID, "al2fb_facebook_excerpt", $fb_text);
			update_post_meta($postID, "al2fb_facebook_text", $fb_text);
			update_post_meta($postID, "_wpas_mess", $fb_text);
			
			// set special datas for video post type
			if ($datas->type=='video'){
				if ($datas->video_datas->shortcode!="")
					update_post_meta($postID, "mt_post_embed_code", $datas->video_datas->shortcode);
	
				else
					update_post_meta($postID, "mt_post_embed_code", $datas->video_datas->embed);
				
				wp_set_object_terms($postID, "post-format-video", "post_format");
				update_post_meta($postID, "al2fb_facebook_video",  $datas->source);
				update_post_meta($postID, "al2fb_facebook_exclude_video", "1");
			}
			
			foreach ($list_datas as $d){
				if ($d->type == "picture" && count($d->pictures) >= 4){
					wp_set_object_terms($postID, "post-format-gallery", "post_format");
				}
			}
			
			$this->set_redbox_post_datas($postID,$list_datas);
			// if we've got a date for the published post
			if ($datas->created != ''){
				// finally pubish the post for the given date
				$the_post = array('ID' => $postID , 'post_status' => 'publish','post_modified' => $datas->created,'post_date' => $datas->created);
				wp_update_post($the_post);
				// unsure the date is not automated by wp
				$wpdb->get_results('UPDATE ' . $wpdb->prefix .'posts SET post_date="'.$datas->created.'",post_modified="'.$datas->created.'",post_status="publish" WHERE ID='.$postID);
			}
		}
		return $postID;
	}
	
	// alway ran when wordpress save a post
	public function redbox_save_post($post_id){
		global $wpdb;
		$post = get_post($post_id);
		$sql = 'DELETE FROM ' . $wpdb->prefix .'commentmeta 
			WHERE meta_key="redbox_post_id" AND meta_value="'.$post_id.'"';
		$wpdb->get_results($sql);
		if ($post->post_status != 'trash' && $post->post_type == 'post'){
			$sql = 'SELECT * FROM ' . $wpdb->prefix .'postmeta 
			WHERE post_id='.$post_id.' AND meta_key="redbox_data_container"';
			if ($rows = $wpdb->get_results($sql)){
				$list_datas=array();
				$list_datas = unserialize($rows[0]->meta_value);
				$this->redbox->manager->set_redbox_post_datas($post_id,$list_datas);
			}
			$content = explode('<!--more-->',$post->post_content);
			$fb_text = strip_tags($content[0]);
			$fb_text = strip_tags($fb_text);
			$categories = get_the_category( $post_id );
			$category = $categories[0]->name;
			if (substr($category,-1)=="s") $category = substr($category,0,strlen($category)-1);
			$fb_text.= "\n\n". $category . " : " . get_permalink($post_id);
			$fb_text.= "\n\n" . $this->redbox->configuration->fb_post_sign;
			update_post_meta($post->ID, "al2fb_facebook_excerpt", $fb_text);
			$description = $fb_text;
			$phrases = explode("\n",$description);
			if (count($phrases)>0){
				if (str_word_count($phrases[0]) < 30) {
					$beforeMore = "";
					foreach($phrases as $phrase) {
						if (str_word_count($beforeMore) < 30) {
							$beforeMore.= $phrase."\n";
						} else {
							$afterMore.= $phrase."\n";
						}
					} 
				} else { 
					$beforeMore = $phrases[0];
				}
				$fb_text = $beforeMore;
			} 
			update_post_meta($post->ID, "al2fb_facebook_text", $fb_text);
			update_post_meta($post->ID, "_wpas_mess", $fb_text);
		}
	}
	
	
	public function set_redbox_post_datas($post_id,$list_datas){
		global $wpdb;
		// manage the redbox_data_container for the post meta data
		$sql = 'SELECT * FROM ' . $wpdb->prefix .'postmeta 
			WHERE post_id='.$post_id.' AND meta_key="redbox_data_container"';
		if ($rows = $wpdb->get_results($sql)){
			$sql = 'UPDATE ' . $wpdb->prefix .'postmeta 
				SET meta_value="'.addslashes(serialize($list_datas)).'" 
				WHERE post_id='.$post_id.' AND meta_key="redbox_data_container"';
			$wpdb->get_results($sql);
		}
		else{
			$sql = 'INSERT INTO ' . $wpdb->prefix .'postmeta 
				SET post_id='.$post_id.', meta_key="redbox_data_container" , meta_value="'.addslashes(serialize($list_datas)).'"';
			$wpdb->get_results($sql);
		}
		// get all redbox url sources for the post
		$sql = 'DELETE FROM ' . $wpdb->prefix .'postmeta 
			WHERE post_id='.$post_id.' AND meta_key="redbox_source"';
		$wpdb->get_results($sql);
		foreach($list_datas as $datas){
			if (trim($datas->source)!=''){
				$sql = 'INSERT INTO ' . $wpdb->prefix .'postmeta SET post_id='.$post_id.', meta_key="redbox_source" , meta_value="'.$datas->source.'"';
				$wpdb->get_results($sql);
			}
		}
		// check propositions sources to "connect" them to the published post
		$sql = 'DELETE FROM ' . $wpdb->prefix .'commentmeta 
			WHERE meta_key="redbox_post_id" AND meta_value="'.$post_id.'"';
		$wpdb->get_results($sql);
		$urls = array();
		foreach($list_datas as $datas) $urls[] = $datas->url;
		if ($clones = $this->redbox->manager->check_clones($urls,'link_post')){
			foreach($clones as $clone){
				if ($clone['type']=='proposition'){
					$sql = 'INSERT INTO ' . $wpdb->prefix .'commentmeta 
					SET comment_id='.$clone['id'].' , meta_key="redbox_post_id" , meta_value="'.$post_id.'"';
					$wpdb->get_results($sql);
					$sql = 'UPDATE ' . $wpdb->prefix .'comments 
					SET comment_approved="1" WHERE comment_ID='.$clone['id'];
					$wpdb->get_results($sql);
				}
			}
		}
	}

	
	public function check_clones($urls,$mode='post'){
		global $wpdb;
		$clones = array();
		// if we have a string with urls, make an array with it
		if (!is_array($urls)){
			preg_match_all('!https?://[\S]+!', $urls, $match);
			$urls=$match[0];
		}

		for ($i=0;$i<count($urls);$i++){
			$urls[$i] = $this->redbox->retriever->cleanUrl($urls[$i]);
		}

		if ($mode=='post_facebook'|| $mode=='link_post'){
			$u_tmp = $urls;
			$urls = array();
			for ($i=0;$i<count($u_tmp);$i++){
				if(stripos($u_tmp[$i],"facebook.com")==false){
					$urls[]=$u_tmp[$i];
				}
			}
		}
		// check if it is a self link, so we need to link the post
		foreach ($urls as $url){
			$sql = 'SELECT * FROM ' . $wpdb->prefix .'posts WHERE post_name="'. str_replace('/','',str_replace(home_url(),'',$url)).'"';
			$rows = $wpdb->get_results($sql);
			foreach ($rows as $row){
				$sql = 'SELECT * FROM ' . $wpdb->prefix .'postmeta WHERE 
				post_id='.$row->ID.' AND meta_key="redbox_data_container"';
				$data_container='';
				if ($rows_container = $wpdb->get_results($sql)){
					$data_container = $rows_container[0]->meta_value;
				}
				$clones[] = array('type'=>'post','id'=>$row->ID,'strict'=>true,'data_container'=>$data_container);
			}
		}
		if (count($clones)>0) return $clones;
		
		// check if we already have a post for the url
		if ($mode!='link_post'){
			foreach ($urls as $url){
				$sql = 'SELECT * FROM ' . $wpdb->prefix .'postmeta WHERE meta_key="redbox_source" AND meta_value="'.$url.'"';
				$rows = $wpdb->get_results($sql);
				foreach ($rows as $row){
					$post_id = $row->post_id;
					$strict = true;
					// we've got a clone, check if it's "strict" clone of this post
					foreach ($urls as $strict_url){
						$sql = 'SELECT * FROM ' . $wpdb->prefix .'postmeta WHERE 
							post_id='.$post_id.' AND meta_key="redbox_source" AND meta_value="'.$strict_url.'"';
						if (!$rows_strict = $wpdb->get_results($sql)){
							$strict = false;
							break;
						}
					}
					$allready = false;
					foreach ($clones as $a_clone) {
						if ($a_clone['type']=='post' && $a_clone['id']== $post_id){
							$allready = true;
							break;
						}
					}
					if (!$allready) {
						$sql = 'SELECT * FROM ' . $wpdb->prefix .'postmeta WHERE 
							post_id='.$post_id.' AND meta_key="redbox_data_container"';
						$data_container='';
						if ($rows_container = $wpdb->get_results($sql)){
							$data_container = $rows_container[0]->meta_value;
						}
						if ($mode!='post_facebook'){
							$clones[] = array('type'=>'post','id'=>$post_id,'strict'=>$strict,'data_container'=>$data_container);
						}
						elseif($strict){
							$sql = 'SELECT * FROM ' . $wpdb->prefix .'postmeta WHERE post_id='.$post_id.' AND 
							(meta_key="redbox_linked_with_blog" OR meta_key="redbox_posted_from_blog" OR meta_key="al2fb_facebook_link_id")';
							if (!$wpdb->get_results($sql)){
								$clones[] = array('type'=>'post','id'=>$post_id,'strict'=>$strict,'data_container'=>$data_container);
							}
						}
					}
				}
			}
		}
		//die;
		if (count($clones)>0) return $clones;
		if ($mode!='post' && $mode!='post_facebook'){
			foreach ($urls as $url){
				// check if we already have a proposition for the url
				$sql = 'SELECT * FROM ' . $wpdb->prefix .'commentmeta WHERE meta_key="redbox_source" AND meta_value="'.$url.'"';
				if ($rows = $wpdb->get_results($sql)){
					$comment_id = $rows[0]->comment_id;
					$strict = true;
					// we've got a clone, check if it's "strict" clone of this comment
					foreach ($urls as $strict_url){
						$sql = 'SELECT * FROM ' . $wpdb->prefix .'commentmeta WHERE 
							comment_id='.$comment_id.' AND meta_key="redbox_source" AND meta_value="'.$strict_url.'"';
						if (!$rows_strict = $wpdb->get_results($sql)){
							$strict = false;
							break;
						}
					}
					$allready = false;
					foreach ($clones as $a_clone) {
						if ($a_clone['type']=='proposition' && $a_clone['id']== $comment_id){
							$allready = true;
							break;
						}
					}
					if (!$allready) {
						$sql = 'SELECT * FROM ' . $wpdb->prefix .'commentmeta WHERE 
							comment_id='.$comment_id.' AND meta_key="redbox_data_container"';
						$data_container='';
						if ($rows = $wpdb->get_results($sql)){
							$data_container = $rows[0]->meta_value;
						}
						$clones[] = array('type'=>'proposition','id'=>$comment_id,'strict'=>$strict,'data_container'=>$data_container);
					}
				}
			}
			if (count($clones)>0) return $clones;
		}
		return null;
	}

	public function trash_post_by_id_fb($id_fb){
		global $wpdb;
		$wpdb->get_results('UPDATE ' . $wpdb->prefix .'redbox_fb SET status="trash" WHERE id_fb="'.$id_fb.'"');
		$sql = 'SELECT * FROM ' . $wpdb->prefix .'postmeta WHERE meta_key="al2fb_facebook_link_id" AND meta_value="'.$id_fb.'"';
		$rows = $wpdb->get_results($sql);
		foreach ($rows as $row){
			$wpdb->get_results('UPDATE ' . $wpdb->prefix .'posts SET post_status="trash" WHERE ID='.$row->post_id);
			return $row->post_id;
		}
	}

	private function getTags($message_to_check_tags,$tags_for_post){
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
				if (strpos($message_to_check_tags,' '.$tag) !== false || strpos($message_to_check_tags,"\n".$tag) !== false) {
					$term_reference = ($wp_tag["slug"] != "") ? $wp_tag["slug"] : $wp_tag["term_id"];
					$tags_for_post[]=$term_reference;
				}
			}
		}
		preg_match_all('/#(\w+)/',$message_to_check_tags,$matches);
		foreach($matches[0] as $match){
			$match = str_replace('#','',$match);
			if (!($wp_tag = get_term_by("name", $match, "post_tag", ARRAY_A))) {
				$wp_tag = wp_insert_term($match, "post_tag", array("description" => $match, "slug" => $match));
			}
			$tags_for_post[]=$match;
		}
		return $tags_for_post;
	}


	public function download_image($url, $post_id,$desc,$thumb=false) {
		$file_array['name'] = urldecode(basename($url));

		// TODO : SUPPORT FALLBACK DL
		// seems to be OK but need more tests
		//$this->redbox->fallBack = false;
		/////////////////////////////
		
		if (!$this->redbox->fallBack){
			if (REDBOX_DEBUG==true) echo "<br>Try download " .$url. "... ";
			$tmp = download_url($url);
			$file_array['tmp_name'] = $tmp;
		}
		if ( is_wp_error( $tmp ) && $this->redbox->configuration->fallBackUrl){
			// let's try a fallback...
			if (REDBOX_DEBUG==true) echo "<br>Error ! Try fallback... ";
			$tmp = $this->download_fallback($url);
			$file_array['tmp_name'] = $tmp;
		}
		
		if ( is_wp_error( $tmp ) ) {
			if (REDBOX_DEBUG==true) echo "<br>Error when downloading the media.";
			@unlink($file_array['tmp_name']);
			$file_array['tmp_name'] = '';
			return $id;			
		}
		if ($debug) echo "<br>Downloaded ! let's handle the media in WP via RedBox ... ";
		$id = $this->redbox_media_handle_sideload( $file_array, $post_id);//, $desc );
		if ( is_wp_error($id) ) {
			if (REDBOX_DEBUG==true) echo "<br>Error ! let's handle the media in WP native ... "; 
			$id = media_handle_sideload( $file_array, $post_id);//, $desc );
			if ( is_wp_error($id) ) {
				if (REDBOX_DEBUG==true) echo "<br>Error ! Can not handle the media ". $url . "<br>- File : " . $file_array['tmp_name'];
				@unlink($file_array['tmp_name']);
				return $id;
			}
		}
		if ($thumb) {
			set_post_thumbnail($post_id, $id);
			update_post_meta($post_id, "al2fb_facebook_image_id", $id);
		}
		return $id;
	}
	
	public function download_fallback($url){
		//echo "<br /><br /><br /><br /><br /><br /><br /><br /><br /><br />DOWNLOAD FALLBACK : ".$url."<br /><br /><br /><br /><br /><br /><br /><br /><br /><br />";
		  
		  $path = wp_tempnam($url);

		  file_put_contents($path, file_get_contents($this->redbox->configuration->fallBackUrl.'?url='.$url));
		  if (filesize($path) > 0) return $path;else return false;
		  
		  ##################################""
		  # OLD CODE BYPASSED
		  # open file to write
		  $fp = fopen ($path, 'w+');
		  # start curl
		  $ch = curl_init();
		  curl_setopt( $ch, CURLOPT_URL, $this->redbox->configuration->fallBackUrl.'?url='.$url );
		  # set return transfer to false
		  curl_setopt( $ch, CURLOPT_RETURNTRANSFER, false );
		  curl_setopt( $ch, CURLOPT_BINARYTRANSFER, true );
		  curl_setopt( $ch, CURLOPT_SSL_VERIFYPEER, false );
		  # increase timeout to download big file
		  curl_setopt( $ch, CURLOPT_CONNECTTIMEOUT, 5 );
		  # write data to local file
		  curl_setopt( $ch, CURLOPT_FILE, $fp );
		  # execute curl
		  curl_exec($ch);
		  # close curl
		  curl_close( $ch );
		  # close local file
		  fclose( $fp );
		  
		  //echo filesize($path);
		  
		  if (filesize($path) > 0) return $path;else return false;
	}
	
	public function download_url( $url, $timeout = 300 ) {
		//WARNING: The file is not automatically deleted, The script must unlink() the file.
		if ( ! $url )
			return new WP_Error('http_no_url', __('Invalid URL Provided.'));

		$tmpfname = wp_tempnam($url);
		if ( ! $tmpfname )
			return new WP_Error('http_no_file', __('Could not create Temporary file.'));

		$response = wp_safe_remote_get( $url, array( 'timeout' => $timeout, 'stream' => true, 'filename' => $tmpfname ) );

		if ( is_wp_error( $response ) ) {
			unlink( $tmpfname );
			return $response;
		}

		if ( 200 != wp_remote_retrieve_response_code( $response ) ){
			unlink( $tmpfname );
			return new WP_Error( 'http_404', trim( wp_remote_retrieve_response_message( $response ) ) );
		}

		$content_md5 = wp_remote_retrieve_header( $response, 'content-md5' );
		if ( $content_md5 ) {
			$md5_check = verify_file_md5( $tmpfname, $content_md5 );
			if ( is_wp_error( $md5_check ) ) {
				unlink( $tmpfname );
				return $md5_check;
			}
		}

		return $tmpfname;
	}
	
	 /*
	 * I had to create my own media handle sideload function because i got a 'white screen' with the official one
	 * with no visible errors that i could see, even in the logs
	 */
	
	public function redbox_media_handle_sideload($file_array, $post_id, $desc = null, $post_data = array()) {
		$overrides = array('test_form'=>false,'test_size'=>false,'test_type'=>true,'test_upload'=>false);		
		if (REDBOX_DEBUG==true) echo "Trying handle file ... ";
		$file = wp_handle_sideload($file_array,$overrides);
		if ( isset($file['error']) ) {
			if (REDBOX_DEBUG==true) echo "<br>Error with 'wp_handle_sideload' : ".$file['error'];
			if (REDBOX_DEBUG==true) echo "<br>Trying to force type to image ...";
			$file_array['name']= $file_array['name'].".jpg";
			$file = wp_handle_sideload($file_array,$overrides);
			if ( isset($file['error']) ) {
				return new WP_Error( 'upload_error', $file['error'] );
			}
		}
		if (REDBOX_DEBUG==true) echo "Media sideloaded ! ";
		$url = $file['url'];
		$type = $file['type'];
		$file = $file['file'];
		$title = preg_replace('/\.[^.]+$/', '', basename($file));
		$content = '';
		
		if ( isset( $desc ) )
		$title = $desc;
	
		// Construct the attachment array
		$attachment = array_merge( array(
		'post_mime_type' => $type,
		'guid' => $url,
		'post_parent' => $post_id,
		'post_title' => $title,
		'post_content' => $content,
		), $post_data );
	
		// This should never be set as it would then overwrite an existing attachment.
		if ( isset( $attachment['ID'] ) )
			unset( $attachment['ID'] );
	
		// Save the attachment metadata
		$id = wp_insert_attachment($attachment, $file, $post_id);
		if ( !is_wp_error($id) )
		wp_update_attachment_metadata( $id, wp_generate_attachment_metadata( $id, $file ) );
	
		return $id;
	}
	
	public function post_to_redbox_container($post){
		$redbox_post = new RedboxDataContainer();
		$redbox_post->url = get_permalink( $post->ID );
		$redbox_post->fb_id = '';
		$categories = get_the_category();
		$redbox_post->category = $categories[0]->cat_name;
		$redbox_post->source = $redbox_post->url;
		$redbox_post->title = $post->post_title;
		$redbox_post->origin = get_bloginfo('name');
		$redbox_post->created = $post->post_date;
		$redbox_post->author_name = get_the_author_meta('user_nicename');
		$redbox_post->author_url = get_the_author_link();
		$redbox_post->author_picture->url = get_avatar(get_the_author_meta('ID'),32,'',false,true);
		$content = explode('<!--more-->',$post->post_content);
		$redbox_post->message = strip_tags($content[0]);
		$redbox_post->description = strip_tags($content[1]);
		preg_match('/^(?>\S+\s*){1,30}/', strip_tags($content[0]), $match);
		$redbox_post->short_description = $match[0]."...";
		$redbox_post->pictures = array();
		$picture = new RedboxPictureDataContainer();
		$thumb = get_post_thumbnail_id($post->ID);
		$picture->url = wp_get_attachment_url( $thumb,'full' );
		$picture->title = $redbox_post->title;
		$redbox_post->pictures[] = $picture;
		$redbox_post->video_datas = new RedboxVideoDataContainer();
		$redbox_post->video_datas->url = get_post_meta( $post->ID, 'al2fb_facebook_video', true);
		$redbox_post->video_datas->embed = get_post_meta( $post->ID, 'mt_post_embed_code', true);
		if ($redbox_post->video_datas->url && $redbox_post->video_datas->url!=''){
			$redbox_post->type = 'video';
		}
		else{
			$redbox_post->type = 'article';
		}
		return $redbox_post;

	}
} // end class


?>
