<?php
/* XMPP notification capabilities
 *
 **/


class RedBoxXMPP{

	public function __construct(&$redbox){
		// connect to the global redbox instance
		$this->redbox = $redbox;

		add_action('show_user_profile',array(&$this, 'redbox_xmpp_users_profile_fields'));
		add_action('edit_user_profile',array(&$this, 'redbox_xmpp_users_profile_fields'));

		add_action('personal_options_update', array(&$this, 'redbox_xmpp_users_save_fields' ));
		add_action('edit_user_profile_update', array(&$this, 'redbox_xmpp_users_save_fields' ));
	}

	// profile fileds to configure an autheticated connexion to Jappix
	public function redbox_xmpp_users_profile_fields($user){
	?>
		<h3>Gestion des notifications RedBox par XMPP</h3>
		<table class="form-table">
			<tr>
				<th><label for="redbox">Le JID sur lequel envoyer les notifications.</label></th>
				<td>
					<input type="nickname" name="redbox_xmpp_user_JID" id="redbox_xmpp_user_JID" value="<?php echo esc_attr( get_user_meta( $user->ID , 'redbox_xmpp_user_JID', true ) ); ?>" class="regular-text" /><br />
					<span class="description">Entrez votre identifiant XMPP (votrelogin@exemple.org)</span>
				</td>
			</tr>
			<tr>
				<th><label for="redbox">Recevoir les notifications pour les propositions</label></th>
				<td>
					<input type="checkbox" name="redbox_xmpp_nofif_proposition[]" id="redbox_xmpp_nofif_proposition" <?php 
					echo get_user_meta( $user->ID , 'redbox_xmpp_nofif_proposition', true ) == "true" ? "checked" : ""; 
					?> value="<?php echo esc_attr( get_user_meta( $user->ID , 'redbox_xmpp_nofif_proposition', true ) ); ?>" class="regular-text" /><br />
					<span class="description">Vous recevrez les notifications XMPP lorsque des propositions auront été soumises.</span>
				</td>
			</tr>
			<tr>
				<th><label for="redbox">Recevoir les notifications pour les publications</label></th>
				<td>
					<input type="checkbox" name="redbox_xmpp_nofif_publication[]" id="redbox_xmpp_nofif_publication" <?php 
					echo get_user_meta( $user->ID , 'redbox_xmpp_nofif_publication', true ) == "true" ? "checked" : ""; 
					?> value="<?php echo esc_attr( get_user_meta( $user->ID , 'redbox_xmpp_nofif_publication', true ) ); ?>" class="regular-text" /><br />
					<span class="description">Vous recevrez les notifications XMPP lorsque des publications auront lieux sur le site.</span>
				</td>
			</tr>
			<tr>
				<th><label for="redbox">Recevoir les notifications pour les commentaires</label></th>
				<td>
					<input type="checkbox" name="redbox_xmpp_nofif_comments[]" id="redbox_xmpp_nofif_comments" <?php 
					echo get_user_meta( $user->ID , 'redbox_xmpp_nofif_comments', true ) == "true" ? "checked" : ""; 
					?> value="<?php echo esc_attr( get_user_meta( $user->ID , 'redbox_xmpp_nofif_comments', true ) ); ?>" class="regular-text" /><br />
					<span class="description">Vous recevrez les notifications XMPP pour les commentaires.</span>
				</td>
			</tr>
		</table>
	<?php
	}

	// save configuration
	public function redbox_xmpp_users_save_fields( $user_id ) {
		if ( !current_user_can( 'edit_user', $user_id ) )
			return false;
		update_usermeta( $user_id, 'redbox_xmpp_user_JID', $_POST['redbox_xmpp_user_JID'] );
		if ($_POST['redbox_xmpp_nofif_proposition'])
			update_usermeta( $user_id, 'redbox_xmpp_nofif_proposition', "true" );
		else
			update_usermeta( $user_id, 'redbox_xmpp_nofif_proposition', "" );
		if ($_POST['redbox_xmpp_nofif_publication'])
			update_usermeta( $user_id, 'redbox_xmpp_nofif_publication', "true" );
		else
			update_usermeta( $user_id, 'redbox_xmpp_nofif_publication', "" );
		if ($_POST['redbox_xmpp_nofif_comments'])
			update_usermeta( $user_id, 'redbox_xmpp_nofif_comments', "true" );
		else
			update_usermeta( $user_id, 'redbox_xmpp_nofif_comments', "" );
	}

