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
		}
		
		/** TODO GET THIS CONFIG FROM ADMIN PAGE SECTION **/
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

		$this->crowdfundings = array("fr.ulule.com","kisskissbankbank.com","mymajorcompany.com","babyloan.org","mailforgood.com","spear.fr","ecobole.fr","arizuka.com","cowfunding.fr","uniteddonations.co","kickstarter.com");
		$this->fallBackUrl = "http://gregory.wojtalik.be/redbox_curl_fallback.php";
		$this->to_clean_in_urls = array('&fb_source=message','&noredirect=1','&autoplay=1','&feature=youtu.be');
		$this->sub_replace_source = array("\nLa source : ","\nLa suite : ","\nLa vidéo : ","\nL'article : ","\nSource : ","\nVidéo : ","\nArticle : ","\nInfo : ","\nInfos : ","\nLien : ","\n");
		$this->to_clean_in_titles = array(' - YouTube',' on Vimeo',' - France Info',' - Terra eco',' - Vidéo Dailymotion',' - Basta!',
						' DOCUMENTAIRE',' - RTBF Medias', '(reportage complet)','(french version)' );
		$this->to_clean_in_texts = array('Infos & Débats sur Mr Mondialisation','Voir la publication de Mr Mondialisation','Infos & Débats | Mr Mondialisation | ','Infos & Débats l Mr Mondialisation | ','Infos et Débats @ Mr Mondialisation','Infos et débats Mr Mondialisation','Infos & débats Mr Mondialisation');
	}

}

