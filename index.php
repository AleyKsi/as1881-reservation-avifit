	<?php    
	//---------------------- Paramètres   /!\ Important
	$participantsMax	= 12; 	// Nombre maximal de participants (par défaut : 12)
	$dateDebloquante	= 15; 	// Jour du mois débloquant les inscriptions du mois suivant (par défaut : le 15 du mois)
	$dateDebloLimit	= 1; 	// Nombre de mois débloqués en arrivant au jour de la variable précédente (par défaut : 1)
	$datePurge		= 10;	// Nombre de jours avant lequel les informations sont supprimées (RGPD toussa) (par défaut : 10)
	$arraySeances		= array ("Dimanche" => "", "Lundi" => "1830", "Mardi" => "", "Mercredi" => "1830,1930", "Jeudi"=> "", "Vendredi" => "1830", "Samedi" =>"");// On liste les séances qu'on veut, on met les horaires au format HHmm, séparés par une "," , pour pouvoir les manipuler comme des nombres par la suite
	$arrayAnimateur	= array ("Dimanche" => "", "Lundi" => "", "Mardi" => "", "Mercredi" => "JP", "Jeudi"=> "", "Vendredi" => "Fred", "Samedi" =>"");// On liste les animateurs des séances
	$baseURL			= "https://dev.codeix.fr/as1881-avifit";
	$arrayAdmin		= array ("Alexis" => "alexis.jenny@gmail.com"); // Liste des admins et des mails associés avec comme format : "Nom" => "Email", séparé d'une virgule
	
	//----------------------  Fonctions	& Pré-requis
	function getBetweenDates($startDate, $endDate)
		{
			$rangArray = [];
				
			$startDate = strtotime($startDate);
			$endDate = strtotime($endDate);
				 
			for ($currentDate = $startDate; $currentDate < $endDate; 
											$currentDate += (86400)) {

				$date = date('w-Y-m-d', $currentDate);
				$rangArray[] = $date;
			}  
			return $rangArray;
		}
		
		date_default_timezone_set('Europe/Paris'); // On définit la timezone sur notre fuseau horaire
		$dayHuman 	= array("Dimanche","Lundi","Mardi","Mercredi","Jeudi","Vendredi","Samedi",);	// On liste les jours de la semaine en français
		$monthHuman 	= array("Janvier","Février","Mars","Avril","Mai","Juin","Juillet","Aout","Septembre","Octobre","Novembre","Décembre"); // On liste les mois de l'année en français
		
		// On prépare les limites
		$dateToday = date('Y-m-d');
		$dateTodayPieces = explode ("-", $dateToday); // 0 : Année ;  1 : Mois ;  2 : jour
		$dateTodayPiecesToHuman = $dateTodayPieces[1] - 1; // Pas envie d'en faire un int...	
		$dateTodayHuman = $dateTodayPieces[2].' '.$monthHuman[ $dateTodayPiecesToHuman ] .' '.$dateTodayPieces[0]; // On a la date du jour au format compréhensible
	
		
	//---------------------- Chargement de la BDD, toutes les requetes peuvent utiliser cette variable pour charger la bdd
	if(!$xml=simplexml_load_file('data.xml')) 	echo "Echec de chargement de la base de données des inscriptions";
	if(!$wl=simplexml_load_file('wl.xml')) 		echo "Echec de chargement de la base de données de la liste d'attente";
	
	
	//---------------------- Grooming - On fait le ménage dans la BDD	
		// On détermine la date avant laquelle toutes les entrées sont supprimées
		$dateDeNettoyage = date('Ymd') - $datePurge .'1830'; // On supprime 10 jours avant la date du jour
		// echo $dateDeNettoyage;
		foreach($xml->xpath("//insc[ translate(@date,'-','') <  $dateDeNettoyage ]") as $el) { // On mouline dans la liste des inscrits
			$domRef = dom_import_simplexml($el); 
			$domRef->parentNode->removeChild($domRef); // On supprime le child du parent pour retomber sur notre entrée
			$dom = new DOMDocument('1.0'); $dom->preserveWhiteSpace = false; $dom->formatOutput = true; // Préservation de la présentation
			$dom->loadXML($xml->asXML()); // On charge le résultat dans un DOM Doc
			$dom->save("data.xml"); // On écrit le résultat
		}
				
		foreach($wl->xpath("//wl[ translate(@date,'-','') <  $dateDeNettoyage ]") as $el) { // On mouline dans la waiting list (WL)
			$domRef = dom_import_simplexml($el); 
			$domRef->parentNode->removeChild($domRef); // On supprime le child du parent pour retomber sur notre entrée
			$dom = new DOMDocument('1.0'); $dom->preserveWhiteSpace = false; $dom->formatOutput = true; // Préservation de la présentation
			$dom->loadXML($wl->asXML()); // On charge le résultat dans un DOM Doc
			$dom->save("wl.xml"); // On écrit le résultat
		}	
	//---------------------- Inscription, Désinscription et Waiting List
		// On vérifie si on a des données en POST ou en GET
		$GP_name	= ""; 
		$GP_email	= "";
		$GP_date 	= "";
		$isAdmin = false;
		
		if		($_GET["name"] != "") 		$GP_name	=	$_GET["name"];
		elseif  ($_POST["name"] != "")		$GP_name	=	$_POST["name"];
		
		if		($_GET["email"] != "") 		$GP_email	=	$_GET["email"];
		elseif  ($_POST["email"] != "") 		$GP_email	=	$_POST["email"];
											$GP_date 	=	$_GET["date"];
		
		//echo 'Debug :'.$GP_date.' - '.$GP_email.' - '.$GP_name;
		
		if($GP_name !="" && $GP_email!="") {  // Si on a un post ou un get d'email et de name, l'inscription est ouverte
			$unlockInsc	= true;
			$unlockStyle 	= "unlocked";
			$urlIdentity 	= '?name='.$GP_name.'&email='.$GP_email;
			$myURL 		= $baseURL.'/'.$urlIdentity; // On génère l'URL complète pour que l'utilisateur puisse le mettre en favoris
			
			// On détermine si la personne est admin
			foreach ($arrayAdmin as $k => $v) {
				if($k == $GP_name && $v == $GP_email) $isAdmin = true;
				}
			}
			
		else $myURL = $baseURL.'?anonymous';
		
		
		// Gestion de l'inscription à la séance
			// Si on est sur un act ADD, alors on termine l'inscription
			if($_GET["act"] == "add" && $GP_name != "" && $GP_email != "" && $GP_date!="") {		
				// On vérifie qu'on a pas déjà une inscription avec ce nom et cet email
				$xmlWriteQuery		= $xml->xpath("//insc[@email= '$GP_email' and @name='$GP_name' and @date='$GP_date']");
				$xmlWriteCount		= $xml->xpath("//insc[@date= '$GP_date']"); // On compte le nombre d'inscription avant d'aller plus loin
				
				if 		(count($xmlWriteQuery) > 0 ) echo '<script>alert("Vous êtes déjà inscrit sur cette session")</script>';	 // Si on trouve une inscription dans cette date avec ce nom et cet email, on arrête le script				
				elseif 	(count($xmlWriteCount) >= $participantsMax) echo '<script>alert("Désolé ! La place a été prise le temps que vous cliquiez sur le bouton !")</script>'; // Si on a atteint le nombre max de participant pendant le raffraichissement, on arrête le script		
				
				// On écrit le fichier XML pour les inscriptions
				else {
					$cs = $xml->addChild('insc','');	 // On ajoute une nouvelle entrée		
					$cs->addAttribute("date",$GP_date);
					$cs->addAttribute("name",$GP_name);
					$cs->addAttribute("email",$GP_email);
					
					$dom = new DOMDocument('1.0'); $dom->preserveWhiteSpace = false; $dom->formatOutput = true; // Préservation de la présentation
					$dom->loadXML($xml->asXML()); // On charge le résultat dans un DOM Doc
					$dom->save("data.xml"); // On écrit le résultat
					
					
					// On en profite pour se retirer de la waiting list le cas échéant
					foreach($wl->xpath('//wl[ @email="'. $GP_email .'" and @name="'.$GP_name.'" and @date="'.$GP_date.'"]') as $el) {
						$domRef = dom_import_simplexml($el); 
						$domRef->parentNode->removeChild($domRef); // On supprime le child du parent pour retomber sur notre entrée
						$dom = new DOMDocument('1.0'); $dom->preserveWhiteSpace = false; $dom->formatOutput = true; // Préservation de la présentation
						$dom->loadXML($wl->asXML()); // On charge le résultat dans un DOM Doc
						$dom->save("wl.xml"); // On écrit le résultat
				  }			
				}
			}
			
			
			// Si on est sur un act REMOVE, alors on supprime le truc
			if($_GET["act"] == "remove" && $GP_name != "" && $GP_email != "") {	
				foreach($xml->xpath('//insc[ @email="'. $GP_email .'" and @name="'.$GP_name.'" and @date="'.$GP_date.'"]') as $el) {
					$domRef = dom_import_simplexml($el); 
					$domRef->parentNode->removeChild($domRef); // On supprime le child du parent pour retomber sur notre entrée
					$dom = new DOMDocument('1.0'); $dom->preserveWhiteSpace = false; $dom->formatOutput = true; // Préservation de la présentation
					$dom->loadXML($xml->asXML()); // On charge le résultat dans un DOM Doc
					$dom->save("data.xml"); // On écrit le résultat
				  }
				  
				$kp = explode("-", $GP_date); // On explose la date pour pouvoir manipuler le contenu - 0 = année, 1 = mois, 2 = jour, 3 = heure	
				$kp2Trim 			= $kp[1] - 1; // On enleve 1 pour tomber sur l'array	
				$monthFr 			= $monthHuman[$kp2Trim];
				$hourFr 			= substr_replace($kp[3], "h" , 2, 0); // On rajoute un "H" pour une lecture facile		
				$dateHuman 		= $dayFr .' '. $kp[2] .' '.$monthFr.' '. $kp[0].' à '.$hourFr.''; // On transforme la date 		
				  
				// On génère les emails pour les personnes en liste d'attente
					foreach($wl->xpath('//wl[@date="'.$GP_date.'"]') as $el) {					
						$mailBody = '
							Bonjour '.$el["name"].'<br/><br/>
							Une place vient de se libérer pour la séance d\'AVIFIT du <b>'.$dateHuman.'</b> ! Une chance !<br/><br/>
							Si la place est toujours disponible, vous pouvez utiliser le lien suivant pour la retrouver la liste des sessions : <a href="'.$baseURL.'/?name='.$el["name"].'&email='.$el["email"].'" target="_blank">Liste des séances d\'Avifit disponibles BETA TEST</a>.<br/><br/>
							Ce lien vous authentifie automatiquement.<br/><br/>	
							<i>Ce mail est automatique, NE PAS REPONDRE ! </i><br/><br/>					
							A bientôt !';
							
						
						// On prépare le mail à envoyer pour confirmer
						$to = $el["email"] ;
						$subject = 'AS1881 - Une place vient de se libérer pour le '.$dateHuman.' !';
						$message =  $mailBody;
						
						$headers =  'From: [TEST !!!] AS1881 - Avifit <avironstrasbourg1881@gmail.com >' . "\r\n";
						$headers .= 'Reply-To: avironstrasbourg1881@gmail.com ' . "\r\n";
						$headers .= "MIME-Version: 1.0\r\n";
						$headers .= "Content-Type: text/html; charset=UTF-8\r\n";
						// On envoie le mail 
						mail($to,$subject,$message, $headers);
						// echo $message; // Debug
					}
				
			}
			
			// Si on est sur un act REMOVE, alors on supprime le truc
			if($_GET["act"] == "adminRemove" && $_GET["targetName"] != "" && $_GET["targetEmail"] != "" && $_GET["date"] != "" && $isAdmin == true)  {	
				foreach($xml->xpath('//insc[ @email="'. $_GET["targetEmail"] .'" and @name="'.$_GET["targetName"].'" and @date="'.$_GET["date"].'"]') as $el) {
					$domRef = dom_import_simplexml($el); 
					$domRef->parentNode->removeChild($domRef); // On supprime le child du parent pour retomber sur notre entrée
					$dom = new DOMDocument('1.0'); $dom->preserveWhiteSpace = false; $dom->formatOutput = true; // Préservation de la présentation
					$dom->loadXML($xml->asXML()); // On charge le résultat dans un DOM Doc
					$dom->save("data.xml"); // On écrit le résultat
				  }
				  
				$kp = explode("-", $GP_date); // On explose la date pour pouvoir manipuler le contenu - 0 = année, 1 = mois, 2 = jour, 3 = heure	
				$kp2Trim 			= $kp[1] - 1; // On enleve 1 pour tomber sur l'array	
				$monthFr 			= $monthHuman[$kp2Trim];
				$hourFr 			= substr_replace($kp[3], "h" , 2, 0); // On rajoute un "H" pour une lecture facile		
				$dateHuman 		= $dayFr .' '. $kp[2] .' '.$monthFr.' '. $kp[0].' à '.$hourFr.''; // On transforme la date 		
				  
				// On génère les emails pour les personnes en liste d'attente
					foreach($wl->xpath('//wl[@date="'.$GP_date.'"]') as $el) {					
						$mailBody = '
							Bonjour '.$el["name"].'<br/><br/>
							Une place vient de se libérer pour la séance d\'AVIFIT du <b>'.$dateHuman.'</b> ! Une chance !<br/><br/>
							Si la place est toujours disponible, vous pouvez utiliser le lien suivant pour la retrouver la liste des sessions : <a href="'.$baseURL.'/?name='.$el["name"].'&email='.$el["email"].'" target="_blank">Liste des séances d\'Avifit disponibles BETA TEST</a>.<br/><br/>
							Ce lien vous authentifie automatiquement.<br/><br/>	
							<i>Ce mail est automatique, NE PAS REPONDRE ! </i><br/><br/>					
							A bientôt !';
							
						
						// On prépare le mail à envoyer pour confirmer
						$to = $el["email"] ;
						$subject = 'AS1881 - Une place vient de se libérer pour le '.$dateHuman.' !';
						$message =  $mailBody;
						
						$headers =  'From: [TEST !!!] AS1881 - Avifit <avironstrasbourg1881@gmail.com >' . "\r\n";
						$headers .= 'Reply-To: avironstrasbourg1881@gmail.com ' . "\r\n";
						$headers .= "MIME-Version: 1.0\r\n";
						$headers .= "Content-Type: text/html; charset=UTF-8\r\n";
						// On envoie le mail 
						mail($to,$subject,$message, $headers);
						// echo $message; // Debug
					}			
			}
		
		// Gestion de la waiting list
			// Si on est sur un act waitinListRemove, alors on supprime le truc
			if($_GET["act"] == "waitingListRemove" && $GP_name != "" && $GP_email != "") {	
				foreach($wl->xpath('//wl[ @email="'. $GP_email .'" and @name="'.$GP_name.'" and @date="'.$GP_date.'"]') as $el) {
				   $domRef = dom_import_simplexml($el); 
				   $domRef->parentNode->removeChild($domRef); // On supprime le child du parent pour retomber sur notre entrée
				   $dom = new DOMDocument('1.0'); $dom->preserveWhiteSpace = false; $dom->formatOutput = true; // Préservation de la présentation
				   $dom->loadXML($wl->asXML()); // On charge le résultat dans un DOM Doc
				   $dom->save("wl.xml"); // On écrit le résultat
				  }				
			}
			
			// Si on est sur un act waitingListADD, alors on y go
			if($_GET["act"] == "waitingListAdd" && $GP_name != "" && $GP_email != "" && $GP_date!="") {		
				// On vérifie qu'on a pas déjà une inscription avec ce nom et cet email
				$wlWriteQuery		= $wl->xpath("//wl[@date= '$GP_date' and @name='$GP_name' and @email='$GP_email']");
				
				if (count($wlWriteQuery) > 0 ) { // Si on trouve une inscription dans cette date avec ce nom et cet email, on arrête le script
					echo '<script>alert("Vous êtes déjà inscrit sur cette liste d\'attente")</script>';
					
				}		
				
				// On écrit le fichier XML pour les inscriptions
				else {
					$cs = $wl->addChild('wl','');	 // On ajoute une nouvelle entrée		
					$cs->addAttribute("date",$GP_date);
					$cs->addAttribute("name",$GP_name);
					$cs->addAttribute("email",$GP_email);
					$wl->asXML("wl.xml"); // On écrit dans un fichier XML
				}
		}
		
	//---------------------- Gestion des filtres
	$listFiltersInCSS 	= ""; // On vide le contenu qui sera envoyé dans la CSS au cas où
	$listFiltersInURL 	= ""; // On vide la liste des filtres pour commencer
	$listFiltersInHref = ""; // On vide la liste des items dans le href qu'on va faire
	$listFiltersArray 	= array(); // On prépare un array pour stocker les statuts des filtres
	$listFiltersI		= 0 ;
	if($unlockInsc 	!= true) $urlSafety = "?anonymous";
	
	// Il faut que je repasse deux fois par cette boucle pour pouvoir avoir l'état de TOUS les filtres. J'ai pas trouvé de moyen d'optimiser la chose.
	foreach($arraySeances as $as => $ask) {	
		$listSeanceDuJour = explode(",", $ask); // On éclate la liste des horaires du jours
		
		foreach ($listSeanceDuJour as $i) { // On commence par établir l'URL pour maintenir les différents éléments à travers les manipulations
			if($i != "") {
				$aski = substr_replace($i, "h" , 2, 0); // On rajoute un "H" pour une lecture facile
				$cssFiltersName = $as.''.$aski; // On génère le nom de la classe CSS pour l'affichage/désaffichage
				
				if($_GET[$cssFiltersName] == "hide") {
					$listFiltersArray[$cssFiltersName] = "hide";
					$listFiltersI++ ; // On incrémente le compteur de filtre actif
					}
					
				elseif($_GET[$cssFiltersName] == "show" OR $_GET[$cssFiltersName] == "") { // Le but est de récupérer les items dans l'URL, de changer l'option pour CET element, mais de garder son statut pour générer les liens suivants			
					$listFiltersInURL .= ""; // Dans l'URL actuelle, on ne mets rien si rien n'est précisé	
					$listFiltersArray[$cssFiltersName] = "show";	
					}				 
			}
		}
	}
	
	foreach($arraySeances as $as => $ask) {	
		$listSeanceDuJour = explode(",", $ask); // On éclate la liste des horaires du jours
		
		foreach ($listSeanceDuJour as $i) { // On commence par établir l'URL pour maintenir les différents éléments à travers les manipulations
			if($i != "") {
				$aski = substr_replace($i, "h" , 2, 0); // On rajoute un "H" pour une lecture facile
				$cssFiltersName = $as.''.$aski; // On génère le nom de la classe CSS pour l'affichage/désaffichage
				$listFiltersInHref = ""; // On reset l'HREF pour cette variable
				
				// On commence une boucle avec l'array qui contient les filtres
				foreach($listFiltersArray as $filter => $filterkey) { 
					if($filter != $cssFiltersName && $filterkey == "hide") { // Si l'array ne correspond pas au filtre et que son statut est HIDE, on l'ajoute à l'URL pour cette phase là
						$listFiltersInHref .= '&'.$filter.'=hide';				
					}
					elseif($filter == $cssFiltersName && $filterkey == "hide") {
						$listFiltersInHref .= '';
						$listFiltersInClass = 'filter-hidden';
						$listFiltersInURL .= '&'.$cssFiltersName.'=hide';
						$listFiltersInCSS .= '.'.$cssFiltersName.' {display:none;}';
					}
					elseif($filter == $cssFiltersName && $filterkey == "show"){
						$listFiltersInHref .= '&'.$cssFiltersName.'=hide';
						$listFiltersInClass = 'filter-shown';	
					}
						
					// DEBUG   echo 'Pour : '. $filter.' => '.$filterkey.' ; URL dans le HREF = '.$listFiltersInHref.'<br>';
				}		
				 
				$listFilters .= '<a href="'.$myURL.''.$listFiltersInHref.'" class="'.$listFiltersInClass.'">'.$as .' à '.$aski.'</a>    ';				
			}
		}
	}	
	if($listFiltersI	> 0) $listFilters = '<a href="'.$myURL.'" >Toutes les séances</a>    '. $listFilters ;
	
	//---------------------- Affichage
		// Gestion des dates affichées	
			// On manipule les années
			if ( $dateTodayPieces[1] == 12 ) $dateLimitYear = $dateTodayPieces[0] + 1; // Si on est en décembre, la limite est fixé à l'an prochain
			else $dateLimitYear = $dateTodayPieces[0]; 
			
			// On manipule les mois
			if($dateTodayPieces[2] >= $dateDebloquante ) { $dateLimitMonth = $dateTodayPieces[1] + 1 + $dateDebloLimit ;} // Si on est le $dateDebloquante du mois, on décale au mois suivant
			else { $dateLimitMonth = $dateTodayPieces[1] + $dateDebloLimit ;}
			
			$dateLimitMonthHuman = $monthHuman[ $dateLimitMonth - 2 ];
			
			// On définit le jour de la limite, qui sera toujours le 1er du mois suivant pour plus de facilité, et ca m'évite de prendre en compte les mois à 28, 29, 30 & 31 jours
			$dateLimitDay = 1 ;
		
		
		$dateLimit = $dateLimitYear.'-'.$dateLimitMonth.'-'.$dateLimitDay; // On reconstruit la date limite	
		$dates = getBetweenDates($dateToday, $dateLimit); // On liste les dates entre aujourd'hui et la date limite
			
		$kId			= 1; // On met le compteur de session à 1
		
		$dateDisplay =""; // On prépare le display
		
		foreach ($dates as $k) { // Boucle qui passera chaque jour en revue
		
			$kp = explode("-", $k); // On explose la date pour pouvoir manipuler le contenu - 0 = type jour, 1 = année, 2 = mois, 3 = jour		
				$kp2Trim = $kp[2] - 1; // On enleve 1 pour tomber sur l'array		
				$dayFr	= $dayHuman[ $kp[0] ] ;
				$monthFr = $monthHuman[$kp2Trim];
				
			
			if($arraySeances[$dayFr] != "") { // Si le jour correspond à l'array du début, alors on affiche une ligne
			
				$listSeanceDuJour = explode(",", $arraySeances[$dayFr]); // On éclate la liste des horaires du jours
				
				
				foreach ($listSeanceDuJour as $i) {		
				
					// On formate la date en un truc pas trop moche
					$iHuman 			= substr_replace($i, "h" , 2, 0); // On rajoute un "H" pour une lecture facile		
					$animateurDuJour 	= $arrayAnimateur[ $dayFr ]; // On détermine l'animateur du jour
					$inscMe			= false; // On remet à false le fait d'être inscrit
					$dateHuman 		= '<span class="date-first-line"><span class="date-jour-type">'. $dayFr .'</span> '. ($animateurDuJour != "" ? "avec $animateurDuJour" : "").' </span><br/><span class="date-second-line"> <span class="date-jour">'. $kp[3] .'</span> <span class="date-mois">'.$monthFr.'</span> <span class="date-annee">'. $kp[1].'</span> à <span class="date-heure">'.$iHuman.'<span></span>'; // On transforme la date 				
					
					
					// On récupère les inscrits et les gens de la waiting list
						$dateXmlQuery 	= $kp[1].'-'.$kp[2].'-'.$kp[3].'-'.$i; // Je reconstruit la date
						
						// Gestion des inscrits 
						$xml_query 		= $xml->xpath("//insc[@date= '$dateXmlQuery' ]"); // On query uniquement le xml pour la date demandée
						$i_inscrits		= 0 ; // On met un i aux inscrits pour pouvoir les compter
						$listInscrits 	= ""; // On reset la liste des inscrit
						
						// Gestion de la waiting list
						$wl_count_query	= $wl->xpath("//wl[@date= '$dateXmlQuery']"); // On query uniquement le xml pour la date demandée
						$wl_query 		= $wl->xpath("//wl[@date= '$dateXmlQuery' and @name='$GP_name' and @email='$GP_email']"); // On query uniquement le xml pour la date demandée
						$wl_nbr_inscrits	= count($wl_count_query);					
						$wlInscrits		= ""; // On reset la waiting list au cas où
						$listInscritsMe	= ""; // On reset le fait d'être inscrit
						if(count($wl_query) > 0) $wlMe = true ; else $wlMe = false ; // Si on est présent dans la waiting list, on indique que le statut "wlMe" est true,sinon, on est en false		
					
					// On commence la boucle, pour chaque entrée
					foreach($xml_query as $q) {		
						if($q["name"] == $GP_name && $q["email"] == $GP_email) 	{ // Si on est inscrit on met en évidence son inscription et on permet de se désinscrire
								$listInscritsMe = '<span class="cmoi"> '.$q["name"] .'</span> | '; 
								$inscMe = true ; 
							}
						else {
							if($isAdmin == true) $listInscrits .= '<a href="'.$myURL.'&act=adminRemove&targetName='.$q["name"].'&targetEmail='.$q["email"].'&date='.$q["date"].'">';
							$listInscrits .= '<span class="inscrit">'.$q["name"] .'</span> '; // Sinon, on affiche juste les noms des inscrits							
							if($isAdmin == true) $listInscrits .= '</a>';			
						}
						$i_inscrits++; // On incrémente le nombre d'inscrits
						}
					$listInscrits = $listInscritsMe.' '.$listInscrits ; // Pour que ca soit plus lisible, je mets l'inscription en début de liste
						
					// On détermine la liste des gens en waiting list... à voir si on le garde
					if($wl_nbr_inscrits >> 0) {
						$wlInscrits = '<br/><span class="waitingListIntro">Liste d\'attente ('. $wl_nbr_inscrits .') :';  
						foreach($wl_count_query as $r) {
							$wlInscrits .= $r['name'].' ';
						}
						$wlInscrits .= '</span>';
						}
					
					// On compte le nombre de participants
					$nbrPlacesRestantes = $participantsMax - $i_inscrits; // On détermine le nombre de places restantes
					if($i_inscrits>=$participantsMax) $inscFull = true; else $inscFull = false; // Si on a atteint le nombre max de participants, on désactive l'URL d'inscription et on ouvre la possibilité pour la liste d'attente
					
					
					
					
					// On détermine ici si une nouvelle class sera appliquée sur la ligne, pour faciliter la lecture
					if($inscMe==true)			$cardCssDisplay = "inscMe";
					elseif($wlMe == true) 		$cardCssDisplay = "wlme";
					elseif($inscFull == true) 	$cardCssDisplay = "inscFull";
					else						$cardCssDisplay = "";
					
					// On détermine le style de la ligne pour une alternance de couleurs plus sympa
					if($kId % 2 == 0) 			$cardCssDisplay .= ' type1';
					else			 			$cardCssDisplay .= ' type2';
					
					
					// On affiche le tout
					$dateDisplay .= '
						<div class="card '.$cardCssDisplay.' '.$dayFr.''.$iHuman.'">
								<div class="cell date">'.$dateHuman.'</div>
								<div class="cell inscrits">'.$listInscrits.' '.$wlInscrits.'</div>
								<div class="cell places">'.$nbrPlacesRestantes.' <br/> <span class="text-places-restantes">places restantes</span></div>
								<div class="cell check">';
								if($unlockInsc == true) {
									if		($inscFull == false && $inscMe == false )						$dateDisplay.= '<a class="insc-insc" 				href="'.$urlIdentity.'&act=add&date='.$dateXmlQuery.''.$listFiltersInURL.'#anchor-form">S\'inscrire</a>';
									elseif	($inscFull == true && $inscMe == false && $wlMe	 == false)		$dateDisplay.= '<a class="insc-listeattente"		href="'.$urlIdentity.'&act=waitingListAdd&date='.$dateXmlQuery.''.$listFiltersInURL.'#anchor-form">S\'inscrire sur <br/>Liste d\'attente<br/> ('.$wl_nbr_inscrits.' en attente)</a>';	
									elseif	($inscFull == true && $inscMe == false && $wlMe	 == true)		$dateDisplay.= '<a class="insc-listeattente-me"	href="'.$urlIdentity.'&act=waitingListRemove&date='.$dateXmlQuery.''.$listFiltersInURL.'#anchor-form">Se retirer de <br/>Liste d\'attente<br/> ('.$wl_nbr_inscrits.' en attente)</a>';							
									elseif	($inscMe == true)											$dateDisplay.= '<a class="insc-desinsc" 			href="'.$urlIdentity.'&act=remove&date='.$dateXmlQuery.''.$listFiltersInURL.'#anchor-form">Se désinscrire</a>';
								}
								
					$dateDisplay.='				
								</div>
							</div>
						';
						
						$kId ++;
					}
				}
			}
		$kCount = $kId - 1 ;// On calcule le nombre de séances
		
		
		// Affichag du login
		
		if($unlockInsc  == false) $loginDisplay =  '
			<input name="name" placeholder="Nom" value="'.$GP_name.'" />
			<input name="email" type="email" placeholder="E-mail" value="'.$GP_email.'"/>
			<input type="submit" value="S\'authentifier"/>
			';
		else {
			$loginDisplay =  '
			Bienvenue <b>'.$GP_name.'</b> ('.$GP_email.') ! <br/> <br/>
			Voici votre lien de connexion rapide, gardez-le en favoris :<br/> <a href="'.$myURL.'">'.$myURL.'</a><br/><br/>
			<a href="https://dev.codeix.fr/as1881-avifit/">Se déconnecter</a>								
			';			
									
			if($isAdmin == true) $loginDisplay .=  '<br/><br/><b>Vous êtes administrateur ! Vous pouvez supprimer des personnes dans les listes</b> !';
			}
		
		
	?>
	<html>
		<head>
			<script src="https://ajax.googleapis.com/ajax/libs/jquery/3.6.3/jquery.min.js"></script>
			<title>AS1881 - Séances d'Avifit BETA</title>
			<meta charset="UTF-8">
			<meta name="description" content="AS1881 - Séances d'Avifit BETA">
			<meta name="keywords" content="AS881, Avifit, Réservation, Strasbourg, Aviron, Aviron Strasbourg, Aviron Strasbourg 1881">
			<meta name="author" content="Alexis JENNY">
			<meta name="viewport" content="width=device-width, initial-scale=1.0">
			<style>
				* {font-family:Helvetica;}
				html {padding:0;margin:0;}
				body {background:#F0F0F0;padding:0;margin:0;background-image: linear-gradient(175deg,#F0F0F0 40%, #c92d39 40.1%,#c92d39 60%, #F0F0F0 60.1%);background-attachment: fixed;}
				header {text-align:center;font-weight:bold;padding:30px;color:#c92d39;font-size:20pt;}
				header .header-logo {margin:10px auto;height:300x;}
				a, a:active, a:visited {color:#c92d39;}
				.hidden {display:none;}
				.table	{display:table;}
				.table-row	{display:table-row;}
				.table-cell	{display:table-cell;}
				
				.content {width: 980px;margin : 10px auto;border: 4px solid #c92d39;padding: 20px 20px;border-top:0;border-bottom:0;border-radius:5px;box-sizing: border-box;background:#F9F9F9;box-shadow:1px 1px 3px #000;}
					.introduction {margin: 20px 0 20px 0;}
					.inscription {margin: 20px 0;}
					.form {padding:10px;text-align:center;}
					.form input {width:20%;border:2px solid #c92d39;padding:10px;background:#FFFFFF;color:#c92d39;border-radius:5px;}
					.form input[type=submit] {background:#c92d39;color:#FFFFFF;cursor:pointer;}
					.form input[type=submit]:hover {cursor:pointer;background:#FFFFFF;color:#c92d39;}
					
					.control-panel {text-align:left;font-size:8pt;margin-top:20px;border-top: 2px dashed #c92d39;padding-top:30px;}
					.control-panel a {margin-right:10px;padding:3px 7px;border-radius:5px;text-decoration:none;}						
						a.filter-shown, a.filter-shown:visited, a.filter-shown:active 	{border:1px solid #c92d39;font-weight:bold;background:#c92d39;color:white;}
						a.filter-hidden,a.filter-hidden:visited,a.filter-hidden:active	{border:1px solid grey;background:white;color:#c92d39;}
				
					.unlocked input {opacity:.3;border:0;background:#rgba:0000}
					
					.card {display:table;width:100%;margin:3px auto;border-spacing: 5px;border-collapse: separate;box-sizing: border-box;border-radius:5px;border:2px solid transparent;z-index:1;transition:all .5s}
						.cell 					{display:table-cell;padding:5px 2px;text-wrap:normal;word-wrap:break-word;vertical-align:middle;}
						.cell:not(:last-child) 	{border-right:2px solid #c92d39;}
						.date 					{width:20%;}
						.date .date-first-line	{font-size:80%;}
						.date .date-second-line	{}
						.inscrits 				{width:60%;font-size:80%;}
						.inscrits span.inscrit	{margin-left:5px;}
						.inscrits span.inscrit:not(:last-child) {border-right:1px solid grey;padding-right:5px;}
						.places 				{width:5%;font-weight:bold;text-align:center;padding:0 5px 0 5px;}
							.places .text-places-restantes {font-size:70%;color:grey;}
						.check 					{width:15%;font-size:90%;}
						.cmoi 					{font-weight:bold;color:white;background:#c92d39;padding:5px;border-radius:5px;}
						.check a, .check a:active,.check a:visited {display:block;background:#c92d39;border-radius:5px;color:white;text-align:center;padding:5px;text-decoration:none;font-weight:bold;border:1px solid transparent;}
						.check a.insc-insc {}
						.check a.insc-desinsc {background:white;color:#c92d39;border:1px solid #c92d39;font-size:8pt;}
						.check a.insc-listeattente {background:#5861D8;font-size:8pt;}
						.check a.insc-listeattente-me {background:white;border:1px solid #5861D8;color:#5861D8;font-size:8pt;}
						.check a:hover {transition: all .7s; box-shadow: inset -20em 0 0 0 #00000050;}
						
						.date-jour-type {font-weight:bold;}
						.date-jour		{font-weight:bold;color:#c92d39}
						.date-mois 		{font-weight:bold;color:#c92d39}						
						.date-annee		{}
						.date-heure		{font-weight:bold;color:#c92d39}
						
						.waitingListIntro {font-size:7pt;color:grey;}
						
						
					.card.Lundi		{}
					.card.Mercredi	{}
					.card.Vendredi	{}
					.card.type1		{background:transparent;}
					.card.type2		{background:#F0F0F0FA;}
					
					
					.card.inscFull 	{background:transparent;opacity:80%;}
					.card.inscMe 	{background:#c92d3905;border:2px solid #c92d3910; border-left:5px solid #c92d39;}
						
					.card:hover 							{transition: all .4s;border:2px solid #c92d39;border-left:5px solid #c92d39;transition: all .5s;}
					.card:not(.inscMe):hover				{border:2px solid #c92d39;}
						.card:hover .cell					{transition: all .4s}
						.card:hover	.cell:not(:last-child) 	{border-right:2px solid grey;}
					.card:hover .cell a,.card:hover .cell a:active, .card:hover .cell a:visited {}
					
				
					.notice {background:#F2F2F2;border:1px solid #000;border-radius:5px;margin:20px auto;padding:20px;box-sizing:border-box;font-size:80%;}
						.notice .table {display:table;width:100%;border-collapse: separate;box-sizing: border-box;border-spacing:10px;}
						.notice .check {display:table-cell;width:150px;border-collapse: separate;box-sizing: border-box;border-spacing:10px;}
						.notice .table-cell {font-size:90%;}

					
						
				hr.fancy-line {border: 0;height: 1px;}
				hr.fancy-line:before {top: -0.5em;height: 1em;}
				hr.fancy-line:after {content:'';height: 0.5em; top:1px;}
				hr.fancy-line:before, hr.fancy-line:after {content: '';position: relative; width: 100%;}
				hr.fancy-line, hr.fancy-line:before {background: radial-gradient(ellipse at center, rgba(0,0,0,0.1) 0%,rgba(0,0,0,0) 75%);}
				#bottom-line {margin-top:30px;}
				
				<?php echo $listFiltersInCSS;?>
				
				@media screen and (max-width: 1080px) {
				  .content {width:100%;padding:5px;}
				  .content .form input {display:block;width:85%;margin:10px auto;}
				  .card {width:98%;}
				  .header-logo {height:100px;}
				  .inscrits {font-size:50%;}				
				}
			</style>
			<script>
				history.replaceState('', 'AS1881 - Avifit - <?php echo $GP_name;?>', ' <?php echo $myURL;?>#anchor-form ');
			</script>			
		</head>
		
		
		<header> 			
			<img class="header-logo" src="logo-as1881-2.svg" alt="logo-avifit"/>
		</header>
		
		
		<body>
			<div class="content">
				<div class="introduction">
					Bienvenue sur la page d'inscriptions aux sessions d'avifit du club Aviron Strasbourg 1881. <br/>										
				</div>
				
				<div class="notice">
					Les séances sont listées ci-dessous avec l'heure du début :
						<ul>
							<li>Les séances durent 1 heure maximum</li>
							<li>Evitez de venir en retard, si vous n'êtes pas là au début, votre place pourra être donnée à un membre arrivant</li>
							<li>Les animateurs sont donnés à titre d'information et une modification peut-être apportée</li>
							<li>Les séances du mois suivant sont débloquées le  <?php echo $dateDebloquante ;?></li>					
						</ul>
					Le nombre de places restantes est indiqué à droite.<br/>
					
					<div class="table">
						<div class="table-row">
							<div class="check"><a href="javascript:void(0);" class="insc-insc" >S'inscrire</a></div>
							<div class="table-cell">S'il reste des places disponibles, ce bouton s'affiche, vous pouvez vous inscrire. Vous ne recevrez aucune confirmation par mail, le tout est instantanée.</div>
						</div>
						<div class="table-row">						
							<div class="check"><a href="javascript:void(0);" class="insc-desinsc" >Se désinscrire</a></div>
							<div class="table-cell">Une fois inscrit, vous pouvez vous désinscrire si vous ne pouvez pas venir à la séance, libérant une place pour un autre membre. <br/><b>Attention : </b> Si vous ne vous désinscrivez pas d'une session que vous allez manquer, l'administrateur peut vous désinscrire des séances suivantes, libérant des places pour d'autres membres.</div>
						</div>
						<div class="table-row">	
							<div class="check"><a href="javascript:void(0);" class="insc-listeattente" >S'inscrire sur la <br/>Liste d'attente</a></div>
							<div class="table-cell">Si une session est complète, vous pouvez vous mettre en liste d'attente et vous recevrez un mail lorsqu'une place se libère</div>
						</div>
						<div class="table-row">	
							<div class="check"><a href="javascript:void(0);" class="insc-listeattente-me" >Se retirer de la <br/> Liste d'attente</a></div>
							<div class="table-cell">Si vous ne désirez plus être prévenu qu'une place se libère, vous pouvez cliquer sur ce bouton pour vous désinscrire des notifications pour cette session</div>
						</div>
					</div>						
				</div>
				
				<hr class="fancy-line"/>
							
				<div class="inscription">
					<div class="form <?php echo $unlockStyle;?> " id="anchor-form">
						<form action="/as1881-avifit/#anchor-form" method="post">
							<?php echo $loginDisplay;?>	
						</form>					
					
					<div class="control-panel">
						Filtres :
						<?php echo $kCount.' séances sont ouvertes aux inscriptions jusque <b> fin '.$dateLimitMonthHuman.'</b>.';?>
						<br/><br/>
						<?php echo $listFilters;?>
					</div>
					
					</div>
					<!----
					<div class="card">
						<div class="cell date">Date</div>
						<div class="cell animateur">Animateur</div>
						<div class="cell inscrits">Liste des participants</div>
						<div class="cell check"></div>
					</div>	
					-->			
				</div>			
				
				<div class="inscription-passe">
					<?php echo $dateDisplay; ?>
				</div>
				
			</div>
			
		</body>
		
		<footer>
		</footer>
	</html>
