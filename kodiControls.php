<?php

// Config
$KodiAddress =  "xxx.xxx.xxx.xxx";
$KodiUser = "user";
$KodiPass = "pass";

$url = "http://" . $KodiAddress . "/jsonrpc";

// Setup Headers

if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {

	header("Access-Control-Allow-Origin: {$_SERVER['HTTP_ORIGIN']}");
	header('Access-Control-Allow-Credentials: true');
	
	if (isset($_SERVER['HTTP_ACCESS_CONTROL_REQUEST_METHOD']))
		header("Access-Control-Allow-Methods: GET, POST, OPTIONS");         

	if (isset($_SERVER['HTTP_ACCESS_CONTROL_REQUEST_HEADERS']))
		header("Access-Control-Allow-Headers: {$_SERVER['HTTP_ACCESS_CONTROL_REQUEST_HEADERS']}");

	exit(0);
}

if (isset($_SERVER['HTTP_ORIGIN'])) {
	header("Access-Control-Allow-Origin: {$_SERVER['HTTP_ORIGIN']}");
	header('Access-Control-Allow-Credentials: true');
	header('Access-Control-Max-Age: 86400');    // cache for 1 day
}

if(isset($_SERVER["CONTENT_TYPE"]) && strpos($_SERVER["CONTENT_TYPE"], "application/json") !== false) {
  $_POST = array_merge($_POST, (array) json_decode(trim(file_get_contents('php://input')), true));
}

$postdata = $_POST;

echo "<pre>Request \r\n";
print_r($postdata);
echo "</pre>";

function sendKodiRequest($data) {
	GLOBAL $url;
	GLOBAL $KodiUser;
	GLOBAL $KodiPass;
	$ch = curl_init( $url );
	# Setup request to send json via POST.
	$payload = json_encode($data);
	$headers = array(
		'Content-Type:application/json',
		'Authorization: Basic '. base64_encode($KodiUser.":".$KodiPass) // <---
	);
	curl_setopt( $ch, CURLOPT_POSTFIELDS, $payload );
	curl_setopt( $ch, CURLOPT_HTTPHEADER, $headers);
	curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
	
	# Return response instead of printing.
	curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true );
	# Send request.
	
	$result = curl_exec($ch);
	curl_close($ch);
	
	# Print response.
	error_log($result);
	echo "Request:\r\n<pre>$payload</pre>";
	echo "Result:\r\n<pre>$result</pre>";
	return $result;

}


function activateWindow($window) {
	if($window == "movies") {
		$data = array("jsonrpc" => "2.0", "method" => "GUI.ActivateWindow", "params" => array("window" => "videos", "parameters" => array("videodb://movies/titles/")), "id" => 1);		
	} else if ($window == "tv shows" || $windows == "tv episodes" || $window == "tv") {
		$data = array("jsonrpc" => "2.0", "method" => "GUI.ActivateWindow", "params" => array("window" => "videos", "parameters" => array("videodb://tvshows/titles/")), "id" => 1);	
	} else if ($window == "tv in progress") {
		$data = array("jsonrpc" => "2.0", "method" => "GUI.ActivateWindow", "params" => array("window" => "videos", "parameters" => array("videodb://inprogresstvshows/")), "id" => 1);	
	} else {
		$data = array("jsonrpc" => "2.0", "method" => "GUI.ActivateWindow", "params" => array("window" => $window), "id" => 1);	
	}
	sendKodiRequest($data);	
	
}

function kodiFullscreen(){
	$data = array("jsonrpc" => "2.0", "method" => "GUI.SetFullscreen", "params" => array("fullscreen" => true), "id" => 1);
	sendKodiRequest($data);	
}

function activeKodiPlayerId() {
	$data = array("jsonrpc" => "2.0", "method" => "Player.GetActivePlayers", "id" => 1);
	$result = json_decode(sendKodiRequest($data), true);
	if($result['result']) {
		$players = $result['result'];
		$count = count($players);
		if($count == 0) {
			return -1;
		}		
		if($count > 1) {
			error_log('More than one active player');
			return $players[0]['playerid'];
		}		
		return $players[0]['playerid'];		
	}	
	return -1;
}

function kodiPlayPause() {
	$playerId = activeKodiPlayerId();
	if($playerId != -1) {
		$data = array("jsonrpc" => "2.0", "method" => "Player.PlayPause", "params" => array("playerid" => $playerId), "id" => 1);
		sendKodiRequest($data);
	}

}

