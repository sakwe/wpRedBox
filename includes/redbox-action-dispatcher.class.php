<?php
/* Action dispatcher
 *
 **/
$BOMBED = array();



class RedBoxDispatcher{

	public function __construct(&$redbox){
		$this->redbox = $redbox;
		$this->redbox->action = null;
		$this->proposed_id = null;
		// check for action from http if none for constructor
		if (isset($_GET['redbox_action']) && $_GET['redbox_action']!='') 
			$this->redbox->action = $_GET['redbox_action'];
		if (isset($_POST['redbox_action']) && $_POST['redbox_action']!='') 
			$this->redbox->action = $_POST['redbox_action'];
		
		// add the ajax support for the dispatcher
		add_action( 'wp_ajax_redbox_action_ajax', array(&$this, 'redbox_action_ajax_callback') );
		add_action( 'wp_ajax_nopriv_redbox_action_ajax', array(&$this, 'redbox_action_ajax_callback') );
		$this->dialogs= array();
		
		// get the session datas queue
		if (isset($_SESSION["redbox_action_queue"]))
			$this->action_queue = $_SESSION["redbox_action_queue"];
		else
			$this->action_queue = null;
		
		// get the session datas queue
		if (isset($_SESSION["redbox_retrieved_queue"]))
			$this->datas_queue = $_SESSION["redbox_retrieved_queue"];
		else
			$this->datas_queue = null;
			
		// get the proposed id
		if (isset($_GET['comment_id'])) $this->proposed_id = $_GET['comment_id'];
		elseif (isset($_POST['comment_id'])) $this->proposed_id = $_POST['comment_id'];
		elseif (isset($_SESSION['comment_id'])) $this->proposed_id = $_SESSION['comment_id'];
		if ($this->proposed_id !=null) $_SESSION['comment_id'] = $this->proposed_id;
				
		// get the url(s) (+message) to import 
		if (isset($_GET['url_to_import']) && $_GET['url_to_import']!='') 
			$this->url_to_import = $_GET['url_to_import'];
		if (isset($_POST['url_to_import'])) $this->url_to_import = $_POST['url_to_import'];
		
		// load the action code if some action to execute
		if ($this->redbox->action){
			add_action('init', array(&$this, "do_redbox_action"));
			add_action('admin_footer', array(&$this, "redbox_enqueue_dispatcher"));
			add_action('wp_footer', array(&$this, "redbox_enqueue_dispatcher"));
		}
	}
	
	// this will handle ajax action queries and dispatch it
	public function redbox_action_ajax_callback(){
		global $BOMBED;
		if (isset($_POST['redbox_ajax_working_action'])) {
			$this->working_action = $_POST['redbox_ajax_working_action'];
		}
		else{
			$this->working_action = $this->redbox->action;
		}
		
		if (isset($_POST['redbox_ajax_id'])) {
			if ($this->working_action != "redbox_submit_from_blog" && 
				$this->working_action != "redbox_submit_from_admin_widget" && 
				$this->working_action != "redbox_submit_from_adminbar"){
				$this->proposed_id = $_POST['redbox_ajax_id'];
			}
			else{
				$_SESSION["url_to_import"] = stripslashes($_POST['redbox_ajax_id']);
			}
		}
		
		if (isset($_SESSION['url_to_import'])) {
			$this->url_to_import = stripslashes($_SESSION['url_to_import']);
		}
		
		if (isset($_POST['redbox_ajax_action'])){
			$this->redbox->action = $_POST['redbox_ajax_action'];
			$this->do_redbox_action();
			$this->redbox_enqueue_dispatcher();
			die;
		}
	}
	
