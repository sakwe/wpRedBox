<?php
/* Blog integration
 *
 **/


class RedBoxBlog{

	public function __construct(&$redbox){
		// connect to the global redbox instance
		$this->redbox = $redbox;
		$this->dialogs=array();
		// this will load the header for the blog integration
		add_action('admin_head', array(&$this, "admin_header"));
		
		// add ajax url for the frontend
		add_action('wp_head',array(&$this,'pluginname_ajaxurl'));
		
		// this will load script, css in admin for everybody
		add_action('admin_enqueue_scripts', array(&$this, "enqueue_wp_scripts"));
		
		// this will load script, css in the blog 
		add_action('wp_enqueue_scripts', array(&$this, "enqueue_wp_scripts"));
		
		// this will redirect the default template for the "RedBox" blog page and its comments part
		add_action("template_redirect", array(&$this, 'redbox_theme_redirect'));
		add_filter("comments_template", array(&$this,"redbox_comment_template"));
		
		add_action('pre_get_comments',array(&$this,'redbox_comment_filter'));
		
		add_action('init', array(&$this,'habfna_disable_admin_bar'), 9);
	}
	
	
	public function admin_header() {
		global $post_type;
		echo '<style>';
		echo '#icon-redbox { background:transparent url('.plugins_url().'/redbox/img/redbox.png) no-repeat !important; }';
		echo '</style>';
	}
	
	function pluginname_ajaxurl() {
		?>
		<script type="text/javascript">
		var ajaxurl = '<?php echo admin_url('admin-ajax.php'); ?>';
		</script>
		<?php
	}
	
	
	public function habfna_hide_admin_bar_settings(){
		echo '<style type="text/css">.show-admin-bar {display: none;}</style>';
	}
	
	public function habfna_disable_admin_bar()
	{
		if(!current_user_can('edit_posts'))
		{
			add_filter( 'show_admin_bar', '__return_false' );
			add_action( 'admin_print_scripts-profile.php', array(&$this,'habfna_hide_admin_bar_settings') );
		}
	}
		
	
	public function redbox_comment_filter($query){

		//$query->query_vars['status']='hold';
		//return $query;
	}
	