	public function get_user_list_for_notification($type = "proposition"){
		global $wpdb;
		$sql = 'SELECT * FROM ' . $wpdb->prefix .'users u, ' . $wpdb->prefix .'usermeta m 
			WHERE u.ID=m.user_id 
			AND m.meta_key="redbox_xmpp_nofif_'.$type.'" 
			AND m.meta_value="true"';
		$users = $wpdb->get_results($sql);
		return $users;
	}

	public function send_notification($xmppTarget,$htmlMsg,$textMsg=''){
		if (REDBOX_DEBUG==true) {
			echo "Try sending XMPP message to : " . $xmppTarget . "<br />\n";
			echo "	Message content : " . $htmlMsg . "<br />\n";
		}
		$xmppUser = $this->redbox->configuration->xmppRedBoxID;
		$xmppPass = $this->redbox->configuration->xmppRedBoxPass;
		if ($textMsg=='') $textMsg = strip_tags(br2nl($htmlMsg));
		$htmlMsg = $htmlMsg;
		$postMsg = 'to=' . urlencode($xmppTarget) 
				.'&type=chat'
				.'&body=' . urlencode($textMsg);
		$postMsg.= '&html=' . urlencode('<body xmlns="http://www.w3.org/1999/xhtml">'. $htmlMsg . '</body>' );
		$ctx = stream_context_create(
				 array(	'http' => array(
						'method' => 'POST',
						'header' => array(
							'Content-type: application/x-www-form-urlencoded',
							'Authorization: Basic ' . base64_encode($xmppUser . ':' . $xmppPass),
						),
					'content' => $postMsg
					)
				)
			);
		$content = file_get_contents($this->redbox->configuration->xmppPostMessageUrl, false, $ctx);
		if (REDBOX_DEBUG==true) {
			if ($content && trim($content)!= '')
				echo "Content returned from XMPP server :".$content."<br />\n";
		}
		return $content;
	}
	
	public function send_notification_for_proposition($commentID){
		if (REDBOX_DEBUG==true) {
			echo "Running global routine to send proposition XMPP notification for ".$commentID."...<br />\n";
		}
		$comment = get_comment($commentID);
		$meta_value = get_comment_meta($commentID,"redbox_data_container",true);
		if ($meta_value){
			$retrieved = unserialize(stripslashes($meta_value));
			$retrieved = $meta_value;
			$datas = $datas=$this->redbox->retriever->get_proposed_import($retrieved);
			$users = $this->get_user_list_for_notification("proposition");
			foreach ($users as $destinationUser){
				$JID = get_user_meta($destinationUser->ID,"redbox_xmpp_user_JID",true);
				if (user_can($destinationUser->ID,"edit_posts")) {
					if ($comment->comment_approved!="1" && $comment->comment_approved!="trash"){
						$messages = $this->get_proposition_notif_for_admin($comment,$datas,$destinationUser);
						$sended = $this->send_notification($JID,$messages['htmlMsg'],$messages['textMsg']);
					}
				} else {
					if ($comment->comment_approved == '1'){
						$messages = $this->get_proposition_notif_for_user($comment,$datas,$destinationUser);
						$sended = $this->send_notification($JID,$messages['htmlMsg'],$messages['textMsg']);
					}
				}
				if ($sended && trim($sended)!=''){
					$this->redbox->dispatcher->dialogs[]= $this->redbox->dispatcher->dialogBox("FROM " . $this->redbox->configuration->xmppRedBoxID . " TO ", $JID,REDBOX_XMPP_ERROR,"error");
				}
			}
		}
	}
	
