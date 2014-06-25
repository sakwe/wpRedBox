<?php
/* RedBox configuration
 *
 **/


class RedBoxFacebook{

	public $fb_config, $categories;

	public function __construct(&$redbox){
		$this->redbox = $redbox;
	}
	
	
	public function redbox_view_facebook(){
		?>
		<div class="wrap">
			<div id="icon-redbox" class="icon32">
				<br>
			</div>
			<h2>Import Facebook</h2>
			<form action="options.php" method="post" id="redbox_facebook_form">
				<?php 
				echo $this->redbox_import_pannel();
				//$this->redbox_import_buttons();
				?>
			</form>
		</div>
		<?php
	}
	
	public function redbox_import_status($type=false) {
		global $wpdb;
		global $importInfo,$posts_id;
		$counts= $this->redbox_import_counts($type);
		
		switch ($type){
			case 'post':
				$listed_msg = REDBOX_FACEBOOK_POSTS_TMP;
				$imported_msg = REDBOX_FACEBOOK_POSTS_INWP;
				break;
				
			case 'gallery':
				$listed_msg = REDBOX_FACEBOOK_GALLERIES_TMP;
				$imported_msg = REDBOX_FACEBOOK_GALLERIES_INWP;

				break;
			case 'feed':
				$listed_msg = REDBOX_FACEBOOK_PROPOSITIONS_TMP;
				$imported_msg = REDBOX_FACEBOOK_PROPOSITIONS_INWP;

				break;
			default:
				$listed_msg = REDBOX_FACEBOOK_TMP;
				$imported_msg = REDBOX_FACEBOOK_INWP;

				break;
		
		}
		
		$return.= $counts['listed']." ".$listed_msg;
		$return.="<br />";
		$return.= $counts['imported']." ".$imported_msg;
		$return.="<br />";
		if ($importInfo!="") $return.= "<br />".$importInfo."<br /><br />";
		return $return;
	}
	
