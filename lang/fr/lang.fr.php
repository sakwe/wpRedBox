<?php

define("TITLE_REDBOX","RedBox");

define("BIG_TITLE_REDBOX_CONFIGURATION","Configuration et lancement du REDBOX");

define("YES","Oui");

define("NO","Non");

define("CANCEL","Annuler");

define("CLOSE","Fermer");

define("INFO","Information");

define("SUCCESS","Réussi");

define("WARNING","Attention");

define("ERROR","Erreur");

define("QUESTION","Question");

define("PRESENT","Présent");

define("NONE","Aucun");

define("DELETE","Supprimer");

define("UPLOAD_FILE_ERROR","Erreur lors de l'envoi de fichier");

define("FILE_GENERATED_BY","Fichier généré par");

define("REDBOX_CONFIGURATION","Configuration de RedBox");

define("REDBOX_CONFIGURATION_FACEBOOK", "Configuration spécifique à Facebook");

define("REDBOX_IMPORT_FROM_FACEBOOK","Import à partir de votre page Facebook");

define("REDBOX_IMPORT_RESULTS", "Résultat d'importation");

define("REDBOX_INFO_CONFIG","<p><b>Configuration générale de votre RedBox.</b></p>
				<p>RedBox permet essentiellement d'importer du contenu dans votre Blog. Il permet aux adminitrateurs, éditeurs, auteurs et contributeurs de créer du contenu très facilement à partir d'une URL fournie. Le contenu approprié sera enlevé des métadonnées détectées (titre, description, image et vidéo) pour créer la base d'un post sur votre blog.</p>
				<p>RedBox peut également importer directement les publications qui ne figurent pas dans votre blog à partir de votre page Facebook. </p>
				<p>Enfin, RedBox permet aux abonnés à votre blog de vous proposer simplement des url à publier. Un outil d'administration vous permet de valider ou non un lien proposé et de l'importer dans votre blog.</p>
				</div>");
				
define("REDBOX_CONFIGURE_BLOG_TITLE","Configurez ici la manière dont RedBox va s'intégrer dans votre blog.");

define("REDBOX_BLOG_PAGE_NAME","Page RedBox de votre blog");

define("REDBOX_AUTOTAGS_LIST","Auto tags sur les mots suivants");

define("REDBOX_FACEBOOK_TMP","posts recensés depuis la page");

define("REDBOX_FACEBOOK_INWP","posts facebook dans worpress");

define("REDBOX_IMPORT_BUTTON_HELP","
		<p>RedBox doit d'abord récupérer la liste des posts de votre page. Lors de la première exécution, cette étape prend relativement du temps, en fonction du nombre de posts existants sur votre page</p>
		<p>L'option permettant de \"Forcer la liste à se raffraichir\" reparcourra complètement votre timeline facebook à la recherche de posts manquants.</p>
		<p>Une fois la liste établie, \"Vérifier les posts sur la page\" s'arrêtera de lister les posts au dernier posts importé précédement (ne parcourt par toute la timeline)</p>
		<p>\"Importer les posts manquants\" va importer complètement les post listés et créer des publications sur votre blog à partir des informations récupérées (si \"Post to facebook\" est activité, les commentaires seront également importés). Les posts créés dans votre blog seront datés aux heures de publication trouvées sur votre timeline</p>
		<p>\"Forcer la mise à jour des posts\" sera très long en exécution car il parcourra et réimportera l'entièreté de vos posts sur votre timeline</p>
		");

define("REDBOX_CHECK_FACEBOOK","Vérifier les posts sur la page");

define("REDBOX_CHECK_FACEBOOK_FORCED","Forcer la liste à se raffraichir");

define("REDBOX_IMPORT_FACEBOOK_NEEDED","Importer les posts manquants");

define("REDBOX_IMPORT_FACEBOOK_FORCED","Forcer la mise à jour des posts");

define("REDBOX_FACEBOOK_CONFIG_HELP","<p>En complément aux plugins qui peuvent poster de votre blog vers votre page Facebook, RedBox permet d'importer les publications de votre page Facebook qui ne sont pas dans votre blog.</p><p>RedBox est compatible avec les Plugin \"Post to Facebook (al2fb)\" et \"Pulicize (compris dans le JetPack)\"</p><p>RedBox permet également d'importer à la demande toute publication publique de Facebook.</p>");

define("REDBOX_FACEBOOK_ID_LABEL","Id Facebook de votre page");

define("REDBOX_FACEBOOK_APPID_LABEL","App Id");

define("REDBOX_FACEBOOK_SECRET_LABEL","App Secret");



?>