function kodiStop() {
	$playerId = activeKodiPlayerId();
	if($playerId != -1) {
		$data = array("jsonrpc" => "2.0", "method" => "Player.Stop", "params" => array("playerid" => $playerId), "id" => 1);
		sendKodiRequest($data);
	}
}

function kodiMove($direction) {
	$playerId = activeKodiPlayerId();
	if($playerId != -1) {
		if($direction == 'next') {
			$data = array("jsonrpc" => "2.0", "method" => "Player.GoTo", "params" => array("playerid" => $playerId, "to" => "next"), "id" => 1);
			sendKodiRequest($data);		
		} else {
			$data = array("jsonrpc" => "2.0", "method" => "Player.Move", "params" => array("playerid" => $playerId, "direction" => $direction), "id" => 1);			
			sendKodiRequest($data);
		}
	}
}

function kodiPlayMovie($id) {
	$data = array("jsonrpc" => "2.0", "method" => "player.open", "params" => array("item" => array("movieid" => $id)), "id" => 1);
	return sendKodiRequest($data);
}

function kodiGetMovies($string, $includewatched = true) {
	$terms = explode(" ", $string);
	$filter = array();
	foreach($terms as $term){
		array_push($filter, array("operator" => "contains", "field" => "title", "value" => $term));
	}
	if($includewatched){
		array_push($filter, array("operator" => "greaterthan", "field" => "playcount", "value" => "0"));
	}
	
	$data = array("jsonrpc" => "2.0", "method" => "VideoLibrary.GetMovies", "params" => array("sort" => array("order" => "descending", "method" => "year"), "filter" => array("and" => $filter)), "id" => 1);
	return sendKodiRequest($data);

}

function kodiPlayTVEpisode($id) {
	$data = array("jsonrpc" => "2.0", "method" => "player.open", "params" => array("item" => array("episodeid" => $id)), "id" => 1);
	return sendKodiRequest($data);
}

function kodiGetTVEpisodes($id, $includewatched = false, $limit = 0) {
	$limits = array("start" => 0,"end" => $limit);	
	$filter = array();
	if($includewatched){
		array_push($filter, array("operator" => "lessthan", "field" => "playcount", "value" => "1"));
		array_push($filter, array("operator" => "greaterthan", "field" => "playcount", "value" => "0"));
	} else {
		array_push($filter, array("operator" => "lessthan", "field" => "playcount", "value" => "1"));
	}
	$data = array("jsonrpc" => "2.0", "method" => "VideoLibrary.GetEpisodes", "params" => array("sort" => array("order" => "ascending", "method" => "episode"), "tvshowid" => $id, "limits" => $limits, "filter" => array("and" => $filter)), "id" => 1);
	return sendKodiRequest($data);
}

function kodiGetTVShows($string) {
	$terms = explode(" ", $string);
	$filter = array();
	foreach($terms as $term){
		array_push($filter, array("operator" => "contains", "field" => "title", "value" => $term));
	}
	$data = array("jsonrpc" => "2.0", "method" => "VideoLibrary.GetTVShows", "params" => array("sort" => array("order" => "descending", "method" => "year"), "filter" => array("and" => $filter)), "id" => 1);
	return sendKodiRequest($data);
	
}
function kodiGetPlaylists() {
	$data = array("jsonrpc" => "2.0","method" => "Playlist.GetPlaylists", "params" => array(),"id" => 1);
	return sendKodiRequest($data);
}

function getPlayListId($type) {
	$playlists = json_decode(kodiGetPlaylists(),true);
	foreach($playlists['result'] as $playlist) {
		if($playlist['type'] == $type) {
			return $playlist['playlistid'];
		}
	}
}

function kodiMovieSearchResults($items) {
	// TODO: Find a way to output multiple movies

}


