<?php
/**
 * Base TVDB library class, provides universal functions and variables
 *
 * @package PHP::TVDB
 * @author Ryan Doherty <ryan@ryandoherty.com>
 **/
 
/**
 * Constants defined here outside of class because class constants can't be 
 * the result of any operation (concatenation)
 */
 
class TVDB {

	private $xmlMask = 1;
	private $banMask = 2;
	private $zipMask = 4;
	
	private $baseUrl = 'http://thetvdb.com';
	private $apiUrl = '/api/';
	private $apiKey = TVDB_API_KEY;
	
	protected $lang;
	
	protected $bannerMirror;
	protected $xmlMirror;
	protected $zipMirror;

	protected $previousTime;

	public function __construct($lang = 'en') {
		$this->lang = $lang;
	}

	protected function request($params, $xml = false) {
		
		switch($params['action']) {
			case 'allUpdates':
				$url = "{$this->baseUrl}{$this->apiUrl}Updates.php?type=all&time={$params['time']}";
			break;
			case 'fullSeriesData':
				$url = "{$this->zipMirror}{$this->apiUrl}{$this->apiKey}/series/{$params['id']}/all/{$this->lang}.zip";
			break;
			case 'seriesData':
				$url = "{$this->xmlMirror}{$this->apiUrl}{$this->apiKey}/series/{$params['id']}/{$this->lang}.xml";
			break;
			case 'episode':
				$url = "{$this->xmlMirror}{$this->apiUrl}{$this->apiKey}/episodes/{$params['id']}/{$this->lang}.xml";
			break;			
			case 'searchByTitle':
				$url = "{$this->baseUrl}{$this->apiUrl}GetSeries.php?seriesname=".urlencode($params['title'])."&language={$this->lang}";
			break;

			default: return false;
		}
		// echo "<hr>{$url}<hr>".print_r($params,1)."<hr>";
		return ($xml===true)?simplexml_load_string($this->fetchData($url)):$this->fetchData($url);
	}
	protected function fetchData($url) {
		// echo "<hr>{$url}<hr>";
		$ch = curl_init($url);
		curl_setopt($ch, CURLOPT_HEADER, 1);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
	
		$response = curl_exec($ch);
		$httpCode = curl_getinfo($ch ,CURLINFO_HTTP_CODE);
		$headerSize = curl_getinfo($ch,CURLINFO_HEADER_SIZE);
		$data = substr( $response, $headerSize );
		curl_close($ch);
		
		if($httpCode != 200) {
			return false;
		}
		
		return $data;
	}
	protected function buildBaseUrl($query) {
		switch ($query) {
			case 'updates':	case 'search':
				$url = $this->baseUrl.$this->apiUrl;
			break;
			case 'banners':
				$url = $this->bannerMirror;
			break;
			case 'mirrors':
				$url = $this->baseUrl.$this->apiUrl.$this->apiKey.'/';
			break;
			case 'series':
				$url = $this->zipMirror.$this->apiUrl.$this->apiKey.'/';
			break;
			default: $url = false;
		}
		return $url;
	}
	protected function removeEmptyIndexes($array) {
		$length = count($array);
		for ($i=$length-1; $i >= 0; $i--) { 
			if(trim($array[$i]) == '') unset($array[$i]);
		}
		sort($array);
		return $array;
	}

	public function pickRandomMirror($fakeit = false) {
		if ($fakeit) {
			$this->bannerMirror = $this->baseUrl;
			$this->xmlMirror    = $this->baseUrl;
			$this->zipMirror    = $this->baseUrl;
			return;
		}

		$mirrorList = simplexml_load_string($this->fetchData($this->buildBaseUrl('mirrors').'mirrors.xml'));

		$xml = $ban = $zip = array();
		foreach ($mirrorList->Mirror as $m) {
			if ($m->typemask & $this->banMask) {
				$ban[] = (string)$m->mirrorpath;
			}
			if ($m->typemask & $this->xmlMask) {
				$xml[] = (string)$m->mirrorpath;
			}
			if ($m->typemask & $this->zipMask) {
				$zip[] = (string)$m->mirrorpath;
			}
		}
		$this->bannerMirror = $ban[array_rand($ban)];
		$this->xmlMirror = $xml[array_rand($xml)];
		$this->zipMirror = $zip[array_rand($zip)];
	}
	public function searchByTitle($showName) {
		$data = $this->request(array('action'=>'searchByTitle','title'=>$showName), true);
		if($data !== false) {
			$out = array();
			foreach ($data->Series as $series) {
				$out[] = new Tv_Series($series);
			}
			return $out;
		}
		return false;
	}
	public function fetchCurrentTime() {
		$time = simplexml_load_string($this->fetchData(
			$this->buildBaseUrl('updates').'Updates.php?type=none'));
		return (string)$time->Time;
	}
	public function fetchAllUpdates($time) {
		return $this->request(array('action'=>'allUpdates','time'=>$time), true);
	}
	public function fetchSeries($seriesId) {
		if (($data = $this->request(array('action'=>'seriesData','id'=>$seriesId), true)) !== false) {
			return new TV_Series($data->Series);
		}
		return false;
	}
	public function fetchEpisode($episodeId) {
		if (($data = $this->request(array('action'=>'episode','id'=>$episodeId), true)) !== false) {
			return new TV_Episode($data->Episode);
		}
		return false;
	}
	public function fetchFullSeriesData($showId) {
		$data = $this->request(array('action'=>'fullSeriesData','id'=>$showId));
		$zipFile = tempnam(null, null);
		file_put_contents($zipFile, $data);
		$zip = new ZipArchive;
		if ($zip->open($zipFile)) {
			$data = simplexml_load_string($zip->getFromName($this->lang.'.xml'));
		}
		$zip->close();unlink($zipFile);
		
		if ($data) {
			$show = new TV_Series($data->Series);
			if (isset($data->Episode)) {
				foreach ($data->Episode as $ep) {
					$show->addEpisode(new TV_Episode($ep));
				}
			}
			return $show;
		} else {
			return false;
		}
	}
}