	public function get_datas_proposed_viewer($list_datas){
		// FOR TESTS
		//return $this->get_datas_tabs_viewer($list_datas);
		//__________
		
		// get proposed auto datas
		$datas = $this->redbox->retriever->get_proposed_import();
		
		$proposition_content.= '<div class="proposition_content" id="proposition_content">'."\n";
		
		if(trim($datas->author_name)!='') {
			$proposition_content.= '<div class="data_author">';
			if(trim($datas->author_url)!='') $proposition_content.=  '<a href="' . $datas->author_url .'">'.$datas->author_name.'</a>';
			else $proposition_content.=  $datas->author_name;
			if(trim($datas->author_picture->url)!='') {
				$proposition_content.= '<div class="data_author_thumb"><a href="' . $datas->author_picture->url .'" title="' . $datas->author_picture->url .'"><img id="profile" src="' . $datas->author_picture->url .'" /></a></div>';
			}
			$proposition_content.='</div>';
			$proposition_content.= '<div class="clear"></div>';
		}

		$icon = $this->redbox_get_icon_for($datas->type);
		
		$proposition_content.= '<div class="data_title"><h5><i class="'.$icon.'"></i>&nbsp;&nbsp;'.$datas->title.'</h5></div>';
		
		$proposition_video=null;
		if(trim($datas->video_datas->embed)!='') {
			$proposition_video= '<div class="redbox_fitvid">';
			$proposition_video.= stripslashes(htmlspecialchars_decode(do_shortcode($datas->video_datas->embed)));
			$proposition_video.= '</div>';
		}
		
		$proposition_carousel=null;
		switch(count($datas->pictures)){
			case 1:
				$width='650';
				$height='400';
				break;
			case 2:
				$width='350';
				$height='250';
				break;
			case 3:
				$width='300';
				$height='250';
				break;
				
			default:
				$width='250';
				$height='200';
				break;
		}
		if (count($datas->pictures)>0){
			$proposition_carousel= '<div class="redbox_carousel data_content"><ul id="carousel" class="elastislide-list">';
			// show pictures
			foreach ($datas->pictures as $picture){
				if(trim($picture->url)!='') $proposition_carousel.= '<li><a class="redbox_nailthumb"><img src="' . trim($picture->url) .'" /></a></li>';
			}
			$proposition_carousel.= '</ul></div>';
		}

		if	($proposition_video!=null) $proposition_content.= $proposition_video;
		elseif	($proposition_carousel!=null) $proposition_content.= $proposition_carousel;

		if(trim($datas->description)!='') $proposition_content.= '<div id="redbox_scroller" class="data_content"><br />' . nl2br($datas->description).'</div>';
		
		if	($proposition_carousel!=null && $proposition_video!=null && (count($datas->pictures)>2)) $proposition_content.= $proposition_carousel;
		
		if(trim($datas->created)!='') {
			setlocale(LC_ALL, 'fr_FR');
			$date = strftime("%e %B %G",strtotime(stripslashes($datas->created)));
			$proposition_content.= '<div style="text-align:right;width:100%;padding-top:10px;"><span>'.utf8_encode($date).'</span></div>';
		}
		
		$proposition_content.= '<div class="clear"></div>';
		
		$proposition_content.= '</div></div>';

		$code = '<script>
				function redbox_load_dialogs(){
					jQuery(document).ready(function($) {
						$("#redbox_scroller").mCustomScrollbar({
							scrollButtons:{
								enable:true
							},
							callbacks:{
								onScroll:function(){
									$("."+this.attr("id")+"-pos").text(mcs.top);
								}
							}
						});
					
						$("#carousel" ).elastislide();
						
						$(".redbox_fitvid").fitVids();
						
						jQuery(".redbox_nailthumb").nailthumb({width:'.$width.',height:'.$height.'});
					});  
				}
				redbox_load_dialogs(); 
			</script>';

		return array('content'=>$proposition_content,'code'=>$code);
	}
	
	
	public function get_datas_tabs_viewer($list_datas,$with_modifier=false,$short_description=false){
		$js_tabs_loading='';
		$tabs_content = '';
		$tabs_headers='<ol id="toc">'."\n";
		$i=0;
		foreach($list_datas as $datas){
			$i++;
			$tabs_headers.= '<li><a href="#tab=t_'.$i.'" id="tab_t_'.$i.'" name="tab_t_'.$i.'">
						<span>'.$datas->origin.'</span></a></li>'."\n";
		
			$tabs_content.= '<div class="content" id="t_'.$i.'">'."\n";
			
			$tabs_content.= '<div class="data_category"><b>'. $datas->category.'</b> ('. $datas->type.')</div>';
			
			if(trim($datas->title)!='') $tabs_content.= '<div class="data_title"><b>' . $datas->title.'</b></div>';
			
			if(trim($datas->message)!='') $tabs_content.= '<div class="data_message"><b>Message : </b>' . nl2br($datas->message).'</div>';
			
			if(trim($datas->created)!='') $tabs_content.= '<div class="data_date"><b>Date : </b>' . $datas->created.'</div>';
			
			if(trim($datas->author_name)!='') {
				$tabs_content.= '<div class="data_author"><b>Auteur : </b>';
				if(trim($datas->author_url)!='') $tabs_content.=  '<a href="' . $datas->author_url .'">'.$datas->author_name.'</a>';
				else $tabs_content.=  $datas->author_name;
				if(trim($datas->author_picture->url)!='') {
					$tabs_content.= '<div class="data_author_thumb"><a href="' . $datas->author_picture->url .'" title="' . $datas->author_picture->url .'"><img id="profile" src="' . $datas->author_picture->url .'" /></a></div>';
				}
				$tabs_content.='</div>';
			}
						
			$base = $datas->description;
			preg_match('/^(?>\S+\s*){1,50}/', $base, $match);
			if ($match){
				$base = $match[0]."...";
			}
			if(trim($base)!='') $tabs_content.= '<div class="data_description"><b>Description : </b>' . $datas->description.'</div>';
			foreach ($datas->pictures as $picture){	
				$tabs_content.= $picture->url.'<br />';
				if(trim($picture->url)!='') $tabs_content.= '<div class="data_thumb"><a href="' . $picture->url .'" title="' . $picture->title .'">
										<img src="' . $picture->url .'" /></a>';
				if(trim($picture->width)!='') $tabs_content.= '<div class="data_thumb_dim">' . $picture->width.'/' . $picture->height.'</div></div>';
			}
			if(trim($datas->video_datas->img->url)!='') {
				$tabs_content.= '<div class="data_thumb"><img src="' . $datas->video_datas->img->url .'" />';
				$tabs_content.= '<div class="data_thumb_dim">' . $datas->video_datas->img->width.'/' . $datas->video_datas->img->height.'</div></div>';
			}
			$tabs_content.= '<div class="clear"></div>';
			if(trim($datas->video_datas->description)!='') $tabs_content.= '<div class="data_description"><b>Description : </b>' . nl2br($datas->video_datas->description).'</div>';
			
			if(trim($datas->video_datas->embed)!='') $tabs_content.= '<div class="data_video">' . $datas->video_datas->embed.'</div>';

			$tabs_content.= '</div>';

			// tabs js loading code
			if ($js_tabs_loading != '') $js_tabs_loading.=', ';
			$js_tabs_loading.='"t_'.$i.'"';
		}
		$tabs_headers.= "</ol>";
		$js_tabs_loading= $hiddens.'<script type="text/javascript">'."\n".'activatables("tab", [' . $js_tabs_loading . ']);'."\n".'</script>'."\n";
		
		$tabs = '<div class="tabs">'."\n".$tabs_headers."\n".$tabs_content."\n".'</div>';
		return array('content'=>$tabs,'code'=>$js_tabs_loading);
	}
	