function voiceCommand($command) {
	error_log( $command );
	// Next
	if($command == 'kodi next') {
		kodiMove('next');
		return 0;
	}
	// Back
	if($command == 'kodi back') {
		kodiMove('previous');
		return 0;
	}
	// Play/Pause
	if($command == 'kodi pause' || $command == 'kodi unpause' || $command == 'kodi play' || $command == 'kodi resume') {
			kodiPlayPause();
			return 0;
	}
	// Stop
	if($command == 'kodi stop') {
			kodiStop();
			return 0;
	}
	// Full Screen
	if($command == 'kodi fullscreen' || $command == 'kodi full screen') {
		kodiFullscreen();
		return 0;
		
	}	
	// Go To Commands
	$re = '/(go to|goto)\W?(?<window>.*)/mi';
	preg_match_all($re, $command, $goto, PREG_SET_ORDER, 0);
	if(count($goto) > 0) {
		activateWindow($goto[0]['window']);
		return 0;
		
	}	
	// Play/Watch/Find
	$re = '/(?i)(\b(?<command>play|watch|find)\W?(?<filter>latest|oldest|most recent|next)?\W?\b(?<type>movie|film|song|music|tv|episode)?\b)\W?(?<subType>episode|channel|show|album|artist)?\W?(of)?\W?(?<subject>.+)/mi';
	preg_match_all($re, $command, $matches, PREG_SET_ORDER, 0);
	// Print the entire match result
	/*
	echo "<pre>Request \r\n";
	print_r($matches[0]);
	echo "</pre>";
	*/
	if(count($matches) > 0) {
		$command = $matches[0]['command'];
		$filter = $matches[0]['filter'];
		$type = $matches[0]['type'];
		$subType = $matches[0]['subType'];
		$subject = $matches[0]['subject'];
		if($command == 'play' || $command == 'watch') {
			if($type == 'episode' && $filter == 'next') {
				$shows = json_decode(kodiGetTVShows($subject),true);
				
				$episode = json_decode(kodiGetTVEpisodes($shows['result']['tvshows'][0]['tvshowid'], false, 1),true);
				kodiPlayTVEpisode($episode['result']['episodes'][0]['episodeid']);
				/*
				echo "<pre>";
				print_r($shows);
				echo "</pre>";
				echo "<pre>";
				print_r($episode);
				echo "</pre>";
				*/
				return 0;
			}
			if(($type == 'movie' || $type == 'film' ) && $filter == 'next') {
				$movies = json_decode(kodiGetMovies($subject),true);
				if($movies['results']['limits']['total'] == 1) {
					kodiPlayMovie($movies['results']['movies'][0]['movieid']);
				} else {
					
					kodiMovieSearchResults($movies);
					/*
					echo "<pre>";
					print_r($movies);
					echo "</pre>";
					*/
				}
				return 0;
			}
			if((!$type && !$filter) || (!$type && $filter == 'next')   ) {
				$shows = json_decode(kodiGetTVShows($subject),true);
				if($shows['result']['tvshows'][0]['tvshowid']) {
					$episode = json_decode(kodiGetTVEpisodes($shows['result']['tvshows'][0]['tvshowid'], false, 1),true);
					/*
					echo "<pre>";
					print_r($shows);
					echo "</pre>";
					echo "<pre>";
					print_r($episode);
					echo "</pre>";
					*/
					return 0;
				}
			}
		}
		
		if($command == 'find') {
			if($type == 'tv' || $subtype == 'show'){
				echo kodiGetTVShows($subject);
			}
			if($type == 'movie' || $type ='film'){
				echo kodiGetMovies($subject);
			}
		}
	
	}


}

# Runtime PHP Script

if($_POST) {
	error_log( print_r($_POST, TRUE) );
	if($_POST['request']) {
		$commandType = $_POST['request']['commandType'];
		$command = $_POST['request']['command'];
		if($commandType === 'voice') {
			voiceCommand($command);			
		}
		
		
	
	}


}

$html = "<html>\r\n";
$html .= "<head>\r\n";
$html .= "";
$html .= "</head>\r\n";
$html .= "<body>\r\n";
$html .= "<form name=\"kodi\" method=\"post\" action=\"\" >\r\n";
$html .= "
	<select name=\"action\">
	  <option value=\"playMovie\">Play Movie</option>
	  <option value=\"searchMovie\">Search Movie</option>
	  <option value=\"playNextEpisode\">Play Next Episode of</option>
	  <option value=\"home\">Home</option>
	</select>\r\n
";
$html .= "<input name=\"requestString\" type=\"text\" size=\"100\">\r\n";
$html .= "";
$html .= "<input type=\"Submit\" value=\"Go\" />";
$html .= "</form>\r\n";
$html .= "</body>\r\n";
$html .= "</html>\r\n";

echo $html;





?>