	public function get_proposition_notif_for_user($comment,$datas,$destinationUser){
		$htmlMsg = "";
		$textMsg = "";
		$htmlMsg.= '<div style="clear:both;float:none;"><br /></div>';
		
		$linkToProposition = "";
		if ($comment->comment_ID!=null) {
			$linkToProposition = htmlspecialchars("/?redbox_action=redbox_view_proposed&comment_id=".$comment->comment_ID);
		}
		$action=VIEW_PROPOSITION;
		if ($destinationUser->ID == $comment->user_id){
			$textLabel= ucfirst($destinationUser->user_login) .", " . strtolower(YOUR_PROPOSITION_APPROVED);
		} else {
			$textLabel=PROPOSITION_DONE_ON;
		}
		$htmlMsg.= $textLabel . ' <a href="'.get_site_url().'/'.$this->redbox->configuration->redboxPageName.'">';
		$htmlMsg.= $this->redbox->configuration->redboxPageName.'</a>';
		$htmlMsg.= ' (<a href="'.get_site_url().'/'.$this->redbox->configuration->redboxPageName.$linkToProposition.'">'.$action.'</a>)';
		$htmlMsg.= '<br />';
		
		$textMsg.= $textLabel . ' '.get_site_url().'/'.$this->redbox->configuration->redboxPageName ."\n";
		$textMsg.= $action . ' : '.get_site_url().'/'.$this->redbox->configuration->redboxPageName.$linkToProposition."\n";
		
		if (trim($datas->pictures[0]->url)!=''){
			$picture_url = $datas->pictures[0]->url;
			$htmlMsg.= '<img width="80px" height="55px" style="width:80px;height:55px;float:left;" src="'.$picture_url.'"/>';
		}
		$htmlMsg.= $datas->category . ' : <a href="'.$datas->source.'">' . $datas->title . '</a><br />';
		$textMsg.= $datas->category . ' : ' . $datas->title . "\n";
		$textMsg.= 'Source : '.$datas->source ."\n";
		if (trim($datas->short_description)!=''){
			$match[0]= $datas->short_description;
		}
		else{
			preg_match('/^(?>\S+\s*){1,40}/', $datas->message, $match);
			if (!$match) $match[0]= $datas->message;
		}
		$htmlMsg.= '<p>'.nl2br($match[0]).'</p><br />';
		$textMsg.= $match[0]."\n";
		if (trim($comment->comment_author)!='' && $destinationUser->ID != $comment->user_id){
			$htmlMsg.= PROPOSED_BY . ' <a href="'.$comment->comment_author_url.'">' . $comment->comment_author . '</a><br />';
			$textMsg.= PROPOSED_BY . ' ' . $comment->comment_author;
			if (trim($user->user_url)=="") {
				$textMsg.= '('.$comment->comment_author_url.')';
			}
			$textMsg.=  "\n";
		}
		$htmlMsg.= '<div style="clear:both;float:none;"><br /></div>';
		return array ('textMsg'=>$textMsg,'htmlMsg'=>$htmlMsg);
	}
	