	// global action dispatcher
	public function do_redbox_action($action=null) {
		global $wpdb;
		if ($action) $this->redbox->action=$action;
		
		if ($this->redbox->action=="import_facebook_posts_continue" && isset($_SESSION['last_proposed_id'])){
			$this->redbox->action= "import_facebook_posts_forced";
			$this->redbox->dispatcher->proposed_id = $_SESSION['last_proposed_id'];
			$counts= $this->redbox->facebook->redbox_import_counts('post');
			$_SESSION['to_import']= $counts['listed'];
		}
		
		$labels = array('x'=> CLOSE,'y'=> PROPOSE,'n'=> CANCEL,'c'=> CANCEL);
		switch ($this->redbox->action){
			
			case "xmpp_test_proposition" : 
				$this->redbox->xmpp->send_notification_for_proposition($this->redbox->dispatcher->proposed_id);
			
			break;
			
			case "xmpp_test_comment" : 
				$this->redbox->xmpp->send_notification_for_comment($this->redbox->dispatcher->proposed_id);
			
			break;
			
			case "diaspora_test_post" : 
				$this->redbox->diaspora->setProtocol("https");
				$this->redbox->diaspora->setId("mondi@mondiaspora.org");
				$this->redbox->diaspora->setPassword("pogomonkey");
				$this->redbox->diaspora->setMessage("Coucou !");
				echo $this->redbox->diaspora->postToDiaspora();
				exit;
			break;
			
			case "clean_zero_return":
				RecursiveFolder(WP_CONTENT_DIR.'/themes/spectro');
				echo '<h2>These files had UTF8 BOM, but i cleaned them:</h2><p class="FOUND">';
				foreach ($BOMBED as $utf) { echo $utf ."<br />\n"; }
				RecursiveFolder(WP_CONTENT_DIR.'/plugins/redbox');
				echo '<h2>These files had UTF8 BOM, but i cleaned them:</h2><p class="FOUND">';
				foreach ($BOMBED as $utf) { echo $utf ."<br />\n"; }
				echo '</p>';
			break;

			case "fix_readmore":
				echo "<br>*************************** fix_readmore ****************************<br>";
				$sql = 'SELECT * FROM ' . $wpdb->prefix .'posts WHERE post_status="publish" AND post_type="post" AND post_date>="2012-01-01" ORDER BY post_date DESC';
				$rows = $wpdb->get_results($sql);
				$i=0;
				foreach ($rows as $row){
					$description = $row->post_content;
					echo "<br>________________________________________________________________<br>";
					$description = str_replace("<br /><!--more--><br />\n","\n",$description);
					$description = str_replace("\n<br /><!--more--><br />","\n",$description);
					$description = str_replace("<br /><!--more--><br />","",$description);
					$description = str_replace("<!--more-->\n","\n",$description);
					$description = str_replace("\n<!--more-->","\n",$description);
					$description = str_replace("<!--more-->","",$description);
					$phrases = explode("\n",$description);
					if (count($phrases)>0){
						$afterMore = "";
						if (str_word_count($phrases[0]) < 40) {
							$beforeMore = "";
							foreach($phrases as $phrase) {
								if (str_word_count($beforeMore) < 40) {
									$beforeMore.= $phrase."\n";
								} else {
									$afterMore.= $phrase."\n";
								}
							} 
						} else { 
							$beforeMore = $phrases[0];
							$afterMore = str_replace($beforeMore,"",$description);
						}
						$description = $beforeMore."\n<!--more-->".$afterMore;
					} else {
						$description = $description."<!--more-->";
					}
					echo "<br>----------------------------------------------------------------<br>";
					  $my_post = array(
					      'ID'           => $row->ID,
					      'post_content' => $description
					  );
					// Update the post into the database
					  wp_update_post( $my_post );
					echo "<br>________________________________________________________________<br>";
					$i++;
					//if ($i==60) break;
				}
				echo "<br>*************************** ".$i." readmore fixed in posts ! ****************************<br>";
				exit;
			break;

			case "clean_retrolink":
				$sql = 'SELECT * FROM wp_comments WHERE comment_type = "trackback" '."\n";
				$rows = $wpdb->get_results($sql);
				$nbSup=0;
				foreach ($rows as $row){
					$sql = 'DELETE FROM wp_commentmeta WHERE comment_id='.$row->comment_ID."\n";
					$wpdb->get_results($sql);
					$sql = 'DELETE FROM wp_comments WHERE comment_ID='.$row->comment_ID."\n";
					$wpdb->get_results($sql);
					$nbSup++;
				}
				echo "<br>".$nbSup." messages supprimés";

			break;

			case "clean_archives":
				$sql = 'SELECT ID FROM wp_posts';
				$rows = $wpdb->get_results($sql);
				$nbSup=0;
				foreach ($rows as $row){
					$category = get_the_category($row->ID);

					if ($category[0]->slug=='productions'){
					$sql = 'UPDATE `wp_posts` SET post_type="portfolio" WHERE ID='.$row->ID."\n";
					$wpdb->get_results($sql);
					$nbSup++;
					}
				}
				echo "<br>".$nbSup." portfolio retrouvés !";

			break;


			case "unset_music":
				$sql = 'SELECT p.ID, p.post_name FROM wp_posts as p, wp_terms, wp_term_relationships, wp_term_taxonomy WHERE 
				wp_terms.name = "clips musicaux" AND 
				wp_term_taxonomy.term_id = wp_terms.term_id AND 
				wp_term_taxonomy.taxonomy = "category" AND 
				p.ID = wp_term_relationships.object_id AND 
				wp_term_taxonomy.term_taxonomy_id = wp_term_relationships.term_taxonomy_id  AND 
				p.post_type = "post" AND 
				p.post_status = "publish"';
				$posts = $wpdb->get_results($sql);
				foreach ($posts as $post){

					if ($rows = $wpdb->get_results('SELECT * FROM ' . $wpdb->prefix .'postmeta 
								WHERE meta_key="al2fb_facebook_link_id" AND post_id='.$post->ID)){
						
						$sql = 'UPDATE ' . $wpdb->prefix .'redbox_fb 
							SET status=NULL 
							WHERE id_fb="'.$rows[0]->meta_value.'"';
						$wpdb->get_results($sql);

					}
				}
			break;
			
			case "clean_comments":				
				$sql = 'SELECT * FROM wp_posts '."\n";
				$rows = $wpdb->get_results($sql);
				$nbSup=0;
				foreach ($rows as $row){
					$sql = 'SELECT * FROM wp_comments WHERE comment_author LIKE "%wojtalik%" AND comment_post_ID='.$row->ID."\n";
					$coms = $wpdb->get_results($sql);
					$i=0;
					foreach ($coms as $com){
						$i++;
						if ($i > 0) {
							echo "<br>Conserve : ".get_permalink($row->ID); 
							break;				
						}
					}
					if ($i == 0) {
						$sql = 'SELECT * FROM wp_comments WHERE comment_author LIKE "%facebook%" AND comment_post_ID='.$row->ID."\n";
						$coms = $wpdb->get_results($sql);
						$i=0;
						foreach ($coms as $com){														
							$sql = 'DELETE FROM wp_commentmeta WHERE comment_id='.$com->comment_ID."\n";
							$wpdb->get_results($sql);
							$sql = 'DELETE FROM wp_comments WHERE comment_ID='.$com->comment_ID."\n";
							$wpdb->get_results($sql);
							$nbSup++;
						}
					}
				}
				echo "<br>".$nbSup." messages supprimés";
				break;
			
			case "look_for_music":
				$sql = 'SELECT p.ID, p.post_name,p.post_date FROM wp_posts as p, wp_terms, wp_term_relationships, wp_term_taxonomy WHERE 
				wp_terms.name = "courtes vidéos" AND 
				wp_term_taxonomy.term_id = wp_terms.term_id AND 
				wp_term_taxonomy.taxonomy = "category" AND 
				p.ID = wp_term_relationships.object_id AND 
				wp_term_taxonomy.term_taxonomy_id = wp_term_relationships.term_taxonomy_id  AND 
				p.post_type = "post" AND 
				p.post_status = "publish"';
				$posts = $wpdb->get_results($sql);
				foreach ($posts as $post){
					//echo "<br />".$post->post_date;
					$checkdate = getdate(strtotime(($post->post_date)));
					if ($checkdate['hours'] >= 20 && $checkdate['hours'] <= 23 && $checkdate['year'] >= 2013 ){
						//$cat = get_term_by("name", "Clips musicaux", 'category', ARRAY_A);
						wp_set_object_terms($post->ID, 'clips-musicaux', 'category');
						echo "<br />".$post->post_name;
					}
				}
			break;
			
			case "clean_urls_of_sources":
				$sql = 'SELECT * FROM ' . $wpdb->prefix .'postmeta WHERE meta_key="redbox_source"';
				$rows = $wpdb->get_results($sql);
				foreach ($rows as $row){
					$sql = 'UPDATE ' . $wpdb->prefix .'postmeta 
						SET meta_value="'.$this->redbox->retriever->cleanUrl($row->meta_value).'" 
						WHERE meta_id='.$row->meta_id;
					$wpdb->get_results($sql);
				}
				$sql = 'SELECT * FROM ' . $wpdb->prefix .'commentmeta WHERE meta_key="redbox_source"';
				$rows = $wpdb->get_results($sql);
				foreach ($rows as $row){
					$sql = 'UPDATE ' . $wpdb->prefix .'postmeta 
						SET meta_value="'.$this->redbox->retriever->cleanUrl($row->meta_value).'" 
						WHERE meta_id='.$row->meta_id;
					$wpdb->get_results($sql);
				}
				break;
			
			case "unset_for_301":
				echo $sql = 'SELECT wp_postmeta.meta_value AS meta_value FROM wp_posts, wp_postmeta WHERE wp_posts.post_title like "%301%" AND wp_posts.ID = wp_postmeta.post_id AND wp_postmeta.meta_key = "al2fb_facebook_link_id"';
				$rows = $wpdb->get_results($sql);
				foreach ($rows as $row){
					$sql = 'UPDATE wp_redbox_fb SET status = "" WHERE id_fb = "'.$row->meta_value .'"';
					echo $sql . "<br />";
					$rbs = $wpdb->get_results($sql);
				}
				exit;
				break;
			
			case "unset_for_gallery":
				echo $sql = 'SELECT wp_postmeta.meta_value AS meta_value FROM wp_posts, wp_postmeta WHERE  ((wp_posts.post_content like "%street-art%" OR wp_posts.post_content like "%streetart%" OR wp_posts.post_content like "%street art%" OR wp_posts.post_content like "%art urbain%" ) OR (wp_posts.post_content like "%habitat%" AND wp_posts.post_content like "%alternatif%")) AND wp_posts.ID = wp_postmeta.post_id AND wp_postmeta.meta_key = "al2fb_facebook_link_id"';
				$rows = $wpdb->get_results($sql);
				foreach ($rows as $row){
					$sql = 'UPDATE wp_redbox_fb SET status = "" WHERE id_fb = "'.$row->meta_value .'"';
					echo $sql . "<br />";
					$rbs = $wpdb->get_results($sql);
				}
				exit;
				break;
			
			case "unset_for_sources":
				$sql = 'SELECT * FROM ' . $wpdb->prefix .'postmeta WHERE meta_key="redbox_source" AND meta_value LIKE "%france24%"';
				$rows = $wpdb->get_results($sql);
				foreach ($rows as $row){
					$sql = 'SELECT DISTINCT(r.id_fb) AS id_fb FROM ' . $wpdb->prefix .'postmeta p, ' . $wpdb->prefix .'redbox_fb r WHERE p.meta_key="al2fb_facebook_link_id" AND p.post_id ='.$row->post_id . ' AND r.id_fb=p.meta_value';
					$rbs = $wpdb->get_results($sql);
					foreach ($rbs as $rb){
						$sql = 'UPDATE ' . $wpdb->prefix .'redbox_fb 
						SET status= "" 
						WHERE id_fb="'.$rb->id_fb.'"';
						$wpdb->get_results($sql);
					}
				}
				break;
				
				
			case "fix_portfolio_url":
				$sql = 'SELECT * FROM ' . $wpdb->prefix .'posts WHERE post_type="portfolio"';
				$rows = $wpdb->get_results($sql);
				foreach ($rows as $row){
					$sql = 'SELECT * FROM ' . $wpdb->prefix .'posts WHERE 
						post_type NOT LIKE "portfolio" AND 
						post_name = "'.$row->post_name.'" 
					';
					$dup = $wpdb->get_results($sql);
					$i=0;
					foreach ($dup as $d){
						$i++;
						$sql = 'UPDATE ' . $wpdb->prefix .'posts 
						SET post_name="'.$row->post_name.'-'.$i.'" 
						WHERE ID='.$d->ID;
						$wpdb->get_results($sql);
					}
				}
				break;


			case "redbox_check_for_link":
				//$_SESSION["redbox_retrieved_queue"] = serialize($retrieved);
				// retrieve data container for the link and 
				$retrieved = $this->redbox->retriever->get_datas($this->url_to_import);
				// get the viewer interface for datas we got
				$viewer =  $this->redbox->blog->get_datas_proposed_viewer($retrieved);
				// load the dialog box to the user
				$labels = array('x'=> CLOSE,'y'=> '','n'=> '','c'=> '');
				if( !current_user_can( 'read' ) ){
					$this->dialogs[]= $this->dialogBox(array('content'=>REDBOX_ERROR_PROPOSITION_NOT_ALLOWED,'labels'=>$labels),REDBOX_ERROR_PROPOSITION,"warning");
				}
				else{
					$this->dialogs[]= $this->dialogBox(array('content'=>$viewer['content'],'code'=>$viewer['code'],'labels'=>$labels),"Datas for link : ".$this->url_to_import,"info","");
				}
				//exit;
				break;

				
			case "check_facebook":
			case "check_facebook_forced":
			case "check_facebook_posts":
			case "check_facebook_posts_forced":
			case "check_facebook_galleries":
			case "check_facebook_galleries_forced":
			case "check_facebook_propositions":
			case "check_facebook_propositions_forced":
			
				if (stripos($this->redbox->action,'forced'))$forced=true;else $forced=false;

				if (stripos($this->redbox->action,'posts'))
					$graph_type = 'post';
				elseif (stripos($this->redbox->action,'galleries'))
					$graph_type = 'gallery';
				elseif (stripos($this->redbox->action,'proposition'))
					$graph_type = 'feed';
				else
					$graph_type = false;
					
				$checked = $this->redbox->facebook->update_fb_table($forced,$graph_type);
				if ($checked){
					$this->dialogs[]= $this->workingMessageFor($this->redbox->action,$checked);
				}
				else{
					$this->dialogs[]= $this->dialogBox($this->redbox->facebook->redbox_import_status($graph_type),REDBOX_CHECK_FACEBOOK,"success");
				}
				break;
		
			case "import_facebook":
			case "import_facebook_forced":
			case "import_facebook_posts":
			case "import_facebook_posts_forced":
			case "import_facebook_galleries":
			case "import_facebook_galleries_forced":
			case "import_facebook_propositions":
			case "import_facebook_propositions_forced":
			
				if (stripos($this->redbox->action,'forced'))$forced=true;else $forced=false;
				
				if (stripos($this->redbox->action,'posts'))
					$graph_type = 'post';
				elseif (stripos($this->redbox->action,'galleries'))
					$graph_type = 'gallery';
				elseif (stripos($this->redbox->action,'proposition'))
					$graph_type = 'feed';
				else
					$graph_type = false;
					
				$imported = $this->redbox->facebook->import_fb($forced,$graph_type);
				if ($imported){
					$this->dialogs[]= $this->workingMessageFor($this->redbox->action,$imported);
				}
				else{
					$this->dialogs[]= $this->dialogBox(REDBOX_IMPORT_FACEBOOK_SUCCESS,REDBOX_IMPORT_FACEBOOK_NEEDED,"success");
				}
				break;
				
			case "sync_facebook":
			case "sync_facebook_posts":
			case "sync_facebook_galleries":
			case "sync_facebook_propositions":
				
				if (stripos($this->redbox->action,'posts'))
					$graph_type = 'post';
				elseif (stripos($this->redbox->action,'galleries'))
					$graph_type = 'gallery';
				elseif (stripos($this->redbox->action,'propositions'))
					$graph_type = 'feed';
				else
					$graph_type = false;
					
				$synced = $this->redbox->facebook->sync_fb($graph_type);
				if ($synced){
					$this->dialogs[]= $this->workingMessageFor($this->redbox->action,$synced);
				}
				else{
					$this->dialogs[]= $this->dialogBox(REDBOX_SYNC_FACEBOOK_SUCCESS,REDBOX_SYNC_FACEBOOK,"success");
				}
				break;
			
			case "redbox_working_action":
				$this->dialogs[]= $this->workingMessageFor($this->working_action);
				break;
				
			case "redbox_working_action_mini":
				$this->dialogs[]= $this->workingMiniFor($this->working_action);
				break;
			
				
			case "redbox_proposition_approve":
				if( current_user_can( 'edit_posts' ) ){
					$sql = 'UPDATE ' . $wpdb->prefix .'comments 
					SET comment_approved="1" WHERE comment_ID='.$this->proposed_id;
					$wpdb->get_results($sql);
					// Send the notification via XMPP 
					$this->redbox->xmpp->send_notification_for_proposition($this->proposed_id);
					if ($this->dialogs[]=$this->propositionGetViewer($this->proposed_id)){
						return true;
					}
				}
				else{
					$this->dialogs[]= $this->propositionGetViewer($this->proposed_id).$this->dialogBox(array('content'=>REDBOX_ERROR_APPROVE_PROPOSITION_NOT_ALLOWED,'labels'=>$labels),REDBOX_ERROR_PROPOSITION,"warning");
				}
				return false;
				break;
				
			case "redbox_proposition_disapprove":
				if( current_user_can( 'edit_others_posts' ) ){
					$sql = 'UPDATE ' . $wpdb->prefix .'comments 
					SET comment_approved="0" WHERE comment_ID='.$this->proposed_id;
					$wpdb->get_results($sql);
					if ($this->dialogs[]=$this->propositionGetViewer($this->proposed_id)){
						return true;
					}
				}
				else{
					$this->dialogs[]= $this->propositionGetViewer($this->proposed_id).$this->dialogBox(array('content'=>REDBOX_ERROR_DISAPPROVE_PROPOSITION_NOT_ALLOWED,'labels'=>$labels),REDBOX_ERROR_PROPOSITION,"warning");
				}
				return false;
				break;
				
			case "redbox_proposition_delete":
			$delete_ok = false;
				if( current_user_can( 'edit_others_posts' ) ){
					//$this->dialogs[]=$this->propositionGetViewer($this->proposed_id);
					$delete_ok = true;
				}
				else{
					$sql = 'SELECT * FROM ' . $wpdb->prefix .'comments WHERE comment_id='.$this->proposed_id;
					if ($rows = $wpdb->get_results($sql)){
						foreach ($rows as $row){
							if (get_current_user_id() ==  $row->user_id){
								$delete_ok = true;
							}
						}
					}
				}
				if ($delete_ok){
					$sql = 'DELETE FROM ' . $wpdb->prefix .'commentmeta WHERE comment_id='.$this->proposed_id;
					$wpdb->get_results($sql);
					$sql = 'DELETE FROM ' . $wpdb->prefix .'comments WHERE comment_ID='.$this->proposed_id;
					$wpdb->get_results($sql);
					$this->dialogs[]= $this->propositionGetViewer();
					$this->dialogs[]= $this->dialogBox("",REDBOX_PROPOSITION_DELETED,"error");
				} else {
					$this->dialogs[]= $this->propositionGetViewer($this->proposed_id).$this->dialogBox(array('content'=>REDBOX_ERROR_DELETE_PROPOSITION_NOT_ALLOWED,'labels'=>$labels),REDBOX_ERROR_PROPOSITION,"warning");
				}
				break;
				
			case "redbox_post_proposed":
				// retrieve data container for the comment 
				$sql = 'SELECT * FROM ' . $wpdb->prefix .'commentmeta WHERE comment_id='.$this->proposed_id.' AND meta_key="redbox_data_container"';
				if ($rows = $wpdb->get_results($sql)){
					$retrieved = unserialize(stripslashes($rows[0]->meta_value));
					$this->redbox->retriever->list_datas = $retrieved; 
					// keep the result in the session to use it in the next user loop
					$_SESSION["redbox_retrieved_queue"] = serialize($retrieved);
					// get the viewer interface for datas we got
					$viewer =  $this->redbox->blog->get_datas_proposed_viewer($retrieved);
					// load the dialog box to the user
					if( !current_user_can( 'edit_posts' ) ){
						$this->dialogs[]= $this->dialogBox(array('content'=>REDBOX_ERROR_POST_NOT_ALLOWED,'labels'=>$labels),REDBOX_ERROR_POST,"warning");
					}
					else{
						if ($comment = get_comment($this->proposed_id)) {
							switch ($comment->comment_approved){
								case 'trash' : 
									$value=RESTORE;
									break;
								case '1' : 
									$value=DISAPPROVE;
									break;
								default : 
									$value=APPROVE;
									break;
							}
						}
						$labels = array('x'=> CLOSE,'y'=> PUBLISH,'n'=> $value,'c'=> CANCEL);
						$this->dialogs[]= $this->dialogBox(array('content'=>$viewer['content'],'code'=>$viewer['code'],'labels'=>$labels),REDBOX_IMPORT_DIALOG,"yes_no_cancel","redbox_do_post",',"'.$this->proposed_id.'","'.$comment->comment_approved.'"');
					}
				}
				break;
		
			case "redbox_propose_proposed":
				// retrieve data container for the comment 
				$sql = 'SELECT * FROM ' . $wpdb->prefix .'commentmeta WHERE comment_id='.$this->proposed_id.' AND meta_key="redbox_data_container"';
				if ($rows = $wpdb->get_results($sql)){	
					$retrieved = unserialize(stripslashes($rows[0]->meta_value));
					$this->redbox->retriever->list_datas = $retrieved; 
					// keep the result in the session to use it in the next user loop
					$_SESSION["redbox_retrieved_queue"] = serialize($retrieved);
					// get the viewer interface for datas we got
					$viewer =  $this->redbox->blog->get_datas_proposed_viewer($retrieved);
					// load the dialog box to the user
					$labels = array('x'=> CLOSE,'y'=> PROPOSE,'n'=> CANCEL,'c'=> CANCEL);
					if( !current_user_can( 'read' ) ){
						$this->dialogs[]= $this->dialogBox(array('content'=>REDBOX_ERROR_PROPOSITION_NOT_ALLOWED,'labels'=>$labels),REDBOX_ERROR_PROPOSITION,"warning");
					}
					else{
						$this->dialogs[]= $this->dialogBox(array('content'=>$viewer['content'],'code'=>$viewer['code'],'labels'=>$labels),REDBOX_PROPOSE_DIALOG,"yes_no","redbox_do_proposition");
					}
				}
				break;
		
			case "redbox_view_proposed":
				// retrieve data container for the comment 
				$sql = 'SELECT * FROM ' . $wpdb->prefix .'commentmeta WHERE comment_id='.$this->proposed_id.' AND meta_key="redbox_data_container"';
				if ($rows = $wpdb->get_results($sql)){	
					$retrieved = unserialize(stripslashes($rows[0]->meta_value));
					$this->redbox->retriever->list_datas = $retrieved; 
					// get the viewer interface for datas we got
					$viewer =  $this->redbox->blog->get_datas_proposed_viewer($retrieved);
					$labels = array('x'=> CLOSE,'y'=> PROPOSE,'n'=> CANCEL,'c'=> CANCEL);
					$this->dialogs[]= $this->dialogBox(array('content'=>$viewer['content'],'code'=>$viewer['code'],'labels'=>$labels),REDBOX_VIEW_DIALOG,"info");
				}
				else{
					$this->dialogs[]= $this->dialogBox(array('content'=>REDBOX_ERROR_PROPOSITION_NOT_ALLOWED,'labels'=>$labels),REDBOX_ERROR_PROPOSITION,"warning");
				}
				break;
				
			case "redbox_view_post":
				if ($post = get_post($this->proposed_id)){
					$retrieved = array();
					$retrieved[] = $this->redbox->manager->post_to_redbox_container($post);
					$this->redbox->retriever->list_datas = $retrieved; 
					// get the viewer interface for datas we got
					$viewer =  $this->redbox->blog->get_datas_proposed_viewer($retrieved);
					$labels = array('x'=> CLOSE,'y'=> VISIT,'n'=> CLOSE,'c'=> CANCEL);
					$this->dialogs[]= $this->dialogBox(array('content'=>$viewer['content'],'code'=>$viewer['code'],'labels'=>$labels),REDBOX_POST,"yes_no","redbox_go_to_post",',"'.get_permalink($this->proposed_id).'"');
				}
				else{
					$this->dialogs[]= $this->dialogBox(array('content'=>REDBOX_ERROR_PROPOSITION_NOT_ALLOWED,'labels'=>$labels),REDBOX_ERROR_PROPOSITION,"warning");
				}
				break;
		
			case "redbox_submit_from_admin_widget":
			case "redbox_submit_from_adminbar":
				if( current_user_can( 'read' ) ){
					if( !current_user_can( 'edit_posts' ) ){
						$mode = "propose";
					}
					else{
						$mode = "post";
					}
					// retrieve datas from urls we received
					$retrieved = $this->redbox->retriever->get_datas($this->url_to_import);
					if ($retrieved && count($retrieved) > 0){
						// keed the result in the session to use it in the next user loop
						$_SESSION["redbox_retrieved_queue"] = serialize($retrieved);
						$urls = array();
						foreach($retrieved as $datas) $urls[] = $datas->source;
						$clones = $this->redbox->manager->check_clones($urls,$mode);
						if (!$clones){
							// get the viewer interface for datas we got
							$viewer =  $this->redbox->blog->get_datas_proposed_viewer($retrieved);
							// load the dialog box to the user
							if( $mode == "post" ){
								$labels = array('x'=> CLOSE,'y'=> PUBLISH,'n'=> CANCEL,'c'=> CANCEL);
								$this->dialogs[]= $this->dialogBox(array('content'=>$viewer['content'],'code'=>$viewer['code'],'labels'=>$labels),REDBOX_IMPORT_DIALOG,"yes_no","redbox_do_post");
							}
							else{
								$labels = array('x'=> CLOSE,'y'=> PROPOSE,'n'=> CANCEL,'c'=> CANCEL);
								$this->dialogs[]= $this->dialogBox(array('content'=>$viewer['content'],'code'=>$viewer['code'],'labels'=>$labels),REDBOX_PROPOSE_DIALOG,"yes_no","redbox_do_proposition");
							}
						}
						else{
							$this->dialogs[]= $this->clone_dialog($clones,$mode);
						}
					}
					else{
						$this->dialogs[]= $this->dialogBox(REDBOX_ERROR_PROPOSITION_INVALID,REDBOX_ERROR_PROPOSITION,"warning");
					}
				}
				else{
					$this->dialogs[]= $this->dialogBox(REDBOX_ERROR_PROPOSITION_NOT_ALLOWED,REDBOX_ERROR_PROPOSITION,"warning");
				}
				break;
				
			case "redbox_submit_from_blog":
				if( current_user_can( 'read' ) ){
					$mode = "propose";
					// retrieve datas from urls we received
					$retrieved = $this->redbox->retriever->get_datas($this->url_to_import);
					if ($retrieved && count($retrieved) > 0){
						// keed the result in the session to use it in the next user loop
						$_SESSION["redbox_retrieved_queue"] = serialize($retrieved);
						$urls = array();
						foreach($retrieved as $datas) $urls[] = $datas->source;
						$clones = $this->redbox->manager->check_clones($urls,$mode);
						if (!$clones){
							// get the viewer interface for datas we got
							$viewer =  $this->redbox->blog->get_datas_proposed_viewer($retrieved);
							// load the dialog box to the user
							$labels = array('x'=> CLOSE,'y'=> PROPOSE,'n'=> CANCEL,'c'=> CANCEL);
							$this->dialogs[]= $this->dialogBox(array('content'=>$viewer['content'],'code'=>$viewer['code'],'labels'=>$labels),REDBOX_PROPOSE_DIALOG,"yes_no","redbox_do_proposition");
						}
						else{
							$this->dialogs[]= $this->clone_dialog($clones,$mode);
						}
					}
					else{
						$this->dialogs[]= $this->dialogBox(REDBOX_ERROR_PROPOSITION_INVALID,REDBOX_ERROR_PROPOSITION,"warning");
					}
				}
				else{
					$this->dialogs[]= $this->dialogBox(REDBOX_ERROR_PROPOSITION_NOT_ALLOWED,REDBOX_ERROR_PROPOSITION,"warning");
				}
				break;
			
			case "redbox_confirm_proposition":
				$retrieved = unserialize($this->datas_queue);
				if( current_user_can( 'edit_posts' ) ){
					$this->redbox->manager->insert_redbox_proposition($retrieved);
					$this->dialogs[]= $this->dialogBox(REDBOX_PROPOSITION_VALIDATED,REDBOX_IMPORT_THANKS,"success");
				}
				elseif( current_user_can( 'read' ) ){
					$this->redbox->manager->insert_redbox_proposition($retrieved);
					$this->dialogs[]= $this->dialogBox(REDBOX_PROPOSITION_DONE,REDBOX_IMPORT_THANKS,"success");
				}
				else{
					$this->dialogs[]= $this->dialogBox(REDBOX_ERROR_PROPOSITION_NOT_ALLOWED,REDBOX_ERROR_PROPOSITION,"warning");
				}
				unset($_SESSION["redbox_retrieved_queue"]);
				break;
			
			case "redbox_confirm_post":
				$retrieved = unserialize($this->datas_queue);
				if( !current_user_can( 'edit_posts' ) ){
					$this->dialogs[]= $this->dialogBox(REDBOX_ERROR_POST_NOT_ALLOWED,REDBOX_ERROR_POST,"warning");
				}
				else{
					$post_id = $this->redbox->manager->insert_redbox_post($retrieved);
					$this->target = "http://".$_SERVER["HTTP_HOST"]."/wp-admin/post.php?post=".$post_id."&action=edit";
					header("Location: ".$this->target);
					echo '<script>window.location.href = "'.$this->target.'";</script>';
					die();
				}
				unset($_SESSION["redbox_retrieved_queue"]);
				break;
			
			case "redbox_resync_post":
				$post_id = null;
				if( !current_user_can( 'edit_posts' ) ){
					$this->dialogs[]= $this->dialogBox(array('content'=>REDBOX_ERROR_POST_NOT_ALLOWED,'labels'=>$labels),REDBOX_ERROR_POST,"warning");
				}
				else{
					if ($retrieved = $this->redbox->retriever->get_datas($this->proposed_id)){
						$post_id = $this->redbox->manager->insert_redbox_post($retrieved);
						$this->target = "http://".$_SERVER["HTTP_HOST"]."/wp-admin/post.php?post=".$post_id."&action=edit";
						header("Location: ".$this->target);
						echo '<script>window.location.href = "'.$this->target.'";</script>';
						die();
					}
					else{
						$post_id = $this->redbox->manager->trash_post_by_id_fb($this->proposed_id);
						$this->target = "http://".$_SERVER["HTTP_HOST"]."/wp-admin/edit.php?post_status=trash&post_type=post";
						header("Location: ".$this->target);
						echo '<script>window.location.href = "'.$this->target.'";</script>';
						die();
					}
				}
				break;
			
			case "redbox_cancel_proposition":
				unset($_SESSION["redbox_retrieved_queue"]);
				break;
			
			case "redbox_go_to":
				$this->target = "http://".$_SERVER["HTTP_HOST"]."/?p=".$this->proposed_id;
				header("Location: ".$this->target);
				break;
				
			case "redbox_auto_check_fb_posts" : 
				$this->redbox->autoUpdate->redbox_auto_check_fb_posts();
				break;
			
			case "redbox_auto_check_fb_feed" : 
				$this->redbox->autoUpdate->redbox_auto_check_fb_feed();
				break;
			
			default:
				$this->dialogs[]= $this->dialogBox(REDBOX_ERROR_UNSUPPORTED_ACTION,REDBOX_ERROR,"warning");
				break;
		}
		
	}
	
	// this include dialogs results from actions
	public function redbox_enqueue_dispatcher(){
		foreach ($this->dialogs as $dialog){
			echo $dialog;
		}
	}
	
	// this handle the clone dialogBox for propostions that already exists
	public function clone_dialog($clones,$mode='post'){
		$content='';
		$title = REDBOX_IMPORT_THANKS;
		$type = "info";
		if ($mode=='post'){
			$labels = array('x'=> CLOSE,'y'=> IMPORT,'n'=> CANCEL,'c'=> CANCEL);
			if( current_user_can( 'edit_posts' ) ){
				$question = " ". REDBOX_IMPORT_ANYWAY;
				$type = "yes_no";
				$function='redbox_do_post';
			}
		}
		else{
			$labels = array('x'=> CLOSE,'y'=> PROPOSE,'n'=> CANCEL,'c'=> CANCEL);
			if( current_user_can( 'edit_posts' ) ){
				$question = " ". REDBOX_PROPOSE_ANYWAY;
				$type = "yes_no";
				$function='redbox_do_proposition';
			}
		}
		foreach($clones as $clone){
			$content = "<h5>";
			if($clone['type']=='post'){
				if($clone['strict']){
					$content.= REDBOX_PROPOSITION_ALREADY_ON_BLOG;
				}
				else{
					$content.= REDBOX_PROPOSITION_CONTENT_ON_BLOG;
				}
				$post = get_post($clone['id']);
				$comment = null;
				$content.= $question;
				$content.= "</h5><br />" . $this->redbox->blog->get_datas_mini_viewer(unserialize($clone['data_container']),array('comment'=>$comment,'post'=>$post,'mode'=>$mode)) . "<br />";
			}
			elseif($clone['type']=='proposition'){
				if($clone['strict']){
					$content.= REDBOX_PROPOSITION_ALREADY_DONE;
				}
				else{
					$content.= REDBOX_PROPOSITION_CONTENT_DONE;
				}
				$post=null;
				$comment = get_comment($clone['id']);
				$content.= $question;
				$content.= "</h5><br />" . $this->redbox->blog->get_datas_mini_viewer(unserialize($clone['data_container']),array('comment'=>$comment,'post'=>$post,'mode'=>$mode)) . "<br />";
			}
		}
		return $this->dialogBox(array('content'=>$content,'labels'=>$labels),$title,$type,$function);
	}
	/**
	 * Add a dialog box that will be displayed to the user.
	 * Types are : message, success, info,error,warning,"yes_no","yes_no_cancel"
	 *--------------------------------------------------------------------------
	 * For "yes_no" and "yes_no_cancel", you have to give a function name to run. The value will be returned to it
	 * If your function doesn't exists, you can give the function code to integrate with the generated html/js code
	 */
	public function dialogBox($message,$title='Info',$type='info',$function_to_run='',$function_args='',$function_code=''){
		if (!is_array($message)) $message = array('content'=>$message,'labels'=>array('x'=> CLOSE,'y'=> YES,'n'=> NO,'c'=> CANCEL));
		$dialog = '<div id="redbox_dialog_message" style="display:none">'.$message['content'].'</div>';
		$dialog.='<script>'."\n".'new Messi(';
		$dialog.="document.getElementById(\"redbox_dialog_message\").innerHTML";
		$style ='';
		$buttons='';
		$callback='';
		
		$buttonClose		= "{id: 0, label: '".$message['labels']['x']."', val: 'X'}";
		$buttonYes		= "{id: 0, label: '".$message['labels']['y']."', val: 'Y', class: 'btn-success'}";
		switch ($message['labels']['n']){
			case APPROVE : 
				$class = ", class: 'btn-info'";
				break;
			case DISAPPROVE : 
				$class = ", class: 'btn-warning'";
				break;
			default : 
				$class = ", class: 'btn-danger'";
				break;
		}
		$buttonNo		= "{id: 1, label: '".$message['labels']['n']."', val: 'N'".$class."}";
		switch ($message['labels']['c']){
			case APPROVE : 
				$class = ", class: 'btn-info'";
				break;
			case DISAPPROVE : 
				$class = ", class: 'btn-warning'";
				break;
			default : 
				$class = "";
				break;
		}
		$buttonCancel		= "{id: 2, label: '".$message['labels']['c']."', val: 'C'".$class."}";
		$type			= (($title!=''&& $type=='') ? 'info' : $type );
		switch ($type){
				case 'success':
						$title = (($title=='') ? SUCCESS : $title );
						$style = "anim success";
						$buttons = "[".$buttonClose."]";
						break;
				case 'info':
						$title = (($title=='') ? INFO : $title );
						$style = "anim info";
						$buttons = "[".$buttonClose."]";
						break;
				case 'warning':
						$title = (($title=='') ? WARNING : $title );
						$style = "anim warning";
						$buttons = "[".$buttonClose."]";
						break;
				case 'error':
						$title = (($title=='') ? ERROR : $title );
						$style = "anim error";
						$buttons = "[".$buttonClose."]";
						break;
				case 'yes_no':
						$style = "anim";
						$title = (($title=='') ? QUESTION : $title );
						$buttons = "[".$buttonYes.",".$buttonNo."]";
						$callback= (($function_to_run=='') ? '' : ", callback: function(val){".$function_to_run . "(val".$function_args.");}");
						break;
				case 'yes_no_cancel':
						$style = "anim";
						$title = (($title=='') ? QUESTION : $title );
						$buttons = "[".$buttonYes.",".$buttonNo.",".$buttonCancel."]";
						$callback= (($function_to_run=='') ? '' : ", callback: function(val){".$function_to_run . "(val".$function_args.");}");
						break;
		}
		if ($title!='') {
			$dialog.=", {title: '".addslashes($title)."', modal: true";
			if ($style!='') {
					$dialog.=", titleClass: '".$style."'";
			}
			$dialog.=", buttons: ".$buttons;
			$dialog.= $callback."\n";
			$dialog.='}'."\n";
		}
		$dialog.=');</script>'."\n";
		$dialog ='<script>'."\n".$function_code."\n".'</script>'."\n".$dialog .'<script>document.getElementById("redbox_dialog_message").innerHTML="";</script>';
		if (isset($message['code'])) $dialog.= $message['code'];
		
		return $dialog;
	}
	
	public function workingMessageFor($working_action=null,$content=null){
		if ($working_action!=null) $this->working_action = $working_action;
		$message ='<div class="working_action_box messi"><div class="working_action_box_content messi-box">';
		
		if($content) {
			$message.= $content;
		}
		else{
			$message.='<div class="messi-titlebox anim">';
			//$message.='<div class="working_action_box_loader"><img src="'.plugins_url().'/redbox/img/loader.gif"/></div>';
			$message.='<div class="working_action_box_message">';
			switch ($this->working_action){
	
				case "check_facebook":
					$message.= REDBOX_CHECK_FACEBOOK_WORKING;
					break;
				case "check_facebook_posts":
					$message.= REDBOX_CHECK_FACEBOOK_POSTS_WORKING;
					break;
				case "check_facebook_galleries":
					$message.= REDBOX_CHECK_FACEBOOK_GALLERIES_WORKING;
					break;
				case "check_facebook_propositions":
					$message.= REDBOX_CHECK_FACEBOOK_PROPOSITIONS_WORKING;
					break;
		
				case "check_facebook_forced":
					$message.= REDBOX_CHECK_FACEBOOK_FORCED_WORKING;
					break;
				case "check_facebook_posts_forced":
					$message.= REDBOX_CHECK_FACEBOOK_POSTS_FORCED_WORKING;
					break;
				case "check_facebook_galleries_forced":
					$message.= REDBOX_CHECK_FACEBOOK_GALLERIES_FORCED_WORKING;
					break;
				case "check_facebook_propositions_forced":
					$message.= REDBOX_CHECK_FACEBOOK_PROPOSITIONS_FORCED_WORKING;
					break;
		
				case "import_facebook":
				case "import_facebook_posts":
				case "import_facebook_galleries":
					$message.= REDBOX_IMPORT_FACEBOOK_WORKING;
					break;
		
				case "import_facebook_forced":
				case "import_facebook_posts_forced":
				case "import_facebook_galleries_forced":
				case "import_facebook_posts_continue":
					$message.= REDBOX_IMPORT_FACEBOOK_FORCED_WORKING;
					break;
		
				case "import_facebook_propositions":
					$message.= REDBOX_IMPORT_FACEBOOK_PROPOSITIONS_WORKING;
					break;
				case "import_facebook_propositions_forced":
					$message.= REDBOX_IMPORT_FACEBOOK_PROPOSITIONS_FORCED_WORKING;
					break;
				
				case "sync_facebook":
				case "sync_facebook_posts":
				case "sync_facebook_galleries":
				case "sync_facebook_propositions":
					$message.= REDBOX_SYNC_FACEBOOK_WORKING;
					break;
				
				case "redbox_submit_from_blog":
					$message.= REDBOX_PROPOSITION_QUERYING;
					break;
		
				case "redbox_resync_post":
					$message.= REDBOX_RESYNCING_POST;
					break;
		
				default:
					$message.= REDBOX_NO_MESSAGE;
					break;
		
			}
			$message.= '</div></div><div class="messi-wrapper messi-content">';
			$message.= '<div class="messi-footbox btnbox">
					<input type="button" onclick="redbox_ajax_do(false);" class="btn btn-danger" value="'.STOP.'" />
				</div></div>';
		}
		
		$message.='</div></div><script>redbox_ajax_do("'.$this->working_action.'","'.$this->proposed_id.'");</script>';
		return $message;
	}
	
	public function workingMiniFor($working_action=null,$content=null){
		if ($working_action!=null) $this->working_action = $working_action;
		$message ='<div class="redbox_waiting_mini"><img src="'.plugins_url().'/redbox/img/loading.gif" /></div>';
		
		$message.='<script>redbox_ajax_do("'.$this->working_action.'","'.$this->proposed_id.'");</script>';
		return $message;
	}
	
	public function propositionGetViewer($proposed_id=null){
		global $wpdb;
		if ($proposed_id!=null) $this->proposed_id = $proposed_id;
		$return = '';
		$comment = get_comment($this->proposed_id);
		$post = null;
		$sql = 'SELECT * FROM ' . $wpdb->prefix .'commentmeta WHERE meta_key="redbox_data_container" AND comment_id='.$this->proposed_id;
		if ($rows = $wpdb->get_results($sql)){
			$list_datas = unserialize($rows[0]->meta_value);
			$sql = 'SELECT * FROM ' . $wpdb->prefix .'commentmeta WHERE meta_key="redbox_post_id" AND comment_id='.$this->proposed_id;
			$rows = $wpdb->get_results($sql);
			foreach ($rows as $row){
				if ($post = get_post($row->meta_value)) {
					break;
				}
			}
		}
		$return = $this->redbox->blog->get_datas_fancy_viewer($list_datas,array('comment'=>$comment,'post'=>$post),3);
		if ($return){
			/*$return.="<script>
				var container = document.querySelector('#redbox_container');
				var msnry = new Masonry( container, {itemSelector: '.redbox_item'});</script>";*/
		}
		return $return;
	}
}

// Recursive finder
function RecursiveFolder($sHOME) {
  global $BOMBED, $WIN;

  $win32 = ($WIN == 1) ? "\\" : "/";

  $folder = dir($sHOME);

  $foundfolders = array();
  while ($file = $folder->read()) {
	echo $file.'<br>';
    if($file != "." and $file != "..") {
      if(filetype($sHOME . $win32 . $file) == "dir"){
	$foundfolders[count($foundfolders)] = $sHOME . $win32 . $file;
      } else {
	$content = file_get_contents($sHOME . $win32 . $file);
	$BOM = SearchBOM($content);
	if ($BOM) {
	  $BOMBED[count($BOMBED)] = $sHOME . $win32 . $file;

	  // Remove first three chars from the file
	  $content = substr($content,3);
	  // Write to file 
	  file_put_contents($sHOME . $win32 . $file, $content);
	}
      }
    }
  }
  $folder->close();

  if(count($foundfolders) > 0) {
    foreach ($foundfolders as $folder) {
      RecursiveFolder($folder, $win32);
    }
  }
}

// Searching for BOM in files
function SearchBOM($string) { 
    if(substr($string,0,3) == pack("CCC",0xef,0xbb,0xbf)) return true;
    return false; 
}

?>
