<?php
function logError($str) {
	// echo 'ERROR: ' . $str . '<br/>';
}
function logWarn($str) {
	// echo 'WARN: ' . $str . '<br/>';
}
function logInfo($str) {
	if (defined('ISAJAX')) {
		sendMessage(array('code' => 'info', 'msg' => $str));
	} else {
		// echo 'INFO: ' . $str . '<br/>';
	}
}
define('REQ_VERSION_MASK', 		1);
define('REQ_WRITABLE_MASK',		2);
define('REQ_DUMMY_MASK', 		4);
define('REQ_EXT_SIMPXML_MASK',	8);
define('REQ_EXT_CURL_MASK',    16);
define('REQ_EXT_PDO_MASK', 	   32);
define('REQ_EXT_SQLITE_MASK',  64);
define('REQ_EXT_ZIP_MASK',	  128);
function checkPrereqs() {
	$status = 0;
	// Check php version
	if (!defined('PHP_VERSION_ID')) {$version = explode('.', PHP_VERSION);define('PHP_VERSION_ID', ($version[0] * 10000 + $version[1] * 100 + $version[2]));}
	if (PHP_VERSION_ID < 50207) {define('PHP_MAJOR_VERSION',   $version[0]);define('PHP_MINOR_VERSION',   $version[1]);define('PHP_RELEASE_VERSION', $version[2]);}
	if (
		PHP_MAJOR_VERSION < 5 ||
		(PHP_MAJOR_VERSION == 5 && PHP_MINOR_VERSION < 2) ||
		(PHP_MAJOR_VERSION == 5 && PHP_MINOR_VERSION == 2 && PHP_RELEASE_VERSION < 11)
	) $status += REQ_VERSION_MASK;

	// Check for required extensions
	$extReq = array(
		REQ_EXT_SIMPXML_MASK => 'SimpleXML',
		REQ_EXT_CURL_MASK => 'curl',
		REQ_EXT_PDO_MASK => 'PDO',
		REQ_EXT_SQLITE_MASK => 'pdo_sqlite',
		REQ_EXT_ZIP_MASK => 'zip'
	);
	$extLoaded = get_loaded_extensions();
	foreach ($extReq as $mask => $ext) {
		if (array_search($ext, $extLoaded) === false) {
			$status += $mask;
		}
	}
	
	// Check for writability
	$path = 'inc/db/'.uniqid(mt_rand()).'.tmp';
	$rm = file_exists($path);
	$f = @fopen($path, 'a');
	if ($f!==false) {
		fclose($f);
		if (!$rm) unlink($path);
	} else {
		$status += REQ_WRITABLE_MASK;
	}
	
	if (defined('YOU_DIDNT_EDIT_THE_CONFIG_FILE')) $status += REQ_DUMMY_MASK;

	return ($status > 0) ? $status : true;
}
function decodePrereq($status) {
	$rtn = array();

	// PHP Version
	if ($status & REQ_VERSION_MASK) $rtn[] = 'Php version too low (min: 5.2.11)';
	
	// Missing Extensions
	if ($status & REQ_EXT_SIMPXML_MASK) $rtn[] = 'missing SimpleXML';
	if ($status & REQ_EXT_CURL_MASK) $rtn[] = 'missing Curl';
	if ($status & REQ_EXT_PDO_MASK) $rtn[] = 'missing PDO';
	if ($status & REQ_EXT_SQLITE_MASK) $rtn[] = 'missing pdo_sqlite';
	if ($status & REQ_EXT_ZIP_MASK) $rtn[] = 'missing zip';

	// Writability
	if ($status & REQ_WRITABLE_MASK) $rtn[] = 'DB folder not writable';
	
	// Dummy Check
	if ($status & REQ_DUMMY_MASK) $rtn[] = 'You REALLY need to edit the configuration file';
	
	return $rtn;
}
function firstRunCheck() {
	if (($preReqOkay = checkPrereqs()) !== true) {
		$errors = decodePrereq($preReqOkay);
		include('installError.php');
		die();
	} else {	
		$model = new Missing_Model('sqlite:inc/db/MissingEps.sqlite');
		if ($model->getLastUpdateTime() == 0) {
			include('install.php');
			die();
		}
		return false;
	}
}
function isStreamRequest() {
	if (isset($_GET['rt']) && $_GET['rt'] == 'if') {
		define('STREAM_ENABLED', false);
	} else {
		define('STREAM_ENABLED', true);
	}
	return (isset($_GET['r']) || isset($_GET['_']));
}
function sendRaw($msg) {
	echo $msg;
	if (STREAM_ENABLED) {
		echo ';';
	} else {
		// echo '<br/>';
	}
	fullFlush();
}
function sendMessage($msg) {
	if (STREAM_ENABLED) {
		$msg = json_encode($msg);
		sendRaw(strlen($msg));
		sendRaw($msg);
	} else {
		// if (isset($msg['msg'])) sendRaw($msg['msg']);
		//sendRaw(print_r($msg));
		// if (isset($msg['val'])) sendRaw('<span>*</span>');
		if (isset($msg['val']) && $msg['val'] == 0) sendRaw('<pre>|                                                   |<br/>&nbsp;');
		if (isset($msg['val']) && $msg['val'] % 2 == 0) sendRaw('<span>*</span>');
	}
}
function initStream() {
	if (STREAM_ENABLED) {
		header('Content-Type: text/plain');
		sendRaw(md5(uniqid('', true)));
	}
	sendRaw(str_pad('',1024));
}
function fullFlush() {
	flush();
	ob_flush();
}
function update($num, $tot) {
	static $old = -1;
	$new = (int)((100/$tot) * $num);
	if ($new != $old) {
		$old = $new;
		sendMessage(array('code'=>'sta','val'=>$new));
	}
}
function curlFetch($url) {
	$ch = curl_init($url);
	curl_setopt($ch, CURLOPT_HEADER, 1);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);

	$response = curl_exec($ch);
	$httpCode = curl_getinfo($ch ,CURLINFO_HTTP_CODE);
	$headerSize = curl_getinfo($ch,CURLINFO_HEADER_SIZE);
	$data = substr( $response, $headerSize );
	curl_close($ch);
	
	if($httpCode != 200) return false;
	return $data;
}
function fetchPlexSeriesData($plexUrl) {
	$plexSectionsUrl = $plexUrl . '/library/sections/';
	$plexSeries = array();
	$sections = simplexml_load_string(curlFetch($plexSectionsUrl));
	foreach ($sections->Directory as $sec) {
		if ($sec->attributes()->type == 'show') {
			$allSeries = simplexml_load_string(curlFetch($plexSectionsUrl . $sec->attributes()->key . '/all'));
			$count = count($allSeries);
			$cnt = 0;
			foreach ($allSeries->Directory as $series) {
				if (defined('ISAJAX')) update($cnt++,$count);
				logInfo('Retrieving series: ' . $series->attributes()->title);
				$seriesEps = simplexml_load_string(curlFetch($plexUrl . '/library/metadata/' . $series->attributes()->ratingKey . '/allLeaves'));
				$seasons = array();
				foreach ($seriesEps->Video as $episode) {
					$seasons[(int)$episode->attributes()->parentIndex][(int)$episode->attributes()->index] = true;
				}
				$plexSeries[(int)$series->attributes()->ratingKey] = array('title'=>(string)$series->attributes()->title, 'plexId' => (string)$series->attributes()->ratingKey, 'seasons'=>$seasons);
			}
		}
	}
	return $plexSeries;
}
function getMissingEpisodesForSeriesByPlexData($plexData) {
	global $model;
	$missingEps = array();

	$sepCnt = $model->getAiredEpisodesForASeriesByPlexId($plexData['plexId']);
	if (count($sepCnt) == 0) return false;
	logInfo('Retrieved aired episodes for: ' . $sepCnt[0]['SeriesName']);

	logInfo('Checking for missing episodes...');
	foreach ($sepCnt as $episode) {
		$seasonNum = (int)$episode['SeasonNumber'];
		$episodeNum = (int)$episode['EpisodeNumber'];
		// Check for missing episodes
		if (!isset($plexData['seasons'][$seasonNum][$episodeNum])) {
			// Missing episode found :(
			logInfo('Missing episode found: ' . $episode['EpisodeName']);
			$missingEps[$seasonNum][$episodeNum] = $episode['EpisodeName'];
		}
	}
	return (count($missingEps) == 0)?false:array('title'=>$plexData['title'], 'missing'=>$missingEps);
}
function getSeriesIds($mySeries) {
	if (!is_array($mySeries)) return false;

	global $model, $tvdb;
	$exactMatches = $nearMatches = array();
	$count = count($mySeries)*2; $cnt = 0;
	foreach ($mySeries as $series) {
		update($cnt++, $count);
		// See if we have the id already cached
		if (($seriesId = $model->getSeriesIdByPlexId($series['plexId']))) {
			// We do, use it instead of searching
			$exactMatches[] = (string)$seriesId;
		} else {
			$seriesMatches = $tvdb->searchByTitle($series['title']);
			$found = false;
			foreach ($seriesMatches as $match) {
				if ((string)$match->SeriesName == $series['title']) { 
					// Exact match found
					$exactMatches[] = (string)$match->id;
					$found = true;
					
					// Cache it
					$model->cacheSeriesId((string)$match->id, $series['plexId']);
					break;
				}
			}
			if (!$found) $nearMatches[] = $seriesMatches;
		}
	}
	return array('exact'=>$exactMatches, 'near'=>$nearMatches);
}
function generateMissingList(&$stats = null) {
	if (file_exists('inc/db/missing.db')) {
		$missingSeries = unserialize(file_get_contents('inc/db/missing.db'));
		$ser = $sea = $eps = 0;
		$out = '';
		foreach ($missingSeries as $plexId => $missing) {
			if (SKIP_EXTRAS && count($missing['missing']) == 1 && isset($missing['missing'][0])) continue;
			$ser++;
			$out .= "<h1><input type='checkbox' class='series'/>{$missing['title']}</h1>\n<div class='series'>";
			foreach ($missing['missing'] as $seasonNum => $season) {
				if (SKIP_EXTRAS && $seasonNum == 0) continue;
				$sea++;
				$out .= "\n\t<h2><input type='checkbox' class='season'/>Season: {$seasonNum}</h2>\n\t<div class='season'>";
				foreach ($season as $epNum =>$title) {
					$eps++;
					$out .= "\n\t\t<div class='episode'><input type='checkbox' class='episode'/>Episode {$epNum}: {$title}</div>";
				}
				$out .= "\n\t</div>";
			}
			$out .= "\n</div>";
		}
		$stats = array('ser' => $ser, 'sea' => $sea, 'eps' => $eps);
		return $out;
	}
	return false;
}