	public function get_proposition_notif_for_admin($comment,$datas,$destinationUser){
		$htmlMsg = "";
		$textMsg = "";
		$htmlMsg.= '<div style="clear:both;float:none;"><br /></div>';
		
		if ($comment->comment_ID!=null) {
			$linkToProposition = htmlspecialchars("/?redbox_action=redbox_post_proposed&comment_id=".$comment->comment_ID);
		}
		$textLabel=ucfirst($destinationUser->user_login) .", " ;
		switch ($comment->comment_approved){
			case 'trash' : 
				$textLabel.=strtolower(PROPOSITION_DONE_ON);
				$action=RESTORE;
				break;
			case '1' : 
				$textLabel.=strtolower(PROPOSITION_APPROVED_ON);
				$action=DISAPPROVE;
				break;
			default : 
				$textLabel.=strtolower(PROPOSITION_DONE_ON);
				$action=APPROVE;
				break;
		}
		
		$htmlMsg.= '' . $textLabel . ' <a href="'.get_site_url().'/'.$this->redbox->configuration->redboxPageName.$linkToProposition.'">';
		$htmlMsg.= $this->redbox->configuration->redboxPageName.'</a>';
		$htmlMsg.= ' (<a href="'.get_site_url().'/'.$this->redbox->configuration->redboxPageName.$linkToProposition.'">'.$action.'</a>)';
		$htmlMsg.= '<br />';
		
		$textMsg.=  '' . $textLabel . ' '.get_site_url().'/'.$this->redbox->configuration->redboxPageName ."\n";
		$textMsg.=  '' . $action . ' '.get_site_url().'/'.$this->redbox->configuration->redboxPageName.$linkToProposition."\n";
		if (trim($datas->pictures[0]->url)!=''){
			$picture_url = $datas->pictures[0]->url;
			$htmlMsg.= '<img width="80px" height="55px" style="width:80px;height:auto;float:left;" src="'.$picture_url.'"/>';
		}
		
		
		$htmlMsg.= $datas->category . ' : <a href="'.$datas->source.'">' . $datas->title . '</a><br />';
		$textMsg.= $datas->category . ' : ' . $datas->title . "\n";
		$textMsg.= 'Source : '.$datas->source ."\n";
		if (trim($datas->short_description)!=''){
			$match[0]= $datas->short_description;
		}
		else{
			preg_match('/^(?>\S+\s*){1,40}/', $datas->message, $match);
			if (!$match) $match[0]= $datas->message;
		}
		$htmlMsg.= '<p>'.nl2br($match[0]).'</p><br />';
		$textMsg.= $match[0]."\n";
		if (trim($comment->comment_author)!=''){
			$htmlMsg.= PROPOSED_BY . ' <a href="'.$comment->comment_author_url.'">' . $comment->comment_author . '</a><br />';
			$textMsg.= PROPOSED_BY . ' ' . $comment->comment_author;
			if (trim($user->user_url)=="") {
				$textMsg.= '('.$comment->comment_author_url.')';
			}
			$textMsg.=  "\n";
		}

		
		$htmlMsg.= '<div style="clear:both;float:none;"><br /></div>';
		return array ('textMsg'=>$textMsg,'htmlMsg'=>$htmlMsg);
	}
	
	public function send_notification_for_comment($commentID){
		if (REDBOX_DEBUG==true) {
			echo "Running global routine to send comment XMPP notification for ".$commentID."...<br />\n";
		}
		$comment = get_comment($commentID);
		$post = get_post($comment->comment_post_ID);
		if ($comment && $post && $post->post_name != $this->redbox->configuration->redboxPageName){
			$users = $this->get_user_list_for_notification("comments");
			foreach ($users as $destinationUser){
				$JID = get_user_meta($destinationUser->ID,"redbox_xmpp_user_JID",true);
				if (user_can($destinationUser->ID,"edit_posts")){
					if ($comment->comment_approved!="1" && $comment->comment_approved!="trash"){
						$messages = $this->get_comment_notif_for_admin($comment,$post,$destinationUser);
						$sended = $this->send_notification($JID,$messages['htmlMsg'],$messages['textMsg']);
					}
				} 
				if ($comment->comment_approved == '1'){
					if ($destinationUser->ID == $comment->user_id){
						$messages = $this->get_comment_notif_for_user($comment,$post,$destinationUser);
						$sended = $this->send_notification($JID,$messages['htmlMsg'],$messages['textMsg']);
					} else {
						$parentComment = get_comment($comment->comment_parent);
						if ($destinationUser->ID == $parentComment->user_id){
							$messages = $this->get_comment_notif_for_user($comment,$post,$destinationUser);
							$sended = $this->send_notification($JID,$messages['htmlMsg'],$messages['textMsg']);
						}
					}
				}

				if ($sended && trim($sended)!=''){
					$this->redbox->dispatcher->dialogs[]= $this->redbox->dispatcher->dialogBox("FROM " . $this->redbox->configuration->xmppRedBoxID . " TO ", $JID,REDBOX_XMPP_ERROR,"error");
				}
			}
		}
	}
	
