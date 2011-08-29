<?php
/**
 * TV_Episode class. Class for single tv episode for a TV show.
 *
 * @package PHP::TVDB
 * @author Ryan Doherty <ryan@ryandoherty.com>
 **/
class TV_Episode extends TVDB {
	
	public $id;
	public $Combined_episodenumber;
	public $Combined_season;
	public $DVD_chapter;
	public $DVD_discid;
	public $DVD_episodenumber;
	public $DVD_season;
	public $Director;
	public $EpImgFlag;
	public $EpisodeName;
	public $EpisodeNumber;
	public $FirstAired;
	public $GuestStars;
	public $IMDB_ID;
	public $Language;
	public $Overview;
	public $ProductionCode;
	public $Rating;
	public $RatingCount;
	public $SeasonNumber;
	public $Writer;
	public $absolute_number;
	public $airsafter_season;
	public $airsbefore_episode;
	public $airsbefore_season;
	public $filename;
	public $lastupdated;
	public $seasonid;
	public $seriesid;
	public $flagged;
	public $mirrorupdate;

	
	/**
	 * Constructor
	 *
	 * @access public
	 * @return void
	 * @param simplexmlobject $config simplexmlobject created from thetvdb.com's xml data for the tv episode
	 **/
	public function __construct(SimpleXMLElement $config) {
		parent::__construct();
		foreach ($config->children() as $i =>$blah) {
			switch($i) {
				case 'FirstAired':
					$this->$i = strtotime((string)$blah);
				break;
				case 'GuestStars': case 'Director': case 'Writer':
					$this->$i = $this->removeEmptyIndexes(explode('|', (string)$blah));
				break;
				default:
					$this->$i = (string)$blah;
				break;
			}
		}
	}
}