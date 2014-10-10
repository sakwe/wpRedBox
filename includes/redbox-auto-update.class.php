<?

class RedBoxAutoUpdate{

	public function __construct(&$redbox){
		// connect to the global redbox instance
		$this->redbox = $redbox;
		// this will load script, css in admin for everybody
		add_action('admin_enqueue_scripts', array(&$this, "addAjaxAction"));
		
		// this will load script, css in the blog 
		add_action('wp_enqueue_scripts', array(&$this, "addAjaxAction"));
		
		add_action( 'admin_bar_menu', array( &$this, 'redbox_admin_bar_auto_update_notif' ), 1 );
	}
	
	public function addAjaxAction(){
		$now = time();
		$this->redbox_last_post_check = $options['redbox_last_post_check'];
	
		echo '<script>window.setTimeout(\'redbox_ajax_do("redbox_auto_check_fb_posts");\',4000);</script>';
					$this->redbox_last_feed_check = $options['redbox_last_feed_check'];
		echo '<script>window.setTimeout(\'redbox_ajax_do("redbox_auto_check_fb_feed");\',4000);</script>';
	}
	
	
	public function redbox_auto_check_fb_posts(){
		$this->redbox->facebook->update_fb_posts_table();
	}
	
		public function redbox_auto_check_fb_feed(){
		$this->redbox->facebook->update_fb_feed_table();
	}
	
	public function redbox_admin_bar_auto_update_notif($wp_admin_bar){
		if( ! current_user_can( 'read' ) )
			return;
		global $wpdb;
		
		$sql = 'SELECT COUNT(*) AS postCount FROM ' . $wpdb->prefix .'redbox_fb r WHERE (r.status IS NULL OR r.status = "") AND r.type="post" ';
		$posts = $wpdb->get_results($sql);
			
		$sql = 'SELECT COUNT(*) AS feedCount FROM ' . $wpdb->prefix .'redbox_fb r WHERE (r.status IS NULL OR r.status = "") AND r.type="feed" ';
		$feed = $wpdb->get_results($sql);
		
		if (!$posts && !$feed) return;
		
		$status = '<img src="'.plugins_url().'/redbox/img/redbox-fb-status.png" style="padding-top:3px;width:34px;height:34px;" ';
		$status.= ' title="'.REDBOX_FACEBOOK_IMPORT.'"';
		$status.= ' />';
		$wp_admin_bar->add_menu( array(
			'parent' => false,
			'id'     => 'redbox-status',
			'title'  => $status,
			'meta'   => array(
				'class'    => 'admin-bar-redbox',
				'tabindex' => -1
			)
		) );
		if ($posts[0]->postCount > 0) {
			$wp_admin_bar->add_menu( array(
				'parent' => 'redbox-status',
				'id'     => 'redbox-posts-status',
				'title'  => '<a onclick="redbox_ajax_do(\'import_facebook_posts\',0,1);"><span class="ab-label awaiting-mod count-1"><span class="pending-count">'.$posts[0]->postCount.'</span></span> '.REDBOX_IMPORT_POSTS.' ! '.IMPORT.' ?</a>',
				'meta'   => array(
					'class'    => 'admin-bar-redbox',
					'tabindex' => 1
				)
			) );
		}
		if ($feed[0]->feedCount > 0) {
			$wp_admin_bar->add_menu( array(
				'parent' => 'redbox-status',
				'id'     => 'redbox-fee-status',
				'title'  => '<a onclick="redbox_ajax_do(\'import_facebook_propositions\',0,1);"><span class="ab-label awaiting-mod count-1"><span class="pending-count">'.$feed[0]->feedCount.'</span></span> '.REDBOX_IMPORT_PROPOSITIONS.' ! '.IMPORT.' ?</a>',
				'meta'   => array(
					'class'    => 'admin-bar-redbox',
					'tabindex' => 2
				)
			) );
		}
	}
		
}



?>
