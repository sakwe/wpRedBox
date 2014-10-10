<?php
/* RedBox configuration
 *
 **/


class RedBoxConfiguration{

	public $fb_config, $categories;

	public function __construct(&$redbox){
		$this->redbox = $redbox;
		$this->get_config();
	}
	
	public function get_config(){
		if (function_exists('get_option')){
			$options = get_option('redbox_options');
		
			$this->fb_config = array('primary_id'=>$options['facebook_id'],
						'app_id'=>$options['facebook_app_id'],
						'app_secret'=>$options['facebook_app_secret']
						);
			$this->redboxPageName = $options['redbox_page_name'];
			$this->redbox_shortcode_to_add = $options['redbox_shortcode_to_add'];

			$this->redbox_last_post_check = $options['redbox_last_post_check'];			
			$this->redbox_last_feed_check = $options['redbox_last_feed_check'];
			$this->redbox_last_post_sync = $options['redbox_last_post_sync'];
			$this->redbox_last_feed_sync = $options['redbox_last_feed_sync'];
		}
		
		/** TODO GET THIS CONFIG FROM ADMIN PAGE SECTION **/
		$this->xmppRedBoxID = "redbox@mondiaspora.org";
		$this->xmppRedBoxPass = "r3db0x";
		$this->xmppPostMessageUrl = "https://mondiaspora.org/msg/";

		$this->categories = array(
					'link'=>'Liens',
					'article'=>'Articles',
					'picture'=>'Illustrations',
					'gallery'=>'Galleries',
					'video'=>'Vidéos',
					'short_video'=>'Courtes vidéos',
					'documentary'=>'Documentaires',
					'music'=>'Clips musicaux',
					'crowdfunding'=>'Crowdfunding'
				);
		$this->default_post_image = "http://mrmondialisation.org/wp-content/uploads/2014/10/mrmicone2.jpg";
		//$this->fb_post_sign = "Infos & Débats | [[177043642312050]]";
		$this->fb_post_sign = "Infos & Débats sur https://www.facebook.com/M.Mondialisation";
		$this->crowdfundings = array("fr.ulule.com","kisskissbankbank.com","mymajorcompany.com","babyloan.org","mailforgood.com","spear.fr","ecobole.fr","arizuka.com","cowfunding.fr","uniteddonations.co","kickstarter.com");
		$this->fallBackUrl = "http://gregory.wojtalik.be/redbox_curl_fallback.php";
		//$this->fallBackUrl = "";
		$this->to_clean_in_urls = array('&fb_source=message','&noredirect=1','&autoplay=1','&feature=youtu.be');
		$this->sub_replace_source = array("\nLa source :","\nLa suite : ","\nLa vidéo : ","\nL'article : ","\nSource : ","\nDossier : ","\nA lire : ","\nPDF : ","\nDocumentaire : ","\nSources : ","\nVidéo : ","\nArticle : ","\nInfo :","\nInfos : ","\nLien : ","\nReportage : ","\n-> ","\n");
		$this->to_clean_in_titles = array(' - YouTube',' on Vimeo',' - France Info',' - Terra eco',' - Vidéo Dailymotion',' - Basta!',
						' DOCUMENTAIRE',' - RTBF Medias', '(reportage complet)','(french version)' );
		$this->to_clean_in_texts = array('Infos & Débats sur Mr Mondialisation','Voir la publication de Mr Mondialisation','Infos & Débats | Mr Mondialisation | ','Infos & Débats | Mr Mondialisation','Infos & Débats l Mr Mondialisation | ','Infos & Débats l Mr Mondialisation','Infos et Débats @ Mr Mondialisation','Infos et débats Mr Mondialisation','Infos & débats Mr Mondialisation','#redbox');
	}

}