	public function redbox_import_counts($type=false) {
		global $wpdb;
		global $importInfo,$posts_id;
		$listed=0;
		$imported=0;
		$type_selection='';
		if ($type){
			$type_selection = ' AND r.type="'.$type.'" ';
		}
		if ($rows = $wpdb->get_results('SELECT COUNT(DISTINCT(r.id_fb)) AS NB FROM ' . $wpdb->prefix .'redbox_fb r 
				WHERE ((r.status NOT LIKE "trash") OR (r.status IS NULL)) '.$type_selection)){
			$listed = $rows[0]->NB;
		}
		$imported = $this->redbox_get_fb_imported($type);
		return array('listed'=>$listed,'imported'=>$imported);
	}
	
	public function redbox_get_fb_imported($type=false){
		global $wpdb;
		$type_selection='';
		if ($type){
			$type_selection = ' AND r.type="'.$type.'" ';
		}
		$sql = 'SELECT COUNT(DISTINCT(r.id_fb)) AS NB  FROM ' . $wpdb->prefix .'redbox_fb r WHERE 
				(r.status="published" OR 
				 r.status="redbox_posted_from_blog" OR 
				 r.status="redbox_linked_with_blog")
				 '.$type_selection;
		$rows = $wpdb->get_results($sql);
		return $rows[0]->NB;
	}


	public function redbox_import_pannel() {
		$options = get_option('redbox_options');

		$pannel ='<div class="redbox_clear"></div><span id="redbox_status" rel="resync-fb"></span><div class="redbox_clear"></div>';
		// TABLE
		$pannel.='<table class="redbox_import_pannel">';
			// ROW MAIN TITLES
			$pannel.='<tr class="main_titles">';
				$pannel.='<td colspan="2"  class="check">';
				$pannel.= REDBOX_FACEBOOK_CHECK;
				$pannel.='</td>';
				$pannel.='<td colspan="2"  class="import">';
				$pannel.= REDBOX_FACEBOOK_IMPORT;
				$pannel.='</td>';
				$pannel.='<td  class="sync">';
				$pannel.= REDBOX_FACEBOOK_QUICK_SYNC;
				$pannel.='</td>';
			$pannel.='</tr>';
			// END ROW MAIN TITLES
			// ROW ALL
			$pannel.='<tr>';
				$pannel.='<td class="check">';
				$pannel.='<input type="button" class="redbox_button redbox_button-info" 
						onclick="redbox_ajax_do(\'check_facebook\',0,1);" 
						value="'. REDBOX_CHECK_ALL.'"/>';
				$pannel.='</td>';
				$pannel.='<td class="check">';
				$pannel.='<input type="button" class="redbox_button redbox_button-warning" 
						onclick="redbox_ajax_do(\'check_facebook_forced\',0,1);" 
						value="'. REDBOX_CHECK_ALL_FORCED.'"/>';
				$pannel.='</td>';
				$pannel.='<td class="import">';
				$pannel.='<input type="button" class="redbox_button redbox_button-info" 
						onclick="redbox_ajax_do(\'import_facebook\',0,1);" 
						value="'. REDBOX_IMPORT_ALL.'"/>';
				$pannel.='</td>';
				$pannel.='<td class="import">';
				$pannel.='<input type="button" class="redbox_button redbox_button-danger" 
						onclick="redbox_ajax_do(\'import_facebook_forced\',0,1);" 
						value="'. REDBOX_IMPORT_ALL_FORCED.'"/>';
				$pannel.='</td>';
				$pannel.='<td class="sync">';
				$pannel.='<input type="button" class="redbox_button redbox_button-success" 
						onclick="redbox_ajax_do(\'sync_facebook\',0,1);" 
						value="'. REDBOX_SYNC_ALL.'"/>';
				$pannel.='</td>';
			$pannel.='</tr>';
			// END ALL
			// ROW POSTS
			$pannel.='<tr>';
				$pannel.='<td class="check">';
				$pannel.='<input type="button" class="redbox_button redbox_button-info" 
						onclick="redbox_ajax_do(\'check_facebook_posts\',0,1);" 
						value="'. REDBOX_CHECK_POSTS.'"/>';
				$pannel.='</td>';
				$pannel.='<td class="check">';
				$pannel.='<input type="button" class="redbox_button redbox_button-warning" 
						onclick="redbox_ajax_do(\'check_facebook_posts_forced\',0,1);" 
						value="'. REDBOX_CHECK_POSTS_FORCED.'"/>';
				$pannel.='</td>';
				$pannel.='<td class="import">';
				$pannel.='<input type="button" class="redbox_button redbox_button-info" 
						onclick="redbox_ajax_do(\'import_facebook_posts\',0,1);" 
						value="'. REDBOX_IMPORT_POSTS.'"/>';
				$pannel.='</td>';
				if (isset($_SESSION['last_proposed_id'])){
					$pannel.='<td class="import">';
					$pannel.='<input type="button" class="redbox_button redbox_button-danger" 
							onclick="redbox_ajax_do(\'import_facebook_posts_forced\',0,1);" 
							value="'. REDBOX_IMPORT_POSTS_FORCED.'"/>';
					$pannel.='<input type="button" class="redbox_button redbox_button-warning" 
							onclick="redbox_ajax_do(\'import_facebook_posts_continue\',0,1);" 
							value="'. REDBOX_IMPORT_POSTS_CONTINUE.'"/>';
					$pannel.='</td>';
				}
				else{
					$pannel.='<td class="import">';
					$pannel.='<input type="button" class="redbox_button redbox_button-danger" 
							onclick="redbox_ajax_do(\'import_facebook_posts_forced\',0,1);" 
							value="'. REDBOX_IMPORT_POSTS_FORCED.'"/>';
					$pannel.='</td>';
				}
				$pannel.='<td class="sync">';
				$pannel.='<input type="button" class="redbox_button redbox_button-success" 
						onclick="redbox_ajax_do(\'sync_facebook_posts\',0,1);" 
						value="'. REDBOX_SYNC_POSTS.'"/>';
				$pannel.='</td>';
			$pannel.='</tr>';
			// END POSTS
			// ROW GALLERIES
			$pannel.='<tr>';
				$pannel.='<td class="check">';
				$pannel.='<input type="button" class="redbox_button redbox_button-info" 
						onclick="redbox_ajax_do(\'check_facebook_galleries\',0,1);" 
						value="'. REDBOX_CHECK_GALLERIES.'"/>';
				$pannel.='</td>';
				$pannel.='<td class="check">';
				$pannel.='<input type="button" class="redbox_button redbox_button-warning" 
						onclick="redbox_ajax_do(\'check_facebook_galleries_forced\',0,1);" 
						value="'. REDBOX_CHECK_GALLERIES_FORCED.'"/>';
				$pannel.='</td>';
				$pannel.='<td class="import">';
				$pannel.='<input type="button" class="redbox_button redbox_button-info" 
						onclick="redbox_ajax_do(\'import_facebook_galleries\',0,1);" 
						value="'. REDBOX_IMPORT_GALLERIES.'"/>';
				$pannel.='</td>';
				$pannel.='<td class="import">';
				$pannel.='<input type="button" class="redbox_button redbox_button-danger" 
						onclick="redbox_ajax_do(\'import_facebook_galleries_forced\',0,1);" 
						value="'. REDBOX_IMPORT_GALLERIES_FORCED.'"/>';
				$pannel.='</td>';
				$pannel.='<td class="sync">';
				$pannel.='<input type="button" class="redbox_button redbox_button-success" 
						onclick="redbox_ajax_do(\'sync_facebook_galleries\',0,1);" 
						value="'. REDBOX_SYNC_GALLERIES.'"/>';
				$pannel.='</td>';
			$pannel.='</tr>';
			// END GALLERIES
			// ROW PROPOSITIONS
			$pannel.='<tr>';
				$pannel.='<td class="check">';
				$pannel.='<input type="button" class="redbox_button redbox_button-info" 
						onclick="redbox_ajax_do(\'check_facebook_propositions\',0,1);" 
						value="'. REDBOX_CHECK_PROPOSITIONS.'"/>';
				$pannel.='</td>';
				$pannel.='<td class="check">';
				$pannel.='<input type="button" class="redbox_button redbox_button-warning" 
						onclick="redbox_ajax_do(\'check_facebook_propositions_forced\',0,1);" 
						value="'. REDBOX_CHECK_PROPOSITIONS_FORCED.'"/>';
				$pannel.='</td>';
				$pannel.='<td class="import">';
				$pannel.='<input type="button" class="redbox_button redbox_button-info" 
						onclick="redbox_ajax_do(\'import_facebook_propositions\',0,1);" 
						value="'. REDBOX_IMPORT_PROPOSITIONS.'"/>';
				$pannel.='</td>';
				$pannel.='<td class="import">';
				$pannel.='<input type="button" class="redbox_button redbox_button-danger" 
						onclick="redbox_ajax_do(\'import_facebook_propositions_forced\',0,1);" 
						value="'. REDBOX_IMPORT_PROPOSITIONS_FORCED.'"/>';
				$pannel.='</td>';
				$pannel.='<td class="sync">';
				$pannel.='<input type="button" class="redbox_button redbox_button-success" 
						onclick="redbox_ajax_do(\'sync_facebook_propositions\',0,1);" 
						value="'. REDBOX_SYNC_PROPOSITIONS.'"/>';
				$pannel.='</td>';
			$pannel.='</tr>';
			// END PROPOSITIONS
			// ROW HELPS
			$pannel.='<tr class="main_titles">';
				$pannel.='<td colspan="2"  class="check">';
				$pannel.= REDBOX_CHECK_BUTTON_HELP;
				$pannel.='</td>';
				$pannel.='<td colspan="2"  class="import">';
				$pannel.= REDBOX_IMPORT_BUTTON_HELP;
				$pannel.='</td>';
				$pannel.='<td  class="sync">';
				$pannel.= REDBOX_SYNC_BUTTON_HELP;
				$pannel.='</td>';
			$pannel.='</tr>';
			// END ROW HELPS
		$pannel.='</table>';
		// END TABLE PANNEL
		
		return $pannel;
	}
	
	public function sync_fb($graph_type=false){
		$return = $this->update_fb_table(false,$graph_type);
		if (!$return || trim($return)==''){
			if ($this->redbox->dispatcher->proposed_id =="-1")
				$this->redbox->dispatcher->proposed_id=null;
			$return = $this->import_fb(false,$graph_type);
		}
		return $return;
	}
	public function sync_fb_posts(){
		return sync_fb('post');
	}

	public function sync_fb_galleries(){
		return sync_fb('gallery');
	}

	public function sync_fb_feed(){
		return sync_fb('feed');
	}
	//------------------------------------------
	
	
	public function update_fb_table($force=false,$graph_type=false){
		$check_all = false;
		if(!$graph_type){
			$check_all = true;
			if($this->redbox->dispatcher->proposed_id){
				$fb_url = $this->redbox->dispatcher->proposed_id;
				$action = $this->redbox->action;
				if (stripos($fb_url,'/posts'))
					$graph_type = 'post';
				elseif (stripos($fb_url,'/albums'))
					$graph_type = 'gallery';
				elseif (stripos($fb_url,'/feed'))
					$graph_type = 'feed';
				else{
					$graph_type = 'post';
				}
			}
			else{
				$graph_type = 'post';
			}
			
		}
		switch ($graph_type){
			case 'post':
				$return = $this->update_fb_posts_table($force);
				if ($return) {
					return $return;
					break;
				}
				elseif(!$check_all){
					return $return;
				}
				else{
					$this->redbox->dispatcher->proposed_id = false;
				}
			case 'gallery':
				$return = $this->update_fb_gallery_table($force);
				if ($return) {
					return $return;
					break;
				}
				elseif(!$check_all){
					return $return;
				}
				else{
					$this->redbox->dispatcher->proposed_id = false;
				}
			case 'feed':
				return $this->update_fb_feed_table($force);
				break;
		}
		return false;
	}
	
	
	public function update_fb_posts_table($force=false){
		global $wpdb;
	
		$options = get_option('redbox_options');
		$authToken = $this->redbox->retriever->fetchUrl("https://graph.facebook.com/oauth/access_token?grant_type=client_credentials&client_id={$options['facebook_app_id']}&client_secret={$options['facebook_app_secret']}");
		$to_date=null;
		if (!$force && $row=$wpdb->get_results('SELECT MAX(date) as date FROM ' . $wpdb->prefix .'redbox_fb WHERE type="post"')){
			$to_date = strtotime(stripslashes($row[0]->date));
		}
		if (!$to_date || trim($to_date) == ''){
			$to_date = strtotime(stripslashes($options['facebook_import_date']));
		}
		
		$from_date = time();
		if (!$this->redbox->dispatcher->proposed_id){
			$url = "https://graph.facebook.com/{$options['facebook_id']}/posts?{$authToken}&fields=created_time,type&limit=25&until=$from_date";
		}
		else{
			$url = $this->redbox->dispatcher->proposed_id;
		}
		
		$json_object = $this->redbox->retriever->fetchUrl($url);
		$feedarray = json_decode($json_object);
		foreach ( $feedarray->data as $feed_data ){
			$last_time = $feed_data->created_time;
			if (strtotime(stripslashes($feed_data->created_time)) < $to_date){
				$feedarray->paging->next=null;
				break;
			}
			if ($feed_data->type!='status'){
				if (!$wpdb->get_results('SELECT id FROM ' . $wpdb->prefix .'redbox_fb WHERE id_fb="'.$feed_data->id.'"')){
					$wpdb->insert($wpdb->prefix .'redbox_fb',array('id_fb' => $feed_data->id , 'date' => $feed_data->created_time , 'type'=>'post'));
					// if we already have it in the blog...
					if ($rows = $wpdb->get_results('SELECT * FROM ' . $wpdb->prefix .'postmeta 
								WHERE meta_key="al2fb_facebook_link_id" AND meta_value="'.$feed_data->id.'"')){
						update_post_meta($rows[0]->post_id, "redbox_posted_from_blog", $feed_data->id);
						// update the redbox_fb status
						$sql = 'UPDATE ' . $wpdb->prefix .'redbox_fb 
							SET status="redbox_posted_from_blog" 
							WHERE id_fb="'.$feed_data->id.'"';
						$wpdb->get_results($sql);

					}
				}
			}
			else{
				$wpdb->get_results('DELETE ' . $wpdb->prefix .'redbox_fb WHERE id_fb="'.$feed_data->id.'"');
			}
		}
		$url = $feedarray->paging->next;
		if ($url){
			$this->redbox->dispatcher->proposed_id = $url;
			$bar = '<div class="messi-titlebox anim warning">';
			if ($force){
				$bar.= REDBOX_CHECK_FACEBOOK_POSTS_FORCED_WORKING;
			}
			else{
				$bar.= REDBOX_CHECK_FACEBOOK_POSTS_WORKING;
			}
			$bar.= '<br /><br />Status : '. $last_time."";
			$bar.='</div>';
			$content = $bar.'<div class="messi-wrapper messi-content">'.$content.'';
			$content.= '<div class="messi-footbox btnbox"><input type="button" onclick="redbox_ajax_do(false);" class="btn btn-danger" value="'.STOP.'" /></div>';
			$content.= '</div>';
			return $content;
		}
		else{
			$this->redbox->dispatcher->proposed_id = '-1';
			return null;
		}
		
	}
	
	public function update_fb_gallery_table($force=false){
		global $wpdb;
		$options = get_option('redbox_options');
		$authToken = $this->redbox->retriever->fetchUrl("https://graph.facebook.com/oauth/access_token?grant_type=client_credentials&client_id={$options['facebook_app_id']}&client_secret={$options['facebook_app_secret']}");
		
		$from_date = time();
		$url = "https://graph.facebook.com/{$options['facebook_id']}/albums?{$authToken}&fields=created_time,name&limit=25&until=$from_date";

		while($url){
			$json_object = $this->redbox->retriever->fetchUrl($url);
			$feedarray = json_decode($json_object);
			foreach ( $feedarray->data as $feed_data ){
				if ($feed_data->name!='Timeline Photos'&&$feed_data->name!='Cover Photos'&&$feed_data->name!='Profile Pictures'){
					if (!$wpdb->get_results('SELECT id FROM ' . $wpdb->prefix .'redbox_fb WHERE id_fb="'.$feed_data->id.'"')){
						$wpdb->insert($wpdb->prefix .'redbox_fb',array('id_fb' => $feed_data->id , 'date' => $feed_data->created_time , 'type'=>'gallery'));
						// if we already have it in the blog...
						if ($rows = $wpdb->get_results('SELECT * FROM ' . $wpdb->prefix .'postmeta 
									WHERE meta_key="al2fb_facebook_link_id" AND meta_value="'.$feed_data->id.'"')){
							update_post_meta($rows[0]->post_id, "redbox_posted_from_blog", $feed_data->id);
						}
					}
				}
			}
			$url = $feedarray->paging->next;
		}
	}

	public function update_fb_feed_table($force=false){
		global $wpdb;
		$options = get_option('redbox_options');
		$authToken = $this->redbox->retriever->fetchUrl("https://graph.facebook.com/oauth/access_token?grant_type=client_credentials&client_id={$options['facebook_app_id']}&client_secret={$options['facebook_app_secret']}");
		$to_date=null;
		if (!$force && $row=$wpdb->get_results('SELECT MAX(date) as date FROM ' . $wpdb->prefix .'redbox_fb WHERE type="feed"')){
			$to_date = strtotime(stripslashes($row[0]->date));
		}
		if (!$to_date || trim($to_date) == ''){
			$to_date = time();
			$to_date = strtotime(stripslashes($options['facebook_import_date']));
		}
		$from_date = time();
		if (!$this->redbox->dispatcher->proposed_id){
			$url = "https://graph.facebook.com/{$options['facebook_id']}/feed?{$authToken}&limit=25&fields=id,message";
		}
		else{
			$url = $this->redbox->dispatcher->proposed_id;
		}
		$json_object = $this->redbox->retriever->fetchUrl($url);
		$feedarray = json_decode($json_object);
		foreach ( $feedarray->data as $feed_data ){
		$last_time = $feed_data->created_time;
			if (strtotime(stripslashes($feed_data->created_time)) < $to_date){
				$feedarray->paging->next=null;
				break;
			}
			preg_match("/#".$options['redbox_page_name']."/i", $feed_data->message, $matches);
			//preg_match("/\balternatives\b/i", $feed_data->message, $matches);
			if($matches){
				if (!$wpdb->get_results('SELECT id FROM ' . $wpdb->prefix .'redbox_fb WHERE id_fb="'.$feed_data->id.'"')){
					$wpdb->insert($wpdb->prefix .'redbox_fb',array('id_fb' => $feed_data->id , 'date' => $feed_data->created_time , 'type'=>'feed'));
				}
			}
		}
		$url = $feedarray->paging->next;
		if ($url){
			$this->redbox->dispatcher->proposed_id = $url;
			$bar = '<div class="messi-titlebox anim warning">';
			if ($force){
				$bar.= REDBOX_CHECK_FACEBOOK_PROPOSITIONS_FORCED_WORKING;
			}
			else{
				$bar.= REDBOX_CHECK_FACEBOOK_PROPOSITIONS_WORKING;
			}
			$bar.= '<br /><br />Status : '. $last_time."";
			$bar.='</div>';
			$content = $bar.'<div class="messi-wrapper messi-content">'.$content.'';
			$content.= '<div class="messi-footbox btnbox"><input type="button" onclick="redbox_ajax_do(false);" class="btn btn-danger" value="'.STOP.'" /></div>';
			$content.= '</div>';
			return $content;
		}
		else{
			$this->redbox->dispatcher->proposed_id = '-1';
			return null;
		}
	}

	// import from fb
	// easy calling function 
	public function import_fb_posts($force=false){
		import_fb($force=false,'post');
	}

	public function import_fb_galleries($force=false){
		import_fb($force=false,'gallery');
	}

	public function import_fb_feed($force=false){
		import_fb($force=false,'feed');
	}
	//------------------------------------------
	
	// import from fb
	// it get the first importable fb item, import it, display it 
	// it automatically run the next import by ajax if it exists and no "$rb_to_import" is forced
	public function import_fb($force=false,$graph_type=false,$rb_to_import=null){
		
		if ($rb_to_import || $rb_to_import = $this->get_fb_id_to_import($force,$graph_type)){
			$graph_type_now = $rb_to_import->type;
			
			if ($retrieved = $this->redbox->retriever->get_datas($rb_to_import->id_fb)){
				// check if we feed propositions or posts
				if (!$graph_type_now || $graph_type_now!='feed'){
					$urls = array();
					foreach($retrieved as $datas) $urls[] = $datas->url;
					$clones = $this->redbox->manager->check_clones($urls,"post_facebook");
					if (!$clones){
						//return $redbox_fb->id_fb.$this->redbox->blog->get_datas_mini_viewer($retrieved);
						return $this->update_fb_post($rb_to_import,$retrieved,$force);
					}
					else{
						return $this->link_posts_for($rb_to_import,$clones);
					}
				}
				else{
					if (count($retrieved) > 1)
						return $this->update_fb_proposition($rb_to_import,$retrieved,$force);
					else
						return $this->trash_proposition($rb_to_import);
				}
			}
			else{
				return $this->trash_post($rb_to_import);
			}
		}
		return null;
	}

	public function get_fb_id_to_import($force=false,$graph_type=false){
		global $wpdb;
		$exclude='';
		
		$type_selection='';
		if ($graph_type){
			$type_selection = ' AND r.type="'.$graph_type.'" ';
		}
		
		$sql = 'SELECT * FROM ' . $wpdb->prefix .'redbox_fb r WHERE ((r.status NOT LIKE "trash" ';
		if (!$force){
			$exclude = ' AND r.status NOT LIKE "published" ';
		}
		$exclude = ' AND r.status NOT LIKE "redbox_posted_from_blog" 
				AND r.status NOT LIKE "redbox_linked_with_blog" '.$exclude;
		
		if (!$this->redbox->dispatcher->proposed_id){
			$counts= $this->redbox_import_counts($graph_type);
			if ($force){
				$_SESSION['to_import']= $counts['listed'];
			}
			else{
				$_SESSION['to_import']= $counts['listed'] - $counts['imported'];
			}
			$_SESSION['imported']=0;
			$sql.=  $exclude.') OR (r.status IS NULL OR r.status = "")) ' .$type_selection; 
		}
		elseif($this->redbox->dispatcher->proposed_id== "-1"){
			return null;
		}
		else{
			$sql.= $exclude.') OR (r.status IS NULL OR r.status = "")) AND r.date <= "'.$this->redbox->dispatcher->proposed_id . '"'.$type_selection;
		}
		$sql.= ' ORDER BY r.date DESC LIMIT 2';
		//die();
		if ($rows = $wpdb->get_results($sql)){
			$row = $rows[0];
			// get the next id if exists
			if (count($rows) > 1) {
				$this->redbox->dispatcher->proposed_id = $rows[1]->date; 
				$_SESSION['last_proposed_id'] = $this->redbox->dispatcher->proposed_id;
			}
			else{
				$this->redbox->dispatcher->proposed_id= "-1";
				unset($_SESSION['last_proposed_id']);
			}
			return $row;
		}
		else{
			unset($_SESSION['last_proposed_id']);
			return null;
		}
	}

	public function link_posts_for($rb_to_import,$clones){
		global $wpdb;
		$content='';
		$_SESSION['imported']++;
		$bar = $this->progress_bar(REDBOX_IMPORT_FACEBOOK_POST_LINKED,'warning');
		foreach($clones as $clone){
			update_post_meta($clone['id'], "redbox_linked_with_blog", $rb_to_import->id_fb);
			//update_post_meta($clone['id'], "redbox_data_container", $clone['data_container']);
			// update the redbox_fb status
			$sql = 'UPDATE ' . $wpdb->prefix .'redbox_fb 
				SET status="redbox_linked_with_blog" 
				WHERE id_fb="'.$rb_to_import->id_fb.'"';
			$wpdb->get_results($sql);
			if ($retrieved = unserialize($clone['data_container'])){
				$content.= $this->redbox->blog->get_datas_mini_viewer($retrieved);
			}
		}
		$content = $bar.'<div class="messi-wrapper messi-content">'.$content.'';
		$content.= '<div class="messi-footbox btnbox"><input type="button" onclick="redbox_ajax_do(false);" class="btn btn-danger" value="'.STOP.'" /></div></div>';
		return $content;
	}
	
	public function trash_proposition($rb_to_import){
		global $wpdb;
		// TODO : $this->redbox->manager->trash_proposition_by_id_fb($rb_to_import->id_fb);
		$wpdb->get_results('UPDATE ' . $wpdb->prefix .'redbox_fb SET status="trash" WHERE id_fb="'.$rb_to_import->id_fb.'"');
		$content='';
		$_SESSION['imported']++;
		$bar = $this->progress_bar(REDBOX_PROPOSITION_INVALID_TRASHED,'warning');
		$content = $bar.'<div class="messi-wrapper messi-content">'.$content.'';
		$content.= '<div class="messi-footbox btnbox"><input type="button" onclick="redbox_ajax_do(false);" class="btn btn-danger" value="'.STOP.'" /></div></div>';
		return $content;
	}

	public function trash_post($rb_to_import){
		global $wpdb;
		$this->redbox->manager->trash_post_by_id_fb($rb_to_import->id_fb);
		$content='';
		$_SESSION['imported']++;
		$bar = $this->progress_bar(REDBOX_POST_INVALID_TRASHED,'warning');
		$content = $bar.'<div class="messi-wrapper messi-content">'.$content.'';
		$content.= '<div class="messi-footbox btnbox"><input type="button" onclick="redbox_ajax_do(false);" class="btn btn-danger" value="'.STOP.'" /></div></div>';
		return $content;
	}


	public function update_fb_post($rb_to_import,$retrieved,$force=false){
		global $wpdb;
		$content= null;
		if ($force){
			$this->redbox->manager->insert_redbox_post($retrieved);
			$content = $this->redbox->blog->get_datas_mini_viewer($retrieved);
		}
		else{
			$args = array('meta_key' => 'al2fb_facebook_link_id', 'meta_value' => $rb_to_import->id_fb);
			$exist_post = get_posts( $args );
			//if (count($exist_post) == 0){
				$this->redbox->manager->insert_redbox_post($retrieved);
				$content = $this->redbox->blog->get_datas_mini_viewer($retrieved);
			/*}
			else{
				$content = REDBOX_FACEBOOK_POSTS_ALLREADY_IN_WP . '<br /><br />'.$this->redbox->blog->get_datas_mini_viewer($retrieved);
			}*/
		}
		
		$_SESSION['imported']++;
		if ($content) {
			// update the redbox_fb status
			$sql = 'UPDATE ' . $wpdb->prefix .'redbox_fb 
				SET status="published" 
				WHERE id_fb="'.$rb_to_import->id_fb.'"';
			$wpdb->get_results($sql);
			
			if ($force){
				$text= REDBOX_IMPORT_FACEBOOK_FORCED_WORKING;
			}
			else{
				$text= REDBOX_IMPORT_FACEBOOK_WORKING;
			}
			$bar = $this->progress_bar($text,'info');
			
			$content = $bar.'<div class="messi-wrapper messi-content">'.$content.'';
			$content.= '<div class="messi-footbox btnbox"><input type="button" onclick="redbox_ajax_do(false);" class="btn btn-danger" value="'.STOP.'" /></div></div>';
		}
		else{
			$content = $this->trash_post($rb_to_import);
		}
		return $content;
	}
	
	public function progress_bar($text,$type='info'){
		$_SESSION['imported_progress']= ($_SESSION['imported'] / $_SESSION['to_import']) * 100;
		$bar = '<div class="messi-titlebox anim '.$type.'">'.$text;
		$bar.= '<br /><br />'. intval($_SESSION['imported_progress'])."% (".$_SESSION['imported'] ."/". $_SESSION['to_import'].")";
		$bar.= '<br /><small>'.$retrieved[0]->created.'</small>';
		$bar.= '<br /><div class="messi-titlebox anim success" style="width:'.$_SESSION['imported_progress'].'%"></div>';			
		if ($this->redbox->dispatcher->proposed_id && $this->redbox->dispatcher->proposed_id !="-1"){
			$bar.= '<br /><small>'.NEXT.' : '. $this->redbox->dispatcher->proposed_id.'</small>';
		}
		$bar.='</div>';
		return $bar;
	}
	
	public function update_fb_proposition($rb_to_import,$retrieved,$force=false){
		
		$content= null;
		if ($force){
			$this->redbox->manager->insert_redbox_proposition($retrieved,$rb_to_import->id_fb,true);// true = force author from fb
			$content =  $this->redbox->blog->get_datas_mini_viewer($retrieved);
		}
		else{
			$args = array('meta_key' => 'al2fb_facebook_link_id', 'meta_value' => $id_fb);
			$exist_comment = get_comments( $args );
			if (count($exist_comment) == 0){
				$this->redbox->manager->insert_redbox_proposition($retrieved,$rb_to_import->id_fb,true);// true = force author from fb
				$content =  $this->redbox->blog->get_datas_mini_viewer($retrieved);
			}
		}
		$_SESSION['imported']++;
		if ($content) {
			if ($force){
				$text= REDBOX_IMPORT_FACEBOOK_PROPOSITIONS_FORCED_WORKING;
			}
			else{
				$text= REDBOX_IMPORT_FACEBOOK_PROPOSITIONS_WORKING;
			}
			$bar = $this->progress_bar($text,'info');
			$content = $bar.'<div class="messi-wrapper messi-content">'.$content.'';
			$content.= '<div class="messi-footbox btnbox"><input type="button" onclick="redbox_ajax_do(false);" class="btn btn-danger" value="'.STOP.'" /></div></div>';
		}
		return $content;
	}

}


