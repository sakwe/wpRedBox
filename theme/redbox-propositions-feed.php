<?php	
wp_enqueue_script( 'wp_redbox_custom_comments', WP_PLUGIN_URL.'/redbox/js/custom-comments.js' ); 

global $wpdb,$redbox,$post;

// list all categories for counts
$sections = array();
$login_classes='';
foreach($redbox->configuration->categories as $type=>$category){
	$login_classes.= " ".suppr_specialchar(suppr_accents($category));
	$sections[suppr_specialchar(suppr_accents($category))]= array('name'=> $category,'count'=>0,'type'=> $type);
}
// HACK TO SUPPORT OLD "photos" category
$sections['Photos']= array('name'=>'Photos','count'=>0,'slug'=>'picture');
?>

<div id="comments-wrapper clearfix">
	<div id="wpbody">
	<span id="redbox_status"></span>
	<?php 
	$connect=null;
	if( current_user_can( 'read' ) ) {
		?>
		
		<form name="redbox_form" method="post" >
		<table width="100%"><tr><td width="66%" style="text-align:right;">
		<textarea class="redbox_textarea" id="redbox_textarea" placeholder="Soumettez-nous votre proposition" name="url_to_import" ><?php if (isset($_POST['url_to_import'])) echo stripslashes(trim($_POST['url_to_import'])); ?></textarea>
		<input type="hidden" id="redbox_action" name="redbox_action" value="redbox_submit_from_blog" />
		<input type="button" class="redbox_button" onclick="redbox_ajax_do('redbox_submit_from_blog',document.getElementById('redbox_textarea').value,1)" class="redbox_button" value="Proposer" />
		</td><td width="33%" style="vertical-align:top;">
		<p>Vous pouvez entrer une ou plusieurs url en relation avec le sujet que vous désirez proposer.</p>
		<p>La RedBox accepte les articles, les vidéos, les images, les publications, photos et galleries Facebook.</p>
		</td></tr></table>
		</form>
		<?php
		
	}
	else{
		$connect = '<div class="redbox_login redbox_item four columns'.$login_classes.'" id="redbox-item-connect">'.do_shortcode('[widget classname="LoginWithAjaxWidget" instance="title=Vous devez être connecté pour participer."]').'</div>';
	}
	$prev_link = "";
	$next_link ="";
	if ( have_comments() ) :  
	
	$redbox_container = '<div class="container" style="margin-left:-15px;">';
	$redbox_container.= '<div id="redbox_container">';


	$nb_com_pp = intval(get_query_var('comments_per_page'));
	//$nb_com_pp = "10";
	$page = intval(get_query_var('cpage'));
	$user = wp_get_current_user();
	$sql = "SELECT comment_ID FROM " . $wpdb->prefix .'comments WHERE comment_post_ID='.$post->ID.' ';
	$sql.= 'AND (comment_approved="1" OR comment_approved=1 ';
	if ($user->ID){
		$sql.= 'OR (comment_approved NOT LIKE "trash" AND user_id='.$user->ID.')';
	}
	$sql.= ') ';
	
	$sql.= ' ORDER BY comment_ID DESC';
	$rows = $wpdb->get_results($sql);
	$nb_com = count($rows);
	if ($page == 0) {
		$start = 1;
		$stop = $nb_com_pp;
	}
	else{
		$start = 1 + ( $nb_com_pp * ($page - 1));
		$stop = $start + $nb_com_pp;
	}

	if (!$start || $start < 1) $start=1;
	if (!$stop || $stop > $nb_com) $stop=$nb_com;
	
	//echo $page."-".$nb_com_pp."-".$nb_com."-".$start."-".$stop;
	
	if ( get_comment_pages_count() > 1 && get_option( 'page_comments' ) ){
		if ($page != 1)
			$prev_link = get_permalink()."comment-page-".($page - 1);
		if ($page != get_comment_pages_count())
		$next_link = get_permalink()."comment-page-".($page + 1);
	}
	
	$comments_ids = array();
	for ($i=$start;$i<=$stop;$i++){
		$exists = false;
		foreach ($comments_ids as $ids){
			if ($ids == $rows[$i-1]->comment_ID){
				$exists = true;
				break;
			}
		}
		if (!$exists) $comments_ids[] = $rows[$i-1]->comment_ID;
	}
	$comments_unapproved=array();
	if( current_user_can( 'edit_posts' ) ){
		if ($page==1 || $page==0){
			$sql = 'SELECT MAX(comment_ID) as id FROM ' . $wpdb->prefix .'comments';
			$rows = $wpdb->get_results($sql);
			$first = $rows[0]->id;
		}
		else{
			$first = $comments_ids[0];
		}
		$last = $comments_ids[count($comments_ids)-1];
		
		$sql = 'SELECT comment_ID FROM ' . $wpdb->prefix .'comments WHERE comment_post_ID='.$post->ID.' AND comment_ID<='.$first.' AND comment_ID>='.$last. ' AND (comment_approved="0" OR comment_approved="")';
		
		if ($rows = $wpdb->get_results($sql)){
			foreach($rows as $row){
				$found=false;
				foreach ($comments_ids as $comments_id){
					if ($comments_id==$row->comment_ID){
						$found=true;
						break;
					}
				}
				if (!$found) $comments_unapproved[]=$row->comment_ID;
			}
		}
	
		$comments_ids = array_merge($comments_ids,$comments_unapproved);
	}
	
	arsort($comments_ids);
	
	$i=0;
	$redbox_lines ='';
	if ($connect) $redbox_lines = $redbox_lines . $connect;
	foreach ($comments_ids as $comments_id){
		$comment = get_comment($comments_id);
		$post = null;
		
		$sql = 'SELECT * FROM ' . $wpdb->prefix .'commentmeta WHERE meta_key="redbox_data_container" AND comment_id='.$comment->comment_ID;
		if ($rows = $wpdb->get_results($sql)){
			$list_datas = unserialize($rows[0]->meta_value);
			$sql = 'SELECT * FROM ' . $wpdb->prefix .'commentmeta WHERE meta_key="redbox_post_id" AND comment_id='.$comment->comment_ID;
			$rows = $wpdb->get_results($sql);
			foreach ($rows as $row){
				if ($post = get_post($row->meta_value)) break;
			}
		}
		else{
			preg_match_all('!https?://[\S]+!', $comment->comment_content, $match);
			$list_datas = $redbox->retriever->get_datas($match[0]);
			$redbox->manager->set_redbox_comment_datas($comment->comment_ID,$list_datas);
		}
		$redbox_column = $redbox->blog->get_datas_fancy_viewer($list_datas,array('comment'=>$comment,'post'=>$post));
		if (trim($redbox_column)!=''){
			$sections[suppr_specialchar(suppr_accents($list_datas[0]->category))]['count']++;
			$redbox_column = '<div class="redbox_item '.suppr_specialchar(suppr_accents($list_datas[0]->category)).'" 
						id="redbox_proposition_box_'.$comment->comment_ID.'">'.$redbox_column.'</div>';
			$i++;
			$redbox_lines = $redbox_lines . $redbox_column;
		}
	}
	$redbox_container.= $redbox_lines;
	$redbox_container.= '</div>';
	$redbox_container.= '</div>';
	//if (trim($prev_link)!='') $redbox_filter.= '<div class="redbox_navigation"><a href="'.$prev_link.'">>Propositions plus récentes</a></div>';
	$redbox_filter = '<section id="options" class="clearfix">';
	$redbox_filter.= '<ul id="redbox-filters" class="option-set clearfix" data-option-key="filter">';
	//
	$redbox_filter.= '<li><a href="#filter" id="view_all" data-option-value="*">'.__('Tout','mthemes').'<span class="post-count">'.count($comments_ids).'</span></a></li>';
	foreach($sections as $slug => $section) {
		if ($section['count']>0){
			$redbox_filter.= '<li><a href="#filter" data-option-value=".'.$slug.'"><i class="'.$redbox->blog->redbox_get_icon_for($section['type'])
.'"></i> '.$section['name'].'<span class="post-count">'.$section['count'].'</span></a></li>';
		}
	}

	
	$redbox_filter.= '</ul>';
	$redbox_filter.= '</section>';
	//if (trim($next_link)!='') $redbox_filter.= '<div class="redbox_navigation"><a href="'.$next_link.'">Propositions plus anciennes</a></div>';

	echo '</div>';
	echo $redbox_filter;
	echo $redbox_container;

	?>
	

	<script>
	jQuery(document).ready(function($) {
		 $(function(){
		  
		  var $container = $('#redbox_container');

		  $container.isotope({
			itemSelector : '.redbox_item'
		  });
		  
		  
		  var $optionSets = $('#options .option-set'),
			  $optionLinks = $optionSets.find('a');

		  $optionLinks.click(function(){
		  
			var $this = $(this);
			// don't proceed if already selected
			if ( $this.hasClass('current') ) {
			  return false;
			}
			var $optionSet = $this.parents('.option-set');
			$optionSet.find('.current').removeClass('current');
			$this.addClass('current');
	  
			// make option object dynamically, i.e. { filter: '.my-filter-class' }
			var options = {},
				key = $optionSet.attr('data-option-key'),
				value = $this.attr('data-option-value');
			// parse 'false' as false boolean
			value = value === 'false' ? false : value;
			options[ key ] = value;
			if ( key === 'layoutMode' && typeof changeLayoutMode === 'function' ) {
			  // changes in layout modes need extra logic
			  changeLayoutMode( $this, options )
			} else {
			  // otherwise, apply new options
			  $container.isotope( options );
			}
	
			return false;
		  });
		  
		  btn = $("#view_all");
		  btn.delay(2000).queue(function(){$(this).click().dequeue();});
		});
	});
	</script>
	</div><!-- #wpbody -->
</div><!-- #comments-wrapper -->


<?php else : // or, if we don't have comments:

	/* If there are no comments and comments are closed,
	 * let's leave a little note, shall we?
	 */
	if ( ! comments_open() ) :
?>
	<p class="nocomments"><?php _e( 'Comments are closed.', 'mthemes' ); ?></p>
<?php endif; // end ! comments_open() ?>

<?php endif; // end have_comments() ?>


	