	public function get_comment_notif_for_user($comment,$post,$destinationUser){
		$htmlMsg = "";
		$textMsg = "";
		$textLabel=ucfirst($destinationUser->user_login) .", " ;
		if ($comment->comment_approved=="1"){
			$htmlMsg.= '<span style="clear:both;float:none;"><br /></span>';
			if ($destinationUser->ID == $comment->user_id){
				$textLabel.=strtolower(YOUR_COMMENT_APPROVED);
			} else {
				$textLabel.=strtolower(YOUR_COMMENT_ANSWERED);
			}
			$htmlMsg.= $textLabel . ' <a href="'.get_permalink($post->ID).'#comment-'.$comment->comment_ID.'">'.$post->post_title.'</a><br />';
			$htmlMsg.= '<p>'.nl2br($comment->comment_content).'</p><br />';
			$textMsg.= $textLabel . ' '.get_permalink($post->ID).'#comment-'.$comment->comment_ID."\n";
			$textMsg.= $comment->comment_content."\n";
			$htmlMsg.= '<span style="clear:both;float:none;"><br /></span>';
		}
		
		return array ('textMsg'=>$textMsg,'htmlMsg'=>$htmlMsg);
	}

	public function get_comment_notif_for_admin($comment,$post,$destinationUser){
		$htmlMsg = "";
		$textMsg = "";
		$htmlMsg.= '<span style="clear:both;float:none;"><br /></span>';
		if ($comment->comment_ID!=null) {
			$linkToProposition = htmlspecialchars("/wp-admin/comment.php?action=editcomment&c=".$comment->comment_ID);
		}
		$textLabel=ucfirst($destinationUser->user_login) .", " ;
		switch ($comment->comment_approved){
			case 'trash' : 
				$textLabel.=strtolower(COMMENT_DONE_ON);
				$action=RESTORE;
				break;
			case '1' : 
				$textLabel.=strtolower(COMMENT_APPROVED_ON);
				$action=DISAPPROVE;
				break;
			default : 
				$textLabel.=strtolower(COMMENT_DONE_ON);
				$action=APPROVE;
				break;
		}
		$htmlMsg.= '' . $textLabel . ' <a href="'.get_permalink($post->ID).'">';
		$htmlMsg.= $post->post_title.'</a>';
		$htmlMsg.= ' (<a href="'.get_site_url().$linkToProposition.'">'.$action.'</a>)';
		$htmlMsg.= '<br />';
		if (trim($comment->comment_author)!=''){
			$htmlMsg.= COMMENTED_BY . ' <a href="'.$comment->comment_author_url.'">' . $comment->comment_author . '</a><br />';
			$textMsg.= COMMENTED_BY . ' ' . $comment->comment_author;
			if (trim($user->user_url)=="") {
				$textMsg.= '('.$comment->comment_author_url.')';
			}
			$textMsg.=  "\n";
		}
		$textMsg.=  '' . $textLabel . ' ' .get_permalink($post->ID)."\n";
		$textMsg.=  '' . $action . ' '.get_site_url().$linkToProposition."\n";
		$htmlMsg.= '<p>'.nl2br($comment->comment_content).'</p><br />';
		$textMsg.= $comment->comment_content."\n";
		
		$htmlMsg.= '<span style="clear:both;float:none;"><br /></span>';
		return array ('textMsg'=>$textMsg,'htmlMsg'=>$htmlMsg);
		
	}
	
	public function send_notification_for_post($post,$user=null){
		if (REDBOX_DEBUG==true) {
			echo "Running global routine to send post XMPP notification...<br />\n";
		}
		$htmlMsg = "";
		$htmlMsg.= '<span style="clear:both;float:none;"><br /></span>';
		
		$htmlMsg.= '<span style="clear:both;float:none;"><br /></span>';
		$users = $this->get_user_list_for_notification("publication");
		foreach ($users as $destinationUser){
			$JID = get_user_meta($destinationUser->ID,"redbox_xmpp_user_JID",true);
			$sended = $this->send_notification($JID,$htmlMsg);
			if ($sended && trim($sended)!=''){
				$this->redbox->dispatcher->dialogs[]= $this->redbox->dispatcher->dialogBox("FROM " . $this->redbox->configuration->xmppRedBoxID . " TO ", $JID,REDBOX_XMPP_ERROR,"error");
			}
		}
	}
	

}
