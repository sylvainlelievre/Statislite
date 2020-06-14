<?php
/*
 * Fichier stat.php à inclure par le fichier body.inc.html 
 * ou par une saisie directe dans configuration du site, options avancées, Insérer un script dans "Body"
 * <?php include './module/statislite/include/stat.php'; ?>
*/

// Paramètres
$base = './site/file/statislite/';
$basebot = './site/file/statislite/filtres_primaires/';

// Lecture de l'adresse IP
// Si internet partagé
if (isset($_SERVER['HTTP_CLIENT_IP'])) {
	$ip = $_SERVER['HTTP_CLIENT_IP'];
}
// Si derrière un proxy
elseif (isset($_SERVER['HTTP_X_FORWARDED_FOR'])) {
	$ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
}
// Sinon : IP normale
else {
	if(isset($_SERVER['REMOTE_ADDR'])){
		$ip = $_SERVER['REMOTE_ADDR'];
	}
	else{
		$ip = '';
	}
}

// Si cumul.json n'existe pas on le crée
if(! is_file($base.'cumul.json')){
	$json = '{}';
	$cumul = json_decode($json, true);
	$cumul['nb_clics'] = 0;
	$cumul['nb_visites'] = 0;
	$cumul['duree_visites'] = 0;
	$cumul['clients'] = array( 'systeme' => array(), 'navigateur' => array(), 'langage' => array(), 'localisation' => array());
	$cumul['robots'] = array( 'ua' => 0, 'ip'=> 0, 'np'=> 0, 'tv'=> 0, 'ue'=>0);
	// Si sessionLog.json existe et n'est pas vide date_debut sera sa première date sinon ce sera la date actuelle
	$cumul['date_debut'] = date('Y/m/d H:i:s');
	if(is_file($base.'sessionLog.json')){
		$json = file_get_contents($base.'sessionLog.json');
		$log = json_decode($json, true);
		foreach($log as $numSession=>$values){
			if(isset($log[$numSession]['vues'][0])){
				$cumul['date_debut'] = substr($log[$numSession]['vues'][0], 0 , 19);
			}
			break;
		}
	}
	$cumul['date_fin'] = date('Y/m/d H:i:s');
	$cumul['pages'] = array();
	$json = json_encode($cumul);
	file_put_contents($base.'cumul.json',$json);
}

// Filtrage des robots, test uniquement une fois en début de session
if(!isset($_SESSION['filtrage'])){

   // Filtrage par HTTP_USER_AGENT
   // liste_bot.txt à mettre à jour avec les données du site http://d1.a.free.fr/downloads.php
   $user_agent = strtolower($_SERVER['HTTP_USER_AGENT']);
   $UAbot = 0;
   if(is_file($basebot.'liste_bot.txt')){
	   $regex = file_get_contents($basebot.'liste_bot.txt');
	   $UAbot=preg_match( $regex, $user_agent );
   }
   //UA vide c'est considéré comme un robot
   if ($user_agent == ""){
		$UAbot=1;
	}
	
   // Filtrage par vos IP uniquement (CNIL) 
   $IPbot = 0;
   if(is_file($basebot.'liste_vos_ip.txt')){
	   $regex = file_get_contents($basebot.'liste_vos_ip.txt');
	   $IPbot = preg_match( $regex, $ip );
   }
	 
   // Formation du résultat 1, 0
   if ($UAbot==1 || $IPbot==1){
		$resultat=1;
		// comptage des 'robots' par type UA ou IP
		if($UAbot == 1){
			$type = 'ua';
			// Enregistrement dans le fichier robots.json
			if(is_file($base.'robots.json')){
				$json = file_get_contents($base.'robots.json');
			}
			else{
				$json = '{}';
			}
			$robots = json_decode($json, true);
			$robots[date('Y/m/d H:i:s')] = $_SERVER['HTTP_USER_AGENT'];
			// Limitation aux 200 derniers robots
			if( count($robots) > 200){
				foreach($robots as $key=>$value){
					unset($robots[$key]);
					break;
				}
			}
			$json = json_encode($robots);
			file_put_contents($base.'robots.json',$json);
			
		}
		else{
			$type = 'ip';
		}
		$json = file_get_contents($base.'cumul.json');
		$cumul = json_decode($json, true);
		$cumul['robots'][$type] = $cumul['robots'][$type] + 1;
		$json = json_encode($cumul);
		file_put_contents($base.'cumul.json',$json);
   }
   else{
		$resultat=0;
   }
   $_SESSION['filtrage'] = $resultat;
}

// Filtrage par QUERY STRING
$QSbot = 0;
if(is_file($basebot.'liste_querystring.txt')){
   $regex = file_get_contents($basebot.'liste_querystring.txt');
   $QSbot=preg_match( $regex, $_SERVER['QUERY_STRING'] );
}

// Si c'est un vrai visiteur et que la page n'est pas exclue on enregistre date et query string
if($_SESSION['filtrage'] == 0 && $QSbot == 0){

	// Lecture et décodage du fichier json en cours
	if(is_file($base.'sessionLog.json')){
		$json = file_get_contents($base.'sessionLog.json');
		if(strlen($json) < 20 ){ 
			$json = '{}';
		}
	}
	else{
		$json = '{}';
	}
	$log = json_decode($json, true);

	// Détection d'inactivité de plus de 10 minutes => nouvel indice
	if( ! isset($_SESSION['actif'])){
		$_SESSION['indice'] = bin2hex(openssl_random_pseudo_bytes(16));
	}
	elseif( Time() - $_SESSION['actif'] > 600){
		$_SESSION['indice'] = bin2hex(openssl_random_pseudo_bytes(16));
	}
	$indice = $_SESSION['indice'];
	
	// Identification de l'utilisateur
	$zwii_user_id = 'visiteur';
	if(isset($_COOKIE['ZWII_USER_ID'])){
         $zwii_user_id = $_COOKIE['ZWII_USER_ID'];
    }
	// Nouvel user_id suite à une connexion ou à une déconnexion => nouvel indice
	if(isset($log[$indice]['user_id'])){
		if($zwii_user_id != $log[$indice]['user_id']){
			$_SESSION['indice'] = bin2hex(openssl_random_pseudo_bytes(16));
			$indice = $_SESSION['indice'];
		}
	}
	
	//Initialisation si c'est un nouvel indice
	if(!isset($log[$indice])){
		$log[$indice] = array('ip' => $ip, 'user_id'=> $zwii_user_id, 'userAgent' => $_SERVER['HTTP_USER_AGENT'], 'langage' => $_SERVER['HTTP_ACCEPT_LANGUAGE'], 'referer' => $_SERVER['HTTP_REFERER'], 'vues' => array(), 'client' => array() );
	}
	// Ajout de la vue sous la forme date et page vue
	$indice2 = count($log[$indice]['vues']);
	if( $_SERVER['QUERY_STRING'] != ''){
		$log[$indice]['vues'][$indice2] = date('Y/m/d H:i:s').' * '.$_SERVER['QUERY_STRING'];
	}
	else{
		$log[$indice]['vues'][$indice2] = date('Y/m/d H:i:s').' * Page d\'accueil';
	}

	// Encodage et sauvegarde
	$json = json_encode($log);
	file_put_contents($base.'sessionLog.json',$json);
}

// Vatiable de session utilisée pour détecter l'activité
$_SESSION['actif'] = Time();

?>