	public function get_datas_fancy_viewer($list_datas,$linked=null,$post=null){
		global $wpdb;

		if (is_array($linked)){
			$comment = $linked['comment'];
			$post = $linked['post'];
			if (!isset($linked['mode'])) $linked['mode']='post';
			$mode = $linked['mode'];
		}
		elseif ($linked){
			$comment = $linked;
			$post = null;
			$mode = 'post';
		}
		elseif ($post){
			$mode = 'post_to_redbox';
		}
		$datas=$this->redbox->retriever->get_proposed_import($list_datas);
		
		$icon = $this->redbox_get_icon_for($datas->type);
		
		if (trim($datas->author_picture->url)!=''){
			$image = '<img src="'.$datas->author_picture->url.'" class="redbox_mini_icon"/>&nbsp;&nbsp;';
		}
		else{
			$image = '<i class="'.$icon.'"></i>&nbsp;&nbsp;';
		}
		$author='';
		$can_publish=false;
		$can_edit=false;
		if ($post && !($mode == 'post_to_redbox')){
			if ($post->post_status == "publish"){
				$title_class = "redbox-titlebox anim success";
				$a_tag_content = 'target="_blank" href="http://'.$_SERVER['HTTP_HOST'].'?p='.$post->ID.'" title="'.VISIT.'"';
				if (current_user_can( 'edit_others_posts' )){
					$can_edit=true;
				}
			}
			else{
				if (current_user_can( 'edit_others_posts' )){
					$title_class = "redbox-titlebox anim warning";
					$target = "http://".$_SERVER["HTTP_HOST"]."/wp-admin/post.php?post=".$post->ID."&action=edit";
					$a_tag_content = 'target="_blank" href="'.$target.'" title="'.PUBLISH.'"';
					$can_edit=true;
				}
				elseif (current_user_can( 'edit_posts' )){
					if(get_current_user_id()==$post->post_author ){
						$title_class = "redbox-titlebox anim warning";
						$target = "http://".$_SERVER["HTTP_HOST"]."/wp-admin/post.php?post=".$post->ID."&action=edit";
						$a_tag_content = 'target="_blank" href="'.$target.'" title="'.PUBLISH.'"';
						$can_edit=true;
					}
					else{
						$title_class = "redbox-titlebox anim success";
						$a_tag_content = 'href="javascript:redbox_ajax_do(\'redbox_view_proposed\','.$comment->comment_ID.');" title="'.VIEW_PROPOSITION.'"';
					}
				}
				else{
					$title_class = "redbox-titlebox anim info";
					$a_tag_content = 'href="javascript:redbox_ajax_do(\'redbox_view_proposed\','.$comment->comment_ID.');" title="'.VIEW_PROPOSITION.'"';
				}
			}
			if (current_user_can( 'edit_posts' )){
				if ($post->post_status != "publish"){
					$author_title = EDITED_BY;
				}
				else{
					$author_title = PUBLISHED_BY;
				}
				$user = get_user_by( 'id', $post->post_author );
				$author = '<div class="redbox_minibox_icon">'.$author_title.' <a target="_blank" href="'.$user->user_url.'">'.$user->display_name.'</a></div>';
			}
		}
		elseif ($comment){
			if (count($list_datas)>0 && $list_datas[0]->url!=''){
				$options = get_option('redbox_options');
				$sql = 'SELECT ID FROM ' . $wpdb->prefix .'posts WHERE post_name="'.$options['redbox_page_name'].'"';
				$rows = $wpdb->get_results($sql);
				if ($mode=='post' && current_user_can( 'edit_posts' )){
					$can_publish=true;
					$title_class = "redbox-titlebox anim info";
					$a_tag_content = 'href="javascript:redbox_ajax_do(\'redbox_post_proposed\','.$comment->comment_ID.');" title="'.PUBLISH.'"';
				}
				else{
					$title_class = "redbox-titlebox anim info";
					$a_tag_content = 'href="javascript:redbox_ajax_do(\'redbox_view_proposed\','.$comment->comment_ID.');" title="'.VIEW_PROPOSITION.'"';
				}
				switch ($comment->comment_approved){
					case '1' : 
						$title_class = "redbox-titlebox anim info";
						break;
					case 'trash':
						$title_class = "redbox-titlebox anim error";
						break;
					default : 
						$title_class = "redbox-titlebox anim warning";
						break;
				}
			}
		}
		elseif($mode == 'post_to_redbox'){
			$title_class = "redbox-titlebox anim success";
			$a_tag_content = ' target="_blank" href="'.get_permalink($post->ID).'" title="'.VISIT.'"';
			if (current_user_can( 'edit_others_posts' )){
				$can_edit=true;
			}
		}
		$mini_viewer = '';
		if($mode == 'post_to_redbox'){
			$item_id = $post->ID;
			$mini_viewer.= '<div class="four columns portfolio-item" id="redbox-item-'.$post->ID.'">';
			$mini_viewer.= '<span class="'.$title_class.'" style="display:inline-block;width:100%;height:100%;">';
			$mini_viewer.= '<div class="redbox_minibox_icon">'.$image.$datas->category.'</div>';
			//$mini_viewer.= '<div class="redbox_minibox_author">'.PUBLISHED_BY.' '.$datas->author_url.'</div>';
			$mini_viewer.= '</span>';
		}else{
			$item_id = $comment->comment_ID;
			$mini_viewer.= '<div class="four columns portfolio-item" id="redbox-item-'.$comment->comment_ID.'">';
			$mini_viewer.= '<span class="'.$title_class.'" style="display:inline-block;width:100%;height:100%;">';
			$mini_viewer.= '<div class="redbox_minibox_icon">'.$image.$datas->category.'</div>';
			//$mini_viewer.= '<div class="redbox_minibox_author">'.PROPOSED_BY.' <a target="_blank" href="'.$comment->comment_author_url.'">'.$comment->comment_author.'</a></div>';
			$mini_viewer.= '</span>';
		}
		// hack to fit image at best and leave it work with isotope at the same time...
		$code=''; 
		$maxWidth = 350;
		$maxHeight = 232;
		$heihtWillHave = ( $datas->pictures[0]->height / $datas->pictures[0]->width ) * $maxWidth;
		if ($heihtWillHave > $maxHeight){
			$width = $datas->pictures[0]->width;
			$code.= '<script>
					jQuery(document).ready(function($) {
						jQuery("#redbox_nailthumb_'.$item_id.'").nailthumb({width:'.$maxWidth.',height:'.$maxHeight.',fitDirection:\'top center\'});
					}); 
				</script>';
		} else {
			$widthWillHave = ( $datas->pictures[0]->width / $datas->pictures[0]->height ) * $maxHeight;
			if ($widthWillHave < $maxWidth && $datas->pictures[0]->width > 0){
				$height = $datas->pictures[0]->height;
				$code.= '<script>
						jQuery(document).ready(function($) {
							jQuery("#redbox_nailthumb_'.$item_id.'").nailthumb({width:'.$maxWidth.',height:'.$maxHeight.',fitDirection:\'top center\'});
						}); 
					</script>';
			}
		}
		$withHeight = "";
		if (trim($code)!="") {
			$withHeight = "width:".$maxWidth."px;height:".$maxHeight."px;";
		}
		$mini_viewer.= '<a class="image-overlay project-overlay" '. $a_tag_content.'>';
		$mini_viewer.= '<div class="portfolio-item-top redbox-item-top" style="text-align:center;'.$withHeight.'">';
		if (trim($datas->pictures[0]->url)!=''){
			$picture_url = $datas->pictures[0]->url;
		}
		else{
			$picture_url = WP_PLUGIN_URL."/redbox/img/redbox.jpg";
		}
		$mini_viewer.= '<img style="display:inline-block;text-align:center;" id="redbox_nailthumb_'.$item_id.'" src="'.$picture_url.'" alt="'.$datas->title.'" title="'.$datas->title.'" />';
		$mini_viewer.= '<div class="over-bg"></div>';
		$mini_viewer.= '<div class="over-info"><i class="'.$icon.'"></i></div>';
		$mini_viewer.= '</div>';
		$mini_viewer.= '<div class="portfolio-item-info style="margin-top:Opx;padding-top:0px;">';
		$mini_viewer.= '<h5>'.$datas->title.'</h5>';
		if (trim($datas->short_description)!=''){
			$match[0]= $datas->short_description;
		}
		else{
			preg_match('/^(?>\S+\s*){1,40}/', $datas->message, $match);
			if (trim($match[0])=='') $match[0]= $datas->message;
		}
		$mini_viewer.= '<div style="margin-top:10px;">'.$match[0].'</div>';
		
		$mini_viewer.= '</div>';
		$mini_viewer.= '</a>';
		if ($mode == 'post_to_redbox'){
			setlocale(LC_ALL, 'fr_FR');
			$date = strftime("%e %B %G",strtotime(stripslashes($datas->created)));
			$mini_viewer.= '<div style="text-align:right;width:100%;"><span>'.utf8_encode($date).'</span></div>';
		}else{
			if (trim($comment->comment_author) != ''){
				$mini_viewer.= '<div style="text-align:right;width:100%;padding-top:10px;">'.PROPOSED_BY.' <a target="_blank" href="'.$comment->comment_author_url.'">'.$comment->comment_author.'</a></div>';
			}
		}
		$mini_viewer.= '<span style="display:inline-block;width:100%;height:100%;">'.$author.'<div class="redbox_minibox_import redbox_button_box">';
		
		
		if ($can_edit && $mode != 'post_to_redbox'){
			if (current_user_can( 'edit_others_posts' ) && $post->post_status != "publish"){
				$btn_style='redbox_button-success';
				$btn_title= EDIT."/".PUBLISH;
			}
			else{
				$btn_style='';
				$btn_title=EDIT;
			}
			$target = "http://".$_SERVER["HTTP_HOST"]."/wp-admin/post.php?post=".$post->ID."&action=edit";
			$mini_viewer.= '<input type="button" 
					onclick="window.location.href=\''.$target.'\';" 
					class="redbox_button '.$btn_style.'" value="'.$btn_title.'"/>';
		}
		
		if (!$post && current_user_can( 'edit_posts' ) && $list_datas && count($list_datas) > 0){
			if ($can_publish){
				$mini_viewer.= '<input type="button" 
						onclick="redbox_ajax_do(\'redbox_post_proposed\','.$comment->comment_ID.');" 
						class="redbox_button redbox_button-success" value="'.PUBLISH.'"/>';
			}
			
			switch ($comment->comment_approved){
				case 'trash' : 
					if (current_user_can( 'edit_others_posts')){
						$mini_viewer.= '<input type="button" 
							onclick="redbox_ajax_do(\'redbox_proposition_disapprove\',\''.$comment->comment_ID.'\')" 
							class="redbox_button redbox_button-warning" value="'.RESTORE.'"/>';
					}
					break;
				case '1' : 
					if (current_user_can( 'edit_others_posts')){
						$mini_viewer.= '<input type="button" 
							onclick="redbox_ajax_do(\'redbox_proposition_disapprove\',\''.$comment->comment_ID.'\')" 
							class="redbox_button redbox_button-warning" value="'.DISAPPROVE.'"/>';
					}
					break;
				default : 
					$mini_viewer.= '<input type="button" 
							onclick="redbox_ajax_do(\'redbox_proposition_approve\',\''.$comment->comment_ID.'\')" 
							class="redbox_button redbox_button-info" value="'.APPROVE.'"/>';
					if (current_user_can( 'edit_others_posts')){
						$mini_viewer.= '<input type="button" 
							onclick="redbox_ajax_do(\'redbox_proposition_delete\',\''.$comment->comment_ID.'\')" 
							class="redbox_button redbox_button-danger" value="'.DELETE.'"/>';
					}
					break;
			}
		} elseif (get_current_user_id() && get_current_user_id() ==  $comment->user_id){
			$mini_viewer.= '<input type="button" 
					onclick="redbox_ajax_do(\'redbox_proposition_delete\',\''.$comment->comment_ID.'\')" 
					class="redbox_button redbox_button-danger" value="';
			if ($post->post_status == "publish") $mini_viewer.=CLEAN;
			else $mini_viewer.=CANCEL;
			$mini_viewer.='"/>';
		
		}
		$mini_viewer.= '</span></div>';
		$mini_viewer.= '</div>';
		
		return $mini_viewer.$code;
	}
	
	
	public function get_datas_mini_viewer($list_datas,$linked=null,$number_on_width=1){
		global $wpdb,$post;
		$mini_viewer = '';
		if (is_array($linked)){
			$comment = $linked['comment'];
			$post = $linked['post'];
			if (!isset($linked['mode'])) $linked['mode']='post';
			$mode = $linked['mode'];
		}
		else{
			$comment = $linked;
			$post = null;
			$mode = 'post';
		}
		$datas=$this->redbox->retriever->get_proposed_import($list_datas);
		$mini_viewer.= '<div class="redbox_proposition_box" style="width:'.round((100/$number_on_width),0).'%"><div class="redbox_proposition_box_content';
		if ($post) $mini_viewer.= " published";
		$mini_viewer.= '">';
		
		$icon = $this->redbox_get_icon_for($datas->type);
		$mini_viewer.= '<div class="redbox_minibox_icon"><i class="'.$icon.'"></i> '.$datas->category.'</div>';
		if ($comment)
			$mini_viewer.= '<div class="redbox_minibox_author">'.PROPOSED_BY.' <a targeg="_blank" href="'.$comment->comment_author_url.'">'.$comment->comment_author.'</a></div>';
		
		if (trim($datas->pictures[0]->url)!=''){
			$picture_url = $datas->pictures[0]->url;
		}
		else{
			$picture_url = WP_PLUGIN_URL."/redbox/img/redbox.jpg";
		}
		
		if ($datas->type!='picture') {
			$mini_viewer.= '<div class="redbox_minibox_title"><a target="_blank" href="'.$datas->source.'">'.$datas->title.'</a></div>';
			$mini_viewer.= '<div class="redbox_minibox_picture"><img src="'.$picture_url.'"/></div>';
			if (trim($datas->short_description)!=''){
				$match[0]= $datas->short_description;
			}
			else{
				preg_match('/^(?>\S+\s*){1,40}/', $datas->message, $match);
				if (!$match) $match[0]= $datas->message;
			}
			$mini_viewer.= '<div class="redbox_minibox_description"><span>'.$match[0].'</span></div>';
		}
		else{
			$mini_viewer.= '<div class="redbox_clear"></div>
					<div class="redbox_minibox_picture_type"><img src="'.$picture_url.'"/></div>
					<div class="redbox_clear"></div>';
		}
		
		if ($post){
			if ($post->post_status == "publish"){
				$title_class = "redbox-titlebox anim success";
				$a_tag_content = 'target="_blank" href="http://'.$_SERVER['HTTP_HOST'].'?p='.$post->ID.'" title="'.VISIT.'"';
				$a_title = VISIT;
			}
			else{
				if (current_user_can( 'edit_posts' )){
					$title_class = "redbox-titlebox anim warning";
					$target = "http://".$_SERVER["HTTP_HOST"]."/wp-admin/post.php?post=".$post->ID."&action=edit";
					$a_tag_content = 'target="_blank" href="'.$target.'" title="'.PUBLISH.'"';
					$a_title = PUBLISH;
				}
				else{
					$title_class = "redbox-titlebox anim info";
					$a_tag_content = 'href="javascript:redbox_ajax_do(\'redbox_view_proposed\','.$comment->comment_ID.');" title="'.VIEW_PROPOSITION.'"';
					$a_title = VIEW_PROPOSITION;
				}
			}
		}
		else{
			if ($comment && count($list_datas)>0 && $list_datas[0]->url!=''){
				$options = get_option('redbox_options');
				$sql = 'SELECT ID FROM ' . $wpdb->prefix .'posts WHERE post_name="'.$options['redbox_page_name'].'"';
				$rows = $wpdb->get_results($sql);
				if ($mode=='post' && current_user_can( 'edit_posts' )){
					$title_class = "redbox-titlebox anim info";
					$a_tag_content = 'href="javascript:redbox_ajax_do(\'redbox_post_proposed\','.$comment->comment_ID.');" title="'.PUBLISH.'"';
					$a_title = PUBLISH;
				}
				else{
					$title_class = "redbox-titlebox anim info";
					$a_tag_content = 'href="javascript:redbox_ajax_do(\'redbox_view_proposed\','.$comment->comment_ID.');" title="'.VIEW_PROPOSITION.'"';
					$a_title = VIEW_PROPOSITION;
				}
				if ($comment->comment_approved){
					$title_class = "redbox-titlebox anim info";
				}
				else{
					$title_class = "redbox-titlebox anim warning";
				}
			}
		}
		if ($a_title) $mini_viewer.= '<div class="redbox_clear"></div><span class="'.$title_class.'" style="display:inline-block;width:100%;height:100%;"><div class="redbox_minibox_import btnbox"><a '.$a_tag_content.' class="redbox_button">'.$a_title.'</a></div></span>';
		
		$mini_viewer.= "</div></div>";
		
		return $mini_viewer;
	}
	
	// redbox theme integration 
	public function redbox_theme_redirect(){
		global $post;
		$options = get_option('redbox_options');
		if ($post->post_name == $options['redbox_page_name']) {
			$template_path = WP_PLUGIN_DIR.'/redbox/theme/redbox-page-theme.php';
			RedBoxBlog::do_theme_redirect($template_path);
		}
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
	// redbox comment template : transform "comments" management to "propositions" managment
	public function redbox_comment_template($comment_template){
		global $post;
		$options = get_option('redbox_options');
		if ($post->post_name == $options['redbox_page_name']) {
			return WP_PLUGIN_DIR.'/redbox/theme/redbox-propositions-feed.php';
		}
		return;
	}

	public function redbox_get_icon_for($slug,$complete_name='true'){
		switch ($slug){
			case "illustrations" :
				$icon = "picture";
				break;
			case "articles" :
				$icon = "globe";
				break;
			case "activite" :
				$icon = "bullhorn";
				break;
			case "courtes-video" :
				$icon = "film";
				break;
			case "culture" :
				$icon = "heart";
				break;
			case "documentaires" :
				$icon = "film";
				break;
			case "interviews" :
				$icon = "microphone";
				break;
			case "liens" :
				$icon = "link";
				break;
			case "uncategorized" :
				$icon = "leaf";
				break;
			case "redaction" :
				$icon = "info-sign";
				break;
			case "videos" :
				$icon = "facetime-video";
				break;
			case "video" :
				$icon = "facetime-video";
				break;
			case "documentaires" :
				$icon = "film";
				break;
			case "habitat-alternatif" :
				$icon = "home";
				break;
			case "street-art" :
				$icon = "rocket";
				break;
			case "crowdfunding" :
				$icon = "group";
				break;
			case "gallerie" :
				$icon = "picture";
				break;
			case "picture" :
				$icon = "picture";
				break;
			case "gallery" :
				$icon = "picture";
				break;
			case "short_video" :
				$icon = "facetime-video";
				break;
			case "documentary" :
				$icon = "picture";
				break;
			case "music" :
				$icon = "music";
				break;
			default : 
				$icon = "file-alt";
				break;

		}
		if ($complete_name==true)
			return "icon-".$icon;
		else
			return $icon;
	}
	
	public function enqueue_wp_scripts() {
		wp_enqueue_script( 'jquery', 'http://code.jquery.com/jquery-1.9.1.min.js' );
		wp_enqueue_script( 'wp_redbox_importer_admin', WP_PLUGIN_URL.'/redbox/js/importer.jquery.js' );
		wp_register_style( 'redbox-style', WP_PLUGIN_URL."/redbox/css/redbox.css");
		wp_enqueue_style( 'redbox-style' );
		wp_register_style( 'redbox-blog-style', WP_PLUGIN_URL."/redbox/css/redbox-blog.css");
		wp_enqueue_style( 'redbox-blog-style' );
		wp_register_style( 'redbox-messi-style', WP_PLUGIN_URL."/redbox/css/messi.css");
		wp_enqueue_style( 'redbox-messi-style' );
		wp_register_style( 'redbox-tabs-style', WP_PLUGIN_URL."/redbox/css/tabs.css");
		wp_enqueue_style( 'redbox-tabs-style' );
		wp_register_style( 'redbox-scroll-style', WP_PLUGIN_URL."/redbox/css/scroller.css");
		wp_enqueue_style( 'redbox-scroll-style' );
		wp_register_style( 'redbox-elastislide-style', WP_PLUGIN_URL."/redbox/css/elastislide.css");
		wp_enqueue_style( 'redbox-elastislide-style' );
		wp_register_style( 'redbox-custom-elastislide-style', WP_PLUGIN_URL."/redbox/css/custom.css");
		wp_enqueue_style( 'redbox-custom-elastislide-style' );
		wp_register_style( 'redbox-nailthumb-style', WP_PLUGIN_URL."/redbox/css/jquery.nailthumb.min.css");
		wp_enqueue_style( 'redbox-nailthumb-style' );
		wp_enqueue_script( 'redbox-messi-dialog', WP_PLUGIN_URL.'/redbox/js/messi.min.js' );
		wp_enqueue_script( 'redbox-dispatcher', WP_PLUGIN_URL.'/redbox/js/redbox-dispatcher.js' );
		wp_enqueue_script( 'redbox-tabs', WP_PLUGIN_URL.'/redbox/js/activatables.js' );
		//wp_enqueue_script( 'redbox-masonry', WP_PLUGIN_URL.'/redbox/js/masonry.js' );
		wp_enqueue_script( 'redbox-isotope', WP_PLUGIN_URL.'/redbox/js/jquery.isotope.min.js' );
		wp_enqueue_script( 'redbox-scroller', WP_PLUGIN_URL.'/redbox/js/scroller.js' );
		wp_enqueue_script( 'redbox-fitvids', WP_PLUGIN_URL.'/redbox/js/jquery.fitvids.js' );
		wp_enqueue_script( 'redbox-modernizr', WP_PLUGIN_URL.'/redbox/js/modernizr.custom.17475.js' );
		wp_enqueue_script( 'redbox-pp-custom', WP_PLUGIN_URL.'/redbox/js/jquerypp.custom.js' );
		wp_enqueue_script( 'redbox-elastislide', WP_PLUGIN_URL.'/redbox/js/jquery.elastislide.js' );
		wp_enqueue_script( 'redbox-thumbs', WP_PLUGIN_URL.'/redbox/js/jquery.nailthumb.min.js' );
		// INIT DONE IN CODE : wp_enqueue_script( 'redbox_init', WP_PLUGIN_URL.'/redbox/js/redbox-init.js' );
		$options = get_option('redbox_options');

		if (isset($options['redbox_page_id']) && isset($_GET['p']) && $_GET['p']== $options['redbox_page_id']) {
			wp_enqueue_script( 'wp_redbox_custom_comments', WP_PLUGIN_URL.'/redbox/js/custom-comments.js' );
			
		}
		if( isset($_SESSION['dialogs'])){
			echo $_SESSION['dialogs'];
			unset($_SESSION['dialogs']);
		}
		global $post;
		if ((!isset($_GET['page']) || $_GET['page'] != "redbox-facebook") && ( $post->post_name != $options['redbox_page_name']))	
			echo '<span id="redbox_status"></span>';
		
	}
	
	
}



?>
